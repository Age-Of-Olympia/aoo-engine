/* Throwaway — chevron fixe visible sur portable court, effacé au scroll. */
describe('scroll hint', () => {
    it('visible at load on short screen, fades on scroll', () => {
        cy.viewport(1366, 768);
        cy.visit('index.php');
        cy.wait(800);
        cy.get('#landing-scroll-hint').should('be.visible').then($el => {
            const r = $el[0].getBoundingClientRect();
            expect(r.bottom).to.be.lte(768);
        });
        cy.screenshot('hint-laptop', { capture: 'viewport', overwrite: true });
        cy.scrollTo(0, 300);
        cy.wait(500);
        cy.get('#landing-scroll-hint').should('have.class', 'hint-hidden');
    });
});
