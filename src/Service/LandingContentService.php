<?php

namespace App\Service;

use Classes\Db;
use RuntimeException;

/**
 * Contenu éditorial de la page d'accueil (landing_sections /
 * landing_news / landing_images) : lectures pour LandingSectionsView,
 * mutations pour l'admin (admin/landing.php).
 *
 * Les images de la galerie vivent dans img/ui/landing/ ; l'upload
 * valide le type réel du fichier (getimagesize) et normalise le nom.
 * La suppression d'une ligne d'image supprime aussi son fichier si
 * plus aucune ligne ne le référence.
 */
class LandingContentService
{
    public const IMAGE_DIR = 'img/ui/landing';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /* Normalisation des images (GD) : la planche au double du format
     * d'affichage CSS (2.1:1, écrans retina), la pleine plafonnée pour
     * le carrousel ; JPEG (ce GD n'a pas le webp), alpha aplati sur le
     * ton vélin des cadres. */
    private const PLATE_WIDTH = 840;
    private const PLATE_HEIGHT = 400;
    private const FULL_MAX_SIDE = 1920;
    private const JPEG_QUALITY = 85;
    private const VELLUM_RGB = [0xfb, 0xf5, 0xe8];

    private Db $db;
    private string $root;

    public function __construct(?Db $db = null, ?string $root = null)
    {
        $this->db = $db ?? new Db();
        $this->root = rtrim($root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2)), '/');
    }

    /* ---------- Sections de texte ---------- */

    /** @return list<object> */
    public function listSections(): array
    {
        return $this->fetchAll('SELECT id, slug, title, body, position, is_active FROM landing_sections ORDER BY position, id');
    }

    public function saveSection(string $slug, string $title, string $body, int $position, bool $isActive): void
    {
        if (!preg_match('/^[a-z0-9_-]{1,50}$/', $slug)) {
            throw new RuntimeException('Code de section invalide (minuscules, chiffres, _ ou -, 50 max).');
        }
        if (trim($title) === '') {
            throw new RuntimeException('Le titre de la section est requis.');
        }

        $this->db->exe(
            'INSERT INTO landing_sections (slug, title, body, position, is_active) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body),
                position = VALUES(position), is_active = VALUES(is_active)',
            [$slug, trim($title), $body, $position, (int) $isActive]
        );
    }

    public function deleteSection(string $slug): void
    {
        $this->db->exe('DELETE FROM landing_sections WHERE slug = ?', [$slug]);
    }

    /* ---------- Chroniques ---------- */

    /** @return list<object> */
    public function listNews(): array
    {
        return $this->fetchAll('SELECT id, news_date, title, text, is_active FROM landing_news ORDER BY news_date DESC, id DESC');
    }

    public function saveNews(?int $id, string $date, string $title, string $text, bool $isActive): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Date de chronique invalide (AAAA-MM-JJ).');
        }
        if (trim($title) === '') {
            throw new RuntimeException('Le titre de la chronique est requis.');
        }

        if ($id === null) {
            $this->db->exe(
                'INSERT INTO landing_news (news_date, title, text, is_active) VALUES (?, ?, ?, ?)',
                [$date, trim($title), trim($text), (int) $isActive]
            );

            return;
        }

        $this->db->exe(
            'UPDATE landing_news SET news_date = ?, title = ?, text = ?, is_active = ? WHERE id = ?',
            [$date, trim($title), trim($text), (int) $isActive, $id]
        );
    }

    public function deleteNews(int $id): void
    {
        $this->db->exe('DELETE FROM landing_news WHERE id = ?', [$id]);
    }

    /* ---------- Galerie ---------- */

    /** @return list<object> */
    public function listImages(): array
    {
        return $this->fetchAll('SELECT id, path, plate_path, caption, position, is_active FROM landing_images ORDER BY position, id');
    }

    /**
     * Enregistre un fichier uploadé dans img/ui/landing/ et crée sa ligne.
     *
     * L'image est normalisée (normalizeFile) : une version « pleine »
     * plafonnée pour le carrousel et une « planche » recadrée au format
     * des gravures de l'accueil — toutes les planches ont ainsi la même
     * taille, quel que soit l'original. Si GD ne sait pas lire le format
     * (webp sans support…), l'original est gardé tel quel : l'accueil
     * reste correct (recadrage CSS), seul le poids n'est pas optimisé.
     */
    public function addImage(string $tmpPath, string $originalName, string $caption, int $position): void
    {
        if (getimagesize($tmpPath) === false) {
            throw new RuntimeException('Le fichier reçu n\'est pas une image lisible.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            throw new RuntimeException('Format non géré (' . implode(', ', self::IMAGE_EXTENSIONS) . ').');
        }

        $base = strtolower((string) preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME)));
        $base = trim($base, '-_') ?: 'apercu';

        $dir = $this->ensureImageDir();

        $normalized = self::normalizeFile($tmpPath, $dir, $base);

        if ($normalized !== null) {
            [$fullName, $plateName] = $normalized;
            $this->db->exe(
                'INSERT INTO landing_images (path, plate_path, caption, position, is_active) VALUES (?, ?, ?, ?, 1)',
                [self::IMAGE_DIR . '/' . $fullName, self::IMAGE_DIR . '/' . $plateName, trim($caption), $position]
            );

            return;
        }

        /* Format illisible par GD : original conservé tel quel.
         * move_uploaded_file : uniquement depuis un vrai upload HTTP ;
         * rename en repli pour les tests/CLI. */
        $name = self::uniqueName($dir, $base, $extension);
        if (!@move_uploaded_file($tmpPath, $dir . '/' . $name) && !@rename($tmpPath, $dir . '/' . $name)) {
            throw new RuntimeException('Écriture du fichier impossible.');
        }

        $this->db->exe(
            'INSERT INTO landing_images (path, plate_path, caption, position, is_active) VALUES (?, \'\', ?, ?, 1)',
            [self::IMAGE_DIR . '/' . $name, trim($caption), $position]
        );
    }

    public function updateImage(int $id, string $caption, int $position, bool $isActive): void
    {
        $this->db->exe(
            'UPDATE landing_images SET caption = ?, position = ?, is_active = ? WHERE id = ?',
            [trim($caption), $position, (int) $isActive, $id]
        );
    }

    public function deleteImage(int $id): void
    {
        $res = $this->db->exe('SELECT path, plate_path FROM landing_images WHERE id = ?', [$id]);
        $row = $res->fetch_object();
        if (!$row) {
            return;
        }

        $this->db->exe('DELETE FROM landing_images WHERE id = ?', [$id]);

        /* Les fichiers ne partent que s'ils vivent dans notre répertoire
         * et que plus aucune ligne ne les référence (les seeds pointent
         * vers des images du jeu, jamais supprimées ici). */
        foreach ([$row->path, $row->plate_path] as $path) {
            if ($path === '' || !str_starts_with($path, self::IMAGE_DIR . '/')) {
                continue;
            }
            $stillUsed = $this->db->exe(
                'SELECT id FROM landing_images WHERE path = ? OR plate_path = ?', [$path, $path]
            )->num_rows;
            if (!$stillUsed) {
                @unlink($this->root . '/' . $path);
            }
        }
    }

    /* ---------- Lectures de l'accueil (contenu visible seulement) ---------- */

    /** @return list<object> */
    public function activeSections(): array
    {
        return $this->fetchAll('SELECT slug, title, body FROM landing_sections WHERE is_active = 1 ORDER BY position, id');
    }

    /** @return list<object> */
    public function latestNews(int $limit): array
    {
        return $this->fetchAll('SELECT news_date, title, text FROM landing_news WHERE is_active = 1 ORDER BY news_date DESC, id DESC LIMIT ' . max(1, $limit));
    }

    /** @return list<object> */
    public function activeImages(): array
    {
        return $this->fetchAll('SELECT path, plate_path, caption FROM landing_images WHERE is_active = 1 ORDER BY position, id');
    }

    /**
     * Normalise une image source en deux JPEG dans $dir : la version
     * pleine (plafonnée à FULL_MAX_SIDE) et la planche (recadrage
     * centré couvrant PLATE_WIDTH × PLATE_HEIGHT).
     *
     * @return array{0: string, 1: string}|null [nom plein, nom planche],
     *         ou null si GD ne sait pas décoder ce format.
     */
    public static function normalizeFile(string $sourcePath, string $dir, string $base): ?array
    {
        $source = self::loadGdImage($sourcePath);
        if ($source === null) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        /* Version pleine : plafonnée, jamais agrandie */
        $scale = min(1.0, self::FULL_MAX_SIDE / max($width, $height));
        $fullW = max(1, (int) round($width * $scale));
        $fullH = max(1, (int) round($height * $scale));
        $full = self::vellumCanvas($fullW, $fullH);
        imagecopyresampled($full, $source, 0, 0, 0, 0, $fullW, $fullH, $width, $height);

        /* Planche : recadrage centré qui COUVRE le format des gravures */
        $cropW = min($width, (int) round($height * self::PLATE_WIDTH / self::PLATE_HEIGHT));
        $cropH = min($height, (int) round($cropW * self::PLATE_HEIGHT / self::PLATE_WIDTH));
        $cropX = (int) (($width - $cropW) / 2);
        $cropY = (int) (($height - $cropH) / 2);
        $plate = self::vellumCanvas(self::PLATE_WIDTH, self::PLATE_HEIGHT);
        imagecopyresampled($plate, $source, 0, 0, $cropX, $cropY, self::PLATE_WIDTH, self::PLATE_HEIGHT, $cropW, $cropH);

        $fullName = self::uniqueName($dir, $base, 'jpg');
        $plateBase = pathinfo($fullName, PATHINFO_FILENAME) . '_plate';

        if (!imagejpeg($full, $dir . '/' . $fullName, self::JPEG_QUALITY)
            || !imagejpeg($plate, $dir . '/' . $plateBase . '.jpg', self::JPEG_QUALITY)) {
            @unlink($dir . '/' . $fullName);
            throw new RuntimeException('Écriture des images normalisées impossible.');
        }

        imagedestroy($source);
        imagedestroy($full);
        imagedestroy($plate);

        return [$fullName, $plateBase . '.jpg'];
    }

    /** Décode via GD selon le type réel ; null si format non géré. */
    private static function loadGdImage(string $path): ?\GdImage
    {
        $type = getimagesize($path)[2] ?? 0;

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_BMP  => @imagecreatefrombmp($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default        => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /** Toile pré-remplie du ton vélin : le JPEG n'a pas d'alpha. */
    private static function vellumCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...self::VELLUM_RGB));

        return $canvas;
    }

    /** Nom libre dans $dir (le dérivé _plate compris) : suffixe si pris. */
    private static function uniqueName(string $dir, string $base, string $extension): string
    {
        $candidate = $base;
        for ($i = 2; file_exists($dir . '/' . $candidate . '.' . $extension)
                || file_exists($dir . '/' . $candidate . '_plate.' . $extension); $i++) {
            $candidate = $base . '-' . $i;
        }

        return $candidate . '.' . $extension;
    }

    private function ensureImageDir(): string
    {
        $dir = $this->root . '/' . self::IMAGE_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer ' . self::IMAGE_DIR . '.');
        }

        return $dir;
    }

    /* ---------- Interne ---------- */

    /** @return list<object> */
    private function fetchAll(string $sql): array
    {
        $rows = [];

        $res = $this->db->exe($sql);
        while ($row = $res->fetch_object()) {
            $rows[] = $row;
        }

        return $rows;
    }
}
