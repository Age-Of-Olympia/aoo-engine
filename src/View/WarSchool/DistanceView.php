<?php

namespace App\View\WarSchool;

use Classes\Player;
use Classes\Str;
use Classes\Item;
use App\Service\ActionService;
use App\Service\ActionPassiveService;

class DistanceView
{
    public static function render(Player $player, Player $target): void
    {
        $actionService = new ActionService();
        $actionPassiveService = new ActionPassiveService();
        $actions = $actionService->getActionsByCategory('distance');
        $passives = $actionPassiveService->getActionPassivesByCategory('distance');

        $nb_comp = $actionPassiveService->getActionPassiveCount($player->getId()) + $player->get_spells_count();

        $playerGold = $player->get_gold();

        if (!empty($_POST['buySkillId']) || !empty($_POST['buyPassiveId'])) {
            if (ob_get_length()) ob_clean();
            echo '<div id="data">Limite de compétences atteinte (max ' . NUMBER_MAX_COMP . ') !</div>';
            exit;

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

        echo '<h1>Compétences à Distance</h1>';
        echo '<h2>Vous avez ' . $playerGold . ' Po</h2>';
        echo '<h2>Compétences apprises : ' . $nb_comp . '/' . NUMBER_MAX_COMP . '</h2>';
        echo '<details style="cursor: pointer; margin-bottom: 20px; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 5px;">';
            echo '<summary style="display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: bold; margin: 15px 0; outline: none;">';
                echo '<span style="display: list-item; list-item-type: disclosure-closed; margin-right: 10px;"></span>';
                echo '<h3 style="margin: 0; display: inline; font-size: 1.17em;">Plus d\'informations sur les Compétences</h3>';
            echo '</summary>';
            echo '<h3 style="margin: 5px 0;">Les compétences à Distance touchent avec la <strong>CT</strong> et s\'esquivent avec la <strong>CC</strong> et l\'<strong>Agi</strong> (75% de la meilleure, 25% de l\'autre)</h3>';
            echo '<h3 style="margin: 5px 0;">Les compétences à Distance subissent les malus de Tir</h3>';
            echo '<h3 style="margin: 5px 0;">Les compétences <strong style="color: #c0392b;">offensives</strong> sont en rouge et font des dégâts basés sur la <strong>F</strong> et réduits par la <strong>E</strong></h3>';
            echo '<h3 style="margin: 5px 0;">Les compétences <strong style="color: #8e44ad;">déstabilisantes</strong> sont en violet et ne font pas de dégâts</h3>';
            echo '<h3 style="margin: 5px 0;">Les compétences <strong style="color: #2980b9;">personnelles</strong> sont en bleu et appliquent un bonus personnel</h3>';
            echo '<h3 style="margin: 5px 0;">Les différents Effets sont décrits sur la <a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets" target="_blank" style="text-decoration: underline; color: #2980b9;">page correspondante</a> du Wiki</h3>';
        echo '</details>';        
        
        echo '<div class="section">';
        echo '<h2>Compétences actives</h2>';
        if (empty($actions)) {
        echo '<p>Aucune compétence active à distance disponible.</p>';
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
                $raceColor = WarSchoolUtils::getRaceColor($action->getRace());
                $alreadyLearned = (bool)$player->have_action($action->getName());
                $actionRace = $action->getRace();
                $isRaceLearnable = (bool)$player->data->race == $actionRace;
                $raceTxt = (!empty($actionRace)) ? ucfirst($actionRace) : 'Commun';
                
                $price = $actionService->getPrice($action->getLevel());
                $isFull = ($nb_comp >= NUMBER_MAX_COMP);
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

                echo '<td align="center"><strong>' . $action->getCost() . '</strong></td>';

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
        echo '<p>Aucune compétence passive à distance disponible.</p>';
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
                $raceColor = WarSchoolUtils::getRaceColor($passive->getRace());
                $alreadyLearned = (bool)$player->have_action_passive($passive->getName());
                $isRaceLearnable = (bool)$player->data->race == $passive->getRace();

                $pRace = $passive->getRace();
                $raceTxt = (!empty($pRace)) ? ucfirst($pRace) : 'Commun';
                
                $price = $actionPassiveService->getPrice($passive->getLevel());
                $disabled = ($playerGold < $price) ? 'disabled' : '';

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
                    echo '<button class="create buy-skill-btn" data-id="' . $passiveName . '" data-type="passive" ' . $disabled . '>Acheter : ' . $price . ' Po</button>';
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
                    var btn = $(this);
                    var skillId = btn.data('id');
                    var type = btn.data('type'); // 'active' ou 'passive'

                    if(!confirm('Voulez-vous vraiment apprendre cette compétence ?')) return;
                    
                    btn.prop('disabled', true);

                    var postData = {};
                    if (type === 'passive') {
                        postData = { 'buyPassiveId': skillId };
                    } else {
                        postData = { 'buySkillId': skillId };
                    }

                    $.ajax({
                        type: "POST",
                        url: window.location.href,
                        data: postData,
                        success: function(response) {
                            // On cherche la div #data dans la réponse brute
                            var message = $(response).find('#data').html() || $(response).filter('#data').html();
                            
                            if (message) {
                                alert(message);
                            } else {
                                // Si on ne trouve pas #data, on affiche la réponse brute pour débugger
                                console.log(response); 
                                alert("Réponse serveur : " + response.replace(/<[^>]*>?/gm, ''));
                            }
                            
                            document.location.reload();
                            },
                        error: function(xhr) {
                            alert("Erreur réseau : " + xhr.status);
                            btn.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php

    }

}
