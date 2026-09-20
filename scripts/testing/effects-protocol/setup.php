<?php
/* Stage the "effects" protocol on the dev DB (a copy of experimental): two
 * characters side by side on olympia, a torch with strike effects, feu/lave
 * configured, two test actions. Prints JSON with the ids; teardown.php
 * removes everything.
 *
 * From the host:
 *   docker cp scripts/testing/effects-protocol/setup.php PHP-AOO4-Local:/tmp/e2e-setup.php
 *   docker exec PHP-AOO4-Local php /tmp/e2e-setup.php      # → {"a":..,"b":..,"feuAction":..}
 *   CYPRESS_baseUrl=http://localhost:9000 CYPRESS_a=.. CYPRESS_b=.. CYPRESS_feuAction=.. CYPRESS_feuEffectId=2 \
 *     npx cypress run --spec cypress/e2e/effects-protocol.cy.js --browser electron
 *   docker cp scripts/testing/effects-protocol/teardown.php PHP-AOO4-Local:/tmp/e2e-teardown.php
 *   docker exec PHP-AOO4-Local php /tmp/e2e-teardown.php
 * Report: data_tests/effects-protocol/report.txt. Then `make test-db` (the
 * stage changes feu/lave). */
$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
require '/var/www/html/vendor/autoload.php';
require '/var/www/html/config/db_constants.php';
require '/var/www/html/config/bootstrap.php';
require '/var/www/html/config/constants.php';
$em = \App\Factory\EntityManagerFactory::getEntityManager();
$c = $em->getConnection();
$hash = password_hash('test', PASSWORD_DEFAULT);

// Backups of what we touch
$c->executeStatement("CREATE TABLE IF NOT EXISTS _e2e_effects AS SELECT * FROM effects WHERE name IN ('feu','lave','boue')");
$c->executeStatement("CREATE TABLE IF NOT EXISTS _e2e_item_effects AS SELECT * FROM item_effects WHERE item_id = 36");
$c->executeStatement("UPDATE effects SET carac_mods='{\"e\":-1,\"f\":2}', loss_mods='{\"pv\":-10}', apply_text='{cible} prend feu par {acteur}' WHERE name='feu'");
$c->executeStatement("UPDATE effects SET carac_mods='{\"a\":-1}', loss_mods='{\"pv\":-30}', apply_text='' WHERE name='lave'");
(new \App\Service\ItemEffectService())->replaceForItem(36, [
    ['name' => 'feu', 'duration' => 1, 'outcome' => 'hit', 'target' => 'target'],
    ['name' => 'boue', 'duration' => 1, 'outcome' => 'miss', 'target' => 'self'],
]);

$mk = function (string $name, int $coordsId) use ($c, $hash): int {
    $id = \Classes\Player::put_player($name, 'nain');
    (new \App\Service\AccountService())->setPassword($id, $hash);
    $c->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $id]);
    (new \App\Service\Map\EntityCellService($c))->syncCells($id);
    $c->executeStatement("INSERT IGNORE INTO players_options (player_id, name) VALUES (?, 'newHud')", [$id]);
    return $id;
};
$a = $mk('E2eAttaquant', 108005);   // olympia -24,25
$b = $mk('E2eVictime', 108006);     // olympia -23,25
// Lava on olympia -25,25 (coords 108004)
$c->executeStatement("DELETE FROM map_elements WHERE coords_id = 108004");
\Classes\Element::put('lave', 108004, 20);
// No tutorial prompt for the test characters
$c->executeStatement("UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE player_id IN (?, ?)", [$a, $b]);

// Test actions: feu on target, protection on self
$create = new \App\Service\Action\ActionCreateService();
$edit = new \App\Service\Action\ActionOutcomeEditService();
$save = new \App\Service\Action\ActionSaveService();
$mkAction = function (string $name, string $effect, string $applyTo) use ($c, $create, $edit, $em): int {
    $action = $create->create('buff', $name, ucfirst($name), 1, null, 'ra-fire', null);
    $id = (int) $action->getId();
    foreach ([['RequiresDistance', '{"max":1}'], ['TargetType', '{"allowed":["character"]}'], ['RequiresTraitValue', '{"a":1}']] as [$type, $params]) {
        $c->executeStatement('INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context) VALUES (?, ?, ?, 1, 1, 0)', [$type, $params, $id]);
    }
    $outcome = $edit->addOutcome($id, true);
    $c->executeStatement('UPDATE action_outcomes SET apply_to = ? WHERE id = ?', [$applyTo, $outcome->getId()]);
    $instruction = $edit->addInstruction((int) $outcome->getId(), 'applystatus');
    $c->executeStatement('UPDATE outcome_instructions SET parameters = ? WHERE id = ?', [json_encode(['effect' => $effect, 'apply' => true, 'duration' => 2, 'value' => 1]), $instruction->getId()]);
    return $id;
};
$feuAction = $mkAction('zz_feu_test', 'feu', 'target');
$protAction = $mkAction('zz_prot_test', 'protection', 'self');
foreach (['melee', 'zz_feu_test', 'zz_prot_test'] as $act) {
    $c->executeStatement("INSERT IGNORE INTO players_actions (player_id, name, type) VALUES (?, ?, '')", [$a, $act]);
}
// A holds the torch and a labelled item
$c->executeStatement("INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, 36, 1, 'main1', '')", [$a]);
$pm = (int) $c->fetchOne("SELECT id FROM items WHERE name = 'pierre_mana'");
$c->executeStatement("INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 2, '', '')", [$a, $pm]);
$c->executeStatement("UPDATE items SET label = 'Pierre de Mana' WHERE id = ?", [$pm]);
\App\Service\EffectService::clearCache();

echo json_encode(['a' => $a, 'b' => $b, 'feuAction' => $feuAction, 'protAction' => $protAction]), "\n";
