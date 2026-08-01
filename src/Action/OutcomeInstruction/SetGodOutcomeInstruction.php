<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Hands a god from one side of the gesture to the other: consecrating gives
 * the altar the actor's god, worshipping gives the actor the altar's.
 *
 * A character goes through `change_god`, which also zeroes faith points. A
 * structure is written directly — an altar earns none.
 */
#[ORM\Entity]
class SetGodOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    private const ACTOR = 'actor';
    private const TARGET = 'target';

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('from', FieldType::ENUM, 'Dieu pris à', default: self::ACTOR, options: [
                self::ACTOR => 'Celui qui agit',
                self::TARGET => 'La cible',
            ]),
            new ParameterField('to', FieldType::ENUM, 'Donné à', default: self::TARGET, options: [
                self::ACTOR => 'Celui qui agit',
                self::TARGET => 'La cible',
            ]),
            new ParameterField('rename', FieldType::STRING, 'Renommer le receveur', help: '{dieu} = nom du Dieu ; vide = ne pas renommer'),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $params = $this->getParameters() ?? [];
        $from = (string) ($params['from'] ?? self::ACTOR) === self::TARGET ? $target : $actor;
        $to = (string) ($params['to'] ?? self::TARGET) === self::ACTOR ? $actor : $target;

        if ($from->id === $to->id) {
            return new OutcomeResult(
                false,
                outcomeSuccessMessages: array(),
                outcomeFailureMessages: ['SetGod : la source et la destination sont la même entité.']
            );
        }

        $godId = (int) ($from->data->godId ?? 0);

        if ($godId === 0) {
            return new OutcomeResult(
                false,
                outcomeSuccessMessages: array(),
                outcomeFailureMessages: ['SetGod : aucun Dieu à transmettre.']
            );
        }

        $god = \App\Factory\PlayerFactory::legacy($godId);
        $god->get_data();

        if ($actor->isSimulated()) {
            return new OutcomeResult(
                true,
                outcomeSuccessMessages: ['Placerait sous la protection de ' . $god->data->name . '.'],
                outcomeFailureMessages: array()
            );
        }

        if (($to->data->player_type ?? 'real') === 'real' || ($to->data->player_type ?? '') === 'npc') {
            $to->change_god($god);
        } else {
            (new \Classes\Db())->exe(
                'UPDATE players SET godId = ? WHERE id = ?',
                array($godId, $to->id)
            );
            $to->refresh_data();
        }

        // An altar says whose it is: without it, the god is nowhere on screen.
        $rename = trim((string) ($params['rename'] ?? ''));

        if ($rename !== '') {
            $name = str_replace('{dieu}', (string) $god->data->name, $rename);

            (new \Classes\Db())->exe('UPDATE players SET name = ? WHERE id = ?', array($name, $to->id));
            $to->refresh_data();
        }

        return new OutcomeResult(
            true,
            outcomeSuccessMessages: [$to->data->name . ' est désormais sous la protection de ' . $god->data->name . '.'],
            outcomeFailureMessages: array()
        );
    }
}
