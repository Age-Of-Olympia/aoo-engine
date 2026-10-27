<?php

$result = (new \App\Service\FaithRewardDistributionService())->distributeMonthly();
if ($result['skipped']) {
    echo ' Distribution déjà effectuée pour ce mois.';
} elseif ($result['god_id'] === null) {
    echo ' Aucun culte gagnant avec des fidèles actifs à récompenser.';
} elseif ($result['distributed'] === 0) {
    echo ' Aucune récompense distribuée (quantité ou catalogue vide).';
} else {
    echo ' ' . $result['distributed'] . ' récompense(s) distribuée(s) aux fidèles du dieu #'
        . $result['god_id'] . ' (' . $result['followers'] . ' fidèle(s)).';
    foreach ($result['details'] as $detail) {
        echo ' ' . $detail['player'] . ' (#' . $detail['player_id'] . ') reçoit '
            . $detail['quantity'] . ' × ' . $detail['item'] . ' (#' . $detail['item_id'] . ').';
    }
}
