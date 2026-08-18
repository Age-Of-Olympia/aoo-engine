<?php

namespace App\View\WarSchool;

use Classes\Player;
use Classes\Str;
use App\Service\ActionService;
use App\Service\RaceService;
use App\View\Action\ActionCostView;
use App\Service\WarSchool\SkillPrerequisiteService;

class SpellView
{
    public static function render(Player $player): void
    {
        $actionService = new ActionService();
        $costView = new ActionCostView($actionService);
        $actions = $actionService->getActionsByCategory('spell');

        $prereqs = SkillPrerequisiteService::forPlayer($player->getId());
        $playerGold = $player->get_gold();

        SkillPurchaseHandler::handlePost($player, $prereqs);

        ob_start();

        echo '<h1>Sorts</h1>';
        $slots = [];
        for ($level = 1; $level <= 5; $level++) {
            $full = $prereqs->hasFreeSpellSlot($level) ? '' : ' style="color: red;"';
            $slots[] = 'lvl ' . $level . ' : <span' . $full . '>' . $prereqs->spellCountAt($level) . '/' . $prereqs->spellSlotsAt($level) . '</span>';
        }
        echo '<p class="ws-info">Vous avez ' . $playerGold . ' Po&nbsp;&middot;&nbsp;Emplacements de sorts : ' . implode('&nbsp;&middot;&nbsp;', $slots) . '</p>';
        echo '<details style="cursor: pointer; margin-bottom: 20px; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 5px;">';
            echo '<summary style="display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: bold; margin: 15px 0; outline: none;">';
                echo '<span style="display: list-item; list-item-type: disclosure-closed; margin-right: 10px;"></span>';
                echo '<h3 style="margin: 0; display: inline; font-size: 1.17em;">Plus d\'informations sur les Sorts</h3>';
            echo '</summary>';
            echo '<h3 style="margin: 5px 0;">Les sorts touchent avec la <strong>FM</strong> et s\'esquivent avec la <strong>FM</strong></h3>';
            echo '<h3 style="margin: 5px 0;">Les sorts <strong style="color: #c0392b;">offensives</strong> sont en rouge et font des dégâts basés sur la <strong>Pui</strong> et réduits par la <strong>Rés</strong></h3>';
            echo '<h3 style="margin: 5px 0;">Les <strong style="color: #8e44ad;">malédictions</strong> sont en violet et ne font pas de dégâts directs</h3>';
            echo '<h3 style="margin: 5px 0;">Les sorts de <strong style="color: #27ae60;">soutien</strong> sont en vert et appliquent un bonus à une cible alliée</h3>';
            echo '<h3 style="margin: 5px 0;">Les sorts <strong style="color: #2980b9;">personnels</strong> sont en bleu et appliquent un bonus personnel</h3>';
            echo '<h3 style="margin: 5px 0;">Les différents Effets sont décrits sur la <a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets" target="_blank" style="text-decoration: underline; color: #2980b9;">page correspondante</a> du Wiki</h3>';
        echo '</details>';        
        
        echo '<div class="section">';
        if (empty($actions)) {
        echo '<p>Aucun sort disponible.</p>';
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
                $isFull = !$prereqs->hasFreeSpellSlot($action->getLevel());
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

    echo Str::minify(ob_get_clean());

    ?>
        <script src="js/warschool.js?v=20260714"></script>
        <?php

    }

}
