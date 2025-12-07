# Cypress E2E Testing Guide

This guide explains how to write and run Cypress end-to-end tests for Age of Olympia v4.

## Quick Start

```bash
# Reset test database and run a specific test
/var/www/html/scripts/testing/reset_test_database.sh && \
timeout 60 xvfb-run npx cypress run --spec "cypress/e2e/your-test.cy.js" --config video=true
```

## Critical: Cypress Session Handling

**MOST IMPORTANT RULE**: To prevent blank screenshots and session loss, **always use a SINGLE `it()` block** for tests that require authentication.

### ❌ WRONG - Multiple `it()` blocks (session gets reset between blocks)

```javascript
describe('My Test', () => {
  it('Step 1: Login', () => {
    cy.login('TestPlayer', 'testpass');
    cy.screenshot('01-after-login'); // ✓ Works
  });

  it('Step 2: Check game', () => {
    cy.screenshot('02-game'); // ❌ BLANK - session was reset!
  });
});
```

### ✅ CORRECT - Single `it()` block (session preserved)

```javascript
describe('My Test', () => {
  it('Complete flow: Login and check game', () => {
    // Step 1: Login
    cy.login('TestPlayer', 'testpass');
    cy.screenshot('01-after-login'); // ✓ Works

    // Step 2: Check game
    cy.screenshot('02-game'); // ✓ Works - session preserved!
  });
});
```

**Why this happens**: Cypress resets browser state (cookies, localStorage, sessionStorage) between each `it()` block by default. This destroys the PHP session cookie, making the user appear logged out.

## Test Database Setup

Tests use a dedicated `aoo4_test` database to avoid interfering with development/production data.

### Reset Test Database Before Each Test Run

**CRITICAL**: Always reset the test database before running tests to ensure consistent state.

```bash
/var/www/html/scripts/testing/reset_test_database.sh
```

This script:
1. Drops and recreates `aoo4_test` database
2. Copies structure from `aoo_prod_20251127`
3. Copies reference data (races, tutorial steps, items, actions)
4. Creates 4 test characters with different tutorial states
5. Clears firewall IP blocks

### Test Characters

| Character Name         | ID  | State                  | Tutorial Progress | XP/PI   | Password  |
|------------------------|-----|------------------------|-------------------|---------|-----------|
| TestFreshPlayer        | 101 | Brand new player       | None              | 0/0     | testpass  |
| TestTutorialStarted    | 102 | Tutorial in progress   | Step 3            | 0/0     | testpass  |
| TestTutorialCompleted  | 103 | Tutorial completed     | Completed         | 240/240 | testpass  |
| TestTutorialSkipped    | 104 | Tutorial skipped       | Skipped           | 50/50   | testpass  |

## Custom Cypress Commands

### `cy.login(playerName, password)`

Logs in an existing player.

```javascript
cy.login('TestFreshPlayer', 'testpass');
```

### `cy.register(name, race, password, email)`

Creates a new player account. Races: `'hs'`, `'elfe'`, `'nain'`

```javascript
cy.register('NewPlayer', 'hs', 'password123', 'test@example.com');
```

## Screenshot Helper Pattern

```javascript
const screenshot = (name, extraWait = 1000) => {
  cy.wait(extraWait);
  cy.get('body').should('be.visible');
  cy.wait(500);
  cy.screenshot(name, { capture: 'viewport', overwrite: true });
};
```

## Best Practices

1. **Always use single `it()` block** for authenticated flows
2. **Always reset database** before test runs
3. **Take screenshots at every step** for debugging
4. **Wait for UI to settle** before screenshots (1-2 seconds)

See `/var/www/html/cypress/e2e/tutorial-simple-test.cy.js` for a complete working example.
