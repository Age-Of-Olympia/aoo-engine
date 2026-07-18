<?php

namespace App\Service;

use App\Service\Action\EnergieRule;
use App\Tutorial\TutorialHelper;
use Classes\Db;
use Classes\Log;
use Classes\Player;
use Classes\View;

/**
 * Moteur de tour : détecte le tour dû et applique TOUTES les mutations
 * du rafraîchissement (récupération de caracs, XP, malus, effets,
 * usure, énergie, nextTurnTime), extrait de NewTurnView — la vue ne
 * fait plus que présenter le récapitulatif retourné.
 *
 * Le récap est aussi journalisé en ÉVÉNEMENT (players_logs, type
 * 'turn') : affiché au début de tour comme avant, ET relisible dans
 * les Évènements si on a oublié ce qui y était écrit (décision
 * 2026-07-18). L'usure y est intégrée — plus de log séparé.
 */
class TurnProcessingService
{
    /**
     * Traite le tour s'il est dû. Null si rien à faire (tutoriel,
     * session admin « nonewturn », pas de session, tour pas encore dû,
     * ou mort aux limbes).
     *
     * @return object|null récap présentable :
     *   {nextTurnTime: int, rows: array<int, array{0: ?string, 1: string, 2: string}>,
     *    wearRecap: string[], showMailPrompt: bool}
     *   — rows = [cléTooltip|null, libellé, valeur (HTML léger)]
     */
    public function processIfDue(Player $player): ?object
    {
        if (TutorialHelper::isInTutorial()) {
            return null;
        }

        if (isset($_SESSION['originalPlayerId']) && $_SESSION['playerId'] == $_SESSION['originalPlayerId']) {
            $_SESSION['nonewturn'] = false;
        }
        if (!empty($_SESSION['nonewturn']) || empty($_SESSION['playerId'])) {
            return null;
        }

        $time = time();
        $player->get_data(false);

        if ($player->data->nextTurnTime > $time) {
            return null;
        }

        $player->getCoords();
        if ($player->coords->plan == 'limbes') {
            return null;
        }

        return $this->process($player, $time);
    }

