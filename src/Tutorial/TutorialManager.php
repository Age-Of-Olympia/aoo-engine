<?php

namespace App\Tutorial;

use App\Entity\EntityManagerFactory;
use App\Entity\TutorialPlayer;
use App\Factory\PlayerFactory;
use Classes\Player;
use Classes\Db;

/**
 * Main orchestrator for tutorial sessions, progress tracking, step loading,
 * and temporary tutorial character lifecycle.
 */
class TutorialManager
{
    private TutorialContext $context;
    private string $sessionId;
    private Db $db;
    private ?TutorialPlayer $tutorialPlayer = null;
    private TutorialStepRepository $stepRepository;

    private TutorialSessionManager $sessionManager;
    private TutorialProgressManager $progressManager;
    private TutorialResourceManager $resourceManager;

    public function __construct(Player $player, string $mode = 'first_time')
    {
        $this->context = new TutorialContext($player, $mode);
        $this->sessionId = $this->generateSessionId();
        $this->db = new Db();
        $this->stepRepository = new TutorialStepRepository();

        // Initialize service layer
        $this->sessionManager = new TutorialSessionManager($this->db);
        $this->resourceManager = new TutorialResourceManager();
        $this->progressManager = new TutorialProgressManager(
            $this->context,
            $this->stepRepository,
            $this->sessionManager
        );
    }

    /**
     * Start a tutorial session.
     *
     * @param string $version Catalog version (scenario identifier).
     * @param string|null $raceOverride Run the tutorial as this race instead of the
     *                                  real player's. Must be in RACES_EXT. Null
     *                                  (default) keeps the real player's race.
     */
    public function startTutorial(string $version = '1.0.0', ?string $raceOverride = null): array
    {
        $player = $this->context->getPlayer();

        $this->resourceManager->cleanupPrevious($player->id);

        $firstStepId = $this->stepRepository->getFirstStepId($version) ?? 'gaia_welcome';
        $totalSteps = $this->stepRepository->getTotalSteps($version);

        $catalog = (new TutorialCatalogService($this->db))->getByVersion($version);
        $templatePlan = $catalog['plan']   ?? 'tutorial';
        $spawnX       = (int) ($catalog['spawn_x'] ?? 0);
        $spawnY       = (int) ($catalog['spawn_y'] ?? 0);

        $session = $this->sessionManager->createSession(
            $player->id,
            $this->context->getMode(),
            $version,
            $totalSteps,
            $firstStepId
        );

        $this->sessionId = $session['session_id'];

        // Null $raceOverride falls through to TutorialPlayerFactory's own
        // "read race from the real player" default — avoid resolving it here
        // so we don't duplicate the lookup.
        $race = $raceOverride ?? ($player->data->race ?? null);

        $this->tutorialPlayer = $this->resourceManager->createTutorialPlayerAsEntity(
            $player->id,
            $this->sessionId,
            $race,
            $templatePlan,
            $spawnX,
            $spawnY
        );

        // Downstream step validators still consume a Classes\Player, so we hydrate
        // a legacy instance from the entity id and hand it to the context.
        $tutorialPlayerInstance = PlayerFactory::legacy((int) $this->tutorialPlayer->getId());
        $tutorialPlayerInstance->get_data();
        $this->context->setPlayer($tutorialPlayerInstance);

        $stepData = $this->progressManager->getCurrentStepForClient(
            $firstStepId,
            $version,
            true
        );

        return array_merge($session, [
            'success' => true,
            'tutorial_player_id' => (int) $this->tutorialPlayer->getId(),
            'step_data' => $stepData
        ]);
    }


    public function resumeTutorial(string $sessionId): array
    {
        $this->sessionId = $sessionId;

        $session = $this->sessionManager->loadSession($sessionId);

        if (!$session) {
            return [
                'success' => false,
                'error' => 'Tutorial session not found'
            ];
        }

        $this->context->restoreState($session['data']);

        $this->tutorialPlayer = $this->resourceManager->getTutorialPlayerAsEntity($sessionId);

        // Movement checks and validation hints must run against the tutorial
        // player, not the main player.
        if ($this->tutorialPlayer) {
            $tutorialPlayerObj = PlayerFactory::legacy((int) $this->tutorialPlayer->getId());
            $tutorialPlayerObj->get_data();
            $this->context->setPlayer($tutorialPlayerObj);
        }

        // Prerequisites only apply on advance; re-applying here would reset
        // resources on every resume / validation check.
        $stepData = $this->progressManager->getCurrentStepForClient(
            $session['current_step'],
            $session['version'],
            false
        );

        return array_merge($session, [
            'success' => true,
            'tutorial_player_id' => $this->tutorialPlayer ? (int) $this->tutorialPlayer->getId() : null,
            'step_data' => $stepData
        ]);
    }

