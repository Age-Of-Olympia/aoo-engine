<?php

namespace App\Service;

use App\Service\Action\EnergieRule;
use App\Service\TurnScheduleService;
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
     * Traite le tour s'il est dû, pour la session en cours.
     *
     * Ce que la session décide s'arrête ici : le tutoriel, le drapeau admin
     * « nonewturn », l'absence de connexion. Ce qui relève du TOUR lui-même vit
     * dans {@see isDue()} et {@see processDue()}, appelables par une entité qui
     * n'a pas de navigateur derrière elle.
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

        return $this->processDue($player);
    }

    /**
     * Le tour est-il dû ? La règle seule : l'heure est passée, et l'entité
     * n'est pas aux limbes.
     *
     * Le plan se lit en base plutôt que par `getCoords()`, qui FAIT QUELQUE
     * CHOSE : sans case, il replace l'entité sur olympia. Demander si un tour
     * est dû ne doit rien déplacer — et une entité remisée hors du plateau y
     * envoyait le legacy en récursion.
     *
     * Nulle part n'est pas les limbes : le tour d'une entité sans case tourne.
     * C'est ce qu'il faut pour ce qui jouera un jour sans être un personnage.
     */
    public function isDue(Player $entity, int $time): bool
    {
        $entity->get_data(false);

        if ($entity->data->nextTurnTime > $time) {
            return false;
        }

        return $this->planOf((int) ($entity->data->coords_id ?? 0)) !== 'limbes';
    }

    /** The plan a cell belongs to; '' when the entity stands on none. */
    private function planOf(int $coordsId): string
    {
        if ($coordsId === 0) {
            return '';
        }

        $row = (new Db())->exe('SELECT plan FROM coords WHERE id = ?', $coordsId)->fetch_object();

        return $row === null ? '' : (string) $row->plan;
    }

    /** Traite le tour s'il est dû, sans rien demander à une session. */
    public function processDue(Player $entity, ?int $time = null): ?object
    {
        $time ??= time();

        return $this->isDue($entity, $time) ? $this->process($entity, $time) : null;
    }

    private function process(Player $player, int $time): object
    {
        $db = new Db();
        $rows = [];

        $player->get_caracs();

        // Cadence fixe ancrée sur l'horaire du tour précédent ; le joueur
        // décale lui-même son prochain tour via api/player/set_next_turn.php
        // (ex « DLA glissante », remplacée par ce décalage manuel).
        $playerTurn = TurnScheduleService::turnDurationSeconds($player->caracs->spd);
        $nextTurnTime = $player->data->nextTurnTime + $playerTurn;

        while ($nextTurnTime <= $time) {
            $nextTurnTime += $playerTurn;
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

        // Malus de mouvement au tour (catalogue : turn_mvt_malus,
        // ex-ralentissement codé en dur) — la valeur portée fait le malus.
        foreach ($player->effectService->turnEffects($player->getEffects(), 'turn_mvt_malus') as $effect) {
            $player->playerBonusService->setBonusByPlayerIdByName(
                $player->id,
                'mvt',
                -$player->playerEffectService->getEffectValueByPlayerIdByEffectName($player->id, $effect->getName())
            );
        }

        $player->playerService->playerUpdateVisible(null);

        /* Les effets se comptent en TOURS : endTime porte le nombre de
         * tours restants. Zéro = terminé, on purge ; les durées NÉGATIVES
         * sont sans fin (PlayerEffectService::DURATION_INFINITE — le vol
         * des oiseaux, un trait de race) et ne sont visées ni par le
         * comptage ni par la décrémentation ci-dessous. */
        $expired = $player->playerEffectService->countExpiredByPlayerId((int) $player->id);
        if ($expired) {
            $player->purge_effects();
            $rows[] = [null, 'Effets terminés', (string) $expired];
        }

        // Un tour consommé sur ceux qui restent.
        $player->playerEffectService->consumeOneTurnByPlayerId((int) $player->id);

        // Usure : le tour est l'unité de décrément — appliquer ici ce que
        // les événements du tour ont armé.
        $wearRecap = (new WearService())->applyNewTurnWear($player->id);

        $db->exe(
            'UPDATE players
             SET nextTurnTime = ?, nextTurnRescheduled = 0, lastActionTime = 0, antiBerserkTime = ?, malus = malus - ?, energie = ?
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
        $carried = $player->getEffects();

        // Blocage de récupération (catalogue : block_recovery, ex-poison
        // pour les PV / poison_magique pour les PM) : la récup tombe à
        // zéro et l'effet expire. Le blocage PRIME sur la régénération.
        if (in_array($k, ['pv', 'pm'], true)) {
            foreach ($player->effectService->turnEffects($carried, 'block_recovery', $k) as $blocker) {
                $player->end_effect($blocker->getName());

                // Le pv historique avait une espace après le + ('+ 0').
                $plus = $k === 'pv' ? '+ 0' : '+0';

                return [$k, CARACS[$k], $plus . ' (<span class="ra ' . $blocker->getIcon() . '"></span> ' . $blocker->getLabel() . ')'];
            }
        }

        // Régénération (catalogue : turn_regen) : la récup PV du tour
        // gagne +RM et l'effet expire.
        if ($k == 'pv') {
            foreach ($player->effectService->turnEffects($carried, 'turn_regen') as $regen) {
                $player->end_effect($regen->getName());
                $val += $player->caracs->rm;

                return [$k, CARACS[$k], '+' . $val . ' (<span class="ra ' . $regen->getIcon() . '"></span> ' . $regen->getLabel() . ')'];
            }
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
