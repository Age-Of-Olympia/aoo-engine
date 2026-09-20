<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
require '/var/www/html/vendor/autoload.php';
require '/var/www/html/config/db_constants.php';
require '/var/www/html/config/bootstrap.php';
require '/var/www/html/config/constants.php';
$c = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
foreach ($c->fetchFirstColumn("SELECT id FROM players WHERE name LIKE 'E2e%'") as $id) {
    // Every table with a foreign key on players, both columns where there are two.
    foreach ($c->fetchAllAssociative("SELECT TABLE_NAME t, COLUMN_NAME col FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = 'players' AND CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME <> 'players'") as $fk) {
        try { $c->executeStatement("DELETE FROM `{$fk['t']}` WHERE `{$fk['col']}` = ?", [$id]); } catch (\Throwable $e) {}
    }
    $c->executeStatement('DELETE FROM players_logs WHERE target_id = ?', [$id]);
    $c->executeStatement('DELETE FROM tutorial_progress WHERE player_id = ?', [$id]);
    $c->executeStatement('DELETE FROM players WHERE id = ?', [$id]);
}
foreach ($c->fetchFirstColumn("SELECT id FROM actions WHERE name LIKE 'zz_%'") as $id) {
    (new \App\Service\Action\ActionDeleteService())->delete((int) $id);
}
$c->executeStatement("DELETE FROM map_elements WHERE coords_id = 108004");
$c->executeStatement("DELETE FROM map_items WHERE coords_id IN (108004, 108005, 108006)");
$c->executeStatement("UPDATE effects e JOIN _e2e_effects b ON b.id = e.id SET e.carac_mods = b.carac_mods, e.loss_mods = b.loss_mods, e.apply_text = b.apply_text");
$c->executeStatement("DELETE FROM item_effects WHERE item_id = 36");
$c->executeStatement("INSERT INTO item_effects (item_id, effect, duration, outcome, target) SELECT item_id, effect, duration, outcome, target FROM _e2e_item_effects");
$c->executeStatement("DROP TABLE _e2e_effects"); $c->executeStatement("DROP TABLE _e2e_item_effects");
echo "clean\n";
