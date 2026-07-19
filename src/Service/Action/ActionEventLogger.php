<?php

namespace App\Service\Action;

use App\Action\ActionResults;
use App\Interface\ActionInterface;
use App\Interface\ActorInterface;
use Classes\Log;

/**
 * Écrit l'ÉVÉNEMENT d'une action exécutée (gabarits action_type_logs
 * résolus par l'exécuteur) — extrait d'action.php pour que TOUT point
 * d'exécution du moteur laisse une trace : action.php, mais aussi les
 * actions démarrées par le déplacement (go.php → creuser). Une action
 * sans événement n'existe pas (retour de revue du 2026-07-19).
 */
final class ActionEventLogger
{
    public static function write(
        ActionInterface $action,
        ActionResults $results,
        ActorInterface $actor,
        ActorInterface $target,
        string $logDetails = ''
    ): void {
        $logTime = time();
        $hideLogs = ($results->isSuccess() && $action->hideOnSuccess()) || $results->isBlocked();

        $actorMainLog = $results->getLogsArray()['actor'] ?? '';
        if (!empty($actorMainLog)) {
            Log::put($actor, $target, $actorMainLog, $hideLogs ? 'hidden_action' : 'action', $logDetails, $logTime);
        }

        if ($target->getId() !== $actor->getId()) {
            $targetMainLog = $results->getLogsArray()['target'] ?? '';
            if (!empty($targetMainLog)) {
                Log::put($target, $actor, $targetMainLog, $hideLogs ? 'hidden_action_other_player' : 'action_other_player', $logDetails, $logTime);
            }
        }
    }
}
