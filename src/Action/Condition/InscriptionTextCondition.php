<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;

/**
 * The text to write came with the gesture (POST text; empty erases).
 * The card's button of an action carrying this condition asks for it
 * (EntityCardView, js/observe.js).
 */
class InscriptionTextCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    /** The posted text, or null when none was sent. */
    public static function requestedText(): ?string
    {
        return isset($_POST['text']) ? (string) $_POST['text'] : null;
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        if (self::requestedText() === null) {
            return new ConditionResult(false, array(), array('Écrivez le texte depuis la fiche de l\'objet.'));
        }

        return new ConditionResult(true, array(), array());
    }
}
