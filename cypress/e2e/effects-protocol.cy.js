/* Throwaway — the effects/death protocol, end to end on the dev DB.
   Stage with scripts/testing/effects-protocol/setup.php first (see its header); ids come from Cypress.env. */
const SQL = (q) => `docker exec aoo-engine-mariadb-aoo4-1 mariadb -u root -ppasswordRoot --default-character-set=utf8mb4 -N aoo4 -e "${q.replace(/"/g, '\\"')}"`;
const A = Number(Cypress.env('a')), B = Number(Cypress.env('b'));

function sql(q) { return cy.exec(SQL(q), { failOnNonZeroExit: false }).its('stdout').then((s) => s.trim()); }
function loginA() {
    cy.login(String(A), 'test');
    // The login starts a first-time tutorial for a fresh character: close it.
    sql(`UPDATE tutorial_progress SET completed = 1 WHERE player_id = ${A}; UPDATE tutorial_players SET is_active = 0 WHERE player_id = ${A}`);
    cy.visit('index.php'); cy.get('#hud', { timeout: 10000 }).should('exist'); cy.wait(800);
}
function closeModal() {
    cy.get('body').then(($b) => { const $c = $b.find('#hud-action-modal .hud-action-modal-close, #hud-action-modal [data-close]'); if ($c.length) { cy.wrap($c.first()).click({ force: true }); } });
    cy.wait(400);
}
function act(coords, actionName) {
    closeModal();
    cy.get(`#game-map .case[data-coords="${coords}"]`).click({ force: true });
    cy.wait(1200);
    cy.get(`#hud-actions .action[data-action="${actionName}"]`, { timeout: 8000 }).should('exist');
    cy.get(`#hud-actions .action[data-action="${actionName}"]`).click({ force: true });
    cy.wait(300);
    cy.get(`#hud-actions .action[data-action="${actionName}"]`).click({ force: true });
    cy.get('#hud-action-modal', { timeout: 10000 }).should('be.visible');
    cy.wait(1200);
    return cy.get('.hud-action-modal-body').invoke('text').then((t) => t.replace(/\s+/g, ' ').trim());
}
function resetPair() {
    // Both back in place, healthy, no effects, A rich in A/PM
    // A death spills the loot: give the torch and the stones back each time.
    sql(`DELETE FROM players_items WHERE player_id = ${A}; INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (${A}, 36, 1, 'main1', ''); INSERT INTO players_items (player_id, item_id, n, equiped, slot) SELECT ${A}, id, 2, '', '' FROM items WHERE name = 'pierre_mana'; DELETE FROM map_items WHERE coords_id IN (108004, 108005, 108006)`);
    sql(`UPDATE players SET coords_id = 108005 WHERE id = ${A}; UPDATE players SET coords_id = 108006 WHERE id = ${B}; DELETE FROM players_logs WHERE player_id IN (${A},${B}) OR target_id IN (${A},${B}); DELETE FROM players_upgrades WHERE player_id = ${B}; UPDATE players SET malus = 0 WHERE id IN (${A},${B}); DELETE FROM players_effects WHERE player_id IN (${A},${B}); DELETE FROM players_bonus WHERE player_id IN (${A},${B}) AND name IN ('pv','a','pm','mvt'); DELETE FROM players_options WHERE player_id = ${A} AND name = 'deathScreenPending'`);
    cy.exec(`docker exec PHP-AOO4-Local php -r '$_SERVER["DOCUMENT_ROOT"]="/var/www/html"; require "/var/www/html/vendor/autoload.php"; require "/var/www/html/config/db_constants.php"; require "/var/www/html/config/bootstrap.php"; require "/var/www/html/config/constants.php"; $s=new \\App\\Service\\Map\\EntityCellService(\\App\\Factory\\EntityManagerFactory::getEntityManager()->getConnection()); $s->syncCells(${A}); $s->syncCells(${B});'`);
}
const R = [];
function report(step, ok, detail) { R.push(`${ok ? 'OK ' : 'KO '} ${step} — ${detail}`); }

