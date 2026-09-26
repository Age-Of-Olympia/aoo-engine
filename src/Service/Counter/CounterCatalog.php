<?php

namespace App\Service\Counter;

/**
 * Le catalogue des comptoirs : les deux écrans (marchand, école de
 * guerre) et leurs onglets.
 *
 * Un bâtiment n'est marchand, banquier ou entraîneur par aucune option de
 * personne : son DIALOGUE mène à l'écran, onglet par onglet
 * (DialogService). Ce catalogue dit ce que chaque onglet affiche — icône,
 * libellé du menu, bouton de la carte de case — et ce que l'écran répond
 * quand il refuse. Le menu des pages, la garde d'accès et les boutons de
 * la carte lisent la même table.
 */
final class CounterCatalog
{
    public const MERCHANT = 'merchant.php';

    public const WAR_SCHOOL = 'warschool.php';

    /**
     * Les écrans : bouton par défaut de la carte de case et refus.
     *
     * @return array<string, array{icon: string, label: string, notServed: string, wrongTab: string, selfBlocked: string, targetBlocked: string}>
     */
    public static function screens(): array
    {
        return [
            self::MERCHANT => [
                'icon' => 'ra-ammo-bag',
                'label' => 'Marchander',
                'notServed' => 'error not merchant',
                'wrongTab' => 'On ne sert pas cela à ce comptoir.',
                'selfBlocked' => 'Vous ne pouvez pas marchander sous l\'effet « %s ».',
                'targetBlocked' => 'Vous ne pouvez pas marchander avec un Marchand sous l\'effet « %s ».',
            ],
            self::WAR_SCHOOL => [
                'icon' => 'ra-axe',
                'label' => 'Apprendre',
                'notServed' => 'error not trainer',
                'wrongTab' => 'On n\'enseigne pas cela dans cette école.',
                'selfBlocked' => 'Vous ne pouvez pas apprendre de nouvelles techniques sous l\'effet « %s ».',
                'targetBlocked' => 'Cet entraîneur n\'est pas en état d\'enseigner.',
            ],
        ];
    }

    /**
     * Les onglets d'un écran, dans l'ordre du menu : icône et libellé du
     * menu, et `card` quand l'onglet mérite son propre bouton sur la
     * carte de case — sinon il passe derrière le bouton de l'écran.
     *
     * @return array<string, array{icon: string, label: string, class?: string, card?: array{icon: string, label: string}}>
     */
    public static function tabs(string $script): array
    {
        return match ($script) {
            self::MERCHANT => [
                'bids' => ['icon' => 'ra-gavel', 'label' => 'Offres de Vente', 'class' => 'sell-button'],
                'asks' => ['icon' => 'ra-scroll-unfurled', 'label' => 'Demandes d\'Achat', 'class' => 'buy-button'],
                'exchanges' => ['icon' => 'ra-x-mark', 'label' => 'Echanges', 'class' => 'exchange-button'],
                'bank' => ['icon' => 'ra-gold-bar', 'label' => 'Banque',
                    'card' => ['icon' => 'ra-gold-bar', 'label' => 'Banque']],
                'inventory' => ['icon' => 'ra-key', 'label' => 'Inventaire'],
                'repair' => ['icon' => 'ra-repair', 'label' => 'Réparer',
                    'card' => ['icon' => 'ra-repair', 'label' => 'Réparer']],
                'recycle' => ['icon' => 'ra-recycle', 'label' => 'Recycler',
                    'card' => ['icon' => 'ra-recycle', 'label' => 'Recycler']],
            ],
            self::WAR_SCHOOL => [
                'melee' => ['icon' => 'ra-crossed-swords', 'label' => 'Mêlée'],
                'distance' => ['icon' => 'ra-archer', 'label' => 'Distance'],
                'magic' => ['icon' => 'ra-fairy-wand', 'label' => 'Magie'],
                'spells' => ['icon' => 'ra-book', 'label' => 'Sorts'],
                'stealth' => ['icon' => 'ra-hood', 'label' => 'Furtivité'],
                'survival' => ['icon' => 'ra-campfire', 'label' => 'Survie'],
            ],
            default => [],
        };
    }

    /** Le bouton de menu d'un onglet, tel que les deux pages l'affichent. */
    public static function menuButton(string $script, string $tab): string
    {
        $entry = self::tabs($script)[$tab] ?? null;
        if ($entry === null) {
            return '';
        }

        return '<button' . (isset($entry['class']) ? ' class="' . $entry['class'] . '"' : '') . '>'
            . '<span class="ra ' . $entry['icon'] . '"></span> ' . $entry['label'] . '</button>';
    }
}
