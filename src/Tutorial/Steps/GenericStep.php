<?php

namespace App\Tutorial\Steps;

use App\Tutorial\TutorialContext;

/**
 * Generic tutorial step (catch-all for simple informational steps)
 *
 * Used for steps that just display text without requiring complex validation.
 * Most tutorial steps can use this class.
 */
class GenericStep extends AbstractStep
{
    // Inherits all functionality from AbstractStep
    // Can be used directly without subclassing for simple steps
}
