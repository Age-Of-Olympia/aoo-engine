# Cypress E2E Testing Principles

## Core Principle: Test the User Interface

**CRITICAL**: End-to-end tests MUST interact with the actual user interface, NOT bypass it with API calls.

### ❌ WRONG - Using API shortcuts
```javascript
// BAD: Bypasses the UI entirely
cy.request('POST', '/register.php', { name: 'Test', ... });
cy.register(name, race, password, email); // Helper that uses cy.request()
```

### ✅ CORRECT - Testing the actual UI
```javascript
// GOOD: Tests what the user actually sees and clicks
cy.visit('/register.php');
cy.get('input[name="name"]').type('Test');
cy.get('select[name="race"]').select('hs');
cy.get('button[type="submit"]').click();
```

## Why This Matters

1. **UI bugs are invisible to API tests**: If a button is hidden, disabled, or has the wrong selector, API tests won't catch it
2. **User experience validation**: We need to verify that users can actually complete the flow
3. **Integration testing**: We test JavaScript, CSS, form validation, event handlers - not just backend logic
4. **Screenshot documentation**: UI tests capture visual proof of the complete user journey

## When API Calls Are Acceptable

API calls are ONLY acceptable for:
- **Setup/teardown**: Creating test data before the test starts
- **Verification**: Checking database state after UI interactions
- **Authentication for non-auth tests**: If testing feature X, you can API-login first to skip the login flow

API calls are NEVER acceptable for:
- **The actual feature being tested**: If testing registration, must use the registration UI
- **User actions**: Clicking, typing, navigating must be done through UI
- **Form submission**: Must use actual buttons/forms, not POST requests

## Test Structure Template

```javascript
describe('Feature X Flow', () => {
  before(() => {
    // ✅ OK: Setup test data via API
    cy.request('POST', '/api/test/setup', {...});
  });

  it('Complete user journey through UI', () => {
    // ✅ REQUIRED: All user actions through UI
    cy.visit('/page');
    cy.get('selector').click();
    cy.get('input').type('value');
    cy.get('button').click();

    // ✅ OK: Verify with API if needed
    cy.request('/api/verify').then(response => {
      expect(response.body.status).to.equal('completed');
    });
  });
});
```

## Common Mistakes to Avoid

### Mistake 1: Hybrid approach
```javascript
// ❌ WRONG: Mixing UI and API for the same flow
cy.visit('/register');
cy.screenshot('page');
cy.register(name, race, password, email); // API shortcut
cy.visit('/index');
```

**Fix**: Do the entire registration through UI.

### Mistake 2: "It's too hard to interact with the UI"
If the UI is difficult to test, that's often a sign of:
- Complex selectors → Add `data-cy` attributes to elements
- Timing issues → Use proper `cy.wait()` or element assertions
- Hidden elements → Wait for them to appear or check if they should be visible

**Don't bypass the UI - fix the test approach!**

## Documentation

Every test should have comments explaining:
- What user action is being performed
- Why waits/delays are necessary
- What state is being verified

## Screenshot Requirements

For critical flows (registration, tutorial, checkout):
- ✅ Capture screenshots at EVERY major step
- ✅ Show intermediate states (form filling, validation, transitions)
- ✅ Verify final state with both screenshots AND assertions

## Review Checklist

Before submitting a test, verify:
- [ ] All user actions use `cy.get()`, `cy.click()`, `cy.type()` - NO `cy.request()` for tested features
- [ ] Screenshots capture the complete user journey
- [ ] Proper waits for dynamic content
- [ ] Assertions verify the user sees the expected result
- [ ] No API shortcuts in the tested flow

---

**Remember**: If a real user can't use the API, your test shouldn't either!
