<?php
namespace Tests\Logs\Mock;

class JsonMock
{
    private static array $mockData = [];

    public static function setMockData(string $table, string $key, $data): void
    {
        self::$mockData[$table][$key] = $data;
    }

    public static function reset(): void
    {
        self::$mockData = [];
    }

    public function decode(string $table, string $key): ?object
    {
        // Données mockées spécifiques
        if (isset(self::$mockData[$table][$key])) {
            return self::$mockData[$table][$key];
        }

        // Données par défaut pour les tests
        switch ($table) {
            case 'players':
                if (strpos($key, '.caracs') !== false) {
                    return (object) ['p' => 3]; // Perception par défaut
                }
                break;
            case 'plans':
                return (object) ['player_visibility' => true];
            case 'races':
                return (object) ['plan' => 'test_plan'];
        }
        return null;
    }
}