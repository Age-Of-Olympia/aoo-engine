<?php
namespace App\Service;

include '../../classes/player.php';
use Player;
use App\Entity\EffectInstruction;

class EffectInstructionExecutor
{
    /**
     * Execute a single instruction (one row from `effect_instructions`)
     *
     * @param Player $target  The player who will be affected
     * @param EffectInstruction $instruction The DB entity describing the operation
     */
    public function executeInstruction(Player $target, EffectInstruction $instruction): void
    {
        $operation = $instruction->getOperation();
        $params    = $instruction->getParameters() ?? [];

        switch ($operation) {
            // case 'MODIFY_STAT':
            //     $this->executeModifyStat($target, $params);
            //     break;

            case 'APPLY_STATUS':
                $this->executeApplyStatus($target, $params);
                break;

            case 'LOG':
                $this->executeLog($target, $params);
                break;

            // Add more operation cases as needed

            default:
                // Unrecognized operation
                // Could log or ignore
                break;
        }
    }

    // private function executeModifyStat(Player $target, array $params): void
    // {
    //     // e.g. { "stat": "hp", "delta": -10 }
    //     $stat = $params['stat'] ?? null;
    //     $delta = $params['delta'] ?? 0;

    //     if ($stat && method_exists($target, 'modifyStat')) {
    //         $target->put_upgrade()modifyStat($stat, $delta);
    //     }
    //     // else fallback logic ...
    // }

    private function executeApplyStatus(Player $target, array $params): void
    {
        // e.g. { "status": "poisoned", "duration": 3 }
        $status = $params['status'] ?? 'unknown';
        $duration = $params['duration'] ?? 1;

        $target->add_effect($status, $duration);
    }

    private function executeLog(Player $target, array $params): void
    {
        // e.g. { "message": "You are burned!" }
        $message = $params['message'] ?? 'No message';
        // Possibly store logs somewhere, or push to a chat/log system
        // $this->logger->info("[$target->getName()] $message");
    }
}
