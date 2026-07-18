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
use Classes\Str;

#[ORM\Entity]
class ApplyStatusOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('effect', FieldType::EFFECT, 'Effet', required: true),
            new ParameterField('apply', FieldType::BOOL, 'Appliquer (sinon retirer)', default: true),
            new ParameterField('duration', FieldType::INT, 'Durée (secondes)', default: 1, help: '1 = jusqu\'au prochain tour'),
            new ParameterField('player', FieldType::ENUM, 'Appliquer à', default: 'both', options: [
                'actor' => 'Acteur',
                'target' => 'Cible',
                'both' => 'Les deux',
            ]),
            new ParameterField('value', FieldType::TRAIT_OR_INT, 'Valeur', default: 1),
            new ParameterField('stackable', FieldType::BOOL, 'Cumulable', default: false),
            new ParameterField(
                'targets',
                FieldType::ENUM,
                'Catégories pouvant recevoir l\'effet',
                default: ['character'],
                multiple: true,
                options: \App\Enum\EntityCategory::options(),
                help: 'Un bâtiment ne prend pas d\'adrénaline — mais peut prendre feu si l\'action le déclare.',
            ),
        );
    }

    /**
     * The effect name + whether to apply (vs end) it. New shape:
     * {"effect": "feu", "apply": true, ...}. Legacy shape (effect as the first
     * param key, value = apply): {"feu": true, ...} — still read for any row not
     * yet migrated / an old bundle.
     *
     * @param array<string, mixed> $params
     * @return array{0: string, 1: bool}
     */
    private function resolveEffect(array $params): array
    {
        if (array_key_exists('effect', $params)) {
            return [(string) $params['effect'], filter_var($params['apply'] ?? true, FILTER_VALIDATE_BOOLEAN)];
        }

        $status = (string) array_key_first($params);

        return [$status, filter_var($params[$status] ?? true, FILTER_VALIDATE_BOOLEAN)];
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        $params =$this->getParameters();
        [$status, $apply] = $this->resolveEffect($params);
        $effectService = new \App\Service\EffectService();
        if ($effectService->isHidden($status)) {
            $this->getOutcome()->getAction()->setHideOnSuccess(true);
        }
        $duration = $params['duration'] ?? 1;
        $timeMessage = 'pour ' . Str::displaySeconds($duration);
        if ($duration == 1) {
            $timeMessage = 'jusqu\'au prochain tour';
        }
        $player = $params['player'] ?? 'both';
        $valueParam = $params['value'] ?? 1;
        if(is_array($valueParam)){
            switch ($valueParam[0]) {
                case 'rollDivisor':
                    $value = max(0,floor(($conditionObject->getActorRoll() - $conditionObject->getTargetRoll())/ $valueParam[1]));
                    break;
                case 'remaining':
                    $value = $actor->getRemaining($valueParam[1]);
                    break;
                default:
                    $value = $valueParam[array_rand( $valueParam)];
            } 
        }    
        else{
            $value = $valueParam;
        }

        $stackable = $params['stackable'] ?? false;

        // The effect name and value come from action parameters; escape them
        // before they go into the outcome HTML (the surrounding <span> markup is
        // ours and stays raw). Defense-in-depth: a config bundle or the raw param
        // editor could otherwise smuggle markup into every player's combat log.
        $statusLabel = htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8');
        $valueLabel = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $statusIcon = $effectService->getIcon($status);

        $outcomeSuccessMessages = array();
        switch ($player) {
            case 'actor':
                if ($status == "finished") {
                    $res = $actor->purge_effects();
                    if ($res > 0) {
                        $outcomeSuccessMessages[0] = $res .' effet(s) terminé(s).';
                    }
                } elseif ($this->mayReceiveEffect($actor, $params, $apply)) {
                    $this->applyEffect($apply, $status, $duration, $value, $stackable, $actor);
                    $outcomeSuccessMessages[0] = $this->appliedMessage($statusLabel, $statusIcon, $stackable, $valueLabel, $timeMessage, $actor->data->name);
                }
                break;
            case 'target':
                if ($this->mayReceiveEffect($target, $params, $apply)) {
                    $this->applyEffect($apply, $status, $duration, $value, $stackable, $target);
                    $outcomeSuccessMessages[0] = $this->appliedMessage($statusLabel, $statusIcon, $stackable, $valueLabel, $timeMessage, $target->data->name);
                }
                break;
            default:
                if ($this->mayReceiveEffect($actor, $params, $apply)) {
                    $this->applyEffect($apply, $status, $duration, $value, $stackable, $actor);
                    $outcomeSuccessMessages[0] = $this->appliedMessage($statusLabel, $statusIcon, $stackable, $valueLabel, $timeMessage, $actor->data->name);
                }

            if ($target->data->name !== $actor->data->name && $this->mayReceiveEffect($target, $params, $apply)) {
                $this->applyEffect($apply, $status, $duration, $value, $stackable, $target);
                $outcomeSuccessMessages[1] = $this->appliedMessage($statusLabel, $statusIcon, $stackable, $valueLabel, $timeMessage, $target->data->name);
            }
            break;
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

    /** Le même message de succès servait les quatre cibles (acteur, cible, les deux). */
    private function appliedMessage(string $statusLabel, string $statusIcon, bool $stackable, string $valueLabel, string $timeMessage, string $playerName): string
    {
        return 'L\'effet '.$statusLabel.' <span class="ra '. $statusIcon .'"></span> (' . ($stackable ? '+' : 'x') . $valueLabel .') est appliqué '. $timeMessage.' à ' . $playerName;
    }

    private function applyEffect (bool $apply, string $effectName, int $duration, int $value, bool $stackable, Player $player){
        if ($apply) {
            $player->add_effect($effectName, $duration, $value, $stackable);
        } else {
            $player->end_effect($effectName);
        }
    }

    /**
     * Category gate (docs/design-buildings-entities.md, retours 2026-07-16) :
     * un effet ne s'APPLIQUE qu'aux catégories d'entités que l'instruction
     * déclare — par défaut les personnages seuls, donc jamais d'adrénaline
     * sur une palissade ; une action de siège peut déclarer ['character',
     * 'structure'] pour mettre le feu à un bâtiment. Le RETRAIT d'un effet
     * (apply=false, purge) reste toujours permis : c'est du nettoyage.
     *
     * @param array<string, mixed> $params
     */
    private function mayReceiveEffect(Player $player, array $params, bool $apply): bool
    {
        if (!$apply) {
            return true;
        }

        $allowed = is_array($params['targets'] ?? null) && $params['targets'] !== []
            ? $params['targets']
            : [\App\Enum\EntityCategory::Character->value];

        $category = \App\Enum\EntityCategory::fromPlayerType($player->data->player_type ?? 'real');

        return in_array($category->value, $allowed, true);
    }
}
