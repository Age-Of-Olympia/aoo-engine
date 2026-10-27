<?php
/** Faith reward catalogue mutations (POST only). */
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\FaithRewardService;

(new AdminMenuAccessService())->enforce('faith.rewards');

$action = (string) ($_POST['action'] ?? '');
handleAdminPost('/admin/faith-rewards.php', static function () use ($action): string {
    $service = new FaithRewardService();
    if ($action === 'monthly-count') {
        $rawCount = $_POST['monthly_reward_count'] ?? '';
        if (!is_scalar($rawCount) || !ctype_digit((string) $rawCount)) {
            throw new InvalidArgumentException('Le nombre de récompenses doit être un entier positif ou nul.');
        }
        $service->setMonthlyRewardCount((int) $rawCount);
        return 'Nombre de récompenses mensuelles mis à jour.';
    }

    $itemId = (int) ($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        throw new InvalidArgumentException('Objet manquant ou invalide.');
    }

    if ($action === 'add') {
        $rawTypes = $_POST['include_types'] ?? [];
        $includeTypes = is_array($rawTypes) ? array_map('strval', $rawTypes) : [];
        $rawQuantity = $_POST['quantity'] ?? '1';
        if (!is_scalar($rawQuantity) || !ctype_digit((string) $rawQuantity)) {
            throw new InvalidArgumentException('La quantité doit être un nombre entier positif.');
        }
        $service->add($itemId, $includeTypes, (int) $rawQuantity);
        return 'Objet ajouté à la liste des récompenses.';
    }
    if ($action === 'quantity') {
        $rawQuantity = $_POST['quantity'] ?? '';
        if (!is_scalar($rawQuantity) || !ctype_digit((string) $rawQuantity)) {
            throw new InvalidArgumentException('La quantité doit être un nombre entier positif.');
        }
        return $service->setQuantity($itemId, (int) $rawQuantity)
            ? 'Quantité de l’objet mise à jour.'
            : 'Cet objet ne figure pas dans la liste des récompenses.';
    }
    if ($action === 'remove') {
        return $service->remove($itemId)
            ? 'Objet retiré de la liste des récompenses.'
            : 'Cet objet ne figurait pas dans la liste.';
    }

    throw new InvalidArgumentException('Action inconnue.');
});
