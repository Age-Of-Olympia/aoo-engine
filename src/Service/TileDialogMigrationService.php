<?php

namespace App\Service;

use Classes\Db;

/**
 * Reprise des déclencheurs `map_dialogs` vers les objets qui les portent.
 *
 * Le modèle a changé : ce qu'une chose a à dire appartient désormais à
 * la CHOSE — son inscription (players.text) ou sa conversation
 * (buildings.dialog) — et non à la case sous elle. Restent les
 * déclencheurs posés avant, collés au sol.
 *
 * Deux raisons de les reprendre plutôt que de les laisser cohabiter :
 * un déclencheur de case s'affiche maintenant MÊME quand un bâtiment
 * l'occupe (c'est le correctif des panneaux muets), donc un texte
 * transféré sans que son déclencheur soit retiré s'afficherait deux
 * fois ; et tant qu'ils existent, l'éditeur de carte ne peut pas être
 * verrouillé sur le nouveau modèle.
 *
 * La reprise se fait en deux temps : un PLAN qui n'écrit rien et se
 * lit, puis son application. Le plan est la partie qui compte — c'est
 * lui qui montre les cas que le recensement n'avait pas prévus.
 */
class TileDialogMigrationService
{
    /** Ce qu'on pose sur une case nue pour porter son texte. */
    public const CARRIER_RACE = 'pancarte';


    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    /**
     * Ce que la reprise ferait, sans rien écrire.
     *
     * @return list<array{coords_id: int, plan: string, x: int, y: int, z: int,
     *                    kept_id: ?int, dropped: list<array{id: int, why: string, params: string}>,
     *                    action: string, target_id: ?int, target_race: ?string,
     *                    text: string, dialog: string, warning: string}>
     */
    public function plan(): array
    {
        $plan = [];

        foreach ($this->groupedByTile() as $coordsId => $group) {
            $plan[] = $this->planForTile((int) $coordsId, $group);
        }

        return $plan;
    }

    /**
     * Applique le plan et rend ce qui a été fait, ligne à ligne.
     *
     * Chaque case est traitée dans sa propre transaction : une case qui
     * échoue ne doit pas emporter les autres, et la reprise doit pouvoir
     * être relancée sur ce qui reste.
     *
     * @return list<array{coords_id: int, done: string, error: string}>
     */
    public function apply(): array
    {
        $done = [];

        foreach ($this->plan() as $entry) {
            $done[] = $this->applyOne($entry);
        }

        return $done;
    }

    /** @return array<int, list<array{id: int, params: string}>> */
    private function groupedByTile(): array
    {
        $res = $this->db->exe(
            'SELECT d.id, d.coords_id, d.params FROM map_dialogs AS d ORDER BY d.coords_id, d.id'
        );

        $grouped = [];
        while ($row = $res->fetch_assoc()) {
            $grouped[(int) $row['coords_id']][] = [
                'id' => (int) $row['id'],
                'params' => (string) $row['params'],
            ];
        }

        return $grouped;
    }

