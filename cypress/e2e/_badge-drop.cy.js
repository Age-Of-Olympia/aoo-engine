/* Throwaway — reading a missive in panel refreshes the badge. */
describe('badge drop on read', () => {
    it('badge count drops after opening a thread', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');
        cy.intercept('GET', '**/check_mail.php*').as('mail');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.wait('@mail');
        cy.wait(500);

        let before;
        cy.get('#current-characters-mails').then(($b) => { before = parseInt($b.text() || '0'); });

        cy.get('#hud-rail a[href="forum.php?forum=Missives"]').click({ force: true });
        cy.wait(1200);
        cy.get(".hud-panel--open .hud-panel-content a b").first().closest("a").click();
        cy.wait('@mail', { timeout: 10000 });
        cy.wait(600);

        cy.get('#current-characters-mails').then(($b) => {
            const after = parseInt($b.text() || '0');
            expect(after).to.be.lessThan(before);
        });
    });
});
