<?php

namespace App\View\WarSchool;

use Classes\Player;
use Classes\Str;
use App\Service\ActionService;

class SpellView
{
    public static function render(Player $player, Player $target): void
    {
        $actionService = new ActionService();

        $actions = $actionService->getActionsByCategory('spell');

        ob_start();

        echo '<h1>Sorts</h1>';
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
                        <th>Race</th>
                        <th>Prix</th>
                    </tr>
                  </thead>';
            echo '<tbody>';

            foreach ($actions as $action) {
                $actionName = $action->getName();
                $color = self::getColor($action->getCategory());
                $raceColor = self::getRaceColor($action->getRace());

                $race = $action->getRace();
                $raceTxt = (!empty($race)) ? ucfirst($race) : 'Commun';
                
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

                echo '<td align="center"><strong style="color: ' . $raceColor . ';">' . $raceTxt . '</strong></td>';

                echo '<td>';
                echo '<button class="create">' . 'Acheter : ' . $price . ' Po</button>';
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';

    echo Str::minify(ob_get_clean());
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
