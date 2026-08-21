<?php

/* Instances de tutoriel à l'abandon : complete.php, cancel.php et le
 * prochain start.php nettoient par joueur — un joueur qui ne revient
 * jamais laisse son instance pour toujours, et un démontage échoué à
 * mi-chemin laisse une instance qu'aucune requête ne retrouve. Ce
 * balayage global ramasse tout ce qui n'est pas une session en cours. */
$report = (new \App\Tutorial\TutorialResourceManager())->cleanupStale();

echo count($report['swept']) . ' instance(s) supprimée(s)';
foreach ($report['skipped'] as $plan => $reason) {
    echo ' | ignoré ' . $plan . ' : ' . $reason;
}
