<?php

namespace App\Tutorial\Exceptions;

/**
 * Exception for tutorial session-related errors
 *
 * Thrown when there are issues with:
 * - Session creation/loading/deletion
 * - Invalid session IDs
 * - Session state inconsistencies
 * - Missing or expired sessions
 */
class TutorialSessionException extends TutorialException
{
}
