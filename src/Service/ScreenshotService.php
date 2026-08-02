<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Factory\PlayerFactory;
use Classes\Player;
use Classes\View;
use Exception;

/**
 * Service for generating screenshots - only for arene_s2 to begin with
 */
class ScreenshotService
{
    private const DEFAULT_SCREENSHOT_PLAYER_ID = -92;

    /** Seul plan capturé automatiquement à ce jour. */
    private const ARENA_PLAN = 'arene_s2';

    /** Pointeur vers le fichier d'events de la dernière capture. */
    private const LATEST_POINTER = '_derniere_capture';

    /**
     * Rayon de vue de la capture. L'ovale de l'arène s'arrête à 11 (bornes
     * lues sur les triggers "forbidden" du plan) ; 12 lui laisse une case de
     * marge pour montrer l'extérieur des murs, soit 25x25 tuiles a 50px =
     * 1250px de cote.
     *
     * 12 est le dernier anneau du plan rempli à 100 %. À 13 il en manque six
     * (les coins), qui sortent NOIRS au rendu : le fond du plan est posé en
     * CSS sur la balise racine, propriété qu'un navigateur peint mais pas
     * rsvg-convert. Au-delà c'est pire, le remplissage tombe à 29 % dès 14.
     */
    private const DEFAULT_RANGE = 12;

    /** Centre du cadre : l'arène est construite autour de l'origine. */
    private const DEFAULT_CENTER = ['x' => 0, 'y' => 0, 'z' => 0];

    /**
     * Zone dont les actions déclenchent une capture. Calée sur le cadre : ce
     * qui est visible dans l'image mérite d'être capturé, y compris les abords
     * immédiats de l'arène où les combattants arrivent et se replient.
     *
     * Valeur de repli quand le JSON du plan ne porte pas de clé "capture" :
     * voir getCaptureZone().
     */
    private const DEFAULT_BOUNDS = ['minX' => -12, 'maxX' => 12, 'minY' => -12, 'maxY' => 12];

    /**
     * playerId passé à View pour rendre "du point de vue de personne".
     *
     * Il ne peut pas valoir null : View fait `$playerId ?? $_SESSION['playerId']`,
     * donc null retomberait sur le joueur connecté, qui serait alors marqué
     * current-player. La surbrillance sauterait d'un combattant à l'autre au fil
     * des images. 0 n'est ni null (pas de repli) ni > 0 (visibilité PNJ, tout le
     * monde est rendu) et ne correspond à aucun joueur.
     */
    private const VIEW_AS_NOBODY = 0;