    private function process(Player $player, int $time): object
    {
        $db = new Db();
        $rows = [];

        $player->get_caracs();

        $playerTurn = 86400 - (($player->caracs->spd - 10) * 3600);

        // Sans l'option dlag, le prochain tour s'ancre sur l'horaire du
        // précédent (cadence stable) ; avec, sur maintenant.
        $nextTurnTime = $player->have_option('dlag')
            ? $time + $playerTurn
            : $player->data->nextTurnTime + $playerTurn;

        while ($nextTurnTime <= $time) {
            $nextTurnTime += 86400 - (($player->caracs->spd - 10) * 3600);
        }

        foreach ($player->effectService->getHiddenNames() as $effect) {
            $player->end_effect($effect);
        }

        if (file_exists('img/foregrounds/doubles/' . $player->id . '.png')) {
            View::delete_double($player);
        }

        // XP du tour, avec rattrapage sur le premier joueur (plafonné).
        $firstPlayerXP = 0;
        $firstPlayerData = Player::get_player_list();
        if (isset($firstPlayerData->first)) {
            $firstPlayerXP = $firstPlayerData->first->xp;
        }

        $gainXp = XP_PER_TURNS;
        if ($player->data->xp + 250 <= $firstPlayerXP) {
            $diff = $firstPlayerXP - ($player->data->xp + 250);
            $gainXp += 1 + floor($diff / 50);
            if ($player->id < 0 && $gainXp > 10) {
                $gainXp = 10;
            }
        }

        $gainXpTxt = '';
        if ($gainXp > 25) {
            $gainXpTxt = ' ( calculé:' . $gainXp . 'xp)';
            $gainXp = 25;
        }

        $rows[] = ['xp', 'Xp', '+' . $gainXp . $gainXpTxt];
        $rows[] = ['pi', 'Pi', '+' . $gainXp];

        $recovMalus = min($player->data->malus, MALUS_PER_TURNS);
        $rows[] = ['malus', 'Malus', '-' . $recovMalus];

        $recovEnergie = EnergieRule::maxEnergieFor((int) $player->caracs->a);

        foreach (CARACS_RECOVER as $k => $carac) {
            $row = $this->recoverCarac($player, $k, $carac);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        // Ae, A et Mvt repartent de zéro à chaque tour.
        $db->exe('DELETE FROM players_bonus WHERE player_id = ? AND name IN("ae","a","mvt")', $player->id);

        if ($player->playerEffectService->hasEffectByPlayerIdByEffectName($player->id, 'ralentissement')) {
            $player->playerBonusService->setBonusByPlayerIdByName(
                $player->id,
                'mvt',
                -$player->playerEffectService->getEffectValueByPlayerIdByEffectName($player->id, 'ralentissement')
            );
        }

        $player->playerService->playerUpdateVisible(null);

        $expired = (int) $db->exe(
            'SELECT COUNT(*) AS n FROM players_effects WHERE endTime <= ? AND endTime != 0 AND player_id = ?',
            [$time, $player->id]
        )->fetch_object()->n;
        if ($expired) {
            $player->purge_effects();
            $rows[] = [null, 'Effets terminés', (string) $expired];
        }

        // Usure : le tour est l'unité de décrément — appliquer ici ce que
        // les événements du tour ont armé.
        $wearRecap = (new WearService())->applyNewTurnWear($player->id);

        $db->exe(
            'UPDATE players
             SET nextTurnTime = ?, lastActionTime = 0, antiBerserkTime = ?, malus = malus - ?, energie = ?
             WHERE id = ?',
            [
                $nextTurnTime,
                $player->data->lastActionTime + (0.25 * $playerTurn),
                $recovMalus,
                $recovEnergie,
                $player->id,
            ]
        );

        $player->put_xp($gainXp);

        $recap = (object) [
            'nextTurnTime' => $nextTurnTime,
            'rows' => $rows,
            'wearRecap' => $wearRecap,
            'showMailPrompt' => $player->id > 0
                && empty($player->data->plain_mail)
                && !$player->data->email_bonus,
        ];

        // L'événement relisible dans les Évènements.
        Log::put($player, $player, $this->eventText($recap), type: 'turn', hiddenText: '', logTime: $time);

        $player->refresh_data();
        $player->refresh_caracs();
        $player->refresh_invent(); // for Ae

        return $recap;
    }

    /**
     * Une ligne de récupération de carac — les poisons annulent la
     * récupération (et se terminent), la régénération l'augmente de RM,
     * l'énergie se calcule à part (pas de ligne).
     *
     * @return array{0: ?string, 1: string, 2: string}|null [cléTooltip, libellé, valeur]
     */
    private function recoverCarac(Player $player, string $k, string $carac): ?array
    {
        $val = $player->caracs->$carac;

        if ($k == 'pm' && $player->have_effect('poison_magique')) {
            $player->end_effect('poison_magique');

            return [$k, CARACS[$k], '+0 (<span class="ra ' . $player->effectService->getIcon('poison_magique') . '"></span> Poison Magique)'];
        }

        if ($k == 'pv' && $player->have_effect('poison')) {
            $player->end_effect('poison');

            return [$k, CARACS[$k], '+ 0 (<span class="ra ' . $player->effectService->getIcon('poison') . '"></span> Poison)'];
        }

        if ($k == 'pv' && $player->have_effect('regeneration')) {
            $player->end_effect('regeneration');
            $val += $player->caracs->rm;

            return [$k, CARACS[$k], '+' . $val . ' (<span class="ra ' . $player->effectService->getIcon('regeneration') . '"></span> Régénération)'];
        }

        if ($k == 'a') {
            return null;
        }

        if (!in_array($k, ['ae', 'a', 'mvt'])) {
            $player->putBonus([$k => $val]);
        }

        return [$k, CARACS[$k], '+' . $val];
    }

    /** Le récap en texte lisible pour l'événement (sans HTML). */
    private function eventText(object $recap): string
    {
        $parts = [];
        foreach ($recap->rows as [$tooltipKey, $label, $value]) {
            $parts[] = $label . ' ' . trim(strip_tags($value));
        }
        if ($recap->wearRecap !== []) {
            $parts[] = 'Usure : ' . strip_tags(implode(' ', $recap->wearRecap));
        }

        return 'Nouveau tour — ' . implode(', ', $parts)
            . '. Prochain tour le ' . date('d/m/Y à H:i', $recap->nextTurnTime) . '.';
    }
}