    /**
     * @param list<array{id: int, params: string}> $group
     * @return array<string, mixed>
     */
    private function planForTile(int $coordsId, array $group): array
    {
        $coords = $this->coords($coordsId);
        $dropped = [];

        /* Les lignes vides d'abord : sur une case à plusieurs
         * déclencheurs, le plus grand id se trouve parfois être une
         * ligne vide laissée par une édition — la garder reviendrait à
         * effacer le texte. */
        $meaningful = [];
        foreach ($group as $row) {
            if (trim($row['params']) === '') {
                $dropped[] = ['id' => $row['id'], 'why' => 'ligne vide', 'params' => ''];
                continue;
            }
            $meaningful[] = $row;
        }

        $entry = [
            'coords_id' => $coordsId,
            'plan' => $coords['plan'] ?? '?',
            'x' => (int) ($coords['x'] ?? 0),
            'y' => (int) ($coords['y'] ?? 0),
            'z' => (int) ($coords['z'] ?? 0),
            'kept_id' => null,
            'dropped' => $dropped,
            'action' => 'rien',
            'target_id' => null,
            'target_race' => null,
            'text' => '',
            'dialog' => '',
            'warning' => '',
        ];

        if ($meaningful === []) {
            $entry['action'] = 'supprimer';

            return $entry;
        }

        /* La dernière édition l'emporte : sur les cases à doublons, la
         * version tardive est celle qu'on a corrigée (guillemets
         * échappés, faute reprise). Les autres sont signalées, pas
         * fondues dedans — les concaténer inventerait un texte que
         * personne n'a écrit. */
        $kept = $meaningful[array_key_last($meaningful)];
        foreach ($meaningful as $row) {
            if ($row['id'] !== $kept['id']) {
                $dropped[] = [
                    'id' => $row['id'],
                    'why' => 'version antérieure sur la même case',
                    'params' => $row['params'],
                ];
            }
        }
        $entry['dropped'] = $dropped;
        $entry['kept_id'] = $kept['id'];

        [$text, $dialog] = $this->readParams($kept['params']);
        $entry['text'] = $text;
        $entry['dialog'] = $dialog;

        $occupant = $this->occupantOf($coordsId);
        if ($occupant !== null) {
            $entry['target_id'] = (int) $occupant['id'];
            $entry['target_race'] = (string) $occupant['race'];
            $entry['action'] = $dialog !== '' ? 'conversation' : 'inscription';

            /* L'objet a déjà quelque chose d'écrit : transférer
             * écraserait, et supprimer le déclencheur sans transférer
             * perdrait le texte. On ne touche donc à RIEN et on le
             * signale — une reprise automatique n'a pas à choisir entre
             * deux textes qu'un humain a écrits. */
            $existing = BuildingService::inscriptionOf($this->entity((int) $occupant['id']));
            if ($dialog === '' && $existing !== '') {
                $entry['action'] = 'conflit';
                $entry['warning'] = 'l\'objet porte déjà une inscription — déclencheur laissé en place,'
                    . ' à trancher à la main';
            }

            return $entry;
        }

        /* Aucune ENTITÉ sur la case — mais rarement rien. Un décor
         * (map_foregrounds) ou une ressource (map_resources) s'y trouve
         * le plus souvent : une pierre de tribut, un tertre, une statue,
         * un autel brisé. Ce sont des objets qui ont quelque chose à
         * dire, et le texte leur appartient — sauf qu'ils ne sont pas
         * des entités et ne peuvent donc rien porter.
         *
         * Poser une pancarte à côté d'un tertre funéraire serait une
         * réponse fausse à une vraie question. On ne décide donc pas :
         * on nomme ce qui est là et on laisse trancher. Seules les cases
         * réellement nues reçoivent un support. */
        $decor = $this->decorOn($coordsId);

        if ($decor !== null) {
            $entry['action'] = 'à trancher';
            $entry['warning'] = 'la case porte ' . $decor['kind'] . ' « ' . $decor['name'] . ' »,'
                . ' qui ne peut rien porter faute d\'être une entité — le texte lui revient pourtant';

            return $entry;
        }

        $entry['action'] = 'poser une ' . self::CARRIER_RACE;

        return $entry;
    }

    /**
     * Lit un `params` et rend [texte, code de dialogue].
     *
     * Trois formes se croisent en base, et la troisième est un piège :
     * une alerte entre guillemets, un code du catalogue, ou du TEXTE —
     * qui peut contenir des virgules. L'ancien rendu découpait sur la
     * virgule, ce qui débitait les phrases en « nom, avatar, code ». On
     * ne découpe donc que si les morceaux désignent réellement un
     * dialogue connu.
     *
     * @return array{0: string, 1: string}
     */
    public function readParams(string $params): array
    {
        $params = trim($params);

        // Alerte brute : le texte est entre guillemets, échappé ou non.
        if ($params !== '' && $params[0] === '"') {
            $text = trim($params, '"');
            $text = str_replace(['\\"', '\\\\'], ['"', '\\'], $text);

            return [trim($text), ''];
        }

        if ($this->dialogExists($params)) {
            return ['', $params];
        }

        // « nom,avatar,code » : seulement si le code désigne un dialogue.
        $parts = explode(',', $params);
        if (count($parts) === 3 && $this->dialogExists(trim($parts[2]))) {
            return ['', trim($parts[2])];
        }

        return [$params, ''];
    }

