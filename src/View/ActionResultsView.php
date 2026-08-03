<?php

namespace App\View;

use App\Action\ActionResults;
use App\Action\Condition\ConditionResult;
use App\Action\OutcomeInstruction\OutcomeResult;

class ActionResultsView
{

    private ActionResults $actionResults;
    private string $actionResultsString;
    public function __construct(ActionResults $actionResults) {
        $this->actionResults = $actionResults;
        $this->actionResultsString = "";
    }

    /**
     * affiche le html du résultat des actions (conditions + effets + dommages éventuels)
     */
    public function displayActionResults(): void
    {
        if ($this->actionResultsString == "") {
            $this->actionResultsString = $this->prepareActionResults();
        }

        echo $this->actionResultsString;
    }

    /**
     * affiche le html du résultat des actions (conditions + effets + dommages éventuels)
     */
    public function getActionResults(): string
    {
        if ($this->actionResultsString == "") {
            $this->actionResultsString = $this->prepareActionResults();
        }

        return $this->actionResultsString;
    }

    private function prepareActionResults(): string
    {
        $actionsDetails = $this->renderHeader();

        // Dégâts et effets : toujours visibles, hors .action-details.
        $actionsDetails .= $this->renderEffectMessages();

        $conditionMessages = $this->renderConditionMessages();

        // Action impossible : la raison du blocage reste visible, pas masquée.
        if ($this->actionResults->isBlocked()) {
            $actionsDetails .= $conditionMessages;
            $conditionMessages = '';
        }

        $actionsDetails .= '<div class="action-details">';
        $actionsDetails .= $conditionMessages;
        $actionsDetails .= $this->renderCostMessages();
        $actionsDetails .= '</div>';

        $actionsDetails .= $this->renderXpMessages();

        return $actionsDetails;
    }

    private function renderHeader(): string
    {
        if ($this->actionResults->isSuccess()) {
            if ($this->everyOutcomeRefused()) {
                return '<div style="color: red;">Echec !</div>';
            }

            return '<div style="color: #66ccff;">Réussite !</div>';
        }
        if ($this->actionResults->isBlocked()) {
            return '<div style="color: orange;">Action Impossible.</div>';
        }

        return '<div style="color: red;">Echec !</div>';
    }

    /**
     * The action's success is its roll; an outcome may still refuse
     * afterwards (a recipe short of ingredients, an occupied build site).
     * A success whose every outcome refused did nothing the player asked:
     * its header must not read as a win.
     */
    private function everyOutcomeRefused(): bool
    {
        $refusedOne = false;
        foreach ($this->actionResults->getOutcomesResultsArray() as $outcomeResult) {
            if (!$outcomeResult instanceof OutcomeResult) {
                continue;
            }
            if ($outcomeResult->isSuccess()) {
                return false;
            }
            $refusedOne = true;
        }

        return $refusedOne;
    }

    private function renderEffectMessages(): string
    {
        $html = '';
        foreach ($this->actionResults->getOutcomesResultsArray() as $effectResult) {
            // An outcome that refused shows its refusal even when the roll
            // succeeded — it used to render its (empty) success messages.
            $refused = $effectResult instanceof OutcomeResult && !$effectResult->isSuccess();
            $messages = $this->actionResults->isSuccess() && !$refused
                ? $effectResult->getOutcomeSuccessMessages()
                : $effectResult->getOutcomeFailureMessages();
            foreach ($messages as $message) {
                $html .= $message . "<br>";
            }
        }

        return $html;
    }

    private function renderConditionMessages(): string
    {
        $html = '';
        foreach ($this->actionResults->getConditionsResultsArray() as $conditionResult) {
            $messages = $this->actionResults->isSuccess()
                ? $conditionResult->getConditionSuccessMessages()
                : $conditionResult->getConditionFailureMessages();
            foreach ($messages as $message) {
                $html .= $message . "<br>";
            }
        }

        return $html;
    }

    private function renderCostMessages(): string
    {
        $html = '';
        foreach ($this->actionResults->getCostsResultsArray() as $costResult) {
            $html .= $costResult . "<br>";
        }

        return $html;
    }

    private function renderXpMessages(): string
    {
        $html = '';
        $xp = $this->actionResults->getXpResultsArray();

        if (isset($xp["actor"]) && $xp["actor"] > 0) {
            $html .= '<div>Vous gagnez ' . $xp["actor"] . ' XP</div>';
        }
        if (isset($xp["target"]) && $xp["target"] > 0) {
            $html .= '<div>Votre cible gagne ' . $xp["target"] . ' XP</div>';
        }

        return $html;
    }


}