<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;

/**
 * What an action asks about a god, on either side of the gesture.
 *
 * Without parameters it answers as it always did, so `prier` is unchanged.
 */
class RequiresGodAffiliationCondition extends BaseCondition implements HasParameterSchema
{
    /** Whose god is examined. */
    public const SIDE_ACTOR = 'actor';
    public const SIDE_TARGET = 'target';

    /** What is asked of it. */
    public const STATE_ANY = 'any';
    public const STATE_NONE = 'none';
    public const STATE_OTHER = 'other';

    private const DEFAULT_MESSAGE = 'Vos prières ne servent à rien, car vous ne vénérez aucun Dieu !';

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('side', FieldType::ENUM, 'De qui', default: self::SIDE_ACTOR, options: [
                self::SIDE_ACTOR => 'Celui qui agit',
                self::SIDE_TARGET => 'La cible',
            ]),
            new ParameterField('state', FieldType::ENUM, 'Ce qu\'on exige', default: self::STATE_ANY, options: [
                self::STATE_ANY => 'Vénère un Dieu',
                self::STATE_NONE => 'N\'en vénère aucun',
                self::STATE_OTHER => 'En vénère un AUTRE que celui qui agit',
            ]),
            new ParameterField('message', FieldType::STRING, 'Message de refus', help: 'Vide = message par défaut'),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $params = $condition->getParameters() ?? [];
        $side = (string) ($params['side'] ?? self::SIDE_ACTOR);
        $state = (string) ($params['state'] ?? self::STATE_ANY);

        $examined = $side === self::SIDE_TARGET ? $target : $actor;

        if ($examined === null) {
            $condition->setBlocking(true);

            return new ConditionResult(false, array(), ['Cette action a besoin d\'une cible.']);
        }

        $god = (int) ($examined->data->godId ?? 0);
        $actorGod = (int) ($actor->data->godId ?? 0);

        $satisfied = match ($state) {
            self::STATE_NONE => $god === 0,
            // A god of its own AND not the actor's: a naked altar fails here.
            self::STATE_OTHER => $god !== 0 && $god !== $actorGod,
            default => $god !== 0,
        };

        if ($satisfied) {
            return new ConditionResult(true, array(), array());
        }

        $condition->setBlocking(true);

        $message = trim((string) ($params['message'] ?? ''));

        return new ConditionResult(false, array(), [
            $message !== '' ? $message : self::refusal($side, $state),
        ]);
    }

    private static function refusal(string $side, string $state): string
    {
        if ($side === self::SIDE_TARGET) {
            return match ($state) {
                self::STATE_NONE => 'Un Dieu y règne déjà.',
                self::STATE_OTHER => 'Vous vénérez déjà ce Dieu.',
                default => 'Aucun Dieu n\'y règne.',
            };
        }

        return $state === self::STATE_NONE
            ? 'Vous vénérez déjà un Dieu.'
            : self::DEFAULT_MESSAGE;
    }
}
