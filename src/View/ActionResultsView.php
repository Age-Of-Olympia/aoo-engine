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
        if ($this->actionResults->isSuccess()) {
            $actionsDetails = '<div style="color: #66ccff;">Réussite !</div>';
        } else {
            $actionsDetails = '<div style="color: red;">Echec !</div>';
        }
        $actionsDetails = $actionsDetails.'<div class="action-details">';

        $effectDetails = array();
        foreach($this->actionResults->getEffectsResultsArray() as $effectResult) {
            if ($this->actionResults->isSuccess()) {
                foreach ($effectResult->getEffectSuccessMessages() as $message) {
                    array_push($effectDetails, $message);
                }
            } else {
                foreach ($effectResult->getEffectFailureMessages() as $message) {
                    array_push($effectDetails, $message);
                }
            }
        }

        if ($effectDetails != null) {
            foreach ($effectDetails as $message) {
                $actionsDetails = $actionsDetails.$message."<br>";
            }
        }

        $conditionsDetails = array();
        foreach($this->actionResults->getConditionsResultsArray() as $conditionResult) {
            if ($this->actionResults->isSuccess()) {
                array_push($conditionsDetails, $conditionResult->getConditionSuccessMessages());
            } else {
                array_push($conditionsDetails, $conditionResult->getConditionFailureMessages());
            }
            if ($conditionsDetails != null) {
                foreach ($conditionsDetails as $messages) {
                    if ($messages != null) {
                        foreach($messages as $message) {
                            $actionsDetails = $actionsDetails.$message."<br>";
                        }
                    }
                }
            }
        }

        foreach($this->actionResults->getCostsResultsArray() as $costResult) {
            $actionsDetails = $actionsDetails.$costResult."<br>";
        }

        $actionsDetails = $actionsDetails.'</div>';

        if (isset($this->actionResults->getXpResultsArray()["actor"])) {
            $actionsDetails = $actionsDetails.'<div>Vous gagnez '.$this->actionResults->getXpResultsArray()["actor"].' XP</div>';
        }
   
        return $actionsDetails;
    }


}