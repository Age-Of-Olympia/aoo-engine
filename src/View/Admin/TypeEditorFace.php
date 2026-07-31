<?php

namespace App\View\Admin;

use App\Entity\Race;

/**
 * Which face of the type editor is being shown.
 *
 * `races` holds four populations that share one editor: playable races,
 * building types, scenery types and harvestable resources. They differ in
 * their label, their list, and where their images live — nothing else, which
 * is why one page serves them all.
 *
 * This replaces a boolean that answered "structure?" in forty-seven places.
 * A boolean could name two faces; the others had nowhere to go.
 */
final class TypeEditorFace
{
    public const CHARACTER = 'character';
    public const BUILDING = 'building';
    public const SCENERY = 'scenery';
    public const RESOURCE = 'resource';

    /** All structures; `structure_nature` is what tells the faces apart. */
    public const NATURE_DECOR = 'decor';
    public const NATURE_RESOURCE = 'ressource';

    private function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $singular,
        public readonly string $newLabel,
        public readonly string $page,
    ) {
    }

    /** The face a request asks for, defaulting to playable races. */
    public static function fromRequest(array $query): self
    {
        $kind = (string) ($query['kind'] ?? '');
        $nature = (string) ($query['nature'] ?? '');

        if ($kind !== 'structure') {
            return self::character();
        }

        return match ($nature) {
            self::NATURE_DECOR => self::scenery(),
            self::NATURE_RESOURCE => self::resource(),
            default => self::building(),
        };
    }

    public static function character(): self
    {
        return new self(
            self::CHARACTER,
            'Races',
            'Race',
            '+ Nouvelle race',
            '/admin/races.php'
        );
    }

    public static function building(): self
    {
        return new self(
            self::BUILDING,
            'Types de bâtiments',
            'Type de bâtiment',
            '+ Nouveau type',
            '/admin/structure-types.php'
        );
    }

    public static function scenery(): self
    {
        return new self(
            self::SCENERY,
            'Types de décor',
            'Type de décor',
            '+ Nouveau décor',
            '/admin/scenery-types.php'
        );
    }

    public static function resource(): self
    {
        return new self(
            self::RESOURCE,
            'Types récoltables',
            'Type récoltable',
            '+ Nouveau récoltable',
            '/admin/harvest-types.php'
        );
    }

    /**
     * The face a given row belongs to, whichever page one came from.
     *
     * On DEMANDE sa famille au type, on ne la déduit plus de ses colonnes :
     * depuis que `races` a un tronc et des déclinaisons, la classe le sait.
     * Cette méthode portait une copie de la règle ; il n'en reste qu'une, dans
     * {@see Race::ofFamily()}, là où un formulaire doit encore la construire.
     */
    public static function of(Race $race): self
    {
        return match ($race->familyKey()) {
            Race::FAMILY_SCENERY => self::scenery(),
            Race::FAMILY_RESOURCE => self::resource(),
            Race::FAMILY_BUILDING => self::building(),
            default => self::character(),
        };
    }

    public function isScenery(): bool
    {
        return $this->key === self::SCENERY;
    }

    /** Seule cette face récolte : elle seule règle un rendement. */
    public function isResource(): bool
    {
        return $this->key === self::RESOURCE;
    }

    public function isStructure(): bool
    {
        return $this->key !== self::CHARACTER;
    }

    /** Does this row belong on this face's list? */
    public function keeps(Race $race): bool
    {
        return self::of($race)->key === $this->key;
    }

    /** Images: a structure shows the sprite of what stands on the board. */
    public function imagesPage(): string
    {
        return $this->isStructure()
            ? '/admin/structure-images.php'
            : '/admin/avatars-portraits.php';
    }

    /** Hidden fields carrying the face through a form post. */
    public function formFields(): string
    {
        if (!$this->isStructure()) {
            return '';
        }

        $nature = match ($this->key) {
            self::SCENERY => self::NATURE_DECOR,
            self::RESOURCE => self::NATURE_RESOURCE,
            default => '',
        };

        return '<input type="hidden" name="kind" value="structure">'
            . ($nature === '' ? '' : '<input type="hidden" name="nature" value="' . $nature . '">');
    }

    /** The face's own page, with its query, for links and redirects. */
    public function url(string $query = ''): string
    {
        $own = $query === '' ? '' : '?' . $query;

        return $this->page . $own;
    }
}