describe('effects protocol', () => {
    after(() => { cy.writeFile('data_tests/effects-protocol/report.txt', R.join('\n') + '\n'); });


    it('1. names', () => {
        resetPair(); loginA();
        cy.request('load_inventory.php').its('body').then((b) => {
            report('1 inventory label', b.includes('Pierre de Mana'), 'len=' + b.length + ' has=' + b.includes('Pierre de Mana'));
        });
    });

    it('2. effect on a target + 3. on self', () => {
        resetPair(); loginA();
        act('-23,25', 'zz_feu_test').then((t) => {
            report('2 landing message', /E2eVictime prend feu par E2eAttaquant \(x1, pour 2 tours, E −1, F \+2, PV −10\)/.test(t), t.slice(0, 160));
        });
        sql(`SELECT COUNT(*) FROM players_effects WHERE player_id = ${B} AND name = 'feu'`).then((n) => report('2 B has feu', n === '1', 'rows=' + n));
        sql(`SELECT COUNT(*) FROM players_effects WHERE player_id = ${A} AND name = 'feu'`).then((n) => report('2 A untouched', n === '0', 'rows=' + n));
        sql(`SELECT n FROM players_bonus WHERE player_id = ${B} AND name = 'pv'`).then((n) => report('2 B lost 10 PV', n === '-10', 'pv bonus=' + n));
        // B's own caracs sheet (load_upgrades.php renders the bearer's)
        cy.login(String(B), 'test');
        cy.request('load_upgrades.php').its('body').then((b) => {
            report('2 B sheet shows fire icon on F (blue)', /style="color:blue" title="feu"/.test(b), 'blue feu icon');
            report('2 B sheet shows fire icon on E (red)', /style="color:red" title="feu"/.test(b), 'red feu icon');
        });
        loginA();
        act('-24,25', 'zz_prot_test').then((t) => report('3 self message', /E2eAttaquant/.test(t) && /protection/.test(t), t.slice(0, 120)));
        sql(`SELECT COUNT(*) FROM players_effects WHERE player_id = ${A} AND name = 'protection'`).then((n) => report('3 A has protection', n === '1', 'rows=' + n));
        sql(`SELECT COUNT(*) FROM players_effects WHERE player_id = ${B} AND name = 'protection'`).then((n) => report('3 B has none', n === '0', 'rows=' + n));
    });

    it('4. weapon on hit / on miss (several melee strikes)', () => {
        resetPair(); loginA();
        sql(`INSERT INTO players_bonus (player_id, name, n) VALUES (${A}, 'a', 20) ON DUPLICATE KEY UPDATE n = 20`);
        const results = [];
        for (let i = 0; i < 8; i++) {
            // Half the strikes against an untouchable victim, so both outcomes show up.
            sql(i === 4 ? `INSERT INTO players_upgrades (player_id, name, cost) SELECT ${B}, 'agi', 0 FROM (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) x, (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) y` : 'SELECT 1');
            act('-23,25', 'melee').then((t) => results.push(t));
            sql(`DELETE FROM players_effects WHERE player_id IN (${A},${B}); UPDATE players SET coords_id = 108006 WHERE id = ${B}`);
        }
        cy.then(() => {
            cy.writeFile('data_tests/effects-protocol/strikes.txt', results.join('\n----\n'));
            const hits = results.filter((t) => /Réussite/.test(t)), misses = results.filter((t) => /Echec/.test(t));
            const hitOk = hits.every((t) => /E2eVictime prend feu par E2eAttaquant/.test(t) && !/E2eAttaquant prend feu/.test(t));
            const missOk = misses.every((t) => /boue/.test(t) && /E2eAttaquant/.test(t) && !/prend feu/.test(t));
            report('4 hits burn the target only', hitOk, `${hits.length} hits`);
            report('4 misses muddy the bearer only', missOk, `${misses.length} misses`);
            report('4 both outcomes seen', hits.length > 0 && misses.length > 0, 'dice covered both');
        });
        sql(`DELETE FROM players_upgrades WHERE player_id = ${B}`);
    });

    it('5. element: wound, log, then death page', () => {
        resetPair(); loginA();
        cy.get('#game-map .case[data-coords="-25,25"]').click({ force: true }); cy.wait(1000);
        cy.get('#go-rect').click({ force: true }); cy.wait(2500);
        sql(`SELECT n FROM players_bonus WHERE player_id = ${A} AND name = 'pv'`).then((n) => report('5 lava took 30 PV', n === '-30', 'pv bonus=' + n));
        sql(`SELECT COUNT(*) FROM players_logs WHERE player_id = ${A} AND type = 'element' AND text LIKE '%PV −30%'`).then((n) => report('5 element line in the log', n === '1', 'rows=' + n));
        cy.visit('index.php'); cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.contains('button, a, .hud-tab', /Év[eé]nements/).click({ force: true }); cy.wait(1500);
        cy.get('body').invoke('text').then((t) => report('5 feed shows the line', /PV −30/.test(t), 'events tab'));
        // Down to 5 PV, step again: death page
        sql(`UPDATE players SET coords_id = 108005 WHERE id = ${A}`);
        cy.exec(`docker exec PHP-AOO4-Local php -r '$_SERVER["DOCUMENT_ROOT"]="/var/www/html"; require "/var/www/html/vendor/autoload.php"; require "/var/www/html/config/db_constants.php"; require "/var/www/html/config/bootstrap.php"; require "/var/www/html/config/constants.php"; $p=\\App\\Factory\\PlayerFactory::legacy(${A}); $p->get_caracs(); $left=$p->getRemaining("pv"); $p->putBonus(["pv"=>-($left-5)]); (new \\App\\Service\\Map\\EntityCellService(\\App\\Factory\\EntityManagerFactory::getEntityManager()->getConnection()))->syncCells(${A});'`);
        cy.visit('index.php'); cy.get('#hud', { timeout: 10000 }).should('exist'); cy.wait(800);
        cy.get('#game-map .case[data-coords="-25,25"]').click({ force: true }); cy.wait(1000);
        cy.get('#go-rect').click({ force: true }); cy.wait(4000);
        cy.get('body').invoke('text').then((t) => {
            report('5 death page', /Vous êtes mort/.test(t), 'page');
            report('5 death page events', /a succombé aux éléments/.test(t), 'succombé line');
            report('5 no alert text', !/Vous succombez/.test(t), 'no alert');
        });
        cy.contains('button', /Bienvenue aux Enfers/).click();
        sql(`SELECT c.plan FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ${A}`).then((p) => report('5 in the enfers', p === 'enfers', 'plan=' + p));
    });

    it('6. self death by own weapon (torch row on the bearer)', () => {
        resetPair();
        cy.exec(`docker exec PHP-AOO4-Local php -r '$_SERVER["DOCUMENT_ROOT"]="/var/www/html"; require "/var/www/html/vendor/autoload.php"; require "/var/www/html/config/db_constants.php"; require "/var/www/html/config/bootstrap.php"; require "/var/www/html/config/constants.php"; (new \\App\\Service\\ItemEffectService())->replaceForItem(36, [["name"=>"feu","duration"=>1,"outcome"=>"hit","target"=>"self"],["name"=>"feu","duration"=>1,"outcome"=>"miss","target"=>"self"]]); $p=\\App\\Factory\\PlayerFactory::legacy(${A}); $p->get_caracs(); $p->putBonus(["pv"=>-($p->getRemaining("pv")-5), "a"=>10]);'`);
        loginA();
        act('-23,25', 'melee').then((t) => { cy.writeFile('data_tests/effects-protocol/selfdeath.txt', t); report('6 self death message', /succombez à vos propres effets/.test(t), t.slice(-160)); });
        sql(`SELECT c.plan FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ${A}`).then((p) => report('6 A in the enfers', p === 'enfers', 'plan=' + p));
        sql(`SELECT COUNT(*) FROM players_logs WHERE player_id = ${B} AND type = 'kill'`).then((n) => report('6 no kill for B', n === '0', 'kill rows=' + n));
        cy.exec(`docker exec PHP-AOO4-Local php -r '$_SERVER["DOCUMENT_ROOT"]="/var/www/html"; require "/var/www/html/vendor/autoload.php"; require "/var/www/html/config/db_constants.php"; require "/var/www/html/config/bootstrap.php"; require "/var/www/html/config/constants.php"; (new \\App\\Service\\ItemEffectService())->replaceForItem(36, [["name"=>"feu","duration"=>1,"outcome"=>"hit","target"=>"target"],["name"=>"boue","duration"=>1,"outcome"=>"miss","target"=>"self"]]);'`);
    });

    it('7. HUD: no Refaire after Action Impossible; atelier confirm', () => {
        resetPair(); loginA();
        sql(`INSERT INTO players_bonus (player_id, name, n) VALUES (${A}, 'a', -50) ON DUPLICATE KEY UPDATE n = -50`);
        act('-23,25', 'melee').then((t) => report('7 blocked', /Action Impossible/.test(t), t.slice(0, 80)));
        cy.get('.hud-action-modal-again').should('not.be.visible');
        cy.then(() => report('7 no Refaire', true, 'button hidden'));
        cy.login('1', 'test'); cy.visit('admin/action-workbench.php?id=' + Cypress.env('feuAction'));
        cy.get('button[form="wb-action-delete-form"]').click();
        cy.contains('Supprimer définitivement cette action').should('be.visible');
        cy.contains('button', 'Annuler').click();
        cy.get('#wb-action-form').should('exist');
        cy.then(() => report('7 atelier confirm', true, 'dialog shown, cancel keeps'));
    });

    it('8. regression: generic message, positive gain, export keys', () => {
        resetPair();
        sql(`UPDATE effects SET loss_mods = CONCAT('{', CHAR(34), 'pv', CHAR(34), ':30}') WHERE name = 'lave'`);
        cy.exec(`docker exec PHP-AOO4-Local php -r '$_SERVER["DOCUMENT_ROOT"]="/var/www/html"; require "/var/www/html/vendor/autoload.php"; require "/var/www/html/config/db_constants.php"; require "/var/www/html/config/bootstrap.php"; require "/var/www/html/config/constants.php"; $p=\\App\\Factory\\PlayerFactory::legacy(${A}); $p->get_caracs(); $p->putBonus(["pv"=>-10]);'`);
        loginA();
        cy.get('#game-map .case[data-coords="-25,25"]').click({ force: true }); cy.wait(1000);
        cy.get('#go-rect').click({ force: true }); cy.wait(2500);
        sql(`SELECT COALESCE((SELECT n FROM players_bonus WHERE player_id = ${A} AND name = 'pv'), 0)`).then((n) => report('8 gain capped at max', n === '0', 'pv bonus=' + n));
        sql(`UPDATE effects SET loss_mods = CONCAT('{', CHAR(34), 'pv', CHAR(34), ':-30}') WHERE name = 'lave'`);
        cy.login('1', 'test');
        cy.request('admin/action-export.php?type=effect&id=' + Cypress.env('feuEffectId')).its('body').then((b) => {
            const s = typeof b === 'string' ? b : JSON.stringify(b);
            report('8 effect export keys', /caracMods/.test(s) && /lossMods/.test(s) && /applyText/.test(s), 'keys');
        });
        cy.request('admin/action-export.php?type=item&name=torche').its('body').then((b) => {
            const s = typeof b === 'string' ? b : JSON.stringify(b);
            report('8 item export keys', /strikeEffects/.test(s) && /"label"/.test(s), 'keys');
        });
    });
    it('9. summary', () => {
        cy.writeFile('data_tests/effects-protocol/report.txt', R.join('\n') + '\n');
        const ko = R.filter((l) => l.startsWith('KO'));
        expect(ko, ko.join(' | ')).to.have.length(0);
    });
});
