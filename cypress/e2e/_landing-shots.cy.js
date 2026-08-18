/* Throwaway — captures de la page d'accueil déconnectée. */
describe('landing screenshots', () => {
    it('desktop', () => {
        cy.viewport(1440, 900);
        cy.visit('index.php');
        cy.wait(1200);
        cy.screenshot('landing-desktop', { capture: 'viewport', overwrite: true });
        cy.scrollTo('bottom');
        cy.wait(400);
        cy.screenshot('landing-desktop-bottom', { capture: 'viewport', overwrite: true });
    });

    it('mobile', () => {
        cy.viewport(390, 844);
        cy.visit('index.php');
        cy.wait(1200);
        cy.screenshot('landing-mobile', { capture: 'viewport', overwrite: true });
        cy.scrollTo('bottom');
        cy.wait(400);
        cy.screenshot('landing-mobile-bottom', { capture: 'viewport', overwrite: true });
    });
});

describe('landing partners', () => {
    it('bottom after lazy load', () => {
        cy.viewport(390, 844);
        cy.visit('index.php');
        cy.wait(1200);
        cy.scrollTo('bottom');
        cy.wait(800);
        cy.scrollTo('bottom');
        cy.wait(400);
        cy.screenshot('landing-mobile-partners', { capture: 'viewport', overwrite: true });
    });
});
