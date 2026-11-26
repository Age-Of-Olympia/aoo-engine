<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Entity\OutcomeInstruction;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PurgeOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('intensity', FieldType::INT, 'Intensité de la Purge', default: 1,
                help: 'Retire cette valeur à chaque effet porté qui utilise une intensité; les effets réduits à zéro disparaissent.')
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $parameters = $this->getParameters() ?? [];
        $intensity = max(0, (int) ($parameters['intensity'] ?? 1));
        $subject = $this->receiver($actor, $target);
        $effectService = new \App\Service\EffectService();
        $changes = [];

        if ($intensity > 0) {
            foreach ($subject->getEffects() as $carriedEffect) {
                $effect = $effectService->getEffectByName($carriedEffect->getName());
                if ($effect === null || !$effect->usesIntensity()) {
                    continue;
                }

                $current = (int) ($carriedEffect->getValue() ?? 0);
                $remaining = $current - $intensity;
                $label = htmlspecialchars($effect->getLabel(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                if ($remaining <= 0) {
                    $subject->end_effect($effect->getName());
                    $changes[] = $label . ' disparaît';
                    continue;
                }

                $subject->playerEffectService->updateEffectByPlayerId(
                    (int) $subject->id,
                    $effect->getName(),
                    $carriedEffect->getEndTime(),
                    $remaining
                );
                $changes[] = $label . ' passe de ' . $current . ' à ' . $remaining;
            }
        }

        $message = $changes === []
            ? 'La Purge ne réduit aucun effet chez ' . $subject->data->name . '.'
            : 'La Purge réduit les effets de ' . $subject->data->name . ' : ' . implode(', ', $changes) . '.';

        return new OutcomeResult(true, [$message], []);
    }
}
