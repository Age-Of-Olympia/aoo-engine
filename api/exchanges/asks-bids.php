<?php

use App\Factory\PlayerFactory;
use App\Service\BidsAsksService;
use Classes\Market;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SanitizeIntChecked($_GET['targetId'], 'error no merchant');

    // Joueur ACTIF (tutoriel / PNJ), comme le reste du flux marchand
    // (scripts/merchant/body.php) : sinon la transaction s'applique au
    // personnage principal alors que la vue affiche l'actif.
    $player = PlayerFactory::active();
    $player->get_data();

    $target = PlayerFactory::legacy((int) $_GET['targetId']);

    $marketAccessError = Market::CheckMarketAccess($player, $target);
    if ($marketAccessError != null) {

        ExitError($marketAccessError);
    }

    $POST_DATA = json_decode(file_get_contents('php://input'), true);
    if (!isset($POST_DATA['action']) || !in_array($POST_DATA['action'], ['accept', 'create', 'cancel'])) {
        ExitError(INVALID_REQ);
    }

    if (!isset($POST_DATA['type']) || !in_array($POST_DATA['type'], ['bids', 'asks'])) {
        ExitError(INVALID_REQ);
    }
    $bidsAsksService = new BidsAsksService();

    if ($POST_DATA['action'] == 'cancel') {

        SanitizeIntChecked($POST_DATA['id']);
        $bidsAsksService->Cancel($POST_DATA['type'], $POST_DATA['id'], $player);

    } elseif ($POST_DATA['action'] == 'create') {

        SanitizeIntChecked($POST_DATA['price']);
        SanitizeIntChecked($POST_DATA['quantity']);
        SanitizeIntChecked($POST_DATA['item_id']);

        /* instance_id est FACULTATIF (une offre de pile n'en a pas) :
         * SanitizeIntChecked sort du script sur une valeur non
         * numérique, il ne peut donc pas s'appliquer à une clé absente.
         * Vide ou 0 = pas d'exemplaire ; on ne laisse pas une valeur
         * bancale devenir 0 en silence. */
        $instanceId = null;
        if (isset($POST_DATA['instance_id']) && $POST_DATA['instance_id'] !== '') {
            SanitizeIntChecked($POST_DATA['instance_id']);
            $instanceId = (int) $POST_DATA['instance_id'] ?: null;
        }

        /* Seuil d'état exigé par une demande d'achat — facultatif, un
         * palier inconnu vaut « aucune contrainte » côté service. */
        $minCondition = 0;
        if (isset($POST_DATA['min_condition']) && $POST_DATA['min_condition'] !== '') {
            SanitizeIntChecked($POST_DATA['min_condition']);
            $minCondition = (int) $POST_DATA['min_condition'];
        }

        $bidsAsksService->Create($POST_DATA['type'], $POST_DATA['item_id'], $POST_DATA['price'], $POST_DATA['quantity'], $player, $instanceId, $minCondition);
    }
    elseif ($POST_DATA['action'] == 'accept') {
        
        SanitizeIntChecked($POST_DATA['id']);
        SanitizeIntChecked($POST_DATA['quantity']);

        /* Exemplaire livré par le vendeur pour satisfaire une demande. */
        $acceptInstanceId = null;
        if (isset($POST_DATA['instance_id']) && $POST_DATA['instance_id'] !== '') {
            SanitizeIntChecked($POST_DATA['instance_id']);
            $acceptInstanceId = (int) $POST_DATA['instance_id'] ?: null;
        }

        $bidsAsksService->Accept($POST_DATA['type'], $POST_DATA['id'],$POST_DATA['quantity'], $player, $acceptInstanceId);
    }
    else {
        ExitError(INVALID_REQ);
    }
}
