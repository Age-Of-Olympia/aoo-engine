<?php

namespace App\Tutorial\Steps;

use App\Tutorial\TutorialContext;
use App\Service\DialogService;

/**
 * Dialog step - Shows NPC dialog from database
 *
 * Used when tutorial needs to show a dialog conversation (e.g., Gaïa talking to player)
 */
class DialogStep extends AbstractStep
{
    /**
     * Get step data including dialog information
     */
    public function getData(): array
    {
        $data = parent::getData();

        // Add dialog information
        if (isset($this->config['dialog_id'])) {
            $dialogService = new DialogService(true); // Tutorial mode
            $dialogData = $dialogService->getDialogData(
                $this->config['dialog_id'],
                $this->context->getPlayer()
            );

            $data['dialog'] = $dialogData;
        }

        return $data;
    }

    /**
     * Dialog steps typically don't require validation
     * (player just reads and clicks through)
     */
    public function requiresValidation(): bool
    {
        // Unless specifically set in config, dialogs auto-advance
        return $this->config['requires_validation'] ?? false;
    }
}
