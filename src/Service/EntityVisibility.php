<?php

namespace App\Service;

use App\Entity\BuildingDetails;
use Classes\Player;
use Classes\View;

/**
 * What a viewer may see of an entity, one rule per question. Every
 * surface that shows an entity (sheet, tile card, container screen,
 * asset tables) asks here instead of deciding on its own.
 *
 * Distances: the viewer's board is the square of Perception around its
 * footprint (Classes\View); "within Perception" and "beside" measure to
 * the entity's nearest cell.
 */
final class EntityVisibility
{
    public function __construct(private readonly Player $viewer)
    {
    }

    private function isSelf(int $entityId): bool
    {
        return (int) $this->viewer->id === $entityId;
    }

    private function isAdmin(): bool
    {
        return (bool) $this->viewer->have_option('isAdmin');
    }

    /** The cell is on the viewer's board: same plan and z, inside its view. */
    public function seesCell(int $x, int $y, int $z, string $plan): bool
    {
        $at = $this->coords();
        if ($at->plan !== $plan || (int) $at->z !== $z) {
            return false;
        }

        $p = $this->perception();
        [$w, $h] = $this->footprint();

        return $x >= $at->x - $p && $x <= $at->x + $p + $w - 1
            && $y >= $at->y - $p - ($h - 1) && $y <= $at->y + $p;
    }

    /** Hidden by invisibleMode from everyone but itself and the admins. */
    public function seesEntity(int $entityId): bool
    {
        if ($this->isSelf($entityId) || $this->isAdmin()) {
            return true;
        }

        return !\App\Factory\EntityManagerFactory::getEntityManager()->getConnection()->fetchOne(
            "SELECT 1 FROM players_options WHERE player_id = ? AND name = 'invisibleMode'",
            [$entityId]
        );
    }

    /** A character's PV, effects, message and worn equipment: itself, or within Perception. */
    public function seesDetailsOf(int $entityId): bool
    {
        return $this->isSelf($entityId) || $this->distanceTo($entityId) <= $this->perception();
    }

    /** When an effect ends: itself, or sharing a faction or a secret faction (never two empty ones). */
    public function seesEffectTimersOf(int $entityId, string $faction, string $secretFaction): bool
    {
        $data = $this->viewer->data;

        return $this->isSelf($entityId)
            || ($faction !== '' && $faction === (string) ($data->faction ?? ''))
            || ($secretFaction !== '' && $secretFaction === (string) ($data->secretFaction ?? ''));
    }

    /** A secret faction shows to its own members and to the admins. */
    public function seesSecretFaction(string $secretFaction): bool
    {
        return $secretFaction !== ''
            && ($secretFaction === (string) ($this->viewer->data->secretFaction ?? '') || $this->isAdmin());
    }

    /** Next to one of the entity's cells. */
    public function isBeside(int $entityId): bool
    {
        return $this->distanceTo($entityId) <= 1;
    }

    /**
     * What is written on a thing: from afar when its type (or the thing)
     * says so, from beside it otherwise, and from anywhere for a chest's
     * own people.
     */
    public function readsInscriptionOf(Player $thing, ?BuildingDetails $details): bool
    {
        $id = (int) $thing->id;
        $container = new ContainerService();

        return BuildingService::readsFromAfar($thing, $details)
            || $this->isBeside($id)
            || ($container->isContainer($id) && $container->mayOversee($id, (int) $this->viewer->id));
    }

    /**
     * What a container holds: never when shut; from anywhere for its
     * people, from beside it for anyone else — who could take from it.
     */
    public function seesContentsOf(int $containerId): bool
    {
        $container = new ContainerService();

        return $container->isContainer($containerId)
            && $container->closureReasonOf($containerId) === null
            && ($this->isBeside($containerId) || $container->mayOversee($containerId, (int) $this->viewer->id));
    }

    private function distanceTo(int $entityId): int
    {
        return View::get_distance_to_entity($this->coords(), $entityId);
    }

    private function coords(): object
    {
        return $this->viewer->coords ?? $this->viewer->getCoords();
    }

    private function perception(): int
    {
        if (!isset($this->viewer->caracs->p)) {
            $this->viewer->get_caracs();
        }

        return (int) $this->viewer->caracs->p;
    }

    /** @return array{int, int} the viewer's width and height in cells */
    private function footprint(): array
    {
        $race = (string) ($this->viewer->data->race ?? '');
        $foot = (new Map\EntityTypeFootprintService())->catalogue()[$race] ?? null;

        return $foot === null ? [1, 1] : [$foot->width(), $foot->height()];
    }
}
