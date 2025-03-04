<?php

class WorldMapCmd extends Command {

    public function __construct() {
        parent::__construct("worldmap",[new Argument('real',true)]);
        parent::setDescription(<<<EOT
Generates the global world map of AOO
EOT);
    }

    public function execute(array $argumentValues): string {
        try {
            $output = "Starting world map generation...\n";
            
            // Create database connection
            $db = new Db();
            
            // Generate the map
            $viewService = new App\Service\ViewService($db);
            $mapPath = $viewService->generateGlobalMap();
            
            $output .= "Map successfully generated at: $mapPath\n";
            return $output;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
