describe('avatars admin — popover joueurs', () => {
  it('ouvre le popover des joueurs et capture', () => {
    cy.request({ method: 'POST', url: '/login.php', form: true, body: { name: 'Cradek', psw: 'test' } });

    cy.visit('/admin/avatars-portraits.php?type=portrait&race=nain');
    cy.contains('code', '1.jpeg')
      .parents('tr')
      .find('details.row-popover summary')
      .first()
      .click();
    cy.wait(300);
    cy.screenshot('avatar-users-popover', { capture: 'viewport' });
  });
});
