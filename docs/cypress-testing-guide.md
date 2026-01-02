# Cypress E2E Testing Guide

This guide explains how to write and run Cypress end-to-end tests for Age of Olympia v4.

## Quick Start

```bash
# Reset test database and run the tutorial test
/var/www/html/scripts/testing/reset_test_database.sh

# Run test (from inside the devcontainer)
CYPRESS_CONTAINER=true xvfb-run --auto-servernum npx cypress run \
  --spec "cypress/e2e/tutorial-production-ready.cy.js" \
  --browser electron

# Run with a specific race (nain, elfe, hs, etc.)
CYPRESS_CONTAINER=true xvfb-run --auto-servernum npx cypress run \
  --spec "cypress/e2e/tutorial-production-ready.cy.js" \
  --env race=elfe \
  --browser electron
```

## Environment Configuration

### Inside vs Outside Container

The test configuration auto-detects the environment:

| Environment | Base URL | How to Set |
|-------------|----------|------------|
| Inside container | `http://localhost` (port 80) | `CYPRESS_CONTAINER=true` |
| Outside container | `http://localhost:9000` | Default |

### Race Selection

Tests support race-adaptive behavior. The tutorial adjusts movement points based on race:

| Race | Max MVT | Command |
|------|---------|---------|
| Nain (default) | 4 | `--env race=nain` |
| Elfe | 5 | `--env race=elfe` |
| Homme-Sauvage | 6 | `--env race=hs` |

Race data is fetched from the `/api/races/get.php` endpoint.

## Critical: Cypress Session Handling

**MOST IMPORTANT RULE**: To prevent blank screenshots and session loss, **always use a SINGLE `it()` block** for tests that require authentication.

### Why?

Cypress resets browser state (cookies, localStorage, sessionStorage) between each `it()` block. This destroys the PHP session cookie, making the user appear logged out.

### Wrong - Multiple `it()` blocks

```javascript
describe('My Test', () => {
  it('Step 1: Login', () => {
    cy.login('TestPlayer', 'testpass');
    cy.screenshot('01-after-login'); // Works
  });

  it('Step 2: Check game', () => {
    cy.screenshot('02-game'); // BLANK - session was reset!
  });
});
```

### Correct - Single `it()` block

```javascript
describe('My Test', () => {
  it('Complete flow: Login and check game', () => {
    // Step 1: Login
    cy.login('TestPlayer', 'testpass');
    cy.screenshot('01-after-login'); // Works

    // Step 2: Check game
    cy.screenshot('02-game'); // Works - session preserved!
  });
});
```

## Test Database Setup

Tests use a dedicated `aoo4_test` database to avoid interfering with development data.

### Reset Test Database Before Each Run

**CRITICAL**: Always reset the test database before running tests.

```bash
/var/www/html/scripts/testing/reset_test_database.sh
```

This script:
1. Drops and recreates `aoo4_test` database
2. Copies structure from main database
3. Copies reference data (races, tutorial steps, items, actions)
4. Creates test characters with different tutorial states
5. Clears firewall IP blocks

### Test Characters

| Character | ID | State | Tutorial | Password |
|-----------|-----|-------|----------|----------|
| TestAdmin | 100 | Admin account | N/A | test |
| TestFreshPlayer | 101 | Brand new | None | testpass |
| TestTutorialStarted | 102 | In progress | Step 3 | testpass |
| TestTutorialCompleted | 103 | Completed | Done | testpass |
| TestTutorialSkipped | 104 | Skipped | Skipped | testpass |

## Custom Cypress Commands

### `cy.login(playerName, password)`

Logs in an existing player:

```javascript
cy.login('TestFreshPlayer', 'testpass');
```

### `cy.register(name, race, password, email)`

Creates a new player account:

```javascript
cy.register('NewPlayer', 'nain', 'password123', 'test@example.com');
```

### Database Queries

Query the test database directly:

```javascript
cy.task('queryDatabase', {
  query: 'SELECT * FROM tutorial_progress WHERE player_id = ?',
  params: [playerId]
}).then((rows) => {
  expect(rows[0].completed).to.eq(1);
});
```

## Fetching Race Data

The test fetches race stats from the API in a `before()` hook:

```javascript
let raceData = { mvt: 4 };

before(() => {
  cy.request(`/api/races/get.php?name=${TEST_ACCOUNT.race}`).then((response) => {
    expect(response.body.success).to.be.true;
    raceData = response.body.race;
    cy.log(`Race: ${raceData.name}, Max MVT: ${raceData.mvt}`);
  });
});
```

## Screenshot Helper Pattern

```javascript
const screenshot = (name, extraWait = 1500) => {
  cy.wait(extraWait);
  cy.get('body').should('be.visible');
  cy.wait(800); // Wait for animations
  cy.screenshot(name, { capture: 'fullPage', overwrite: true });
};
```

## Test Output Locations

Screenshots and videos are saved with timestamps:

```
data_tests/cypress/screenshots/YYYY-MM-DDTHH-MM-SS/
data_tests/cypress/videos/YYYY-MM-DDTHH-MM-SS/
```

## Troubleshooting

### "Missing X server or $DISPLAY"

Use `xvfb-run` to provide a virtual display:

```bash
xvfb-run --auto-servernum npx cypress run ...
```

### "Browser was not found"

Use `electron` browser (always available):

```bash
npx cypress run --browser electron
```

### "Server not running" on localhost:9000

Set `CYPRESS_CONTAINER=true` when running inside the container:

```bash
CYPRESS_CONTAINER=true xvfb-run ...
```

### Session Lost Between Steps

Use a single `it()` block for authenticated flows (see above).

## Best Practices

1. **Always use single `it()` block** for authenticated flows
2. **Always reset database** before test runs
3. **Take screenshots at every step** for debugging
4. **Wait for UI to settle** (1-2 seconds) before screenshots
5. **Use race parameter** to test different race behaviors

## Example Test Files

- **Full tutorial test**: `cypress/e2e/tutorial-production-ready.cy.js`
- **Simple example**: `cypress/e2e/tutorial-simple-test.cy.js`
