<?php

namespace App\View\Entity;

use App\Entity\BuildingDetails;
use App\Enum\EntityCategory;
use App\Factory\PlayerFactory;
use App\Service\BuildingService;
use App\Service\ContainerService;
use App\Service\EntityVisibility;
use App\Service\FactionService;
use App\Service\LockService;
use App\Service\RaceService;
use Classes\Player;
use Classes\Str;

/**
 * What one entity shows to one viewer, computed once with the
 * visibility rules (EntityVisibility). The tile card and the sheet both
 * read it: the card is the short form, the sheet the full one
 * (EntityParts draws the pieces they share).
 */
final class EntityProfile
{
    public readonly EntityVisibility $visibility;
    public readonly bool $isStructure;
    /** A character's PV, effects, message and equipment are shown. */
    public readonly bool $detailed;
    /** Remaining life in %, or null when the viewer may not see it. */
    public readonly ?int $pvPct;
    public readonly ?BuildingDetails $details;
    /** Why it is shut (building or chest), or null. */
    public readonly ?string $closure;
    public readonly bool $isLockable;
    public readonly bool $isContainer;
    /** The god of a consecrated altar, or null. */
    public readonly ?Player $god;

    public function __construct(public readonly Player $viewer, public readonly Player $target)
    {
        if (!isset($target->caracs->pv)) {
            $target->get_caracs();
        }

        $id = (int) $target->id;
        $type = (string) ($target->data->player_type ?? '');

        $this->visibility = new EntityVisibility($viewer);
        $this->isStructure = EntityCategory::fromPlayerType($type ?: null)->isStructure();
        $this->detailed = $this->isStructure || $this->visibility->seesDetailsOf($id);

        $pv = (int) ($target->caracs->pv ?? 0);
        $pvPct = $pv > 0 ? (int) floor($target->getRemaining('pv') / $pv * 100) : 100;
        $this->pvPct = $this->detailed ? $pvPct : null;

        $buildings = new BuildingService();
        $this->details = $type === 'building' ? $buildings->getDetails($id) : null;
        $this->closure = match (true) {
            $this->details !== null => $buildings->closureReason($id, $this->details, $pvPct),
            $type === 'item' => (new ContainerService())->closureReasonOf($id),
            default => null,
        };
        $this->isLockable = $this->isStructure && (new LockService())->isLockable($id);
        $this->isContainer = $this->isStructure && (new ContainerService())->isContainer($id);
        $this->god = self::godOf($target);
    }

    public static function of(Player $viewer, int $entityId): self
    {
        $target = PlayerFactory::legacy($entityId);
        $target->get_data();

        return new self($viewer, $target);
    }

    public function id(): int
    {
        return (int) $this->target->id;
    }

    public function name(): string
    {
        return (string) $this->target->data->name;
    }

    public function playerType(): string
    {
        return (string) ($this->target->data->player_type ?? '');
    }

    /**
     * The portrait: an altar shows its god, a building under construction
     * its site, a structure without art its sprite chain, down to the
     * initials frame.
     */
    public function portraitUrl(): string
    {
        if ($this->god !== null) {
            return (string) $this->god->data->portrait;
        }
        if ($this->details?->getBuildState() === BuildingDetails::STATE_CONSTRUCTION) {
            return BuildingService::siteImage(true);
        }

        // An object shows its item art, never its board sprite (stored as its portrait).
        if ($this->playerType() === 'item') {
            return \Classes\View::exemplarSprite((string) $this->target->data->race, $this->name());
        }

        $url = (string) ($this->target->data->portrait ?? '');
        if ($this->isStructure && ($url === '' || !file_exists($url))) {
            return \Classes\View::structureSprite((string) $this->target->data->race, $this->name());
        }

        return $url;
    }

    /** The type line: race or type label, a catalogue item's label, PNJ and inactive marks. */
    public function typeLabel(): string
    {
        $race = (string) ($this->target->data->race ?? '');
        $raceJson = (new RaceService())->getRaceData($race);
        $pnj = (int) $this->target->id < 0 ? ' - PNJ' : '';

        $label = match (true) {
            $raceJson !== null => $raceJson->name,
            $this->playerType() === 'item' => \App\Service\ItemInstanceService::catalogLabel(
                \App\Factory\EntityManagerFactory::getEntityManager()->getConnection(),
                $race
            ),
            default => ucfirst($race !== '' ? $race : 'inconnu'),
        };

        $label .= $pnj;
        if ((int) $this->target->id > 0 && !empty($this->target->data->isInactive)) {
            $label .= ' (inactif)';
        }

        return $label;
    }

    /**
     * The words on the plaque: a character's message within Perception,
     * a thing's inscription where it can be read; the reason otherwise.
     */
    public function textHtml(): string
    {
        if (!$this->isStructure) {
            return $this->detailed
                ? Str::richText((string) $this->target->data->text)
                : '<em>Ce personnage est trop éloigné pour l\'entendre parler.</em>';
        }

        $inscription = BuildingService::inscriptionOf($this->target);
        if ($inscription === '') {
            return '';
        }

        return $this->visibility->readsInscriptionOf($this->target, $this->details)
            ? Str::richText($inscription)
            : '<em>' . BuildingService::OUT_OF_REACH_NOTICE . '</em>';
    }

    /**
     * Visible effects (hidden ones never), within Perception only.
     *
     * @return list<object>
     */
    public function effects(): array
    {
        if (!$this->detailed) {
            return [];
        }

        $effects = [];
        foreach ($this->target->getEffects() as $effect) {
            if (!$this->target->effectService->isHidden($effect->getName())) {
                $effects[] = $effect;
            }
        }

        return $effects;
    }

    public function seesEffectTimers(): bool
    {
        return $this->visibility->seesEffectTimersOf(
            $this->id(),
            (string) ($this->target->data->faction ?? ''),
            (string) ($this->target->data->secretFaction ?? '')
        );
    }

    /**
     * The factions shown: the public one, and the secret one to its
     * members and the admins.
     *
     * @return list<array{code: string, json: object, role: int, secret: bool}>
     */
    public function factions(): array
    {
        $service = new FactionService();
        $factions = [];

        $code = (string) ($this->target->data->faction ?? '');
        $json = $code !== '' ? $service->getFactionData($code) : null;
        if ($json) {
            $factions[] = ['code' => $code, 'json' => $json, 'role' => (int) ($this->target->data->factionRole ?? 0), 'secret' => false];
        }

        $secret = (string) ($this->target->data->secretFaction ?? '');
        $secretJson = $this->visibility->seesSecretFaction($secret) ? $service->getFactionData($secret) : null;
        if ($secretJson) {
            $factions[] = ['code' => $secret, 'json' => $secretJson, 'role' => (int) ($this->target->data->secretFactionRole ?? 0), 'secret' => true];
        }

        return $factions;
    }

    /** The owner of a structure, or null. */
    public function owner(): ?Player
    {
        $ownerId = $this->target->data->owner_id ?? null;
        if (!$this->isStructure || $ownerId === null) {
            return null;
        }

        $owner = PlayerFactory::legacy((int) $ownerId);
        $owner->get_data();

        return empty($owner->data->name) ? null : $owner;
    }

    private static function godOf(Player $target): ?Player
    {
        $godId = (int) ($target->data->godId ?? 0);
        if ($godId === 0 || ($target->data->race ?? '') !== 'altar') {
            return null;
        }

        $god = PlayerFactory::legacy($godId);
        $god->get_data();

        return empty($god->data->name) ? null : $god;
    }
}
