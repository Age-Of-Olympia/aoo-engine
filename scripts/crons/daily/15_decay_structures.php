<?php

/*
 * Decay of player-built constructions — docs/design-decay-structures.md.
 *
 * Runs daily, but the rule is counted in TURNS: the pass applies whatever
 * each construction owes since its horizon, so the cadence of the cron and
 * the length of a turn need not agree. A run that does not happen is caught
 * up by the next; running twice in a turn changes nothing the second time.
 *
 * Two set-based writes for the whole world, then a PHP loop over what
 * actually collapsed — normally nothing.
 */

$decay = new \App\Service\Decay\StructureDecayService();

$result = $decay->run();

echo $result['decayed'] . ' construction(s) usée(s)';

if ($result['collapsed'] !== []) {
    echo ', ' . count($result['collapsed']) . ' effondrée(s) : #'
        . implode(', #', $result['collapsed']);
}

echo ' — done';
