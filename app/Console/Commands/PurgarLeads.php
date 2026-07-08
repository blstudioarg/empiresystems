<?php

namespace App\Console\Commands;

use App\Enums\EstadoLead;
use App\Models\Lead;
use App\Models\Tenant;
use App\Support\RetencionLeadsTenant;
use Illuminate\Console\Command;

class PurgarLeads extends Command
{
    protected $signature = 'leads:purgar';

    protected $description = 'Elimina definitivamente los leads descartados o no convertidos más antiguos que el plazo de retención de cada tenant (RGPD — minimización)';

    private const TAMANO_LOTE = 500;

    public function handle(): int
    {
        Tenant::query()->each(function (Tenant $tenant) {
            $dias = RetencionLeadsTenant::dias($tenant->id);
            $limite = now()->subDays($dias);

            $total = 0;

            do {
                $ids = Lead::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('tenant_id', $tenant->id)
                    ->where('estado', '!=', EstadoLead::Convertido->value)
                    ->where('created_at', '<', $limite)
                    ->limit(self::TAMANO_LOTE)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                Lead::withoutGlobalScopes()->withTrashed()->whereIn('id', $ids)->forceDelete();
                $total += $ids->count();
            } while ($ids->count() === self::TAMANO_LOTE);

            if ($total > 0) {
                $this->info("Tenant {$tenant->id}: {$total} leads purgados (retención {$dias} días).");
            }
        });

        return self::SUCCESS;
    }
}
