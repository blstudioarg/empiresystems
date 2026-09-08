<?php

namespace App\Models;

use Database\Factories\AsistenteMensajeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Un turno dentro de una conversación del asistente (feature 045).
 *
 * Guardado en el formato que consume la API de Chat Completions (research D3): `rol` +
 * `contenido` + `metadatos` con `tool_calls` o `tool_call_id`. Reconstruir el contexto es un `map`
 * sobre estas filas.
 */
class AsistenteMensaje extends Model
{
    /** @use HasFactory<AsistenteMensajeFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'asistente_mensajes';

    public const ROL_USER = 'user';

    public const ROL_ASSISTANT = 'assistant';

    public const ROL_TOOL = 'tool';

    /**
     * Nota que el sistema escribe para el modelo (resultado de una confirmación, cancelación…).
     * Va al contexto pero NO se le pinta al usuario: no la escribió él y verla como un mensaje suyo
     * confunde.
     */
    public const META_INTERNO = 'interno';

    protected $fillable = [
        'tenant_id',
        'conversacion_id',
        'rol',
        'contenido',
        'metadatos',
    ];

    protected function casts(): array
    {
        return [
            'metadatos' => 'array',
        ];
    }

    public function esInterno(): bool
    {
        return (bool) ($this->metadatos[self::META_INTERNO] ?? false);
    }

    /** @return BelongsTo<AsistenteConversacion, $this> */
    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(AsistenteConversacion::class, 'conversacion_id');
    }

    /**
     * Devuelve el mensaje en el formato exacto que espera el proveedor. Es la inversa de lo que
     * guarda `ConversacionAsistente`.
     *
     * @return array<string, mixed>
     */
    public function aFormatoProveedor(): array
    {
        $mensaje = ['role' => $this->rol, 'content' => $this->contenido];

        foreach (($this->metadatos ?? []) as $clave => $valor) {
            // `interno` es una marca nuestra, no una clave del proveedor: mandarla haría que la
            // API rechazara el mensaje por campo desconocido.
            if ($clave === self::META_INTERNO) {
                continue;
            }

            $mensaje[$clave] = $valor;
        }

        return $mensaje;
    }
}
