<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
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

    /** Only plan with automatic captures. */
    private const ARENA_PLAN = 'arene_s2';

    /** File holding the name of the latest capture's events file. */
    private const LATEST_POINTER = '_derniere_capture';

    /**
     * View radius: 25x25 tiles at 50px. The arena oval ends at 11, 12 adds one
     * ring of wall outside. Beyond 12 the plan is not fully tiled and the missing
     * corners render black: the plan background is a CSS property on the root
     * tag, which rsvg-convert does not paint.
     */
    private const DEFAULT_RANGE = 12;

    /** Frame centre: the arena is built around the origin. */
    private const DEFAULT_CENTER = ['x' => 0, 'y' => 0, 'z' => 0];

    /**
     * Area whose actions trigger a capture, aligned with the frame so the edges
     * where fighters arrive and retreat are captured too. Fallback when the plan
     * JSON has no "capture" key, see getCaptureZone().
     */
    private const DEFAULT_BOUNDS = ['minX' => -12, 'maxX' => 12, 'minY' => -12, 'maxY' => 12];

    /**
     * playerId handed to View to render from nobody's point of view. Null would
     * fall back to $_SESSION['playerId'] and mark that player current-player;
     * 0 matches no player and skips the fallback.
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
     * @param bool $selfContained Embed styles and images in the SVG. Required
     *        when the capture is consumed outside the game page, e.g. through
     *        <img>, where no external resource is loaded. Costly: admin captures
     *        only, automatic captures go through scripts/tools/export_arene.php.
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

            // The capture PNJ is never moved: View gets its frame as an argument,
            // the PNJ position plays no part in the rendering.
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
        // Internal upgrade to entity for read-only lookups.
        // Callers still pass legacy Player (ActorInterface), but the
        // read paths inside this method use the entity layer.
        $coords = $this->locateInsideArena($actor);
        if ($coords === null) {
            return ['success' => false, 'error' => 'Action not inside the arena'];
        }

        $zone = $this->getCaptureZone(self::ARENA_PLAN);

        $microtime = microtime(true);
        $timestamp = date('Y-m-d_H-i-s', (int)$microtime) . '_' . sprintf('%03d', ($microtime - floor($microtime)) * 1000);

        // Actor and action go into the name; sorting by name sorts by time.
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
            // A frame without its events file is mute in the timeline and the
            // next mdj would attach to an older capture: success needs both.
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
                // filename/filepath stay set: the SVG itself exists.
                $result['success'] = false;
                $result['error'] = 'Capture ecrite mais fichier d\'events non ecrit';
                error_log("Screenshot {$result['filename']} : fichier d'events non ecrit dans {$outputDir}");
            }
        }

        return $result;
    }

    /**
     * Frame and trigger area of the plan: the plan JSON "capture" key when
     * present, the constants otherwise. Not the z_levels visible bounds, which
     * describe the whole plan rather than the fighting area.
     *
     * @return array{center: array{x: int, y: int, z: int, plan: string}, range: int, bounds: array{minX: int, maxX: int, minY: int, maxY: int}}
     */
    private function getCaptureZone(string $plan): array
    {
        $center = self::DEFAULT_CENTER + ['plan' => $plan];
        $range  = self::DEFAULT_RANGE;
        $bounds = self::DEFAULT_BOUNDS;

        // Json::decode returns false when the file is missing or invalid; ??
        // applies isset() semantics, so false->capture is null without a warning.
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
     * Coordinates of the actor when standing inside the capture area, else null.
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
     * Appends an event to the latest capture's events file.
     *
     * An mdj change alters no pixel, so it gets no frame of its own: it becomes
     * a bubble on the last image, whose visual state still holds.
     *
     * @param array<string, mixed> $event
     * @param Player|null $actor When given, the event is dropped unless the
     *                           actor stands inside the arena.
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

        // Read and write under one exclusive lock: every orphan event of the
        // same frame targets this file, two mdj in the same second must not
        // overwrite each other. 'r+' not 'c+': a stale pointer must not create
        // an empty file.
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
     * Writes the capture's events file and points LATEST_POINTER at it. The
     * pointer saves a directory listing per orphan event.
     *
     * @param array<string, mixed> $payload
     * @return bool False when the events file could not be written.
     */
    private function writeEventFile(string $captureFilename, string $outputDir, array $payload): bool
    {
        $eventFilename = preg_replace('/\.svg$/', '', $captureFilename) . '.json';

        // LOCK_EX: attachEventToLastCapture takes the same lock as soon as the
        // pointer moves.
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
     * Points LATEST_POINTER at the most recent capture. Monotonic: names carry
     * the timestamp, so a slower request finishing later must not move the
     * pointer backwards. Atomic: temp file then rename, a reader never sees a
     * truncated name.
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
        $player->get_caracs();

        $view = new View($coords, $range, false, $playerOptions, self::VIEW_AS_NOBODY);
        $data = $view->get_view();

        if (strpos($data, '<svg') !== false) {
            $svgStart = strpos($data, '<svg');
            $svgEnd = strrpos($data, '</svg>') + 6;
            $data = substr($data, $svgStart, $svgEnd - $svgStart);
        }

        // Asset path fix on View's output, host-independent so it runs in CLI too.
        $data = str_replace('img/tiles/route.png', 'img/routes/route.png', $data);

        // Paths stay relative: ScreenshotExportService makes a file
        // self-contained when needed (admin preview, arena export).
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

        // View renders two elements per character: the avatar id="playersX" and
        // its shadow id="playersX-shadow". Attribute order is free, hence [^>]*.
        $avatarPattern = '/<image[^>]*\bid="players' . $id . '(?:-shadow)?"[^>]*>/i';

        // Avatar position, to drop the highlighted cell too.
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
