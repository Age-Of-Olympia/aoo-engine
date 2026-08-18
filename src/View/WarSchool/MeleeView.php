<?php

namespace App\View\WarSchool;

use Classes\Player;
use Classes\Str;
use Classes\Item;
use App\Service\ActionService;
use App\Service\ActionPassiveService;
use App\Service\RaceService;
use App\View\Action\ActionCostView;
use App\Service\WarSchool\SkillPrerequisiteService;

class MeleeView
{
    public static function render(Player $player): void
    {
        $actionService = new ActionService();
        $costView = new ActionCostView($actionService);
        $actionPassiveService = new ActionPassiveService();
        $actions = $actionService->getActionsByCategory('melee');
        $passives = $actionPassiveService->getActionPassivesByCategory('melee');

        $prereqs = SkillPrerequisiteService::forPlayer($player->getId());
        $nb_comp = $prereqs->capCount();
        $isFull = $prereqs->isFull();
        $playerGold = $player->get_gold();

        SkillPurchaseHandler::handlePost($player, $prereqs);

        ob_start();

        echo '<h1>Compétences de Mêlée</h1>';
        echo '<p class="ws-info">Vous avez ' . $playerGold . ' Po&nbsp;&middot;&nbsp;Compétences apprises : ' . $nb_comp . '/' . NUMBER_MAX_COMP . ' (sorts + passifs cumulés)</p>';
        echo '<details style="cursor: pointer; margin-bottom: 20px; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 5px;">';
            echo '<summary style="display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: bold; margin: 15px 0; outline: none;">';
                echo '<span style="display: list-item; list-item-type: disclosure-closed; margin-right: 10px;"></span>';
                echo '<h3 style="margin: 0; display: inline; font-size: 1.17em;">Plus d\'informations sur les Compétences</h3>';
            echo '</summary>';
            echo '<h3 style="margin: 5px 0;">Les compétences de Mêlée touchent avec la <strong>CC</strong> et s\'esquivent avec la <strong>CC</strong> ou l\'<strong>Agi</strong> (la meilleure des deux)</h3>';
            echo '<h3 style="margin: 5px 0;">Les compétences <strong style="color: #c0392b;">offensives</strong> sont en rouge et font des dégâts basés sur la <strong>F</strong> et réduits par la <strong>E</strong></h3>';
            echo '<h3 style="margin: 5px 0;">Les compétences <strong style="color: #8e44ad;">déstabilisantes</strong> sont en violet et ne font pas de dégâts</h3>';
            echo '<h3 style="margin: 5px 0;">Les compétences <strong style="color: #2980b9;">personnelles</strong> sont en bleu et appliquent un bonus personnel</h3>';
            echo '<h3 style="margin: 5px 0;">Les différents Effets sont décrits sur la <a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets" target="_blank" style="text-decoration: underline; color: #2980b9;">page correspondante</a> du Wiki</h3>';
        echo '</details>';        
        
        echo '<div class="section">';
        echo '<h2>Compétences actives</h2>';
        if (empty($actions)) {
        echo '<p>Aucune compétence active de mêlée disponible.</p>';
        } else {
        echo '<table border="1" align="center" class="marbre">';
            echo '<thead>
                    <tr>
                        <th>Icône</th>
                        <th>Nom</th>
                        <th>Effet</th>
                        <th>Coût</th>
                        <th>Race</th>
                        <th>Prix</th>
                    </tr>
                  </thead>';
            echo '<tbody>';

            foreach ($actions as $action) {
                $actionName = $action->getName();
                $color = WarSchoolUtils::getColor($action->getCategory());
                $raceColor = RaceService::getRaceColor($action->getRace());
                $alreadyLearned = $prereqs->owns($actionName);
                $actionRace = $action->getRace();
                $isRaceLearnable = (empty($actionRace) || $player->data->race == $actionRace);
                $raceTxt = (!empty($actionRace)) ? ucfirst($actionRace) : 'Commun';
                
                $price = $actionService->getPrice($action->getLevel());

                $imagePath = 'img/spells/' . $actionName . '.jpeg';
                $imageSrc = file_exists($imagePath) ? $actionName : 'todo';

                echo '<tr>';
                echo '<td>';
                echo '<img src="img/spells/' . $imageSrc . '.jpeg" />';
                echo '</td>';

                echo '<td align="left">';
                echo '<strong style="color: ' . $color . ';">' . htmlspecialchars($action->getDisplayName()) . '</strong><br />';
                echo '<sup>Niveau ' . $action->getLevel() . '</sup>';
                echo '</td>';

                echo '<td align="left" style="max-width: 400px; padding: 10px;">';
                echo '<i>' . htmlspecialchars($action->getText()) . '</i>';
                echo '</td>';

                echo '<td align="center"><strong>' . $costView->forAction($action) . '</strong></td>';

                echo '<td align="center"><strong style="color: ' . $raceColor . ';">' . $raceTxt . '</strong></td>';

                echo '<td>';
                echo SkillPurchaseHandler::buyButton($actionName, 'active', $price, $playerGold,
                    $alreadyLearned, $isRaceLearnable, $isFull, $prereqs->isUsable($action));
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';

        echo '<div class="section">';
        echo '<h2>Compétences passives</h2>';
        if (empty($passives)) {
        echo '<p>Aucune compétence passive de mêlée disponible.</p>';
        } else {
            echo '<table border="1" align="center" class="marbre">';
            echo '<thead>
                    <tr>
                        <th>Icône</th>
                        <th>Nom</th>
                        <th>Effet</th>
                        <th>Race</th>
                        <th>Prix</th>
                    </tr>
                  </thead>';
            echo '<tbody>';

            foreach ($passives as $passive) {
                $passiveName = $passive->getName();
                $color = WarSchoolUtils::getColor($passive->getCategory());
                $raceColor = RaceService::getRaceColor($passive->getRace());
                $alreadyLearned = $prereqs->owns($passiveName);
                $passiveRace = $passive->getRace();
                $isRaceLearnable = (empty($passiveRace) || $player->data->race == $passiveRace);

                $pRace = $passive->getRace();
                $raceTxt = (!empty($pRace)) ? ucfirst($pRace) : 'Commun';
                
                $price = $actionPassiveService->getPrice($passive->getLevel());

                $imagePath = 'img/spells/' . $passiveName . '.jpeg';
                $imageSrc = file_exists($imagePath) ? $passiveName : 'todo';

                echo '<tr>';
                echo '<td>';
                echo '<img src="img/spells/' . $imageSrc . '.jpeg" />';
                echo '</td>';

                echo '<td align="left">';
                echo '<strong style="color: ' . $color . ';">' . htmlspecialchars($passive->getDisplayName()) . '</strong><br />';
                echo '<sup>Niveau ' . $passive->getLevel() . '</sup>';
                echo '</td>';

                echo '<td align="left" style="max-width: 400px; padding: 10px;">';
                echo '<i>' . htmlspecialchars($passive->getText()) . '</i>';
                echo '</td>';

                echo '<td align="center"><strong style="color: ' . $raceColor . ';">' . $raceTxt . '</strong></td>';

                echo '<td>';
                echo SkillPurchaseHandler::buyButton($passiveName, 'passive', $price, $playerGold,
                    $alreadyLearned, $isRaceLearnable, $isFull, $prereqs->isPassiveUsable($passive));
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';

    echo Str::minify(ob_get_clean());

    ?>
        <script src="js/warschool.js?v=20260714"></script>
        <?php

    }

}
