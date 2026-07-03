<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\EstadoUsuario;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'rol',
        'activo',
        'avatar_path',
        'estado',
        'aprobado_por',
        'aprobado_en',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => UserRole::class,
            'activo' => 'boolean',
            'estado' => EstadoUsuario::class,
            'aprobado_en' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function isSuperAdmin(): bool
    {
        return $this->rol === UserRole::SuperAdmin;
    }

    public function avatarUrl(): string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : asset('images/user1.jpg');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoUsuario::Pendiente;
    }

    public function estaAprobado(): bool
    {
        return $this->estado === EstadoUsuario::Aprobado;
    }

    public function aprobar(User $por): void
    {
        if ($this->estaAprobado()) {
            return;
        }

        $this->update([
            'estado' => EstadoUsuario::Aprobado,
            'activo' => true,
            'aprobado_por' => $por->id,
            'aprobado_en' => now(),
        ]);
    }

    public function rechazar(): void
    {
        $this->update([
            'estado' => EstadoUsuario::Rechazado,
            'activo' => false,
        ]);
    }
}
