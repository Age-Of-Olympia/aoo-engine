<?php

namespace App\Tutorial;

use App\Tutorial\Steps\AbstractStep;
use App\Tutorial\Steps\GenericStep;
use App\Tutorial\Steps\DialogStep;
use App\Tutorial\Steps\Movement\MovementStep;
use App\Tutorial\TutorialContext;

/**
 * Factory for creating tutorial step instances
 *
 * Maps step_type from database to concrete step classes
 */
class TutorialStepFactory
{
    /**
     * Map of step types to class names
     */
    private static $stepTypeMap = [
        // Generic/Welcome steps
        'welcome' => GenericStep::class,
        'info' => GenericStep::class,
        'generic' => GenericStep::class,

        // Dialog steps
        'dialog' => DialogStep::class,

        // Movement steps
        'movement' => MovementStep::class,
        'movement_intro' => MovementStep::class,
        'movement_limit' => MovementStep::class,

        // Action steps
        'action_intro' => GenericStep::class,
        'action' => GenericStep::class,

        // Combat steps
        'combat_intro' => GenericStep::class,
        'combat' => GenericStep::class,

        // Progression steps
        'xp_intro' => GenericStep::class,
        'pi_intro' => GenericStep::class,
        'level_up' => GenericStep::class,

        // Add more mappings as you create more specialized step classes
    ];

    /**
     * Create step instance from database row
     *
     * @param array $stepData Row from tutorial_configurations table
     * @param TutorialContext $context Tutorial context
     * @return AbstractStep
     */
    public static function createFromData(array $stepData, TutorialContext $context): AbstractStep
    {
        $stepType = $stepData['step_type'];
        $stepNumber = (int)$stepData['step_number'];
        $title = $stepData['title'];
        $config = is_array($stepData['config']) ? $stepData['config'] : json_decode($stepData['config'], true);
        $xpReward = (int)($stepData['xp_reward'] ?? 0);

        // Get appropriate class for this step type
        $className = self::getStepClass($stepType);

        return new $className(
            $context,
            $stepNumber,
            $stepType,
            $title,
            $config ?? [],
            $xpReward
        );
    }

    /**
     * Get step class for type
     *
     * @param string $stepType
     * @return string Class name
     */
    private static function getStepClass(string $stepType): string
    {
        // Check if we have a specific mapping
        if (isset(self::$stepTypeMap[$stepType])) {
            return self::$stepTypeMap[$stepType];
        }

        // Default to GenericStep for unknown types
        return GenericStep::class;
    }

    /**
     * Register custom step type mapping
     *
     * Allows adding new step types without modifying this class
     *
     * @param string $stepType
     * @param string $className
     */
    public static function registerStepType(string $stepType, string $className): void
    {
        self::$stepTypeMap[$stepType] = $className;
    }
}
