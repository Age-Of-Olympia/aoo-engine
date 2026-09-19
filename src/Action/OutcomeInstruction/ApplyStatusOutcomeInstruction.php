<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use Classes\Str;

#[ORM\Entity]
class ApplyStatusOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('effect', FieldType::EFFECT, 'Effet', required: true),
            new ParameterField('apply', FieldType::BOOL, 'Appliquer (sinon retirer)', default: true),
            new ParameterField('duration', FieldType::INT, 'Durée (tours)', default: 1, help: '0 = jusqu\'au prochain tour, -1 = sans fin'),
            new ParameterField('value', FieldType::TRAIT_OR_INT, 'Intensité', default: 1, help: 'Multiplie les caracs modifiées par l\'effet (feu à E −1, intensité 3 → E −3) ; 1 = l\'effet tel que défini. Les PV à l\'application ne sont pas multipliés.'),
            new ParameterField('stackable', FieldType::BOOL, 'Cumulable', default: false, help: 'Réappliqué sur un porteur qui l\'a déjà : les intensités s\'additionnent (sinon la plus forte reste).'),
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
        /* La durée s'exprime en TOURS depuis le passage du moteur d'effets
         * aux tours : zéro tient jusqu'au prochain, négatif ne s'éteint
         * jamais (PlayerEffectService::DURATION_INFINITE). */
        $duration = (int) ($params['duration'] ?? 1);
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

        // The value comes from action parameters: escaped before it goes into
        // the outcome HTML (the effect name is escaped by landingMessage).
        $valueLabel = ($stackable ? '+' : 'x') . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $outcomeSuccessMessages = array();
        $receiver = $this->receiver($actor, $target);

        if ($status == "finished") {
            $res = $receiver->purge_effects();
            if ($res > 0) {
                $outcomeSuccessMessages[0] = $res .' effet(s) terminé(s).';
            }
        } elseif ($this->mayReceiveEffect($receiver, $params, $apply)) {
            $this->applyEffect($apply, $status, $duration, $value, $stackable, $receiver);
            $outcomeSuccessMessages[0] = $effectService->landingMessage($status, $receiver->data->name, $actor->data->name, $duration, $valueLabel);
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

    private function applyEffect (bool $apply, string $effectName, int $duration, int $value, bool $stackable, Player $player){
        if ($apply) {
            $player->add_effect($effectName, $duration, $value, $stackable);
        } else {
            $player->end_effect($effectName);
        }
    }

    /**
     * Category gate : un effet ne s'APPLIQUE qu'aux catégories d'entités que l'instruction
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
