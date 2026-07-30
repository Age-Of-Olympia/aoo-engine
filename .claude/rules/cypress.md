---
paths:
  - "cypress/**"
  - "scripts/testing/**"
---

# Cypress E2E testing

Full guide: [docs/cypress-testing-guide.md](../../docs/cypress-testing-guide.md).

**Quick Start** (from the HOST — the devcontainer image ships neither the MariaDB client
nor Xvfb, so both commands below fail inside it):
```bash
# Reset database and run tutorial test
scripts/testing/reset_test_database.sh && \
CYPRESS_CONTAINER=true xvfb-run --auto-servernum npx cypress run \
  --spec "cypress/e2e/tutorial-production-ready.cy.js" \
  --browser electron

# Test with different race (nain=4 MVT, elfe=5 MVT, hs=6 MVT)
CYPRESS_CONTAINER=true xvfb-run --auto-servernum npx cypress run \
  --spec "cypress/e2e/tutorial-production-ready.cy.js" \
  --env race=elfe \
  --browser electron
```

**Key points**:
- Always use a SINGLE `it()` block for authenticated flows (Cypress resets the session between blocks)
- Reset the test database before each run; the script says so and exits when it cannot reach
  the MariaDB client, instead of hanging (it used to loop forever on a silent `until mysql …`)
- For a one-off schema fix without the client, use the Doctrine connection from the PHP
  container: `php -r 'require "config/bootstrap.php"; …'`
- Test database: `aoo4_test` (5 pre-configured test characters)
- Full test: `cypress/e2e/tutorial-production-ready.cy.js`
- Simple example: `cypress/e2e/tutorial-simple-test.cy.js`
