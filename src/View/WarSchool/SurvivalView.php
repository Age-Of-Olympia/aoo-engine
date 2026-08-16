<?php

namespace App\View\WarSchool;

use Classes\Player;
use Classes\Str;
use Classes\Item;
use App\Service\ActionService;
use App\Service\ActionPassiveService;
use App\Service\RaceService;
use App\View\Action\ActionCostView;

class SurvivalView
{
    public static function render(Player $player): void
    {
        $actionService = new ActionService();
        $costView = new ActionCostView($actionService);
        $actionPassiveService = new ActionPassiveService();
        $actions = $actionService->getActionsByCategory('survival');
        $passives = $actionPassiveService->getActionPassivesByCategory('survival');

        $nb_comp = $actionPassiveService->getActionPassiveCount($player->getId()) + $player->get_spells_count();

        $playerGold = $player->get_gold();
        $isFull = ($nb_comp >= NUMBER_MAX_COMP);

        if (!empty($_POST['buySkillId']) || !empty($_POST['buyPassiveId'])) {
            if (ob_get_length()) ob_clean();
            if ($nb_comp >= NUMBER_MAX_COMP) {
                echo '<div id="data">Limite de compétences atteinte (max ' . NUMBER_MAX_COMP . ') !</div>';
                exit;
            }

            $type = !empty($_POST['buyPassiveId']) ? 'passive' : 'active';
            $skillName = $_POST['buyPassiveId'] ?? $_POST['buySkillId'];

            $skillToBuy = ($type === 'active') 
                ? $actionService->getActionByName($skillName)
                : $actionPassiveService->getActionPassiveByName($skillName);

            if ($skillToBuy) {
                $price = ($type === 'active') 
                    ? $actionService->getPrice($skillToBuy->getLevel()) 
                    : $actionPassiveService->getPrice($skillToBuy->getLevel());

                if ($playerGold < $price) {
                    echo '<div id="data">Or insuffisant !</div>';
                    exit;
                }

                $alreadyHas = ($type === 'active') ? $player->have_action($skillName) : $player->have_action_passive($skillName);
                if ($alreadyHas) {
                    echo '<div id="data">Compétence déjà connue.</div>';
                    exit;
                }

                $goldItem = new Item(1);
                $goldItem->add_item($player, -$price);

                if ($type === 'active') {
                    $player->add_action($skillName); 
                } else {
                    $player->add_action_passive($skillName); 
                }

                echo '<div id="data">Compétence ' . $type . ' apprise !</div>';
                exit;
            }
            echo '<div id="data">Erreur : Compétence introuvable.</div>';
            exit;
        }

        ob_start();

        echo '<h1>Compétences de Survie</h1>';
        echo '<p class="ws-info">Vous avez ' . $playerGold . ' Po&nbsp;&middot;&nbsp;Compétences apprises : ' . $nb_comp . '/' . NUMBER_MAX_COMP . ' (sorts + passifs cumulés)</p>';
        echo '<details style="cursor: pointer; margin-bottom: 20px; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 5px;">';
            echo '<summary style="display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: bold; margin: 15px 0; outline: none;">';
                echo '<span style="display: list-item; list-item-type: disclosure-closed; margin-right: 10px;"></span>';
                echo '<h3 style="margin: 0; display: inline; font-size: 1.17em;">Plus d\'informations sur les Compétences</h3>';
            echo '</summary>';
            echo '<h3 style="margin: 5px 0;">Les compétences <strong style="color: #2980b9;">personnelles</strong> sont en bleu et appliquent un bonus personnel</h3>';
            echo '<h3 style="margin: 5px 0;">Les différents Effets sont décrits sur la <a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets" target="_blank" style="text-decoration: underline; color: #2980b9;">page correspondante</a> du Wiki</h3>';
        echo '</details>';        
        
        echo '<div class="section">';
        echo '<h2>Compétences actives</h2>';
        if (empty($actions)) {
        echo '<p>Aucune compétence active de survie disponible.</p>';
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
                $alreadyLearned = (bool)$player->have_action($action->getName());
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
                if ($alreadyLearned) {
                    echo '<button class="create" disabled>
                            Déjà apprise
                        </button>';
                } elseif (!$isRaceLearnable) {
                    echo '<button class="create" disabled>
                            Impossible à apprendre
                        </button>';
                } else {
                    $disabled = (($playerGold < $price) || $isFull) ? 'disabled' : '';
                    $btnText = $isFull ? 'Max atteint' : 'Acheter : ' . $price . ' Po';
                    echo '<button class="create buy-skill-btn" data-id="' . $actionName . '" data-type="active" ' . $disabled . '>' . $btnText . '</button>';
                }
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
        echo '<p>Aucune compétence passive de survie disponible.</p>';
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
                $alreadyLearned = (bool)$player->have_action_passive($passive->getName());

                $pRace = $passive->getRace();
                $isRaceLearnable = (empty($pRace) || $player->data->race == $pRace);
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
                if ($alreadyLearned) {
                    echo '<button class="create" disabled>
                            Déjà apprise
                        </button>';
                } elseif (!$isRaceLearnable) {
                    echo '<button class="create" disabled>
                            Impossible à apprendre
                        </button>';
                } else {
                    $disabled = (($playerGold < $price) || $isFull) ? 'disabled' : '';
                    $btnText = $isFull ? 'Max atteint' : 'Acheter : ' . $price . ' Po';
                    echo '<button class="create buy-skill-btn" data-id="' . $passiveName . '" data-type="passive" ' . $disabled . '>' . $btnText . '</button>';
                }
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
