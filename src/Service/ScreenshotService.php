<?php

namespace App\Service;

use Classes\Player;
use Classes\View;
use Exception;

/**
 * Service for generating screenshots - only for arene_s2 to begin with
 */
class ScreenshotService
{
    private const DEFAULT_SCREENSHOT_PLAYER_ID = -92;
    private const DEFAULT_RANGE = 10;
    private const DEFAULT_RESTORE_POSITION = ['x' => 0, 'y' => 8, 'z' => 0, 'plan' => 'arene_s2'];

    /**
     * Generate a screenshot at specific coordinates
     * 
     * @param array $coords Coordinates array with x, y, z, plan
     * @param int $range View range for the screenshot
     * @param string|null $filename Custom filename (without extension)
     * @param string|null $outputDir Custom output directory
     * @param int|null $playerId Custom player ID for screenshot
     * @return array Result array with success status, filename, filepath, and error message
     */
    public function generateScreenshot(
        array $coords, 
        int $range = self::DEFAULT_RANGE, 
        ?string $filename = null, 
        ?string $outputDir = null,
        ?int $playerId = null
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

            $screenshotPlayer->move_player((object)['x' => 0, 'y' => 0, 'z' => 0, 'plan' => $coords['plan']]);

            $coordsObject = (object)[
                'x' => $coords['x'],
                'y' => $coords['y'],
                'z' => $coords['z'],
                'plan' => $coords['plan']
            ];

            $svgData = $this->generateSvgData($screenshotPlayer, $coordsObject, $range);
            
            if (!$svgData) {
                $result['error'] = 'Failed to generate SVG data';
                $this->restorePlayerPosition($screenshotPlayer);
                return $result;
            }

            $saveResult = $this->saveScreenshotToFile($svgData, $filename, $outputDir);
            if (!$saveResult['success']) {
                $result['error'] = $saveResult['error'];
                $this->restorePlayerPosition($screenshotPlayer);
                return $result;
            }

            $this->restorePlayerPosition($screenshotPlayer);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            error_log("Screenshot generation completed in {$duration}ms" . ($filename ? " (saved as: {$filename})" : " (preview mode)"));

            $result['success'] = true;
            $result['filename'] = $saveResult['filename'];
            $result['filepath'] = $saveResult['filepath'];
            $result['generation_time_ms'] = $duration;

        } catch (Exception $e) {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            error_log("Screenshot generation failed after {$duration}ms: " . $e->getMessage());
            
            if (isset($screenshotPlayer)) {
                $this->restorePlayerPosition($screenshotPlayer);
            }
            
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
     * @return array Result array
     */
    public function generateAutomaticScreenshot(Player $actor, string $actionName, ?array $coordsMin = array('x' => -7,'y' => -7,'z' => 0,'plan' => 'arene_s2'), ?array $coordsMax = array('x' => 7,'y' => 7,'z' => 0,'plan' => 'arene_s2')): array
    {
        $coords = $actor->getCoords();
        if ($coords->plan !== 'arene_s2') {
            return ['success' => false, 'error' => 'Action not on arene_s2 map'];
        }
        if ($coords->x < $coordsMin['x'] || $coords->x > $coordsMax['x'] || $coords->y < $coordsMin['y'] || $coords->y > $coordsMax['y'] ) {
            return ['success' => false, 'error' => 'Action not inside the arena'];
        }
        $microtime = microtime(true);
        $timestamp = date('Y-m-d_H-i-s', (int)$microtime) . '_' . sprintf('%03d', ($microtime - floor($microtime)) * 1000);
        $filename = "auto_screenshot_arene_s2_{$timestamp}";
        
        $coordsArray = ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'arene_s2'];
        
        $outputDir = $_SERVER['DOCUMENT_ROOT'] . '/img/arene/';
        
        $result = $this->generateScreenshot($coordsArray, self::DEFAULT_RANGE, $filename, $outputDir);
        
        if ($result['success']) {
            error_log("Automatic screenshot saved: {$result['filename']} for action {$actionName} by player {$actor->data->name}");
        }
        
        return $result;
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

        if (!$player->have('options', 'incognitoMode')) {
            return [
                'valid' => false,
                'error' => 'Screenshot player must have incognito mode enabled'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Save current player position for later restoration
     */
    private function savePlayerPosition(Player $player): array
    {
        $coords = $player->getCoords();
        return [
            'x' => $coords->x,
            'y' => $coords->y,
            'z' => $coords->z,
            'plan' => $coords->plan
        ];
    }

    /**
     * Restore player to default position
     */
    private function restorePlayerPosition(Player $player): void
    {
        $restorePosition = (object) self::DEFAULT_RESTORE_POSITION;
        $player->move_player($restorePosition);
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

        $view = new View($coords, $range, false, $playerOptions);
        $data = $view->get_view();

        if (strpos($data, '<svg') !== false) {
            $svgStart = strpos($data, '<svg');
            $svgEnd = strrpos($data, '</svg>') + 6;
            $data = substr($data, $svgStart, $svgEnd - $svgStart);
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/';
            $data = str_replace('img/tiles/route.png', 'img/routes/route.png', $data);
            $data = str_replace('img/', $baseUrl . 'img/', $data);
        }

        $data = $this->convertImagesToBase64($data);
        $data = $this->removeScreenshotPlayerFromSvg($data, $player);

        return $data ?: null;
    }

    /**
     * Convert all external images in SVG to base64 data URIs
     * Handles both regular images and background images
     */
    private function convertImagesToBase64(string $svgData, int $zValue = 0): string
    {
        if (preg_match('/<svg[^>]*style="[^"]*background:\s*url\(\'([^\']+)\'\)/i', $svgData, $bgMatches)) {
            $bgUrl = $bgMatches[1];
            $bgPath = $this->resolveImagePath($bgUrl);
            $bgBase64 = $this->imageToBase64($bgPath);
            
            if ($bgBase64) {
                $svgData = str_replace(
                    $bgUrl, 
                    $bgBase64, 
                    $svgData
                );
            }
        }

        $pattern = '/<image[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $fullImageTag = $matches[0];
            $imageUrl = $matches[1];
            
            if (empty($imageUrl) || strpos($imageUrl, 'data:') === 0) {
                return $fullImageTag;
            }
            
            $imagePath = $this->resolveImagePath($imageUrl);
            $base64Data = $this->imageToBase64($imagePath);
            
            if ($base64Data) {
                return str_replace($imageUrl, $base64Data, $fullImageTag);
            }
            
            return $fullImageTag;
        }, $svgData);
    }

    /**
     * Convert image to base64 data URL
     */
    private function imageToBase64(string $imagePath): ?string
    {
        if (!file_exists($imagePath)) {
            error_log("Image not found: $imagePath");
            return null;
        }

        $mimeType = mime_content_type($imagePath);
        if (!$mimeType) {
            $mimeType = 'image/png';
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            error_log("Failed to read image: $imagePath");
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }

    /**
     * Remove the screenshot PNJ from the SVG output
     * Post-processes the SVG to hide the player taking the screenshot
     */
    private function removeScreenshotPlayerFromSvg(string $svgData, Player $player): string
    {
        $playerId = $player->id;
        $coordsPattern = '/<image[^>]*id="players' . preg_quote($playerId, '/') . '"[^>]*x="(\d+)"[^>]*y="(\d+)"[^>]*>/i';
        $pnjX = null;
        $pnjY = null;
        
        if (preg_match($coordsPattern, $svgData, $matches)) {
            $pnjX = $matches[1];
            $pnjY = $matches[2];
        }
        
        $patterns = [
            '/<image[^>]*id="players' . preg_quote($playerId, '/') . '"[^>]*data-table="players"[^>]*>/i',
            '/<image[^>]*id="players' . preg_quote($playerId, '/') . '"[^>]*class="avatar-shadow"[^>]*>/i',
            
            '/<image[^>]*data-table="players"[^>]*id="players' . preg_quote($playerId, '/') . '"[^>]*>/i',
            '/<image[^>]*class="avatar-shadow"[^>]*id="players' . preg_quote($playerId, '/') . '"[^>]*>/i',
            
            '/<image[^>]*id="players' . preg_quote($playerId, '/') . '"[^>]*>/i'
        ];
        
        if ($pnjX !== null && $pnjY !== null) {
            $patterns[] = '/<rect[^>]*class="case"[^>]*x="' . preg_quote($pnjX, '/') . '"[^>]*y="' . preg_quote($pnjY, '/') . '"[^>]*>/i';
        }
        
        $originalLength = strlen($svgData);
        
        foreach ($patterns as $pattern) {
            $svgData = preg_replace($pattern, '', $svgData);
        }
        
        $newLength = strlen($svgData);
        
        if ($originalLength !== $newLength) {
            $coordsInfo = ($pnjX !== null && $pnjY !== null) ? " at coordinates ({$pnjX},{$pnjY})" : "";
            error_log("Screenshot PNJ removal: Successfully removed PNJ elements (player ID: {$playerId}){$coordsInfo}, removed " . ($originalLength - $newLength) . " characters");
        } else {
            error_log("Screenshot PNJ removal: No PNJ elements found to remove (player ID: {$playerId})");
        }
        
        return $svgData;
    }

    /**
     * Resolve image URL to local file path
     */
    private function resolveImagePath(string $imageUrl): string
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/';
            if (strpos($imageUrl, $baseUrl) === 0) {
                $imageUrl = substr($imageUrl, strlen($baseUrl));
            }
        }
        
        if (strpos($imageUrl, '/') === 0) {
            return $_SERVER['DOCUMENT_ROOT'] . $imageUrl;
        } else {
            return $_SERVER['DOCUMENT_ROOT'] . '/' . $imageUrl;
        }
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
