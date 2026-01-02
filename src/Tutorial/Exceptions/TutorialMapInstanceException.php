<?php

namespace App\Tutorial\Exceptions;

/**
 * Exception for tutorial map instance errors
 *
 * Thrown when there are issues with:
 * - Map instance creation/deletion
 * - Failed file operations (copying plan JSON)
 * - Failed coordinate copying
 * - NPC/resource duplication errors
 * - Plan name conflicts
 */
class TutorialMapInstanceException extends TutorialException
{
}
