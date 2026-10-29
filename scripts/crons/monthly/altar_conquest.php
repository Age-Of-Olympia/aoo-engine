<?php

$result = (new \App\Service\FaithRewardDistributionService())->distributeMonthly();
if ($result['skipped']) {
    echo ' Distribution déjà effectuée pour ce mois.';
    if ($result['details'] === []) {
        echo ' Aucun détail d’attribution enregistré pour cette distribution.';
    } else {
        echo ' ' . $result['distributed'] . ' attribution(s) déjà enregistrée(s) pour le dieu #'
            . $result['god_id']
            . ($result['god_name'] !== null ? ' : ' . $result['god_name'] : '') . '.';
        foreach ($result['details'] as $detail) {
            echo ' ' . $detail['player'] . ' (#' . $detail['player_id'] . ') avait reçu '
                . $detail['quantity'] . ' × ' . $detail['item'] . ' (#' . $detail['item_id'] . ').' . PHP_EOL;
        }
    }
} elseif ($result['god_id'] === null) {
    echo ' Aucun dieu gagnant : aucun plan contrôlé, aucun fidèle actif admissible ou égalité sur le nombre de plans contrôlés.';
} elseif ($result['distributed'] === 0) {
    echo ' Aucune récompense distribuée (quantité ou catalogue vide).';
} else {
    echo ' ' . $result['distributed'] . ' récompense(s) distribuée(s) aux fidèles du dieu #'
        . $result['god_id'] . ' : ' . $result['god_name'] . ' (' . $result['followers'] . ' fidèle(s)).';
    foreach ($result['details'] as $detail) {
        echo ' ' . $detail['player'] . ' (#' . $detail['player_id'] . ') reçoit '
            . $detail['quantity'] . ' × ' . $detail['item'] . ' (#' . $detail['item_id'] . ').' . PHP_EOL;
    }
}
