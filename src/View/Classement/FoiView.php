<?php

namespace App\View\Classement;

use Classes\Db;
use App\Service\AltarControlService;
use DateTimeImmutable;
use DateTimeZone;

class FoiView
{
    public const CACHE = 'datas/public/classements/foi.html';

    /** Logged in within INACTIVE_TIME; $alias is the players row to test. */
    private static function activeSql(string $alias): string
    {
        return '(SELECT acc.last_login_time FROM accounts AS acc WHERE acc.player_id = ' . $alias . '.id)'
            . ' >= (UNIX_TIMESTAMP() - ' . INACTIVE_TIME . ')';
    }

    public static function renderFoi(): void
    {
        echo '<h1>Classement de la Foi</h1>';

        $path = self::CACHE;

        RankingCache::serve($path, __FILE__, static function (): void {
        $db = new Db();

        // Only this season's plans (and the every-season ones) count: a
        // believer stands on one, a controlled plan is one.
        $seasonPlans = array_keys(plans()->forSeason()) ?: [''];
        $inSeason = '(' . implode(',', array_fill(0, count($seasonPlans), '?')) . ')';
        $onSeasonPlan = 'coords_id IN (SELECT id FROM coords WHERE plan IN ' . $inSeason . ')';
        $altarControl = new AltarControlService();
        $controlledByGod = $altarControl->controlledPlanCountsByGod($seasonPlans);
        /* Only a god holding a consecrated altar on a season plan ranks. */
        $altarGodIds = array_map('intval', array_keys($altarControl->altarCountsByGod($seasonPlans)));
        $inAltarGods = '(' . implode(',', $altarGodIds ?: [0]) . ')';

        $sql = '
        SELECT
            g.id,
            g.name,
            COALESCE(SUM(f.pf), 0) AS total_foi,
            COUNT(f.id) AS nb_fideles,
            top.name AS top_prieur_name,
            top.id AS top_prieur_id
        FROM players AS g
        LEFT JOIN players AS f ON f.godId = g.id AND f.id > 0 AND ' . self::activeSql('f') . '
            AND f.' . $onSeasonPlan . '
        LEFT JOIN players AS top ON top.id = (
            SELECT b.id FROM players AS b
            WHERE b.godId = g.id AND b.id > 0 AND ' . self::activeSql('b') . '
            AND b.' . $onSeasonPlan . '
            ORDER BY b.pf DESC
            LIMIT 1
        )
        WHERE g.race = "dieu"
        AND g.id IN ' . $inAltarGods . '
        GROUP BY g.id, top.id, top.name
        HAVING nb_fideles > 0 AND total_foi > 0
        ORDER BY total_foi DESC
        ';

        $res = $db->exe($sql, array_merge($seasonPlans, $seasonPlans));

        echo '
        <h2>Dieux les plus vénérés</h2>
        <table border="1" align="center" class="marbre" cellspacing="0">
        <tr>
            <th>#</th>
            <th>Dieu</th>
            <th>Foi cumulée</th>
            <th>Fidèles</th>
            <th>Fidèle parmi les fidèles</th>
            <th>Plans contrôlés</th>
        </tr>
        ';

        $maxPlans = -1;
        $nomLeader = '';
        $isEquality = false;

        $rank = 1;
        while ($row = $res->fetch_object()) {
            /* A plan is controlled when exactly ONE god has a consecrated
               altar on it; a naked altar is neutral. */
            $row->plans_controles = $controlledByGod[(int) $row->id] ?? 0;
            $topPrieur = $row->top_prieur_name
                ? '<a href="infos.php?targetId='. $row->top_prieur_id .'">'. $row->top_prieur_name .'</a>'
                : '—';

            echo '
            <tr>
                <td align="center">'. $rank .'</td>
                <td><a href="infos.php?targetId='. $row->id .'">'. $row->name .'</a></td>
                <td align="center">'. $row->total_foi .'</td>
                <td align="center">'. $row->nb_fideles .'</td>
                <td>'. $topPrieur .'</td>
                <td>'. $row->plans_controles .'</td>
            </tr>
            ';

            $actuelPlansControles = (int)$row->plans_controles;
            
            if ($actuelPlansControles > $maxPlans) {
                $maxPlans = $actuelPlansControles;
                $nomLeader = $row->name;
                $isEquality = false;
            } elseif ($actuelPlansControles === $maxPlans && $maxPlans > 0) {
                $isEquality = true;
            }

            $rank++;
        }

        echo '</table>';

        echo '<br /><h3>';
        if ($maxPlans <= 0) {
            echo "Aucun Dieu ne contrôle de plan actuellement.";
        } elseif ($isEquality) {
            echo "Aucun Dieu ne contrôle plus de plans que les autres.";
        } else {
            echo "Le Dieu qui contrôle le plus de plans est " . htmlspecialchars($nomLeader) . " avec " . $maxPlans . " plans contrôlés.";
        }
        echo '</h3><br />';

        });

        $nextDistribution = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))
            ->modify('first day of next month')
            ->setTime(0, 0);
        echo '<h3 class="faith-reward-countdown" data-faith-reward-countdown data-deadline="'
            . $nextDistribution->getTimestamp() . '">'
            . 'Temps restant avant la prochaine distribution de récompenses : '
            . '<span data-countdown-value></span></h3>';
    }
}