    /**
     * Generate a screenshot at specific coordinates
     * 
     * @param array $coords Coordinates array with x, y, z, plan
     * @param int $range View range for the screenshot
     * @param string|null $filename Custom filename (without extension)
     * @param string|null $outputDir Custom output directory
     * @param int|null $playerId Custom player ID for screenshot
     * @param bool $selfContained Embarque styles et images dans le SVG.
     *        Nécessaire dès que la capture est consommée hors de la page du
     *        jeu, en particulier via une balise <img> : le SVG y tourne en mode
     *        statique sécurisé, où AUCUNE ressource externe n'est chargée,
     *        relative ou absolue. Coûteux, donc réservé aux captures manuelles
     *        d'administration ; les captures automatiques restent légères et
     *        passent par scripts/tools/export_arene.php.
     * @return array Result array with success status, filename, filepath, and error message
     */
    public function generateScreenshot(
        array $coords,
        int $range = self::DEFAULT_RANGE,
        ?string $filename = null,
        ?string $outputDir = null,
        ?int $playerId = null,
        bool $selfContained = false
    ): array {
        $startTime = microtime(true);
        
        $result = [
            'success' => false,
            'filename' => null,
            'filepath' => null,
            'error' => null
        ];

        try {
            $screenshotPlayerId = $playerId ?? self::DEFAULT_SCREENSHOT_PLAYER_ID;
            $screenshotPlayer = new Player($screenshotPlayerId);
            
            $validation = $this->validateScreenshotPlayer($screenshotPlayer);
            if (!$validation['valid']) {
                $result['error'] = $validation['error'];
                return $result;
            }

            // Le PNJ de capture N'EST PAS déplacé. View reçoit son cadrage en
            // argument, la position du PNJ n'entre nulle part dans le rendu :
            // l'ancien aller-retour vers (0,0) ne servait qu'à le faire entrer
            // dans le champ, ce que removeScreenshotPlayerFromSvg devait ensuite
            // défaire. Le supprimer élimine la course entre deux actions
            // simultanées sur ce PNJ partagé, ses deux lignes de log parasites
            // par capture (Log::put redirige les incognito vers "birdland") et
            // le risque de le laisser échoué au milieu de l'arène sur erreur.
            $coordsObject = (object)[
                'x' => $coords['x'],
                'y' => $coords['y'],
                'z' => $coords['z'],
                'plan' => $coords['plan']
            ];

            $svgData = $this->generateSvgData($screenshotPlayer, $coordsObject, $range);
            
            if (!$svgData) {
                $result['error'] = 'Failed to generate SVG data';
                return $result;
            }

            if ($selfContained) {
                $svgData = (new ScreenshotExportService($_SERVER['DOCUMENT_ROOT'] ?? '.'))
                    ->autonomiser($svgData);
            }

            $saveResult = $this->saveScreenshotToFile($svgData, $filename, $outputDir);
            if (!$saveResult['success']) {
                $result['error'] = $saveResult['error'];
                return $result;
            }

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            

            $result['success'] = true;
            $result['filename'] = $saveResult['filename'];
            $result['filepath'] = $saveResult['filepath'];
            $result['generation_time_ms'] = $duration;

        } catch (Exception $e) {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            error_log("Screenshot generation failed after {$duration}ms: " . $e->getMessage());

            $result['error'] = 'Screenshot generation failed: ' . $e->getMessage();
            $result['generation_time_ms'] = $duration;
        }

        return $result;
    }

    /**
     * Generate automatic screenshot for actions on arene_s2
     *
     * @param Player $actor The player who performed the action
     * @param string $actionName Name of the action performed
     * @param array<int, array<string, mixed>> $events Events to record alongside the frame
     * @return array Result array
     */
    public function generateAutomaticScreenshot(Player $actor, string $actionName, array $events = []): array
    {
        // Internal upgrade to entity for read-only lookups (Phase 4.3c).
        // Callers still pass legacy Player (ActorInterface), but the
        // read paths inside this method use the entity layer.
        $coords = $this->locateInsideArena($actor);
        if ($coords === null) {
            return ['success' => false, 'error' => 'Action not inside the arena'];
        }

        $zone = $this->getCaptureZone(self::ARENA_PLAN);

        $microtime = microtime(true);
        $timestamp = date('Y-m-d_H-i-s', (int)$microtime) . '_' . sprintf('%03d', ($microtime - floor($microtime)) * 1000);

        // L'acteur et l'action entrent dans le nom : trier par nom revient à
        // trier par temps, et la séquence reste lisible sans son manifeste.
        $filename = sprintf(
            'auto_screenshot_%s_%s_%s_%s',
            self::ARENA_PLAN,
            $timestamp,
            $this->slugify((string) $actor->id),
            $this->slugify($actionName)
        );

        $outputDir = $this->getOutputDir();

        $result = $this->generateScreenshot($zone['center'], $zone['range'], $filename, $outputDir);

        if ($result['success']) {
            // Une image dont le fichier d'events manque est un trou muet dans
            // la timeline : elle sera montée sans sa réplique, et les messages
            // du jour suivants se rattacheront à une capture antérieure. On ne
            // peut donc pas annoncer un succès plein sur la seule foi du SVG.
            $result['events_written'] = $this->writeEventFile($result['filename'], $outputDir, [
                'capture'   => $result['filename'],
                'at'        => date('c', (int)$microtime),
                'at_ms'     => (int) round($microtime * 1000),
                'plan'      => self::ARENA_PLAN,
                'actor'     => ['id' => (int) $actor->id, 'x' => $coords->x, 'y' => $coords->y],
                'action'    => $actionName,
                'events'    => $events,
            ]);

            if (!$result['events_written']) {
                // success repasse à false, conformément à ce qui précède : une
                // frame sans son fichier d'events est muette au montage, et le
                // pointeur reste sur la capture d'AVANT, donc les messages du
                // jour suivants se colleront à une image périmée. Les clés
                // filename/filepath restent renseignées : le SVG, lui, existe.
                $result['success'] = false;
                $result['error'] = 'Capture ecrite mais fichier d\'events non ecrit';
                error_log("Screenshot {$result['filename']} : fichier d'events non ecrit dans {$outputDir}");
            }
        }

        return $result;
    }