    public function getTutorialPlayer(): ?TutorialPlayer
    {
        return $this->tutorialPlayer;
    }

    /**
     * Get current step data from database
     *
     * @param int $stepNumber
     * @param string $version
     * @return array|null
     */
    public function getStepData(int $stepNumber, string $version = '1.0.0'): ?array
    {
        return $this->stepRepository->getStepByNumber((float)$stepNumber, $version);
    }

    /**
     * Get current step as AbstractStep object
     *
     * @param int $stepNumber
     * @param string $version
     * @return Steps\AbstractStep|null
     */
    public function getStep(int $stepNumber, string $version = '1.0.0'): ?Steps\AbstractStep
    {
        $stepData = $this->getStepData($stepNumber, $version);

        if (!$stepData) {
            return null;
        }

        return TutorialStepFactory::createFromData($stepData, $this->context);
    }

    /**
     * Get step data by step_id (name)
     *
     * @param string $stepId
     * @param string $version
     * @return array|null
     */
    public function getStepDataById(string $stepId, string $version = '1.0.0'): ?array
    {
        return $this->stepRepository->getStepById($stepId, $version);
    }

    /**
     * Get step object by step_id (name)
     *
     * @param string $stepId
     * @param string $version
     * @return Steps\AbstractStep|null
     */
    public function getStepById(string $stepId, string $version = '1.0.0'): ?Steps\AbstractStep
    {
        $stepData = $this->getStepDataById($stepId, $version);

        if (!$stepData) {
            return null;
        }

        return TutorialStepFactory::createFromData($stepData, $this->context);
    }

    /**
     * Get current step with full data for client (by step_id)
     *
     * @param string $stepId
     * @param string $version
     * @param bool $applyPrerequisites - Whether to apply prerequisites (true for resume, false for normal rendering)
     * @return array|null
     */
    public function getCurrentStepForClientById(string $stepId, string $version = '1.0.0', bool $applyPrerequisites = false): ?array
    {
        $step = $this->getStepById($stepId, $version);

        if (!$step) {
            return null;
        }

        $stepData = $step->getData();
        $stepData = $this->processPlaceholders($stepData);
        $stepData['step_position'] = $this->calculateStepPosition($stepId, $version);

        // Prerequisites only apply on advance / resume entry, not on every render.
        if ($applyPrerequisites && isset($stepData['config']['prerequisites'])) {
            $this->context->ensurePrerequisites($stepData['config']['prerequisites']);
        }

        if ($step->requiresValidation() && method_exists($step, 'getValidationHint')) {
            $dynamicHint = $step->getValidationHint();
            if ($dynamicHint) {
                $stepData['validation_hint'] = $dynamicHint;
            }
        }

        return array_merge($stepData, [
            'tutorial_state' => $this->context->getPublicState()
        ]);
    }

    private function calculateStepPosition(string $stepId, string $version = '1.0.0'): int
    {
        return $this->stepRepository->calculateStepPosition($stepId, $version);
    }

    private function processPlaceholders(array $stepData): array
    {
        $tutorialPlayerId = $this->context->getPlayer()->id;
        $tutorialPlayer = PlayerFactory::legacy($tutorialPlayerId);
        $placeholderService = new TutorialPlaceholderService($tutorialPlayer);

        foreach (['title', 'text', 'validation_hint'] as $field) {
            if (isset($stepData[$field]) && is_string($stepData[$field])) {
                $stepData[$field] = $placeholderService->replacePlaceholders($stepData[$field]);
            }
        }

        return $stepData;
    }

    public function getCurrentStepForClient(int $stepNumber, string $version = '1.0.0', bool $applyPrerequisites = false): ?array
    {
        $step = $this->getStep($stepNumber, $version);

        if (!$step) {
            return null;
        }

        $stepData = $step->getData();
        $stepData = $this->processPlaceholders($stepData);

        if ($applyPrerequisites && isset($stepData['config']['prerequisites'])) {
            $this->context->ensurePrerequisites($stepData['config']['prerequisites']);
        }

        if ($step->requiresValidation() && method_exists($step, 'getValidationHint')) {
            $dynamicHint = $step->getValidationHint();
            if ($dynamicHint) {
                $stepData['validation_hint'] = $dynamicHint;
            }
        }

        return array_merge($stepData, [
            'tutorial_state' => $this->context->getPublicState()
        ]);
    }

