<?php

namespace App\Tutorial\Exceptions;

/**
 * Exception for tutorial step-related errors
 *
 * Thrown when there are issues with:
 * - Step loading from database
 * - Step validation failures
 * - Invalid step data/configuration
 * - Step advancement errors
 * - Missing or invalid step IDs
 */
class TutorialStepException extends TutorialException
{
}
