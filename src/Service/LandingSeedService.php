<?php

namespace App\Service;

use Classes\Db;
use Throwable;

/**
 * Seed du contenu éditorial initial de la page d'accueil
 * (landing_sections / landing_news / landing_images).
 *
 * Contrairement aux seeds dialogues/races (datas/ legacy), le contenu
 * vit ICI, en constantes : il n'existe aucun fichier legacy — c'est la
 * rédaction validée en local (juillet 2026) qui sert d'état initial.
 *
 * Les IMAGES ne sont pas dans git (img/ est ignoré) : elles se
 * déploient à la main dans img/ui/landing/ (archive fournie à part).
 * Une ligne d'image n'est semée que si son fichier existe sur le
 * serveur — les manquantes sont rapportées, relancer le seed après
 * dépôt des fichiers les complète.
 *
 * Création seulement : une section (slug), une chronique (date+titre)
 * ou une image (chemin) déjà en base est préservée telle quelle —
 * relancer n'écrase jamais une édition admin. Transactionnel.
 */
class LandingSeedService
{
    private const SECTIONS = [
        [
            'slug'     => 'presentation',
            'title'    => 'Le monde d\'Olympia',
            'body'     => '<p>Age of Olympia est un jeu de rôle gratuit au tour-par-tour, dans un monde antique où cinq peuples se disputent la faveur des dieux. Quelques minutes par jour suffisent pour jouer votre tour... Mais alliances, guerres et commerce se tissent sur des saisons entières.</p>',
            'position' => 1,
        ],
    ];

    private const NEWS = [
        [
            'news_date' => '2026-07-10',
            'title'     => 'Les Olympiens se promènent',
            'text'      => 'Après avoir traversé la planète pour trouver une clé, les Olympiens sont de retour chez eux.',
        ],
        [
            'news_date' => '2026-06-28',
            'title'     => 'L\'école de guerre ouvre ses portes',
            'text'      => 'Mêlée, distance, magie, furtivité, survie : techniques et sorts contre or sonnant.',
        ],
        [
            'news_date' => '2026-06-12',
            'title'     => 'Le temple est achevé !',
            'text'      => 'Après des mois de labeur, le temple à la gloire de la Déesse est enfin terminé. Les constructeurs Elfes et Nains ont accompli des merveilles.',
        ],
    ];

    private const IMAGES = [
        [
            'path'       => 'img/ui/landing/image2.jpg',
            'plate_path' => 'img/ui/landing/image2_plate.jpg',
            'caption'    => 'Les Elfes aux prises avec les Géants, proche du volcan',
            'position'   => 0,
        ],
        [
            'path'       => 'img/ui/landing/big_battle_20251030.jpg',
            'plate_path' => 'img/ui/landing/big_battle_20251030_plate.jpg',
            'caption'    => 'Immense bataille impliquant tous les peuples d\'Olympia',
            'position'   => 1,
        ],
        [
            'path'       => 'img/ui/landing/img_4262.jpg',
            'plate_path' => 'img/ui/landing/img_4262_plate.jpg',
            'caption'    => 'Nahash capturée à la Faille',
            'position'   => 2,
        ],
        [
            'path'       => 'img/ui/landing/image-2026-03-31-223554-2.jpg',
            'plate_path' => 'img/ui/landing/image-2026-03-31-223554-2_plate.jpg',
            'caption'    => 'Les Enfers... Bien peuplés !',
            'position'   => 3,
        ],
        [
            'path'       => 'img/ui/landing/image1.jpg',
            'plate_path' => 'img/ui/landing/image1_plate.jpg',
            'caption'    => 'Les Hommes-Sauvages défendent leur disque solaire !',
            'position'   => 7,
        ],
    ];

    private Db $db;
    private string $root;

    public function __construct(?Db $db = null, ?string $root = null)
    {
        $this->db = $db ?? new Db();
        $this->root = rtrim($root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2)), '/');
    }

    /**
     * Sème le contenu manquant.
     *
     * @return array{created: list<string>, kept: list<string>, missingFiles: list<string>}
     */
    public function seed(): array
    {
        $created = [];
        $kept = [];
        $missingFiles = [];

        $this->db->beginTransaction();
        try {
            foreach (self::SECTIONS as $section) {
                if ($this->exists('SELECT id FROM landing_sections WHERE slug = ?', [$section['slug']])) {
                    $kept[] = 'section ' . $section['slug'];
                    continue;
                }
                $this->db->exe(
                    'INSERT INTO landing_sections (slug, title, body, position, is_active) VALUES (?, ?, ?, ?, 1)',
                    [$section['slug'], $section['title'], $section['body'], $section['position']]
                );
                $created[] = 'section ' . $section['slug'];
            }

            foreach (self::NEWS as $entry) {
                if ($this->exists('SELECT id FROM landing_news WHERE news_date = ? AND title = ?', [$entry['news_date'], $entry['title']])) {
                    $kept[] = 'chronique « ' . $entry['title'] . ' »';
                    continue;
                }
                $this->db->exe(
                    'INSERT INTO landing_news (news_date, title, text, is_active) VALUES (?, ?, ?, 1)',
                    [$entry['news_date'], $entry['title'], $entry['text']]
                );
                $created[] = 'chronique « ' . $entry['title'] . ' »';
            }

            foreach (self::IMAGES as $image) {
                if ($this->exists('SELECT id FROM landing_images WHERE path = ?', [$image['path']])) {
                    $kept[] = 'image ' . $image['path'];
                    continue;
                }
                if (!is_file($this->root . '/' . $image['path']) || !is_file($this->root . '/' . $image['plate_path'])) {
                    $missingFiles[] = $image['path'];
                    continue;
                }
                $this->db->exe(
                    'INSERT INTO landing_images (path, plate_path, caption, position, is_active) VALUES (?, ?, ?, ?, 1)',
                    [$image['path'], $image['plate_path'], $image['caption'], $image['position']]
                );
                $created[] = 'image ' . $image['path'];
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['created' => $created, 'kept' => $kept, 'missingFiles' => $missingFiles];
    }

    /** @param list<mixed> $params */
    private function exists(string $sql, array $params): bool
    {
        return (bool) $this->db->exe($sql, $params)->num_rows;
    }
}
