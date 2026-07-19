<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Cualquier motivo por el que un fichero completo se rechaza antes de importar nada: cabeceras
 * insuficientes (FR-018), más de 2.000 filas (FR-019), fichero corrupto/vacío/protegido/con tipo
 * falseado (SC-008), o token de previsualización caducado. Siempre se traduce a un 422 con
 * mensaje en español — nunca un 500 (ImportacionController).
 */
class ImportacionInvalidaException extends RuntimeException {}
