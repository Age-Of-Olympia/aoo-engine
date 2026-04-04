<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use App\Service\ActionPassiveService;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use Classes\View;

#[ORM\Entity]
class LifeLossOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        // e.g. { "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait" : "m", "distance" : true, "autoCrit": true, "targetIgnore": ["tronc"], "actorIgnore": false }
        $actorTraitDamages = $this->getParameters()['actorDamagesTrait'] ?? 0;
        $targetTraitDamagesTaken = $this->getParameters()['targetDamagesTrait'] ?? 0;
        $bonusTraitDamagesParameters = $this->getParameters()['bonusDamagesTrait'] ?? 0;
        $isDrain = $this->getParameters()["drain"] ?? false;
        $isSiphon = $this->getParameters()["siphon"] ?? false;
        $bonusTraitDamages = (is_array($bonusTraitDamagesParameters) ? floor($actor->caracs->{$bonusTraitDamagesParameters[0]}/$bonusTraitDamagesParameters[1]) : $bonusTraitDamagesParameters) ?? 0;
        $bonusTraitDefense = $this->getParameters()['bonusDefenseTrait'] ?? 0;
        $othersDamages = 0;
        $othersDefense = 0;
        $distanceInfluence = $this->getParameters()['distance'] ?? false;
        $sautInfluence = $this->getParameters()['saut'] ?? false;
        $targetIgnore = $this->getParameters()['targetIgnore'] ?? false;
        $actorIgnore = $this->getParameters()['actorIgnore'] ?? false;
        $autoCrit = $this->getParameters()['autoCrit'] ?? false;
        $outcomeSuccessMessages = array();
        $encaisse = false;
        $actorEffetFaiblesse = $actor->getEffectValue("faiblesse");
        $actorEffetAgressivite = $actor->getEffectValue("agressivite");
        $targetEffetFragilite = $target->getEffectValue("fragilite");
        $targetEffetArmure = $target->getEffectValue("armure");
        $actionPassiveService = new ActionPassiveService();

        if ($targetIgnore != false) {
            $this->updatePlayerCaracsWithIgnores($targetIgnore, $target);
        }

        if ($actorIgnore != false) {
            $this->updatePlayerCaracsWithIgnores($actorIgnore, $actor);
        }

        foreach ($actor->playerPassiveService->getPassivesByPlayerId($actor->getId()) as $actorPassive) {
            if (in_array($actorTraitDamages, $actorPassive->getTraits()) && ($actorPassive->getType() == "att" || $actorPassive->getType() == "mixte" ) && $actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)) {
                $othersDamages += $actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId());
            }
        }

        foreach ($target->playerPassiveService->getPassivesByPlayerId($target->getId()) as $targetPassive) {
            if (in_array($targetTraitDamagesTaken, $targetPassive->getTraits()) && ($targetPassive->getType() == "def" || $targetPassive->getType() == "mixte" ) && $target->playerPassiveService->checkPassiveConditionsByPlayerById($target,$targetPassive,$conditionObject)) {
                if($targetPassive->getName() === "encaisse"){
                    if($target->getRemaining('pv') <= $target->playerPassiveService->getComputedValueByPlayerIdById($target->id,$targetPassive->getId())){
                        $encaisse = true;
                    }
                }
                else{
                    $othersDefense += $target->playerPassiveService->getComputedValueByPlayerIdById($target->id,$targetPassive->getId());
                }
            }
        }

        if(!empty($actorTraitDamages) && !empty($targetTraitDamagesTaken)){
            $actorDamages = (is_numeric($actorTraitDamages)) ? $actorTraitDamages : $actor->caracs->{$actorTraitDamages};
            $targetDefense = (is_numeric($targetTraitDamagesTaken)) ? $targetTraitDamagesTaken : $target->caracs->{$targetTraitDamagesTaken};
            $bonusDamages = (is_numeric($bonusTraitDamages)) ? $bonusTraitDamages : $actor->caracs->{$bonusTraitDamages};
            $bonusDefense = (is_numeric($bonusTraitDefense)) ? $bonusTraitDefense : $target->caracs->{$bonusTraitDefense};
            
            $baseDamages = $actorDamages - $targetDefense;
        
            $additionalDamages = ($bonusDamages + $othersDamages + $actorEffetAgressivite - $actorEffetFaiblesse) - ($bonusDefense + $othersDefense + $targetEffetArmure - $targetEffetFragilite);

            //minimum damages seulement si l'adversaire à une defense bonus
            if($bonusDefense > 0){
                $bonusDamages = max($bonusDamages, 0);
                $baseDamages = max($baseDamages, 0);
            }

            $totalDamages = $baseDamages + $additionalDamages;

            $cellCount = 0;
            if ($distanceInfluence) {
                $distance = View::get_distance($actor->getCoords(), $target->getCoords());
                $cellCount = $distance - 1;
                $totalDamages = $totalDamages - $cellCount;
            }
            if ($sautInfluence) {
                $distance = View::get_distance($actor->getCoords(), $target->getCoords());
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
    
            //TANK ?
            if($target->getEffectValue("encaisse") > 0){
                $encaisse = true;
            }

            if($encaisse){
                $beforeEncaisseDmg = $totalDamages ?? 0;
                $totalDamages = max(1,floor($totalDamages*0.75));
            }
            $target->putBonus(array('pv'=>-$totalDamages));
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
                $agresssiviteDamagesText = ' + ' . $actorEffetAgressivite . ' (Agresssivité)';
            }
            if($actorEffetFaiblesse > 0){
                $faiblesseDamagesText = ' - ' . $actorEffetFaiblesse . ' (Faiblesse)';
            }
            if($targetEffetFragilite > 0){
                $fragiliteDamagesText = ' + ' . $targetEffetFragilite . ' (Fragilité)';
            }
            if($targetEffetArmure > 0){
                $armureDamagesText = ' - ' . $targetEffetArmure . ' (Armure)';
            }
            $distanceText = "";
            if ($distanceInfluence) {
                $distanceText = ' - '. $cellCount. ' (Distance)';
            }
            if ($sautInfluence) {
                $distanceText = ' + '. floor(0.5 * $cellCount) . ' (Distance)';
            }

            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous infligez <span style="text-decoration: underline;" flow="up" tooltip="' . CARACS[$actorTraitDamages] .' vs '. CARACS[$targetTraitDamagesTaken] . ' : ' . $actorDamages . $bonusDamagesText . $agresssiviteDamagesText . $fragiliteDamagesText . $othersDamagesText .' - ' . $targetDefense . $bonusDefenseText . $faiblesseDamagesText . $armureDamagesText . $distanceText . (($encaisse) ? ' = ' . $beforeEncaisseDmg . ' - ' . ($beforeEncaisseDmg - $totalDamages) . ' (Encaisse)': '') . '">' . $totalDamages . '</span>' .' dégâts à '. $target->data->name.'.';
            
            $malus = random_int(1,3);
            $malusBonus = 0;
            if($actor->playerPassiveService->hasPassiveByPlayerIdByName($actor->getId(),"maitre_bretteur") && $actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actionPassiveService->getActionPassiveByName("maitre_bretteur"),$conditionObject)){
                $malusBonus += $actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId());
            }
            if($actor->playerPassiveService->hasPassiveByPlayerIdByName($actor->getId(),"escarmoucheur") && $actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actionPassiveService->getActionPassiveByName("escarmoucheur"),$conditionObject)){
                $malusBonus += $actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId());
            }
            if (in_array($actorTraitDamages, $actorPassive->getTraits()) && ($actorPassive->getType() == "att" || $actorPassive->getType() == "mixte" ) && $actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)) {
                $othersDamages += $actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId());
            }
            if($target->playerPassiveService->hasPassiveByPlayerIdByName($target->getId(),"inepuisable")){
                $malusBonus--;
            }
            $recoverMalus = floor($totalDamages/2);

            $target->put_malus($malus-$recoverMalus+$malusBonus);
            $malusText = ($malus - $recoverMalus + $malusBonus> 0) ? 'subit ' : ' récupère ';
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $target->data->name . ' ' . $malusText . abs($malus-$recoverMalus+$malusBonus) . ' <span style="text-decoration: underline;" flow="up" tooltip="Attaque : ' . $malus . ', Dégâts : -' . $recoverMalus . ', Bonus : ' . $malusBonus . '">malus</span>.';

            $conditionObject->setLifeloss($totalDamages);

            if($isDrain){
                $drain = floor($totalDamages/3);
                $actor->putBonus(array('pv'=>$drain));
                $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $actor->data->name . ' draine ' . $drain . ' PV.';
            }

            if($isSiphon){
                $siphon = floor($totalDamages/2);
                $actor->putBonus(array('pm'=>$siphon));
                $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $actor->data->name . ' siphone ' . $siphon . ' PM.';
            }

            // put assist
            $actor->put_assist($target, $totalDamages);

        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$totalDamages);
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

