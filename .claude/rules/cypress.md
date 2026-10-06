---
paths:
  - "cypress/**"
  - "scripts/testing/**"
---

# Cypress E2E testing

Full guide: [docs/cypress-testing-guide.md](../../docs/cypress-testing-guide.md).

**Quick Start** (from the HOST — the devcontainer has no display; the host runs Electron on
its own `DISPLAY`, no Xvfb needed). The DB tasks reach MariaDB by its container IP, and
`TEST_DB_NAME` must be the database the web app uses:
```bash
TEST_DB_HOST=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' aoo-engine-mariadb-aoo4-1) \
TEST_DB_NAME=aoo4 \
./node_modules/.bin/cypress run --spec cypress/e2e/tutorial-production-ready.cy.js \
  --browser electron --config baseUrl=http://localhost:9000 [--env race=elfe]
```
`npx cypress` fails here ("Missing script"): call the binary directly. Playable races:
nain, elfe, hs, olympien, geant — the spec reads movement points from the races API.

**Key points**:
- Always use a SINGLE `it()` block for authenticated flows (Cypress resets the session between blocks)
- Reset the test database before each run; the script says so and exits when it cannot reach
  the MariaDB client, instead of hanging (it used to loop forever on a silent `until mysql …`)
- For a one-off schema fix without the client, use the Doctrine connection from the PHP
  container: `php -r 'require "config/bootstrap.php"; …'`
- Test database: `aoo4_test` (5 pre-configured test characters)
- Full test: `cypress/e2e/tutorial-production-ready.cy.js`
- Simple example: `cypress/e2e/tutorial-simple-test.cy.js`
