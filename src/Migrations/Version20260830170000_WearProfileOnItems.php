<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Combat wear stops being opt-in, and says what each object IS.
 *
 * Until now an object wore only if its catalogue row listed the event
 * (`wear_triggers`), so the engine shipped inert and nothing wore at all.
 * The rules decided with the team make attack, being hit and dying wear
 * equipment BY DEFAULT — with a per-object way out, because a divine
 * artefact should not dull.
 *
 * `wear_profile` carries both:
 *
 *   ''            the object is classified by its slot, which is right for
 *                 everything in the catalogue today — no admin pass needed;
 *   'weapon'      wears when its bearer strikes, spared when they are hit;
 *   'protection'  takes its share of the blows received;
 *   'neutral'     neither — it only wears when its bearer dies;
 *   'none'        never wears from combat, whatever it is worn in.
 *
 * The explicit values exist because the slot cannot always tell: `main2`
 * holds a shield today, and will hold an off-hand weapon when two-handed
 * fighting lands. The one that would then be misread names itself.
 *
 * `neutral` is not a nicety: a ring is spared by blows but not by death,
 * so it is neither a protection nor exempt, and the two rules would
 * contradict each other without a third answer.
 *
 * `attack` and `defense` leave `wear_triggers`: those events now belong to
 * the immediate rules. `wear_rate` survives and changes meaning — it is no
 * longer "per turn" but HOW MUCH this object loses per wear event, so the
 * gladius keeps costing 3 a swing where a plain sword costs 1. The turn
 * engine keeps `move` and `usage` for boots and tools.
 *
 * That new meaning has no use for zero. Zero USED to say "never wears",
 * which the profile now says properly; left in place it would read as
 * "loses nothing", and a field where 0 and 1 mean the same thing is a trap
 * for whoever sets it next. So the floor becomes 1, in the data and in the
 * column, and the code stops guarding against a value that can no longer
 * arrive.
 */
final class Version20260830170000_WearProfileOnItems extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ajoute items.wear_profile (arme / protection / ne s\'use pas) et sort attack+defense de wear_triggers : ces événements passent aux règles immédiates';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('items', 'wear_profile')) {
            $this->addSql("ALTER TABLE items ADD wear_profile VARCHAR(16) NOT NULL DEFAULT ''");
        }

        /* The two retired triggers leave the CSV; wear_rate stays, now read
         * as the per-event amount. TRIM the separators an inner removal
         * leaves behind, so 'move,attack,usage' does not become
         * 'move,,usage'. */
        /* 1 is the new floor: a fragility multiplier of zero is not a
         * quantity, it is the old "never wears" in a number's clothes.
         * Backfill BEFORE the default changes, so existing rows and new
         * ones agree. */
        $this->addSql('UPDATE items SET wear_rate = 1 WHERE wear_rate < 1');
        $this->addSql('ALTER TABLE items MODIFY wear_rate INT NOT NULL DEFAULT 1');

        $this->addSql(
            "UPDATE items
                SET wear_triggers = TRIM(BOTH ',' FROM
                    REPLACE(REPLACE(REPLACE(CONCAT(',', wear_triggers, ','),
                        ',attack,', ','), ',defense,', ','), ',,', ','))
              WHERE FIND_IN_SET('attack', wear_triggers)
                 OR FIND_IN_SET('defense', wear_triggers)"
        );
    }

    /**
     * The column goes; the two triggers do not come back. Which objects
     * carried them is not recoverable from the rows that remain, and the
     * events they named are now the rules' business — restoring them would
     * hand back a double charge, not the previous state.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS wear_profile');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