    /**
     * Cadrage et zone de déclenchement du plan.
     *
     * Lus dans le JSON du plan sous la clé "capture" quand elle existe, pour
     * qu'un déplacement de l'arène se règle en donnée plutôt qu'en code. Les
     * constantes servent de repli. Ne PAS se rabattre sur les
     * visibleBoundsMinX/MaxX des z_levels : ils décrivent l'étendue du plan
     * entier (-33..33), pas la zone de combat.
     *
     * @return array{center: array{x: int, y: int, z: int, plan: string}, range: int, bounds: array{minX: int, maxX: int, minY: int, maxY: int}}
     */
    private function getCaptureZone(string $plan): array
    {
        $center = self::DEFAULT_CENTER + ['plan' => $plan];
        $range  = self::DEFAULT_RANGE;
        $bounds = self::DEFAULT_BOUNDS;

        // Json::decode renvoie false si le fichier manque ou n'est pas du JSON.
        // Pas de garde is_object() : ?? applique la sémantique d'isset(), donc
        // false->capture et null->capture valent null sans warning ni erreur.
        $planJson = json()->decode('plans', $plan);
        $capture  = $planJson->capture ?? null;

        if (is_object($capture)) {
            foreach (['x', 'y', 'z'] as $axis) {
                if (isset($capture->center->$axis)) {
                    $center[$axis] = (int) $capture->center->$axis;
                }
            }
            if (isset($capture->range)) {
                $range = (int) $capture->range;
            }
            foreach (array_keys($bounds) as $bound) {
                if (isset($capture->bounds->$bound)) {
                    $bounds[$bound] = (int) $capture->bounds->$bound;
                }
            }
        }

        return ['center' => $center, 'range' => $range, 'bounds' => $bounds];
    }

    /**
     * Coordonnées de l'acteur s'il se tient dans la zone de capture, sinon null.
     *
     * Lecture en couche entité (Phase 4.3c) : les appelants passent encore un
     * Player legacy.
     */
    private function locateInsideArena(Player $actor): ?object
    {
        $actorEntity = PlayerFactory::entity((int) $actor->id);
        if ($actorEntity === null) {
            return null;
        }

        $conn   = EntityManagerFactory::getEntityManager()->getConnection();
        $coords = $actorEntity->getCoords($conn);

        if ($coords === null || $coords->plan !== self::ARENA_PLAN) {
            return null;
        }

        $bounds = $this->getCaptureZone(self::ARENA_PLAN)['bounds'];

        if ($coords->x < $bounds['minX'] || $coords->x > $bounds['maxX']
            || $coords->y < $bounds['minY'] || $coords->y > $bounds['maxY']) {
            return null;
        }

        return $coords;
    }

