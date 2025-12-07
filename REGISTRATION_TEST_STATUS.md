# Registration Test - UI Testing Progress

## Principle Documented ✅

Created **`CYPRESS_E2E_TESTING_PRINCIPLES.md`** documenting:
- ❌ NEVER use API calls (`cy.request()`, `cy.register()`) for the feature being tested
- ✅ ALWAYS test through the actual user interface
- Why this matters: UI bugs are invisible to API tests
- When API calls ARE acceptable (setup/teardown only)

## Test Fixed ✅

**Before**: Hybrid approach using `cy.register()` API call - WRONG!
**After**: Complete UI interaction through the registration dialog flow

## Registration Flow Discovered

The registration is NOT a simple form - it's a **multi-step dialog system**:

### Step 1: Race Selection Dialog
- User sees 5 race options (Elfe, Géant, Homme-Sauvage, Nain, Olympien)
- Click on chosen race

### Step 2: Race Confirmation
- Dialog shows race description
- Options: "Va pour un [race]" or "Finalement, non"
- Click to confirm

### Step 3: Name Entry
- Dialog asks "Peux-tu me dire le nom que portera ton incarnation?"
- Enter character name in input field
- Click "[continuer]"

### Step 4: Final Confirmation
- Dialog: "Va, maintenant, je te créérai un grand destin."
- Options: "Soit. [se réincarner]" or "NON! Attends! [recommencer]"
- Click to proceed

### Step 5: Full Registration Form
- **NOW** the actual form appears with:
  - Password fields (psw1, psw2)
  - Email field (mail)
  - CGU checkbox
  - Submit button

### Step 6: Login
- Visit login page
- Fill name and password through UI
- Submit

### Step 7+: Nouveau Tour page, Game verification, Tutorial check

## Screenshots Captured

The test now captures **13+ screenshots** documenting the entire journey:

```
01-registration-page.png              # Initial page with dialog
02a-race-selected-in-dialog.png       # After clicking race
02b-race-confirmed.png                # After confirming race
02c-name-entered-in-dialog.png        # After entering name
02d-continue-clicked.png              # After clicking continue
02e-final-confirmation.png            # Final confirmation dialog
03-registration-form-loaded.png       # The actual form appears
03a-password1-entered.png             # Form filling
03b-password2-entered.png
03c-email-entered.png
03d-cgu-checked.png
04-registration-submitted.png         # After submit
05-login-page.png                     # Login page
06a-login-form-filled.png            # (if reaches this step)
06b-after-login.png
...and continuing through the flow
```

## Current Status

✅ Dialog flow completely tested through UI
✅ Registration form filled through UI
✅ Screenshots capture every step
❌ Test failing at login step (need to debug)

The test NOW properly follows E2E testing principles - testing what users actually see and click!

## Next Steps

1. Debug the login failure (check error message)
2. Ensure login form interaction is correct
3. Complete the flow through Nouveau Tour and game verification

## Lessons Learned

1. **Never assume the UI structure** - the registration wasn't a simple form, it was a complex dialog flow
2. **Screenshots are essential** - they reveal the actual UI flow
3. **API shortcuts hide complexity** - we would never have discovered this 4-step dialog process if we'd used `cy.register()`
4. **Real UI testing is harder but more valuable** - it tests the actual user experience

---

This registration test now serves as a perfect example of **proper E2E testing**: every click, every form field, every dialog step is tested through the UI, just like a real user would experience it.
