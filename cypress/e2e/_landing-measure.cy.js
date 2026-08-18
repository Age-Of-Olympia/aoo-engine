/* Throwaway — décomposition du layout du pli. */
describe('landing measure', () => {
    it('logs layout chain', () => {
        cy.viewport(1440, 900);
        cy.visit('index.php');
        cy.wait(800);
        cy.window().then(win => {
            const doc = win.document;
            const cols = doc.querySelector('#landing-columns');
            const fold = doc.querySelector('#landing-fold');
            const chain = [];
            let n = cols;
            while (n && n !== doc.documentElement) {
                const c = win.getComputedStyle(n);
                chain.push({
                    tag: n.tagName, id: n.id || null,
                    display: c.display, alignItems: c.alignItems,
                    width: c.width, zoom: c.zoom, transform: c.transform,
                });
                n = n.parentElement;
            }
            cols.style.setProperty('width', '1200px', 'important');
            const after = win.getComputedStyle(cols).width;
            cy.writeFile('data_tests/landing-measure.json', {
                chain,
                foldRect: fold.getBoundingClientRect().width,
                importantWidthTest: after,
                docWidth: doc.documentElement.clientWidth,
            });
        });
    });
});
