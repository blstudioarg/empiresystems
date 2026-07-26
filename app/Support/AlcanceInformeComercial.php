<?php

namespace App\Support;

use App\Models\User;

/**
 * Resuelve el alcance de datos del informe comercial a partir del usuario autenticado (research
 * D4 de la feature 033): "propio" (solo su actividad asignada) o "tenant" (todo el tenant, con
 * `ver-informes-equipo`). También resuelve los bloques visibles según los permisos de módulo ya
 * existentes (`ver-leads`/`ver-oportunidades`/`ver-presupuestos`). Se resuelve siempre en
 * servidor, nunca a partir de un parámetro de la petición (FR-024).
 */
class AlcanceInformeComercial
{
    private function __construct(
        public readonly string $tipo,
        public readonly ?int $comercialId,
        public readonly array $bloquesVisibles,
    ) {}

    public static function paraUsuario(User $usuario): self
    {
        $tipo = $usuario->can('ver-informes-equipo') ? 'tenant' : 'propio';

        $bloques = [];

        if ($usuario->can('ver-leads')) {
            $bloques[] = 'leads';
        }
        if ($usuario->can('ver-oportunidades')) {
            $bloques[] = 'oportunidades';
        }
        if ($usuario->can('ver-presupuestos')) {
            $bloques[] = 'presupuestos';
        }

        return new self($tipo, $tipo === 'propio' ? $usuario->id : null, $bloques);
    }

    public function esTenant(): bool
    {
        return $this->tipo === 'tenant';
    }

    /**
     * Al menos uno de los tres permisos de módulo, condición de acceso a la sección (FR-025).
     */
    public function tieneAccesoAlgunBloque(): bool
    {
        return $this->bloquesVisibles !== [];
    }

    public function tieneBloque(string $bloque): bool
    {
        return in_array($bloque, $this->bloquesVisibles, true);
    }

    /**
     * Aplica el alcance sobre los filtros recibidos: si el usuario no tiene `ver-informes-equipo`,
     * descarta cualquier `comercial_id` de la petición y fuerza el propio (FR-024).
     */
    public function resolverFiltros(FiltrosInforme $filtros): FiltrosInforme
    {
        if ($this->tipo === 'propio') {
            return $filtros->conComercialId($this->comercialId);
        }

        return $filtros;
    }

    /**
     * @return array{tipo: string, comercial_id: ?int, bloques_visibles: list<string>}
     */
    public function aArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'comercial_id' => $this->comercialId,
            'bloques_visibles' => $this->bloquesVisibles,
        ];
    }
}
