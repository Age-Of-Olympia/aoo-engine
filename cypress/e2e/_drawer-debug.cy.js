/* Throwaway — drawer button computed styles. */
describe('drawer debug', () => {
    it('dumps computed styles', () => {
        cy.viewport(390, 844);
        cy.login('Cradek', 'test');
        cy.visit('index.php');
        cy.get('#hud', { timeout: 10000 }).should('exist');
        cy.get('#hud-burger').click();
        cy.wait(500);

        cy.window().then((w) => {
            const out = [];
            w.document.querySelectorAll('#hud-rail #menu button').forEach((b) => {
                const cs = w.getComputedStyle(b);
                out.push({
                    label: (b.textContent || '').trim().slice(0, 20),
                    parent: b.parentElement.tagName + (b.parentElement.id ? '#' + b.parentElement.id : '')
                        + (b.parentElement.getAttribute('href') ? '[' + b.parentElement.getAttribute('href') + ']' : ''),
                    display: cs.display,
                    justify: cs.justifyContent,
                    textAlign: cs.textAlign,
                    padding: cs.padding,
                });
            });
            cy.writeFile('/tmp/drawer-debug.json', JSON.stringify(out, null, 1));
        });
    });
});
