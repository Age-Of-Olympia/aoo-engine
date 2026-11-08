<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Service\Map\CharacterMoveService;
use App\Simulation\SimulationGuard;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlanFixtureTrait;
use Tests\Support\SeedsCharactersTrait;

class CharacterMoveServiceTest extends TestCase
{
    use PlanFixtureTrait;
    use SeedsCharactersTrait;

    private const PLAN = 'test_character_move';

    private Connection $db;

    protected function setUp(): void
    {
        $this->db = EntityManagerFactory::getEntityManager()->getConnection();
        $this->purgePlan($this->db, self::PLAN);
    }

    protected function tearDown(): void
    {
        $this->removeSeededCharacters($this->db);
        $this->purgePlan($this->db, self::PLAN);
    }

    public function testAMoveWritesTheCellAndTheFootprintAndSaysWhereFrom(): void
    {
        $start = $this->coordsIdOn(self::PLAN, 0, 0);
        $character = $this->seedCharacter($this->db, columns: ['coords_id' => $start]);
        $reached = $this->coordsIdOn(self::PLAN, 3, 4);

        $moved = (new CharacterMoveService($this->db))->move($character, $reached);

        $this->assertSame(['from' => $start, 'to' => $reached], $moved);
        $this->assertSame($reached, (int) $this->db->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$character]));
        $this->assertSame(
            [[3, 4]],
            array_map(
                static fn (array $row): array => [(int) $row['x'], (int) $row['y']],
                $this->db->fetchAllAssociative('SELECT x, y FROM entity_cells WHERE player_id = ?', [$character])
            ),
            'the footprint follows the character'
        );
    }

    public function testACharacterOffTheBoardComesFromNowhere(): void
    {
        $character = $this->seedCharacter($this->db, columns: ['coords_id' => $this->coordsIdOn(self::PLAN, 0, 0)]);
        $this->db->executeStatement('UPDATE players SET coords_id = NULL WHERE id = ?', [$character]);
        $reached = $this->coordsIdOn(self::PLAN, 1, 1);

        $this->assertNull((new CharacterMoveService($this->db))->move($character, $reached)['from']);
    }

    public function testAPreviewMovesNobody(): void
    {
        $start = $this->coordsIdOn(self::PLAN, 0, 0);
        $character = $this->seedCharacter($this->db, columns: ['coords_id' => $start]);
        $reached = $this->coordsIdOn(self::PLAN, 2, 2);

        $moved = SimulationGuard::run(fn (): array => (new CharacterMoveService($this->db))->move($character, $reached));

        $this->assertSame(['from' => $start, 'to' => $reached], $moved, 'the preview still says where the move would go');
        $this->assertSame($start, (int) $this->db->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$character]));
    }
}
