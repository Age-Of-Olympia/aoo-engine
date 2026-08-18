/* Throwaway — capture de la page admin Page d'accueil. */
describe('landing admin', () => {
    it('renders the three blocks', () => {
        cy.viewport(1440, 1200);
        cy.login('Cradek', 'test');
        cy.visit('admin/landing.php');
        cy.contains('Sections de texte');
        cy.contains('Dernières chroniques');
        cy.contains("Galerie d'aperçus");
        cy.screenshot('landing-admin', { capture: 'fullPage', overwrite: true });
    });
});
