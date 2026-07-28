<?php

namespace App\View\Admin;

use App\Entity\Race;

/**
 * Which face of the type editor is being shown.
 *
 * `races` holds three populations that share one editor: playable races,
 * building types, and scenery types. They differ in their label, their list,
 * and where their images live — nothing else, which is why one page serves
 * all three.
 *
 * This replaces a boolean that answered "structure?" in forty-seven places.
 * A boolean could name two faces; the third had nowhere to go.
 */
final class TypeEditorFace
{
    public const CHARACTER = 'character';
    public const BUILDING = 'building';
    public const SCENERY = 'scenery';

    /** Scenery types are structures too; `structure_nature` tells them apart. */
    public const NATURE_DECOR = 'decor';

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

        return $nature === self::NATURE_DECOR ? self::scenery() : self::building();
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

    /** The face a given row belongs to, whichever page one came from. */
    public static function of(Race $race): self
    {
        if (!$race->isStructureKind()) {
            return self::character();
        }

        return $race->getStructureNature() === self::NATURE_DECOR
            ? self::scenery()
            : self::building();
    }

    public function isScenery(): bool
    {
        return $this->key === self::SCENERY;
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

        return '<input type="hidden" name="kind" value="structure">'
            . ($this->key === self::SCENERY
                ? '<input type="hidden" name="nature" value="' . self::NATURE_DECOR . '">'
                : '');
    }

    /** The face's own page, with its query, for links and redirects. */
    public function url(string $query = ''): string
    {
        $own = $query === '' ? '' : '?' . $query;

        return $this->page . $own;
    }
}
