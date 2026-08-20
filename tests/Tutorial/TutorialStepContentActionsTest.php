<?php

namespace Tests\Tutorial;

use App\Factory\EntityManagerFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Le CONTENU des étapes ne référence que des actions du catalogue.
 *
 * BasicActionsExistTest relie déjà les actions ACCORDÉES au catalogue.
 * Restait l'autre moitié : les étapes elles-mêmes nomment des actions —
 * dans leur validation (`action_name`) et dans leurs sélecteurs
 * (`.action[data-action="…"]`). Quand « attaquer » est devenu « melee »,
 * aucun test ne l'a dit : l'étape de combat visait un bouton absent et
 * exigeait une action refusée, et le tutoriel devenait infinissable au
 * pas 28 sur 30.
 *
 * Se saute quand le contenu n'est pas semé (base neuve sans catalogue
 * tutoriel) — en CI la suite tourne sur la base semée, où il tranche.
 */
#[Group('tutorial')]
class TutorialStepContentActionsTest extends TestCase
{
    private \Doctrine\DBAL\Connection $conn;

    protected function setUp(): void
    {
        $this->conn = EntityManagerFactory::getEntityManager()->getConnection();

        try {
            $steps = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM tutorial_steps WHERE is_active = 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('tutorial_steps unreachable: ' . $e->getMessage());
        }

        if ($steps === 0) {
            $this->markTestSkipped('tutorial content not seeded in this database');
        }
    }

    public function testEveryValidationActionNameIsInTheCatalog(): void
    {
        // Diff en PHP : les tables du tutoriel et le catalogue ne portent
        // pas la même collation, un '=' SQL entre les deux lève.
        $named = $this->conn->fetchFirstColumn("
            SELECT DISTINCT v.action_name
            FROM tutorial_step_validation v
            JOIN tutorial_steps s ON s.id = v.step_id
            WHERE s.is_active = 1
              AND v.action_name IS NOT NULL AND v.action_name <> ''
        ");

        $known = array_flip($this->conn->fetchFirstColumn('SELECT name FROM actions'));
        $missing = array_values(array_filter($named, static fn(string $n): bool => !isset($known[$n])));

        $this->assertSame(
            [],
            $missing,
            'étapes exigeant une action absente du catalogue : ' . implode(', ', $missing)
        );
    }

    public function testEveryDataActionSelectorTargetsACatalogAction(): void
    {
        // Chaque colonne où le contenu écrit un sélecteur : la cible de
        // l'étape, les clics autorisés, ET la validation (élément attendu
        // visible/cliqué) — celle-là avait échappé au premier renommage.
        $selectors = array_merge(
            $this->conn->fetchFirstColumn("
                SELECT u.target_selector FROM tutorial_step_ui u
                JOIN tutorial_steps s ON s.id = u.step_id
                WHERE s.is_active = 1 AND u.target_selector LIKE '%data-action%'
            "),
            $this->conn->fetchFirstColumn("
                SELECT i.selector FROM tutorial_step_interactions i
                JOIN tutorial_steps s ON s.id = i.step_id
                WHERE s.is_active = 1 AND i.selector LIKE '%data-action%'
            "),
            $this->conn->fetchFirstColumn("
                SELECT v.element_selector FROM tutorial_step_validation v
                JOIN tutorial_steps s ON s.id = v.step_id
                WHERE s.is_active = 1 AND v.element_selector LIKE '%data-action%'
            "),
            $this->conn->fetchFirstColumn("
                SELECT v.element_clicked FROM tutorial_step_validation v
                JOIN tutorial_steps s ON s.id = v.step_id
                WHERE s.is_active = 1 AND v.element_clicked LIKE '%data-action%'
            ")
        );

        $named = [];
        foreach ($selectors as $selector) {
            if (preg_match_all('/data-action="([^"]+)"/', (string) $selector, $m)) {
                foreach ($m[1] as $name) {
                    $named[$name] = true;
                }
            }
        }

        $known = array_flip($this->conn->fetchFirstColumn('SELECT name FROM actions'));
        $missing = array_keys(array_diff_key($named, $known));

        $this->assertSame(
            [],
            $missing,
            'sélecteurs d\'étapes visant un bouton d\'action inexistant : ' . implode(', ', $missing)
        );
    }
}
