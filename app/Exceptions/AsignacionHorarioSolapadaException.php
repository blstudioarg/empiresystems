<?php

namespace App\Exceptions;

/**
 * La vigencia de una nueva asignación de horario se solapa con un rango cerrado existente
 * del mismo miembro (FR-009).
 */
class AsignacionHorarioSolapadaException extends \DomainException {}
