<?php

namespace App\View\Classement;

class ReputationsView
{
    private static function compareByPr($a, $b)
        {
            return $b->pr - $a->pr; // Tri décroissant
        }
    public static function renderReputations($playerList): void
    {

        echo '<h1>Joueurs les plus Réputés</h1>';

        // Fonction de comparaison pour trier par "pr" (Power Rank)
      

        $path = 'datas/public/classements/reputation.html';

        RankingCache::serve($path, __FILE__, static function () use ($playerList): void {
            // Trier le tableau en utilisant la fonction de comparaison
            usort($playerList, self::compareByPr(...));

            // just as a marker — guard against the entire ranking
            // having been filtered out (every player a PNJ/inactive)
            if (!empty($playerList)) {
                $playerList[0]->showReput = 1;
            }

            PlayersTableView::render($playerList);
        });
    }
}
