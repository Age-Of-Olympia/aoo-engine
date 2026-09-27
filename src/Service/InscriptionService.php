<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * What is written on a thing (players.text, the column a character uses
 * for its message of the day), and who may write it. Things only: a
 * character's message stays its own, never written through here.
 *
 * Chests today, walls and signs later: mayInscribe() is the one place
 * that says what can carry an inscription.
 */
final class InscriptionService
{
    public const MAX_LENGTH = 500;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** A chest, written on by its people (owner, or faction rank useChest). */
    public function mayInscribe(int $thingId, int $actorId): bool
    {
        $container = new ContainerService($this->conn);

        return $container->isContainer($thingId) && $container->mayOversee($thingId, $actorId);
    }

    /** Writes the inscription; an empty text erases it. */
    public function inscribe(int $thingId, int $actorId, string $text): void
    {
        if (!$this->mayInscribe($thingId, $actorId)) {
            throw new RuntimeException('Vous ne pouvez pas écrire ici.');
        }

        $text = mb_substr(trim($text), 0, self::MAX_LENGTH);
        $this->conn->executeStatement('UPDATE players SET text = ? WHERE id = ?', [$text, $thingId]);

        (new FactionLogService($this->conn))->addAboutThing(
            $thingId,
            $actorId,
            $text === '' ? 'a effacé l\'inscription de' : 'a écrit sur'
        );
        (new AuditService())->addAuditLog("inscription #{$thingId}: #{$actorId}");
    }
}
