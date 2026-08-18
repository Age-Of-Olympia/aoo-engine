/* Throwaway audit — forum deep views under the paper theme. */
describe('forum deep views', () => {
    it('captures topic list and thread', () => {
        cy.viewport(1440, 900);
        cy.login('Cradek', 'test');

        cy.visit('forum.php?forum=Missives');
        cy.wait(1000);
        cy.screenshot('forum-missives', { capture: 'viewport', overwrite: true });

        cy.visit('forum.php?topic=1783207316084');
        cy.wait(1000);
        cy.screenshot('forum-thread', { capture: 'viewport', overwrite: true });
        cy.scrollTo('bottom');
        cy.wait(400);
        cy.screenshot('forum-thread-bottom', { capture: 'viewport', overwrite: true });
    });
});
