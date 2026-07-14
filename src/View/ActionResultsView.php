<?php

namespace App\View;

use App\Action\ActionResults;
use App\Action\Condition\ConditionResult;

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
            return '<div style="color: #66ccff;">Réussite !</div>';
        }
        if ($this->actionResults->isBlocked()) {
            return '<div style="color: orange;">Action Impossible.</div>';
        }

        return '<div style="color: red;">Echec !</div>';
    }

    private function renderEffectMessages(): string
    {
        $html = '';
        foreach ($this->actionResults->getOutcomesResultsArray() as $effectResult) {
            $messages = $this->actionResults->isSuccess()
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
        $conditionsDetails = array();
        foreach ($this->actionResults->getConditionsResultsArray() as $conditionResult) {
            if ($this->actionResults->isSuccess()) {
                array_push($conditionsDetails, $conditionResult->getConditionSuccessMessages());
            } else {
                array_push($conditionsDetails, $conditionResult->getConditionFailureMessages());
            }
            if ($conditionsDetails != null) {
                foreach ($conditionsDetails as $messages) {
                    if ($messages != null) {
                        foreach ($messages as $message) {
                            $html .= $message . "<br>";
                        }
                    }
                }
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