    /** @return array<string, mixed> */
    private function applyOne(array $entry): array
    {
        $coordsId = (int) $entry['coords_id'];

        if ($entry['action'] === 'conflit' || $entry['action'] === 'à trancher') {
            return ['coords_id' => $coordsId, 'done' => 'laissée en place (conflit)', 'error' => ''];
        }

        try {
            $targetId = $entry['target_id'];

            if ($entry['action'] === 'poser une ' . self::CARRIER_RACE) {
                $targetId = (new BuildingService())->place(
                    self::CARRIER_RACE,
                    (object) [
                        'x' => $entry['x'],
                        'y' => $entry['y'],
                        'z' => $entry['z'],
                        'plan' => $entry['plan'],
                    ]
                );
            }

            if ($targetId !== null && $entry['dialog'] !== '') {
                (new BuildingService())->setDialog((int) $targetId, $entry['dialog']);
            } elseif ($targetId !== null && $entry['text'] !== '' && $entry['warning'] === '') {
                $this->db->exe(
                    'UPDATE players SET text = ? WHERE id = ?',
                    [$entry['text'], (int) $targetId]
                );
                BuildingService::purgeEntityCaches((int) $targetId);
            }

            $this->db->exe('DELETE FROM map_dialogs WHERE coords_id = ?', [$coordsId]);

            return [
                'coords_id' => $coordsId,
                'done' => $entry['action'] . ($targetId !== null ? ' sur #' . $targetId : ''),
                'error' => '',
            ];
        } catch (\Throwable $e) {
            return ['coords_id' => $coordsId, 'done' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Ce qui occupe la case sans être une entité.
     *
     * Le monde a trois familles de choses posées sur une case, et une
     * seule sait porter quelque chose : les ENTITÉS (players). Le décor
     * est dessiné, pas incarné — ni inscription, ni dialogue, ni état.
     * C'est ce que le passage des murs puis des ressources en entités a
     * corrigé pour elles ; les décors attendent encore leur tour.
     *
     * @return array{kind: string, name: string}|null
     */
    private function decorOn(int $coordsId): ?array
    {
        /* Les ressources ont quitté cette recherche : devenues des entités,
           elles sont trouvées par le test d'entité en amont, et peuvent donc
           porter un texte. Reste le décor, qui attend son tour. */
        $res = $this->db->exe('SELECT name FROM map_foregrounds WHERE coords_id = ? LIMIT 1', [$coordsId]);

        if ($res && $res->num_rows) {
            return ['kind' => 'le décor', 'name' => (string) $res->fetch_object()->name];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function coords(int $coordsId): ?array
    {
        $res = $this->db->exe('SELECT x, y, z, plan FROM coords WHERE id = ?', [$coordsId]);

        return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
    }

    /** @return array<string, mixed>|null */
    private function occupantOf(int $coordsId): ?array
    {
        $res = $this->db->exe(
            "SELECT id, race FROM players WHERE coords_id = ? AND player_type = 'building' LIMIT 1",
            [$coordsId]
        );

        return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
    }

    private function entity(int $id): \Classes\Player
    {
        $entity = \App\Factory\PlayerFactory::legacy($id);
        $entity->get_data();

        return $entity;
    }

    private function dialogExists(string $name): bool
    {
        if ($name === '' || !preg_match(DialogService::DIALOG_NAME_PATTERN, $name)) {
            return false;
        }

        $res = $this->db->exe('SELECT 1 FROM dialogs WHERE name = ?', [$name]);

        return (bool) ($res && $res->num_rows);
    }
}
