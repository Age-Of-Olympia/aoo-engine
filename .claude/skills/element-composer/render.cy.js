/* Scratch spec: render composer presets as a 3x3 tiling on a ground tile.
 * Copy to cypress/e2e/_composer.cy.js, set the login, run from the host. */
describe('composer', () => {
  it('renders presets side by side', () => {
    cy.request({ method: 'POST', url: 'http://localhost:9000/login.php', form: true, body: { name: '1', psw: 'test' } });
    cy.visit('http://localhost:9000/admin/element-composer.php');
    cy.get('#p-freq').should('exist');
    cy.get('#bg').select('sur jungle_sauvage');
    ['eau', 'lave'].forEach((name, i) => {
      cy.get(`[data-preset="${name}"]`).click();
      cy.get('#preview-inline-big img').should('have.length', 9);
      cy.get('#preview-inline-big').screenshot(`${i}-${name}`, { overwrite: true });
    });
    /* Variant without a preset: set any control, then trigger input. */
    cy.get('#p-blur').invoke('val', 1.5).trigger('input');
    cy.get('#preview-inline-big').screenshot('blur', { overwrite: true });
  });
});
