/**
 * The action endpoint, over HTTP.
 *
 * Every PHPUnit action test builds ActionExecutorService itself, so action.php
 * — its imports, its bootstrap, its superglobals — is never executed. A stale
 * import there stays green in the suite and fatals in the browser, which is
 * how "attacking a building" broke while 1345 tests passed.
 */

describe('Endpoint action.php', () => {
  /* Devcontainer defaults; CI seeds its own and passes them through
   * CYPRESS_PLAYER_NAME / CYPRESS_PLAYER_PASSWORD. */
  const ACCOUNT = {
    name: Cypress.env('PLAYER_NAME') || 'Cradek',
    password: Cypress.env('PLAYER_PASSWORD') || 'test'
  };

  /* What PHP prints when it dies. `not found` catches a missing class, which
   * is the failure a moved file leaves behind. */
  const FATAL_SIGNS = [
    'Fatal error',
    'Uncaught',
    'Parse error',
    'not found',
    'Unable to connect'
  ];

  const expectNoFatal = (body) => {
    FATAL_SIGNS.forEach((sign) => {
      expect(body, `l'endpoint a levé : ${body.slice(0, 400)}`).not.to.include(sign);
    });
  };

  beforeEach(() => {
    cy.login(ACCOUNT.name, ACCOUNT.password);
  });

  it('boots and resolves the action factory', () => {
    /* No targetId: the controller aims at the actor, which skips the coords
     * guard and still runs the factory — the line that was broken. */
    cy.request({
      method: 'POST',
      url: '/action.php',
      form: true,
      body: { action: 'melee' },
      failOnStatusCode: false
    }).then((response) => {
      expect(response.status).to.eq(200);
      expectNoFatal(response.body);
      expect(response.body).not.to.include('error action');
    });
  });

  it('refuses an unknown action without dying', () => {
    cy.request({
      method: 'POST',
      url: '/action.php',
      form: true,
      body: { action: 'action_qui_nexiste_pas' },
      failOnStatusCode: false
    }).then((response) => {
      expectNoFatal(response.body);
    });
  });

});
