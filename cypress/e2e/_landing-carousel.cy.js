/* Throwaway — carrousel : navigation, et chevrons immobiles entre images. */
describe('landing carousel', () => {
    it('opens, navigates with fixed chevrons, closes', () => {
        cy.viewport(1440, 900);
        cy.visit('index.php');
        cy.wait(1000);
        cy.get('.landing-gallery figure').first().click();
        cy.get('#landing-lightbox.lightbox-open').should('be.visible');
        cy.screenshot('carousel-open', { capture: 'viewport', overwrite: true });

        let before;
        cy.get('.lightbox-next').then($b => { before = $b[0].getBoundingClientRect(); });
        cy.get('.lightbox-next').click();
        cy.wait(300);
        cy.get('.lightbox-next').then($b => {
            const after = $b[0].getBoundingClientRect();
            expect(after.left).to.equal(before.left);
            expect(after.top).to.equal(before.top);
        });
        cy.get('.lightbox-prev').click();
        cy.wait(200);
        cy.screenshot('carousel-back', { capture: 'viewport', overwrite: true });
        cy.get('body').type('{esc}');
        cy.get('#landing-lightbox').should('not.be.visible');
    });
});
