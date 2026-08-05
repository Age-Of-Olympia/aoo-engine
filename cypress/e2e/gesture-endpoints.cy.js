/**
 * The gesture endpoints (chest and faction flows), over HTTP.
 *
 * PHPUnit exercises the services; these endpoints — their imports, their
 * bootstrap, their session reads — only ever run in a browser. A refusal
 * must come back as JSON {error}, never as a fatal or a blank page: the
 * gesture scripts (aooGestureFetch) SAY the error, so a broken endpoint
 * would look like a silent nothing.
 */

describe('Gesture endpoints (container, faction)', () => {
  const ACCOUNT = {
    name: Cypress.env('PLAYER_NAME') || 'Cradek',
    password: Cypress.env('PLAYER_PASSWORD') || 'test'
  };

  const FATAL_SIGNS = [
    'Fatal error',
    'Uncaught',
    'Parse error',
    'not found',
    'Unable to connect'
  ];

  const expectNoFatal = (body) => {
    const text = typeof body === 'string' ? body : JSON.stringify(body);
    FATAL_SIGNS.forEach((sign) => {
      expect(text, `l'endpoint a levé : ${text.slice(0, 400)}`).not.to.include(sign);
    });
  };

  const postJson = (url, payload) => cy.request({
    method: 'POST',
    url: url,
    body: payload,
    failOnStatusCode: false
  });

  beforeEach(() => {
    cy.login(ACCOUNT.name, ACCOUNT.password);
  });

  it('container flows boot and refuse gracefully', () => {
    // Unknown action: the switch's own refusal, proving the endpoint parses.
    postJson('/api/container/flows.php', { action: 'nexiste-pas', containerId: 0 })
      .then((response) => {
        expect(response.status).to.eq(200);
        expectNoFatal(response.body);
        expect(response.body).to.have.property('error', 'action inconnue');
      });

    // Each real action on a container nobody has: a spoken JSON refusal.
    ['stack-withdraw', 'exemplar-withdraw', 'withdraw-all', 'lock'].forEach((action) => {
      postJson('/api/container/flows.php', { action: action, containerId: 0, itemId: 0, n: 1, instanceId: 0, open: 1 })
        .then((response) => {
          expect(response.status).to.eq(200);
          expectNoFatal(response.body);
          expect(response.body, `« ${action} » doit répondre {error}`).to.have.property('error');
        });
    });

    // The panel fragment guards its parameter.
    cy.request({ url: '/load_container.php', failOnStatusCode: false }).then((response) => {
      expectNoFatal(response.body);
      expect(response.body).to.include('error container');
    });
  });

  it('faction endpoints boot and refuse gracefully', () => {
    postJson('/api/faction/members.php', { action: 'add', name: 'PersonneDeCeNom' })
      .then((response) => {
        expect(response.status).to.eq(200);
        expectNoFatal(response.body);
        expect(response.body).to.have.property('error');
      });

    postJson('/api/faction/drive.php', { action: 'take', buildingId: 0 })
      .then((response) => {
        expect(response.status).to.eq(200);
        expectNoFatal(response.body);
        expect(response.body).to.have.property('error');
      });

    // Releasing while driving nothing must not die either way.
    postJson('/api/faction/drive.php', { action: 'release' })
      .then((response) => {
        expect(response.status).to.eq(200);
        expectNoFatal(response.body);
      });

    cy.request({ url: '/load_faction.php?faction=faction_inconnue', failOnStatusCode: false })
      .then((response) => {
        expectNoFatal(response.body);
        expect(response.body).to.include('error faction');
      });
  });
});
