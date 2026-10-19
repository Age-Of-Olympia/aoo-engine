<?php

namespace App\View\Classement;

use App\Entity\Character;
use App\Factory\PlayerFactory;
use App\Service\PlayerCaracsService;
use App\Service\ProgressionService;

class BourrinsView
{
    private const CARACS_BOURRINS = [
        'pv' => "Increvables! Si si je vous jure!",
        'cc' => "Les meilleurs maîtres lames.",
        'ct' => "Les meilleurs tireurs.",
        'f' => "Ils ont des bras comme vos jambes!",
        'e' => "Ils sont plus solides que le roc!",
        'agi' => "Rapides comme l'éclair!",

        'a' => "Les plus grands moulins à baffes!",
        'mvt' => "Ils volent plus qu'ils ne marchent...",
        'p' => "Rien n'échappe à leur regard!",

        'pm' => "Leur magie est inépuisable!",
        'fm' => "Leur esprit est une forteresse!",
        'pui' => "Les plus puissants magiciens!",
        'res' => "Les sorts leurs rebondissent dessus!",
        'r' => "Les plus résistants!",
        'rm' => "Des mineurs de mana"
    ];

    public static function renderBourrins($playerList): void
    {

        echo '<h1>Classement des Bourrins</h1>';

        $path = 'datas/public/classements/bourrins.html';

        RankingCache::serve($path, __FILE__, static function () use ($playerList): void {
            $bestCarac = array();
            $caracsService = new PlayerCaracsService();

            foreach ($playerList as $player) {

                $entity = PlayerFactory::entity((int) $player->id);
                if ($entity === null) {
                    continue;
                }

                $caracs = $entity->getNudeCaracs($caracsService);

                foreach (CARACS as $k => $e) {

                    // first entry
                    if (!isset($bestCarac[$k])) {

                        $bestCarac[$k] = array(
                            $caracs->$k => array($entity)
                        );

                        continue;
                    }

                    // add entry
                    $bestCarac[$k][$caracs->$k][] = $entity;
                }
            }

            foreach (CARACS as $k => $e) {

                if (!isset(self::CARACS_BOURRINS[$k])) {

                    continue;
                }

                echo '<h2>' . self::CARACS_BOURRINS[$k] . ' (' . $e . ')</h2>';

                self::print_best_carac($k, $bestCarac);
            }
        });
    }
    /**
     * Top 5 players over the 3 best values; tied players share a place.
     *
     * @param array<string, array<int, list<Character>>> $bestCarac
     */
    static function print_best_carac(string $carac, array $bestCarac): void
    {
        $tbl = $bestCarac[$carac] ?? [];
        krsort($tbl);

        $podium = array_slice($tbl, 0, 3, true);
        $levels = (new ProgressionService())->of(array_values(array_unique(array_map(
            static fn (Character $e): int => $e->getId(),
            array_merge(...array_values($podium))
        ))));

        $rows = [];
        $place = 1;
        foreach ($podium as $entities) {
            foreach ($entities as $e) {
                $rows[] = (object) [
                    'place' => $place,
                    'id' => $e->getId(),
                    'display_id' => $e->getDisplayId(),
                    'name' => $e->getName(),
                    'race' => $e->getRace(),
                    'pr' => $e->getPr(),
                    'xp' => $levels[$e->getId()]['xp'],
                    'rank' => $levels[$e->getId()]['rank'],
                ];
            }
            $place++;
        }

        PlayersTableView::render(array_slice($rows, 0, 5));
    }
}
