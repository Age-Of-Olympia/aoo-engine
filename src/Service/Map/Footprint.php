<?php

namespace App\Service\Map;

use InvalidArgumentException;

/**
 * La découpe d'un décor : quelles cases sa figure occupe, et à quel titre.
 *
 * Le concept est au centre de tout le chantier des emprises — la palette de
 * l'éditeur, la pose, l'occupation, la page d'administration en dépendent — et
 * il vivait pourtant sous forme de tableau associatif, avec sa forme recopiée
 * mot pour mot dans neuf annotations et reconstruite dans trois services.
 * Trois services qui recalculaient chacun `cells` et `holed` de leur côté :
 * rien ne garantissait qu'ils tombent d'accord.
 *
 * # Ce qui se déduit ne se stocke pas
 *
 * `cells` est le nombre de morceaux, `holed` dit qu'ils n'emplissent pas leur
 * boîte. Les deux se lisent sur les décalages, donc ils se calculent ici et
 * nulle part ailleurs. Une figure ne peut plus se contredire.
 *
 * # Les décalages sont relatifs au premier morceau
 *
 * C'est la convention de tout le reste — la carte, les images d'ensemble,
 * l'éditeur — mais rien ne l'imposait, et le code qui pose une emprise s'y
 * fiait sans filet : une découpe enregistrée décalée aurait posé ses cases à
 * côté de l'entité. La construction normalise, donc l'invariant tient par
 * construction plutôt que par habitude.
 *
 * # La boîte peut être plus grande que la figure
 *
 * D'où deux façons de naître. `fromOffsets()` déduit la boîte des décalages,
 * ce qui convient quand la figure est tout ce qu'on connaît. `boxed()` la
 * reçoit — l'image d'ensemble d'un décor annonce sa taille, et un géant de
 * 3×3 qui n'occupe que quatre cases est troué. Déduire la boîte dans ce cas
 * effacerait justement le trou.
 */
final class Footprint
{
    /** @var array<int, array{0:int,1:int}> */
    private array $offsets;

    private int $w;
    private int $h;

    /** @var array<int, string> */
    private array $roles;

    /**
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     */
    private function __construct(array $offsets, int $w, int $h, array $roles)
    {
        $this->offsets = $offsets;
        $this->w = $w;
        $this->h = $h;
        $this->roles = $roles;
    }

    /**
     * Une figure dont la boîte est exactement ce que les morceaux occupent.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     *
     * @throws InvalidArgumentException une figure sans morceau ne décrit rien
     */
    public static function fromOffsets(array $offsets, array $roles = []): self
    {
        $offsets = self::anchored($offsets);

        $xs = array_column($offsets, 0);
        $ys = array_column($offsets, 1);

        return new self(
            $offsets,
            max($xs) - min($xs) + 1,
            max($ys) - min($ys) + 1,
            self::cleanRoles($roles)
        );
    }

    /**
     * Une figure dont la boîte est connue d'ailleurs, et peut la dépasser.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     *
     * @throws InvalidArgumentException figure vide, ou boîte trop petite pour elle
     */
    public static function boxed(int $w, int $h, array $offsets, array $roles = []): self
    {
        $offsets = self::anchored($offsets);

        if ($w < 1 || $h < 1) {
            throw new InvalidArgumentException('Une boîte de découpe fait au moins une case.');
        }

        if (count($offsets) > $w * $h) {
            throw new InvalidArgumentException(
                'La boîte ' . $w . '×' . $h . ' ne peut pas contenir ' . count($offsets) . ' morceaux.'
            );
        }

        return new self($offsets, $w, $h, self::cleanRoles($roles));
    }

    /** @return array<int, array{0:int,1:int}> décalages, relatifs au premier morceau */
    public function offsets(): array
    {
        return $this->offsets;
    }

    /** @return array<int, string> les seuls rôles explicitement décidés */
    public function roles(): array
    {
        return $this->roles;
    }

    public function width(): int
    {
        return $this->w;
    }

    public function height(): int
    {
        return $this->h;
    }

    /** Le nombre de cases occupées — pas celui de la boîte. */
    public function cells(): int
    {
        return count($this->offsets);
    }

    /** La figure laisse-t-elle des trous dans sa boîte ? */
    public function isHoled(): bool
    {
        return $this->cells() < $this->w * $this->h;
    }

    /** Une figure d'une seule case n'a rien à étendre. */
    public function isSingleCell(): bool
    {
        return $this->cells() < 2;
    }

    /** Le rôle décidé pour un morceau, ou celui qu'on propose à défaut. */
    public function roleOf(int $piece, string $default): string
    {
        return $this->roles[$piece] ?? $default;
    }

    /**
     * Où tombent les cases de la figure quand LE morceau donné est en (x, y).
     *
     * C'est le seul calcul que la figure fait vraiment, et il servait sous
     * quatre formes recopiées : poser depuis la palette, situer les morceaux
     * manquants, étendre une entité sur son emprise, dessiner le curseur. Les
     * quatre demandent la même chose — « montre-moi la figure vue depuis ce
     * morceau-là » — et se trompaient chacune à sa façon quand le morceau
     * choisi n'était pas le premier.
     *
     * @return array<int, array{0:int,1:int}> morceau → position absolue
     */
    public function cellsAround(int $piece, int $x, int $y): array
    {
        [$px, $py] = $this->offsets[$piece] ?? [0, 0];

        $cells = [];

        foreach ($this->offsets as $index => [$dx, $dy]) {
            $cells[$index] = [$x + $dx - $px, $y + $dy - $py];
        }

        return $cells;
    }

    /**
     * La forme attendue par le JavaScript et les gabarits.
     *
     * La frontière est assumée : au-delà, on sérialise en JSON ou on lit dans
     * un gabarit, et un tableau y est plus simple qu'un objet. En deçà, le
     * calcul appartient à la figure.
     *
     * @return array{w:int,h:int,cells:int,holed:bool,offsets:array<int,array{0:int,1:int}>,roles:array<int,string>}
     */
    public function toArray(): array
    {
        return [
            'w'       => $this->w,
            'h'       => $this->h,
            'cells'   => $this->cells(),
            'holed'   => $this->isHoled(),
            'offsets' => $this->offsets,
            'roles'   => $this->roles,
        ];
    }

    /**
     * Ramène les décalages sur le premier morceau, qui devient l'origine.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @return array<int, array{0:int,1:int}>
     *
     * @throws InvalidArgumentException
     */
    private static function anchored(array $offsets): array
    {
        if ($offsets === []) {
            throw new InvalidArgumentException('Une découpe sans morceau ne décrit rien.');
        }

        ksort($offsets);

        [$ax, $ay] = $offsets[array_key_first($offsets)];
        $anchored = [];

        foreach ($offsets as $piece => [$dx, $dy]) {
            $anchored[(int) $piece] = [$dx - $ax, $dy - $ay];
        }

        return $anchored;
    }

    /**
     * @param array<int, string> $roles
     * @return array<int, string>
     */
    private static function cleanRoles(array $roles): array
    {
        $clean = [];

        foreach ($roles as $piece => $role) {
            if ($role !== '') {
                $clean[(int) $piece] = $role;
            }
        }

        ksort($clean);

        return $clean;
    }
}
