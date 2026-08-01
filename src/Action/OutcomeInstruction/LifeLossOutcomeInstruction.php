<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Combat\DamageCalculator;
use App\Action\Combat\DamageModifiers;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use Classes\View;

#[ORM\Entity]
class LifeLossOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        // Attack/defense traits feed the damage roll off the fighters' caracs, so
        // the simulator derives its inputs from this schema (SchemaSimulationInputs).
        // The target reads defense + defense-bonus; everything else is the actor.
        // A bonus set to a fixed number (or a [trait, divisor] pair) is handled there.
        return new ParameterSchema(
            new ParameterField('actorDamagesTrait', FieldType::TRAIT, "Trait d'attaque", required: true),
            new ParameterField('targetDamagesTrait', FieldType::TRAIT, 'Trait de défense', required: true, side: 'target'),
            new ParameterField('bonusDamagesTrait', FieldType::TRAIT_OR_INT, 'Bonus de dégâts'),
            new ParameterField('bonusDefenseTrait', FieldType::TRAIT_OR_INT, 'Bonus de défense', side: 'target'),
            new ParameterField('distance', FieldType::BOOL, 'Influence de la distance', default: false),
            new ParameterField('saut', FieldType::BOOL, 'Influence du saut', default: false),
            new ParameterField('drain', FieldType::BOOL, 'Drain (PV)', default: false),
            new ParameterField('siphon', FieldType::BOOL, 'Siphon (PM)', default: false),
            new ParameterField('autoCrit', FieldType::BOOL, 'Critique automatique', default: false),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        // e.g. { "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait" : "m", "distance" : true, "autoCrit": true, "targetIgnore": ["tronc"], "actorIgnore": false }
        
        // Initialisation des paramètres
        $totalDamages = 0;
        $malus = random_int(1,3);
        $outcomeSuccessMessages = array();
        
        // Récupération des paramètres
        $params = $this->getParameters();
        $actorTraitDamages = $params['actorDamagesTrait'] ?? 0;
        $targetTraitDamagesTaken = $params['targetDamagesTrait'] ?? 0;
        $bonusTraitDamagesParameters = $params['bonusDamagesTrait'] ?? 0;
        $isDrain = $params["drain"] ?? false;
        $isSiphon = $params["siphon"] ?? false;
        $bonusTraitDamages = (is_array($bonusTraitDamagesParameters) ? floor($actor->caracs->{$bonusTraitDamagesParameters[0]}/$bonusTraitDamagesParameters[1]) : $bonusTraitDamagesParameters) ?? 0;
        $bonusTraitDefense = $params['bonusDefenseTrait'] ?? 0;
        $distanceInfluence = $params['distance'] ?? false;
        $sautInfluence = $params['saut'] ?? false;
        $targetIgnore = $params['targetIgnore'] ?? false;
        $actorIgnore = $params['actorIgnore'] ?? false;
        $autoCrit = $params['autoCrit'] ?? false;

        // Modificateurs de dégâts portés par les effets (catalogue :
        // damage_dealt_mod côté attaquant — ex-agressivite/faiblesse —,
        // damage_taken_mod côté cible — ex-fragilite/armure).
        $effectService = new \App\Service\EffectService();
        $dealtMods = $effectService->modifierContributions($actor->getEffects(), 'getDamageDealtMod');
        $takenMods = $effectService->modifierContributions($target->getEffects(), 'getDamageTakenMod');

        // Démolition : le bonus anti-structure de l'arme (pioche, bélier…)
        // s'ajoute aux dégâts quand la CIBLE est une structure — l'héritier
        // de l'ex-destroy.php maintenant que les murs sont des entités.
        if (\App\Enum\EntityCategory::fromPlayerType($target->data->player_type ?? 'real') === \App\Enum\EntityCategory::Structure) {
            $demolition = (int) ($actor->emplacements->main1->data->demolition ?? 0);
            if ($demolition > 0) {
                $dealtMods['pos'] += $demolition;
                $dealtMods['posLabels'][] = 'Démolition';
            }
        }
        $actorEffetAgressivite = $dealtMods['pos'];
        $actorEffetFaiblesse = $dealtMods['neg'];
        $targetEffetFragilite = $takenMods['pos'];
        $targetEffetArmure = $takenMods['neg'];

        if ($targetIgnore != false) {
            $this->updatePlayerCaracsWithIgnores($targetIgnore, $target);
        }

        if ($actorIgnore != false) {
            $this->updatePlayerCaracsWithIgnores($actorIgnore, $actor);
        }
            
        [$othersDamages, $malusBonus] = $this->collectActorDamageBonuses($actor, $conditionObject, $actorTraitDamages);
        [$othersDefense, $encaisse] = $this->collectTargetDefenseBonuses($target, $conditionObject, $targetTraitDamagesTaken);

        // Calcul des dégâts
        if(!empty($actorTraitDamages) && !empty($targetTraitDamagesTaken)){
            $actorDamages = (is_numeric($actorTraitDamages)) ? $actorTraitDamages : $actor->caracs->{$actorTraitDamages};
            $targetDefense = (is_numeric($targetTraitDamagesTaken)) ? $targetTraitDamagesTaken : $target->caracs->{$targetTraitDamagesTaken};
            $bonusDamages = (is_numeric($bonusTraitDamages)) ? $bonusTraitDamages : $actor->caracs->{$bonusTraitDamages};
            $bonusDefense = (is_numeric($bonusTraitDefense)) ? $bonusTraitDefense : $target->caracs->{$bonusTraitDefense};
            
            $modifiers = new DamageModifiers(
                bonusDamages: (int) $bonusDamages,
                othersDamages: (int) $othersDamages,
                agressivite: (int) $actorEffetAgressivite,
                faiblesse: (int) $actorEffetFaiblesse,
                bonusDefense: (int) $bonusDefense,
                othersDefense: (int) $othersDefense,
                armure: (int) $targetEffetArmure,
                fragilite: (int) $targetEffetFragilite,
            );

            //minimum damages seulement si l'adversaire à une defense bonus (clamp d'affichage du bonus)
            if($bonusDefense > 0){
                $bonusDamages = max($bonusDamages, 0);
            }

            $totalDamages = (new DamageCalculator())->rawDamage((int) $actorDamages, (int) $targetDefense, $modifiers);

            $cellCount = 0;
            if ($distanceInfluence) {
                $distance = View::get_distance_to_entity($actor->getCoords(), $target->getId(), $target->getCoords());
                $cellCount = $distance - 1;
                $totalDamages = $totalDamages - $cellCount;
            }
            if ($sautInfluence) {
                $distance = View::get_distance_to_entity($actor->getCoords(), $target->getId(), $target->getCoords());
                $cellCount = $distance - 1;
                $totalDamages = $totalDamages + floor(0.5 * $cellCount);
            }
            if($totalDamages < 1){
                $totalDamages = 1;
            }

            //CRIT
            if(rand(1,100) <= DMG_CRIT || $autoCrit){ 
                    $critAdd = 3;
                    $totalDamages += $critAdd;
                    $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = '<font color="red">Critique ! Dégâts augmentés ! +3 !</font>';
            }
    
            //TANK ? (facteur sur les dégâts subis — catalogue, ex-encaisse ;
            // $encaisse peut déjà venir d'un PASSIF, qui garde son 0.75)
            $takenFactor = $effectService->damageTakenFactor($target->getEffects());
            $beforeEncaisseDmg = $totalDamages;
            if($takenFactor >= 1 && $encaisse){
                $takenFactor = 0.75;
            }
            if($takenFactor < 1){
                $encaisse = true;
                $totalDamages = $this->computeDamageTaken((int) $totalDamages, $takenFactor);
            }
            $target->putBonus(array('pv'=>-$totalDamages));

            /* Usure : porter un coup ARME l'arme de l'attaquant
             * (déclencheur « attack »), l'encaisser ARME les protections de
             * la cible (« defense ») — le décrément tombe au passage de
             * tour. WearService n'est pas intercepté par le SimulationGuard,
             * la garde est ici. */
            if (!$actor->isSimulated() && !$target->isSimulated()) {
                $wear = new \App\Service\WearService();
                $wear->arm($actor->id, 'attack');
                $wear->arm($target->id, 'defense');
            }

            // Gestion des logs
            $bonusDamagesText = '';
            $othersDamagesText = '';
            $agresssiviteDamagesText = '';
            $faiblesseDamagesText = '';
            $fragiliteDamagesText = '';
            $armureDamagesText = '';
            
            if ($bonusDamages > 0) {
                $bonusText = '';
                if (!is_numeric($bonusTraitDamages)) {
                    $bonusText = ' '.CARACS[$bonusTraitDamages];
                }
                $bonusDamagesText = ' + ' . $bonusDamages. ' (Bonus'.$bonusText.')';
            }
            if ($othersDamages > 0) {
                $othersDamagesText = ' + ' . $othersDamages. ' (Bonus compétence)';
            }
            if ($bonusDamages < 0) {
                $bonusText = '';
                if (!is_numeric($bonusTraitDamages)) {
                    $bonusText = ' '.CARACS[$bonusTraitDamages];
                }
                $bonusDamagesText = ' - ' . abs($bonusDamages). ' (Bonus'.$bonusText.')';
            }
            $bonusDefenseText = "";
            if ($bonusDefense > 0) {
                $bonusText = '';
                if (!is_numeric($bonusTraitDefense)) {
                    $bonusText = ' '.CARACS[$bonusTraitDefense];
                }
                $bonusDefenseText = ' - ' . $bonusDefense. ' (Bonus défense'.$bonusText.')';
            }
            if ($bonusDefense < 0) {
                $bonusText = '';
                if (!is_numeric($bonusTraitDefense)) {
                    $bonusText = ' '.CARACS[$bonusTraitDefense];
                }
                $bonusDefenseText = ' + ' . abs($bonusDefense). ' (Bonus défense'.$bonusText.')';
            }
            if($actorEffetAgressivite > 0){
                $agresssiviteDamagesText = ' + ' . $actorEffetAgressivite . ' (' . implode(' + ', $dealtMods['posLabels']) . ')';
            }
            if($actorEffetFaiblesse > 0){
                $faiblesseDamagesText = ' - ' . $actorEffetFaiblesse . ' (' . implode(' + ', $dealtMods['negLabels']) . ')';
            }
            if($targetEffetFragilite > 0){
                $fragiliteDamagesText = ' + ' . $targetEffetFragilite . ' (' . implode(' + ', $takenMods['posLabels']) . ')';
            }
            if($targetEffetArmure > 0){
                $armureDamagesText = ' - ' . $targetEffetArmure . ' (' . implode(' + ', $takenMods['negLabels']) . ')';
            }
            $distanceText = "";
            if ($distanceInfluence) {
                $distanceText = ' - '. $cellCount. ' (Distance)';
            }
            if ($sautInfluence) {
                $distanceText = ' + '. floor(0.5 * $cellCount) . ' (Distance)';
            }

            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous infligez <span style="text-decoration: underline;" flow="up" tooltip="' . CARACS[$actorTraitDamages] .' vs '. CARACS[$targetTraitDamagesTaken] . ' : ' . $actorDamages . $bonusDamagesText . $agresssiviteDamagesText . $fragiliteDamagesText . $othersDamagesText .' - ' . $targetDefense . $bonusDefenseText . $faiblesseDamagesText . $armureDamagesText . $distanceText . (($encaisse) ? ' = ' . $beforeEncaisseDmg . ' - ' . ($beforeEncaisseDmg - $totalDamages) . ' (Encaisse)': '') . '">' . $totalDamages . '</span>' .' dégâts à '. $target->data->name.'.';

            // Une structure n'a pas de malus (elle n'esquive jamais) :
            // ni écriture, ni ligne « subit/récupère X malus » au récap.
            if (\App\Enum\EntityCategory::fromPlayerType($target->data->player_type ?? 'real') !== \App\Enum\EntityCategory::Structure) {
                $recoverMalus = $this->computeRecoverMalus((int) $totalDamages);

                if($target->playerPassiveService->hasPassiveByPlayerIdByName($target->getId(),"inepuisable")){
                    $malusBonus--;
                }

                $target->put_malus($malus-$recoverMalus+$malusBonus);
                $malusText = ($malus - $recoverMalus + $malusBonus> 0) ? 'subit ' : ' récupère ';
                $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $target->data->name . ' ' . $malusText . abs($malus-$recoverMalus+$malusBonus) . ' <span style="text-decoration: underline;" flow="up" tooltip="Attaque : ' . $malus . ', Dégâts : -' . $recoverMalus . ', Bonus : ' . $malusBonus . '">malus</span>.';
            }

            $conditionObject->setLifeloss($totalDamages);

            if($isDrain){
                $drain = $this->computeLeech((int) $totalDamages);
                $actor->putBonus(array('pv'=>$drain));
                $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $actor->data->name . ' draine ' . $drain . ' PV.';
            }

            if($isSiphon){
                $siphon = $this->computeLeech((int) $totalDamages);
                $actor->putBonus(array('pm'=>$siphon));
                $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $actor->data->name . ' siphone ' . $siphon . ' PM.';
            }

            // put assist
            $actor->put_assist($target, $totalDamages);

        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$totalDamages);
    }

    /**
     * Attack-passive bonuses the actor contributes to a hit.
     *
     * @return array{0: int, 1: int} [othersDamages, malusBonus]
     */
    private function collectActorDamageBonuses(Player $actor, ConditionObject $conditionObject, mixed $actorTraitDamages): array
    {
        $othersDamages = 0;
        $malusBonus = 0;
        foreach ($actor->playerPassiveService->getPassivesByPlayerId($actor->getId()) as $actorPassive) {
            if($actorPassive->getName() == "maitre_bretteur" && $actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)){
                $malusBonus += $actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId());
            }
            else if($actorPassive->getName() == "escarmoucheur" && $actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)){
                $malusBonus += $actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId());
            }
            else if (in_array($actorTraitDamages, $actorPassive->getTraits()) && ($actorPassive->getType() == "att" || $actorPassive->getType() == "mixte" ) && $actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)) {
                $othersDamages += $actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId());
            }
        }

        return [$othersDamages, $malusBonus];
    }

    /**
     * Defence-passive bonuses the target contributes, and whether "encaisser" triggers.
     *
     * @return array{0: int, 1: bool} [othersDefense, encaisse]
     */
    private function collectTargetDefenseBonuses(Player $target, ConditionObject $conditionObject, mixed $targetTraitDamagesTaken): array
    {
        $othersDefense = 0;
        $encaisse = false;
        foreach ($target->playerPassiveService->getPassivesByPlayerId($target->getId()) as $targetPassive) {
            if (in_array($targetTraitDamagesTaken, $targetPassive->getTraits()) && ($targetPassive->getType() == "def" || $targetPassive->getType() == "mixte" ) && $target->playerPassiveService->checkPassiveConditionsByPlayerById($target,$targetPassive,$conditionObject)) {
                if($targetPassive->getName() === "dur_cuire"){
                    if($target->getRemaining('pv') <= $target->playerPassiveService->getComputedValueByPlayerIdById($target->id,$targetPassive->getId())){
                        $encaisse = true;
                    }
                }
                else{
                    $othersDefense += $target->playerPassiveService->getComputedValueByPlayerIdById($target->id,$targetPassive->getId());
                }
            }
        }

        return [$othersDefense, $encaisse];
    }

    public function computeDamageTaken(int $damage, float $factor = 0.75): int
    {
        return max(1, (int) floor($damage * $factor));
    }

    public function computeRecoverMalus(int $damage): int
    {
        return (int) floor($damage / 2);
    }

    public function computeLeech(int $damage): int
    {
        return (int) floor($damage / 3);
    }

    private function updatePlayerCaracsWithIgnores(array $ignore, ActorInterface $player)
    {
        $itemToEquip = array();
        foreach($ignore as $emp){
            if(!empty($player->emplacements->{$emp})){
                // unequip
                $player->equip($player->emplacements->{$emp}, true);
                $itemToEquip[$emp] = $player->emplacements->{$emp};
                unset($player->emplacements->{$emp});
            }
        }
        // update caracs & refresh equipment
        $player->get_caracs();
        // store caracs without ignored equipement
        $caracsCp = clone $player->caracs;
        // re equip
        foreach($itemToEquip as $emp=>$item){
            $player->equip($item, true);
        }
    
        // apply caracs without ignored equipement. at this point if ignoring hands, "poing" is equiped in $player but not in db
        $player->caracs = $caracsCp;
    }
}

