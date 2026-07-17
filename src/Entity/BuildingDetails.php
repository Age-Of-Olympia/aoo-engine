<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BuildingDetails — 1:1 satellite row of a Building's `players` row
 * (component pattern, docs/design-buildings-entities.md §4.5; same
 * shape as players_pnjs / tutorial_players).
 *
 * The building's TYPE lives in players.race (a races row of kind
 * 'structure') — not duplicated here.
 *
 * - owner_id: FK players.id of the owning character, nullable — a
 *   building can be faction-held or ownerless (quest/admin placed).
 * - faction: faction CODE from the factions catalog (same convention
 *   as players.faction), empty when neutral. Validated in the service
 *   layer against FactionService, no DB FK — codes are the game-wide
 *   join key, mirroring races/factions seeding.
 * - build_state: lifecycle state, free string on purpose so future
 *   mechanics (construction progressive, usure/dégradation, ruine)
 *   can add states without schema changes. Current values:
 *   'construction' | 'built' | 'ruin'.
 * - dialog: code du dialogue porté par le bâtiment (clé naturelle
 *   dialogs.name, '' = aucun) — le lien vit sur l'entité, pas sur la
 *   case (contrairement aux déclencheurs map_dialogs). Muet en ruine.
 * - is_open: fermeture VOLONTAIRE (admin, un jour le propriétaire).
 *   L'ouverture effective combine ce drapeau et l'état — règle unique
 *   dans BuildingService::closureReason().
 */
#[ORM\Entity]
#[ORM\Table(name: "buildings")]
class BuildingDetails
{
    public const STATE_CONSTRUCTION = 'construction';
    public const STATE_BUILT = 'built';
    public const STATE_RUIN = 'ruin';

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $owner_id = null;

    #[ORM\Column(type: "string", length: 100, options: ["default" => ""])]
    private string $faction = '';

    #[ORM\Column(type: "string", length: 20, options: ["default" => self::STATE_BUILT])]
    private string $build_state = self::STATE_BUILT;

    #[ORM\Column(type: "string", length: 100, options: ["default" => ""])]
    private string $dialog = '';

    #[ORM\Column(type: "boolean", options: ["default" => true])]
    private bool $is_open = true;

    public function getPlayerId(): int
    {
        return $this->player_id;
    }

    public function setPlayerId(int $player_id): self
    {
        $this->player_id = $player_id;
        return $this;
    }

    public function getOwnerId(): ?int
    {
        return $this->owner_id;
    }

    public function setOwnerId(?int $owner_id): self
    {
        $this->owner_id = $owner_id;
        return $this;
    }

    public function getFaction(): string
    {
        return $this->faction;
    }

    public function setFaction(string $faction): self
    {
        $this->faction = $faction;
        return $this;
    }

    public function getBuildState(): string
    {
        return $this->build_state;
    }

    public function setBuildState(string $build_state): self
    {
        $this->build_state = $build_state;
        return $this;
    }

    public function getDialog(): string
    {
        return $this->dialog;
    }

    public function setDialog(string $dialog): self
    {
        $this->dialog = $dialog;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->is_open;
    }

    public function setIsOpen(bool $is_open): self
    {
        $this->is_open = $is_open;
        return $this;
    }
}