    public function advanceStep(array $validationData = []): array
    {
        $session = $this->sessionManager->loadSession($this->sessionId);

        if (!$session) {
            return [
                'success' => false,
                'error' => 'Session not found'
            ];
        }

        try {
            $result = $this->progressManager->advanceStep(
                $this->sessionId,
                $session['current_step'],
                $session['version'],
                $validationData
            );

            if ($result['completed'] ?? false) {
                $completionResult = $this->completeTutorial();

                if (isset($result['final_step_data'])) {
                    $completionResult['final_step_data'] = $result['final_step_data'];
                }

                return $completionResult;
            }

            return $result;

        } catch (Exceptions\TutorialValidationException $e) {
            return [
                'success' => false,
                'error' => 'Step validation failed',
                'hint' => $e->getHint() ?? 'Veuillez compléter l\'étape correctement.'
            ];
        } catch (Exceptions\TutorialException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Internal error: ' . $e->getMessage()
            ];
        } catch (\Error $e) {
            return [
                'success' => false,
                'error' => 'PHP error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Rewards (XP/PI) are only given on first completion. Replays acknowledge
     * success but award nothing.
     */
    private function completeTutorial(): array
    {
        $xpEarned = $this->context->getTutorialXP();
        $piEarned = $xpEarned;

        $realPlayerId = $this->tutorialPlayer
            ? (int) $this->tutorialPlayer->getRealPlayerIdRef()
            : $this->context->getPlayer()->id;
        $isReplay = $this->sessionManager->hasCompletedBefore($realPlayerId);

        $actualXpAwarded = 0;
        $actualPiAwarded = 0;

        if (!$isReplay && $this->tutorialPlayer) {
            $this->tutorialPlayer->transferRewardsToRealPlayer(
                EntityManagerFactory::getEntityManager()->getConnection(),
                $xpEarned,
                $piEarned
            );
            $actualXpAwarded = $xpEarned;
            $actualPiAwarded = $piEarned;

            $realPlayer = PlayerFactory::legacy($realPlayerId);
            $realPlayer->end_option('invisibleMode');

            try {
                $realPlayer->get_data();
                $raceJson = json()->decode('races', $realPlayer->data->race);

                if ($raceJson && !empty($raceJson->actions)) {
                    foreach($raceJson->actions as $actionName) {
                        $realPlayer->add_action($actionName);
                    }
                }
            } catch (\Exception $e) {
                // Race-action seeding is best-effort; tutorial completion must
                // not block on it.
            }
        }

        if ($this->tutorialPlayer) {
            $this->resourceManager->deleteTutorialPlayerAsEntity($this->tutorialPlayer, $this->sessionId);
        }

        $this->sessionManager->completeSession($this->sessionId, $xpEarned);

        // Build completion message based on whether rewards were given
        if ($isReplay) {
            $message = "Félicitations ! Tu as terminé le tutoriel ! Tu l'avais déjà complété auparavant, donc tu ne reçois pas de récompenses cette fois.";
        } else {
            $message = "Félicitations ! Tu as terminé le tutoriel ! Tu as gagné {$actualXpAwarded} XP et {$actualPiAwarded} PI !";
        }

        return [
            'success' => true,
            'completed' => true,
            'xp_earned' => $actualXpAwarded,
            'pi_earned' => $actualPiAwarded,
            'final_level' => $this->context->getTutorialLevel(),
            'is_replay' => $isReplay,
            'message' => $message
        ];
    }

    public static function hasCompletedTutorial(int $playerId): bool
    {
        $sessionManager = new TutorialSessionManager();
        return $sessionManager->hasCompletedBefore($playerId);
    }

    public function getContext(): TutorialContext
    {
        return $this->context;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function jumpToStep(string $sessionId, int $targetStepNumber): bool
    {
        $session = $this->sessionManager->loadSession($sessionId);

        if (!$session) {
            return false;
        }

        $version = $session['version'] ?? '1.0.0';

        $stepData = $this->stepRepository->getStepByNumber((float)$targetStepNumber, $version);

        if (!$stepData) {
            return false;
        }

        try {
            return $this->progressManager->jumpToStep($sessionId, $stepData['step_id'], $version);
        } catch (Exceptions\TutorialStepException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Placeholder until startTutorial() overwrites it with the SessionManager-issued UUID.
    private function generateSessionId(): string
    {
        return sprintf(
            'tut_temp_%s',
            uniqid('', true)
        );
    }
}
