<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class PlayerOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('carac', FieldType::STRING, 'Caractéristique / effet', help: 'ex: foi, mvt, fatigue, visible, energie'),
            new ParameterField('value', FieldType::INT, 'Valeur', default: 0),
            new ParameterField('player', FieldType::ENUM, 'Cible', default: 'target', options: ['actor' => 'Acteur', 'target' => 'Cible']),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        $params =$this->getParameters();
        // e.g. {"carac": "energie", "value" : 4, "player": "actor"}
        // {"carac": "mvt", "value" : 1, "player": "actor"}
        // {"carac" : "energie", "value": 1, "player" : "target"}
        
        $player = $params['player'] ?? null;
        $carac = $params['carac'] ?? null;
        $value = $params['value'] ?? 0;
        $outcomeSuccessMessages = array();

        if ($carac !== null && in_array($player, ['actor', 'target'], true)) {
            if ($player === 'actor' && $carac === 'foi') {
                $god = new Player($actor->data->godId);
                $god->get_data();
                $pf = rand(1, 3);
                $actor->put_pf($pf);
                $outcomeSuccessMessages[0] = 'Vous priez '. $god->data->name .' et gagnez '. $pf .' Points de Foi (total '. $actor->data->pf .'Pf).';
                $outcomeSuccessMessages[1] = '1d3 = '. $pf;
            } elseif ($player === 'actor' && $carac === 'visible') {
                // SimulatedPlayer has no playerService (skips the DB ctor); the
                // preview just reports the effect without the real visibility write.
                if (!$actor->isSimulated()) {
                    $actor->playerService->playerUpdateVisible($value);
                }
                $outcomeSuccessMessages[0] = 'Vous agissez avec furtivité...';
            } else {
                $subject = $player === 'actor' ? $actor : $target;
                $subject->putBonus(array($carac => $value));
                $outcomeSuccessMessages[0] = $this->bonusMessage($subject === $actor, $subject, $carac, (int) $value);
            }
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

    /**
     * Carac-aware success line: keeps the "Vous courez" flavour for the actor's
     * movement gain, otherwise names the carac (and the subject when it's the
     * target rather than the acting player).
     */
    private function bonusMessage(bool $isActor, Player $subject, string $carac, int $value): string
    {
        $signed = ($value >= 0 ? '+' : '') . $value;

        if ($carac === 'mvt' && $isActor) {
            return 'Vous courez ! (' . $signed . ' mouvement !)';
        }

        $label = (defined('CARACS') && isset(CARACS[$carac])) ? CARACS[$carac] : $carac;

        return $isActor
            ? 'Vous gagnez ' . $signed . ' ' . $label . '.'
            : $subject->data->name . ' gagne ' . $signed . ' ' . $label . '.';
    }
}