    /**
     * Rattache un event à la capture la plus récente.
     *
     * Un changement de message du jour ne modifie aucun pixel de la carte :
     * générer une image pour lui produirait un doublon de la précédente. On
     * l'ajoute donc au fichier d'events de la dernière capture, ce qui est
     * exact puisque l'état visuel n'a pas bougé depuis. Au montage il devient
     * une bulle posée sur cette image.
     *
     * @param array<string, mixed> $event
     * @param Player|null $actor Quand il est fourni, l'event est ignoré si
     *                           l'acteur n'est pas dans l'arène : un message du
     *                           jour changé ailleurs n'a rien à y faire.
     */
    public function attachEventToLastCapture(array $event, ?Player $actor = null): bool
    {
        if ($actor !== null && $this->locateInsideArena($actor) === null) {
            return false;
        }

        $outputDir = $this->getOutputDir();
        $pointer   = $outputDir . self::LATEST_POINTER;

        if (!is_readable($pointer)) {
            return false;
        }

        $eventFile = $outputDir . trim((string) file_get_contents($pointer));
        if (!is_readable($eventFile)) {
            return false;
        }

        // Lecture ET écriture sous le même verrou. Le fichier est partagé :
        // tous les events orphelins de la même frame s'y ajoutent, et deux
        // messages du jour changés dans la même seconde visent donc le même.
        // Sans verrou, le second relit le tableau avant que le premier ne l'ait
        // réécrit et efface sa bulle en la recouvrant.
        //
        // 'r+' et non 'c+' : le fichier doit exister, il ne s'agit pas d'en
        // créer un vide sur la foi d'un pointeur périmé.
        $handle = fopen($eventFile, 'r+');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            $payload = json_decode((string) stream_get_contents($handle), true);
            if (!is_array($payload)) {
                return false;
            }

            $payload['events'][] = $event;

            rewind($handle);
            ftruncate($handle, 0);

            $written = fwrite(
                $handle,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            fflush($handle);

            return $written !== false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function getOutputDir(): string
    {
        return ($_SERVER['DOCUMENT_ROOT'] ?? '.') . '/img/arene/';
    }

    /**
     * Écrit le fichier d'events de la capture, plus le pointeur vers lui.
     *
     * Le pointeur évite de lister le répertoire à chaque event orphelin, et
     * reste anodin pour un rsync.
     *
     * @param array<string, mixed> $payload
     * @return bool Faux si le fichier d'events n'a pas pu être écrit.
     */
    private function writeEventFile(string $captureFilename, string $outputDir, array $payload): bool
    {
        $eventFilename = preg_replace('/\.svg$/', '', $captureFilename) . '.json';

        // LOCK_EX : un message du jour peut viser ce fichier dès que le
        // pointeur bascule, et attachEventToLastCapture pose le même verrou.
        $written = file_put_contents(
            $outputDir . $eventFilename,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        if ($written === false) {
            return false;
        }

        $this->updateLatestPointer($outputDir, $eventFilename);

        return true;
    }

    /**
     * Fait pointer LATEST_POINTER sur la capture la plus récente.
     *
     * Deux précautions, parce que plusieurs joueurs agissent en même temps
     * dans l'arène :
     *
     * 1. Monotone. Les noms portent l'horodatage, donc l'ordre lexicographique
     *    est l'ordre chronologique : une requête plus lente qui termine après
     *    une plus rapide ne doit pas faire RECULER le pointeur vers son image,
     *    sans quoi le message du jour suivant se collerait à une frame périmée.
     * 2. Atomique. L'écriture passe par un fichier temporaire puis un rename,
     *    pour qu'un lecteur ne tombe jamais sur un nom tronqué.
     */
    private function updateLatestPointer(string $outputDir, string $eventFilename): void
    {
        $pointeur = $outputDir . self::LATEST_POINTER;

        if (is_readable($pointeur)) {
            $actuel = trim((string) file_get_contents($pointeur));
            if ($actuel !== '' && strcmp($eventFilename, $actuel) < 0) {
                return;
            }
        }

        $temporaire = $pointeur . '.' . getmypid();

        if (file_put_contents($temporaire, $eventFilename) === false) {
            return;
        }

        if (!rename($temporaire, $pointeur)) {
            @unlink($temporaire);
        }
    }

    private function slugify(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '-', $value) ?? 'inconnu';
    }

    /**
     * Validate that the player is suitable for screenshots
     */
    private function validateScreenshotPlayer(Player $player): array
    {
        if ($player->id >= 0) {
            return [
                'valid' => false,
                'error' => 'Screenshot player must be a PNJ (negative ID)'
            ];
        }

        if (!$player->have_option('incognitoMode')) {
            return [
                'valid' => false,
                'error' => 'Screenshot player must have incognito mode enabled'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Generate SVG data using View class
     */
    private function generateSvgData(Player $player, object $coords, int $range): ?string
    {
        $playerOptions = $player->get_options();
        $caracsJson = json()->decode('players', $player->id .'.caracs');
        if (!$caracsJson) {
            $player->get_caracs();
        }

        $view = new View($coords, $range, false, $playerOptions, self::VIEW_AS_NOBODY);
        $data = $view->get_view();

        if (strpos($data, '<svg') !== false) {
            $svgStart = strpos($data, '<svg');
            $svgEnd = strrpos($data, '</svg>') + 6;
            $data = substr($data, $svgStart, $svgEnd - $svgStart);
        }

        // Correction d'un chemin d'asset erroné côté View. Elle vivait dans la
        // branche HTTP_HOST alors qu'elle n'a rien à voir avec l'hôte : elle
        // sautait donc en CLI.
        $data = str_replace('img/tiles/route.png', 'img/routes/route.png', $data);

        // Les chemins restent RELATIFS. Les rendre absolus avec HTTP_HOST
        // soudait chaque capture à l'hôte qui l'avait produite, obligeant à
        // réécrire toutes les URL avant montage. L'autonomie du fichier est
        // obtenue ailleurs, par ScreenshotExportService : au coup par coup pour
        // les captures d'administration, en masse à l'export pour les captures
        // d'arène. Dans les deux cas hors de la requête du joueur.
        $data = $this->removeScreenshotPlayerFromSvg($data, $player);

        return $data ?: null;
    }

    

    

    /**
     * Remove the screenshot PNJ from the SVG output
     * Post-processes the SVG to hide the player taking the screenshot
     */
    private function removeScreenshotPlayerFromSvg(string $svgData, Player $player): string
    {
        $id = preg_quote((string) $player->id, '/');

        // View rend DEUX éléments par personnage : l'avatar id="playersX" et
        // son ombre id="playersX-shadow". Les motifs d'origine exigeaient le
        // guillemet juste après l'id, donc aucun ne visait l'ombre : elle
        // survivait à chaque capture, et comme le PNJ était alors téléporté au
        // centre, c'était une ombre orpheline au milieu de l'arène. Le suffixe
        // optionnel la couvre.
        //
        // Un seul motif suffit : les [^>]* de part et d'autre acceptent
        // n'importe quel ordre d'attributs, ce que les cinq motifs précédents
        // énuméraient à la main sans rien couvrir de plus.
        $avatarPattern = '/<image[^>]*\bid="players' . $id . '(?:-shadow)?"[^>]*>/i';

        // Position de l'avatar, pour retirer aussi la case qu'il surligne.
        $pnjX = null;
        $pnjY = null;
        if (preg_match('/<image[^>]*\bid="players' . $id . '"[^>]*x="(\d+)"[^>]*y="(\d+)"[^>]*>/i', $svgData, $matches)) {
            $pnjX = $matches[1];
            $pnjY = $matches[2];
        }

        $svgData = preg_replace($avatarPattern, '', $svgData);

        if ($pnjX !== null && $pnjY !== null) {
            $svgData = preg_replace(
                '/<rect[^>]*class="case"[^>]*x="' . preg_quote($pnjX, '/') . '"[^>]*y="' . preg_quote($pnjY, '/') . '"[^>]*>/i',
                '',
                $svgData
            );
        }

        return $svgData;
    }

    

    /**
     * Save screenshot data to file
     */
    private function saveScreenshotToFile(string $svgData, ?string $filename = null, ?string $outputDir = null): array
    {
        if (!$filename) {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "screenshot_{$timestamp}";
        }

        if (!str_ends_with($filename, '.svg')) {
            $filename .= '.svg';
        }

        if (!$outputDir) {
            $outputDir = $_SERVER['DOCUMENT_ROOT'] . '/img/screenshots/';
        }

        $filepath = $outputDir . $filename;

        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create screenshots directory'
                ];
            }
        }

        if (file_put_contents($filepath, $svgData) === false) {
            return [
                'success' => false,
                'error' => 'Failed to save screenshot file'
            ];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath
        ];
    }
}
