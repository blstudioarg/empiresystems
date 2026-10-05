<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El servicio de traducción no respondió, respondió con error o se agotó el cupo (feature 050).
 *
 * Nunca debe llegar al usuario: quien llama al proveedor la captura, deja el texto pendiente y el
 * POS lo sigue mostrando en español (FR-013).
 */
class TraduccionNoDisponibleException extends RuntimeException {}
