<?php

namespace App\View\WarSchool;

use Classes\Player;
use Classes\Str;
use Classes\Item;
use App\Service\ActionService;
use App\Service\ActionPassiveService;

class MeleeView
{
    public static function render(Player $player, Player $target): void
    {
        $actionService = new ActionService();
        $actionPassiveService = new ActionPassiveService();
        $actions = $actionService->getActionsByCategory('melee');
        $passives = $actionPassiveService->getActionPassivesByCategory('melee');

        $playerGold = $player->get_gold();

        if (!empty($_POST['buySkillId'])) {
            $skillName = $_POST['buySkillId'];
            $actionToBuy = $actionService->getActionByName($skillName);

            if ($actionToBuy) {
                $price = $actionService->getPrice($actionToBuy->getLevel());
                
                if ($playerGold < $price) {
                    exit('<div id="data">Or insuffisant !</div>');
                }
                if ($player->have_action($skillName)) {
                    exit('<div id="data">Compétence déjà connue.</div>');
                }
                
                $goldItem = new Item(1);
                $goldItem->add_item($player, -$price);
                
                $player->add_action($skillName); 

                exit('<div id="data">Compétence apprise avec succès !</div>');
            }
            exit('<div id="data">Erreur lors de l\'apprentissage.</div>');
        }

        ob_start();

        echo '<h1>Compétences de Mêlée</h1>';

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
                        <th>Race</th>
                        <th>Prix</th>
                    </tr>
                  </thead>';
            echo '<tbody>';

            foreach ($actions as $action) {
                $actionName = $action->getName();
                $color = self::getColor($action->getCategory());
                $raceColor = self::getRaceColor($action->getRace());
                $alreadyLearned = (bool)$player->have_action($action->getName());
                $actionRace = $action->getRace();
                $isRaceLearnable = (bool)$player->data->race == $actionRace;
                $raceTxt = (!empty($actionRace)) ? ucfirst($actionRace) : 'Commun';
                
                $price = $actionService->getPrice($action->getLevel());
                $disabled = ($playerGold < $price) ? 'disabled' : '';

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

                echo '<td align="center"><strong style="color: ' . $raceColor . ';">' . $raceTxt . '</strong></td>';

                echo '<td>';
                if ($alreadyLearned) {
                    echo '<button class="create" disabled>
                            Déjà apprise
                        </button>';
                } elseif ($isRaceLearnable) {
                    echo '<button class="create" disabled>
                            Mauvaise race
                        </button>';
                } else {
                    $disabled = ($playerGold < $price) ? 'disabled' : '';
                    echo '<button class="create buy-skill-btn" data-id="' . $actionName . '" ' . $disabled . '>Acheter : ' . $price . ' Po</button>';
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
        echo '<p>Aucune compétence passive de magie disponible.</p>';
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
                $color = self::getColor($passive->getCategory());
                $raceColor = self::getRaceColor($passive->getRace());
                $alreadyLearned = (bool)$player->have_action($passive->getName());
                $isRaceLearnable = (bool)$player->data->race == $passive->getRace();

                $race = $passive->getRace();
                $raceTxt = (!empty($race)) ? ucfirst($race) : 'Commun';
                
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
                } elseif ($isRaceLearnable) {
                    echo '<button class="create" disabled>
                            Impossible à apprendre
                        </button>';
                } else {
                    echo '<button class="create buy-skill" 
                            data-skill-id="' . $passive->getName() . '" 
                            data-name="' . htmlspecialchars($passive->getDisplayName()) . '" 
                            ' . $disabled . '>
                            Acheter : ' . $price . ' Po
                        </button>';
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
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            $(document).ready(function() {
                $('.buy-skill-btn').click(function() {
                    if(!confirm('Voulez-vous vraiment apprendre cette compétence ?')) return;
                    
                    var btn = $(this);
                    btn.prop('disabled', true);
                    var skillId = btn.data('id');

                    $.ajax({
                        type: "POST",
                        url: window.location.href,
                        data: { 'buySkillId': skillId },
                        success: function(response) {
                            var message = $('<div>').html(response).find('#data').html();
                            alert(message || "Action effectuée");
                            document.location.reload();
                        },
                        error: function() {
                            alert("Erreur réseau");
                            btn.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php

    }

    public static function getColor(String $category): string {

        $parts = explode('-', $category);
        $subCategory = $parts[1] ?? '';
        switch ($subCategory) {
        case 'off':
            return '#c0392b'; 
        case 'support':
            return '#27ae60'; 
        case 'buff':
            return '#2980b9'; 
        case 'curse':
            return '#8e44ad'; 
        default:
            return '#000000'; 
        }
    }

    public static function getRaceColor(String $race): string {

        switch ($race) {
        case 'nain':
            return '#FF0000'; 
        case 'olympien':
            return '#ff9933'; 
        case 'elfe':
            return '#008000'; 
        case 'geant':
            return '#661414'; 
        case 'hs':
            return '#2e6650'; 
        default:
            return '#000000'; 
        }
    }
}
