<?php

namespace App\View;

use App\Service\DateFormatService;
use App\Service\LandingContentService;

/**
 * Sections éditoriales de la page d'accueil, composées AUTOUR de la
 * carte de menu sur le premier écran (pas de défilement pour les
 * voir) : présentation à gauche, dernières chroniques à droite,
 * galerie d'aperçus en bandeau sous les colonnes.
 *
 * Le contenu vit en base (landing_sections / landing_news /
 * landing_images, éditées depuis l'admin) — une section sans contenu
 * actif ne se rend pas, la page dégrade vers la composition nue
 * (la carte reste centrée, colonnes vides). Le corps des sections est
 * du HTML confiance-admin (même contrat que les dialogues) ; titres
 * et légendes sont échappés.
 */
class LandingSectionsView
{
    private const NEWS_LIMIT = 3;

    /** @var list<object> */
    private array $sections;

    /** @var list<object> */
    private array $news;

    /** @var list<object> */
    private array $images;

    public function __construct()
    {
        $content = new LandingContentService();

        $this->sections = $content->activeSections();
        $this->news = $content->latestNews(self::NEWS_LIMIT);
        $this->images = $content->activeImages();
    }

    /** Colonne gauche : blocs de texte (présentation du jeu…). */
    public function renderSections(): void
    {
        foreach ($this->sections as $section) {
            echo '<section class="landing-section" id="landing-' . htmlspecialchars($section->slug, ENT_QUOTES) . '">';
            echo '<h2>' . htmlspecialchars($section->title) . '</h2>';
            echo '<div class="landing-section-body">' . $section->body . '</div>';
            echo '</section>';
        }
    }

    /** Colonne droite : dernières chroniques datées. */
    public function renderNews(): void
    {
        if (!$this->news) {
            return;
        }

        echo '<section class="landing-section" id="landing-chroniques">';
        echo '<h2>Dernières chroniques</h2>';
        echo '<ol class="landing-news">';
        foreach ($this->news as $entry) {
            echo '<li>'
                . '<time datetime="' . htmlspecialchars($entry->news_date, ENT_QUOTES) . '">'
                . (new DateFormatService())->format($entry->news_date)
                . '</time>'
                . '<h3>' . htmlspecialchars($entry->title) . '</h3>'
                . '<p>' . htmlspecialchars($entry->text) . '</p>'
                . '</li>';
        }
        echo '</ol>';
        echo '</section>';
    }

    /** Bandeau sous les colonnes : galerie d'aperçus. */
    public function renderGallery(): void
    {
        if (!$this->images) {
            return;
        }

        echo '<section class="landing-section" id="landing-galerie">';
        echo '<h2>Aperçus du jeu</h2>';
        echo '<div class="landing-gallery">';
        foreach ($this->images as $i => $image) {
            $caption = htmlspecialchars($image->caption);
            /* Légende de planche gravée : « Pl. I — Les plaines de Gaïa » */
            $plate = 'Pl. ' . self::roman($i + 1) . ($caption !== '' ? ' — ' . $caption : '');
            /* La vignette est la version « planche » normalisée (même
             * taille pour toutes) ; le carrousel plein écran reçoit la
             * version pleine via data-full (js/landing_gallery.js). */
            $thumb = $image->plate_path !== '' ? $image->plate_path : $image->path;
            echo '<figure tabindex="0" role="button" aria-label="Agrandir : ' . $caption . '"'
                . ' data-full="' . htmlspecialchars($image->path, ENT_QUOTES) . '">'
                . '<img src="' . htmlspecialchars($thumb, ENT_QUOTES) . '" alt="' . $caption . '" loading="lazy" />'
                . '<figcaption>' . $plate . '</figcaption>'
                . '</figure>';
        }
        echo '</div>';
        echo '</section>';
        echo '<script src="js/landing_gallery.js?v=20260715"></script>';
    }

    /** Numérotation des planches (1 → I) — la galerie en compte peu. */
    private static function roman(int $n): string
    {
        $map = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
        $out = '';
        foreach ($map as $value => $symbol) {
            while ($n >= $value) {
                $out .= $symbol;
                $n -= $value;
            }
        }

        return $out;
    }
}
