<?php
use Classes\Command;
use Classes\Argument;
use Classes\Db;
use App\Service\Map\TileOccupancyService;

/**
 * The `forbidden` triggers, and which of them say nothing any more.
 *
 * A fence was long the only way to make something impassable: a tree, a wall,
 * the back of a fort were drawings and nothing else, so an animator drew the
 * refusal on top by hand. Resources and structures now refuse the step
 * themselves, and those fences became duplicates.
 *
 * A duplicate is worse than useless — it OUTLIVES what it doubled. Take the
 * wall away and the fence stays, refusing the step on an empty cell, with
 * nothing on screen to explain why.
 *
 * Reports by default. What it proposes to remove is only ever a fence whose
 * cell is refused by something permanent: a character standing in a doorway
 * does not count.
 */
class FenceCmd extends Command
{
    public function __construct()
    {
        parent::__construct('fence', [new Argument('what', true)]);
        parent::setDescription(<<<EOT
Déclencheurs « forbidden » faisant double emploi avec ce qui bloque déjà.
Exemple:
> fence           (ce qui ferait double emploi, sans rien toucher)
> fence clean     (retire ceux-là)
EOT);
    }

    public function execute(array $argumentValues): string
    {
        $what = trim((string) ($argumentValues[0] ?? ''));

        $db = new Db();
        $res = $db->exe(
            'SELECT t.id, t.coords_id, c.x, c.y, c.z, c.plan
               FROM map_triggers t
               JOIN coords c ON c.id = t.coords_id
              WHERE t.name = ?
              ORDER BY c.plan, c.z, c.y, c.x',
            array('forbidden')
        );

        $fences = [];

        while ($row = $res->fetch_object()) {
            $fences[] = $row;
        }

        if ($fences === []) {
            return 'Aucun déclencheur « forbidden » sur la carte.';
        }

        $blocked = (new TileOccupancyService())->permanentlyBlocked(
            array_map(static fn (object $f): int => (int) $f->coords_id, $fences)
        );

        $redundant = array_values(array_filter(
            $fences,
            static fn (object $f): bool => isset($blocked[(int) $f->coords_id])
        ));

        if ($redundant === []) {
            return sprintf(
                '%d déclencheur(s) « forbidden », aucun en double : chacun dit quelque chose que rien d\'autre ne dit.',
                count($fences)
            );
        }

        $lines = [];

        foreach ($redundant as $fence) {
            $lines[] = sprintf(
                '  %-18s (%d,%d,%d) — %s bloque déjà',
                $fence->plan,
                $fence->x,
                $fence->y,
                $fence->z,
                $blocked[(int) $fence->coords_id]
            );
        }

        if ($what === 'clean') {
            $ids = implode(',', array_map(static fn (object $f): int => (int) $f->id, $redundant));
            $db->exe("DELETE FROM map_triggers WHERE id IN ({$ids})");

            array_unshift($lines, 'Déclencheurs retirés — la case reste infranchissable par ce qui s\'y trouve :', '');
            $lines[] = '';
            $lines[] = sprintf('%d retiré(s) sur %d.', count($redundant), count($fences));

            return implode('<br />', $lines);
        }

        array_unshift($lines, 'Doubles emplois (essai à blanc — « fence clean » pour les retirer) :', '');
        $lines[] = '';
        $lines[] = sprintf('%d sur %d font double emploi.', count($redundant), count($fences));

        return implode('<br />', $lines);
    }
}
