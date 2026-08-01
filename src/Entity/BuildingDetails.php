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
 *   layer against FactionService, no DB FK — codes are the game-wide
 *   join key, mirroring races/factions seeding.
 * - build_state: lifecycle state, free string on purpose so future
 *   mechanics (construction progressive, usure/dégradation, ruine)
 *   can add states without schema changes. Current values:
 *   'construction' | 'built' | 'ruin'.
 * - dialog: code du dialogue porté par le bâtiment (clé naturelle
 *   dialogs.name, '' = aucun) — le lien vit sur l'entité, pas sur la
 *   case (contrairement aux déclencheurs map_dialogs). Muet en ruine.
 * Le propriétaire et la fermeture volontaire ont quitté ce satellite pour
 * l'ENTITÉ : être possédé et être fermé ne sont pas des privilèges de
 * bâtiment. Ce qui reste ici est ce qu'un bâtiment seul connaît — son état
 * de construction et son dialogue. Sa faction est partie aussi : elle vivait
 * ici ET sur l'entité, et les deux avaient divergé.
 */
#[ORM\Entity]
#[ORM\Table(name: "buildings")]
class BuildingDetails
{
    public const STATE_CONSTRUCTION = 'construction';
    public const STATE_BUILT = 'built';
    public const STATE_RUIN = 'ruin';

    #[ORM\Id]
    #[ORM\Column(type: "integer", name: "player_id")]
    private int $playerId;

    #[ORM\Column(type: "string", length: 20, name: "build_state", options: ["default" => self::STATE_BUILT])]
    private string $buildState = self::STATE_BUILT;

    #[ORM\Column(type: "string", length: 100, options: ["default" => ""])]
    private string $dialog = '';

    /**
     * Ce qui est inscrit sur cet exemplaire se lit-il sans s'approcher ?
     *
     * NULL = comme sa nature (races.readable_from_afar). La nullabilité
     * porte le sens : sans elle, « on a décidé que non » et « on n'a
     * rien décidé » se confondraient, et changer le défaut d'un type ne
     * rattraperait jamais les exemplaires déjà posés.
     */
    #[ORM\Column(type: "boolean", name: "readable_from_afar", nullable: true)]
    private ?bool $readableFromAfar = null;

    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    public function setPlayerId(int $playerId): self
    {
        $this->playerId = $playerId;
        return $this;
    }

    public function getBuildState(): string
    {
        return $this->buildState;
    }

    public function setBuildState(string $buildState): self
    {
        $this->buildState = $buildState;
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

    /** null = suit sa race. */
    public function isReadableFromAfar(): ?bool
    {
        return $this->readableFromAfar;
    }

    public function setReadableFromAfar(?bool $readable): self
    {
        $this->readableFromAfar = $readable;

        return $this;
    }

}
