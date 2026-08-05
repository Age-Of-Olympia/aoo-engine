<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;

/**
 * The session side of wearing a MASK — one method whatever the mask:
 * a PNJ of the account, a faction building. Who MAY wear it is each
 * caller's own door (the PNJ list, the household and its rank flag);
 * what wearing it means — and coming back — is settled here, once.
 */
final class ImpersonationService
{
    /** Puts the session at the entity's commands. */
    public function driveAs(int $entityId): void
    {
        $_SESSION['playerId'] = $entityId;

        // The driven entity wakes: same touch as a login.
        EntityManagerFactory::getEntityManager()->getConnection()->executeStatement(
            'UPDATE players SET lastLoginTime = ? WHERE id = ?',
            [time(), $entityId]
        );
    }

    /** Back to the account's main character; answers its id. */
    public function release(): int
    {
        $mainId = (int) ($_SESSION['mainPlayerId'] ?? 0);
        if ($mainId <= 0) {
            throw new \RuntimeException('Aucun personnage à reprendre.');
        }

        $_SESSION['playerId'] = $mainId;

        return $mainId;
    }
}
