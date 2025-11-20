# Tutorial Admin Interface - Code Quality Analysis

**Date:** 2025-11-20
**Context:** Phase 4 - Post-fixes code quality review

---

## 📊 OVERALL ASSESSMENT

**Rating:** 🟡 **NEEDS IMPROVEMENT** (3/5)

The admin interface is functional and serves its purpose, but has several code quality issues that should be addressed for maintainability, security, and user experience.

---

## 🎯 STRENGTHS

### 1. ✅ Clean Separation of Concerns
- **tutorial.php**: Dashboard/list view
- **tutorial-step-editor.php**: Form/edit view
- **tutorial-step-save.php**: Save handler

This follows MVC pattern reasonably well.

### 2. ✅ Transaction Support
```php
// tutorial-step-save.php:27
$database->beginTransaction();
try {
    // ... multiple inserts/updates
    $database->commit();
} catch (Exception $e) {
    $database->rollback();
}
```
Proper ACID compliance for multi-table operations.

### 3. ✅ Cascade Deletion
```php
// tutorial-step-save.php:71-72
$database->exe("DELETE FROM tutorial_step_ui WHERE step_id = ?", [$dbStepId]);
$database->exe("INSERT INTO tutorial_step_ui (...) VALUES (...)");
```
Clean pattern: delete old data, insert fresh data (prevents orphans).

### 4. ✅ Flash Messages
```php
$_SESSION['flash'] = [
    'type' => 'success',
    'message' => 'Step created successfully!'
];
```
User feedback pattern is good.

---

## 🐛 CODE SMELLS & ANTI-PATTERNS

### 1. ❌ **SQL Injection Risk** (CRITICAL)
**Location:** tutorial.php:84-103, tutorial-step-editor.php:30-74

**Problem:** While using prepared statements (good!), the code doesn't validate/sanitize user input before database operations.

**Example:**
```php
// tutorial-step-save.php:40
!empty($_POST['step_id']) ? $_POST['step_id'] : null
```

No validation that `$_POST['step_id']` is:
- A valid string format
- Not exceeding VARCHAR(50)
- Not containing malicious characters

**Risk Level:** MEDIUM (prepared statements provide protection, but input validation is defense-in-depth)

**Recommendation:**
```php
// Add input validation function
function validateStepId(?string $value): ?string {
    if (empty($value)) return null;

    // Max 50 chars, alphanumeric + underscore + hyphen
    if (strlen($value) > 50 || !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
        throw new InvalidArgumentException("Invalid step_id format");
    }

    return $value;
}

// Usage
$stepId = validateStepId($_POST['step_id'] ?? null);
```

---

### 2. ❌ **XSS Vulnerability** (CRITICAL)
**Location:** Multiple locations in tutorial.php and tutorial-step-editor.php

**Problem:** User-generated content rendered without consistent escaping.

**Examples:**
```php
// GOOD (escaped)
<td><?=htmlspecialchars($step['title'])?></td>

// BAD (not escaped)
<small class="form-text text-muted">Step type: <?=$step['step_type']?></small>
```

**Location:** tutorial-step-editor.php:257, 267, 351, etc.

**Risk Level:** HIGH (allows stored XSS attacks)

**Recommendation:**
- Create helper function for output escaping:
```php
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Usage
<td><?=e($step['title'])?></td>
```

---

### 3. ❌ **No CSRF Protection**
**Location:** All forms (tutorial-step-editor.php, tutorial.php inline forms)

**Problem:** Forms don't include CSRF tokens to prevent cross-site request forgery.

**Example Attack:**
```html
<!-- Attacker's site -->
<form action="https://your-game.com/admin/tutorial-step-save.php" method="POST">
    <input type="hidden" name="db_step_id" value="1">
    <input type="hidden" name="title" value="HACKED">
    <!-- Auto-submit -->
</form>
```

**Risk Level:** HIGH

**Recommendation:**
```php
// In config.php or similar
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In forms
<input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">

// In save handler
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    throw new SecurityException('CSRF token mismatch');
}
```

---

### 4. ❌ **Magic Numbers** (MEDIUM)
**Location:** tutorial-step-save.php:114

**Problem:**
```php
!empty($_POST['action_charges_required']) ? (int)$_POST['action_charges_required'] : 1
```

Why 1? What does it represent?

**Recommendation:**
```php
const DEFAULT_ACTION_CHARGES = 1;

// Usage
!empty($_POST['action_charges_required'])
    ? (int)$_POST['action_charges_required']
    : self::DEFAULT_ACTION_CHARGES
```

---

### 5. ❌ **Repeated Code** (MEDIUM)
**Location:** tutorial-step-save.php:72-93 (UI), 95-117 (validation), 119-144 (prerequisites)

**Problem:** Same pattern repeated 9 times:
```php
$database->exe("DELETE FROM table WHERE step_id = ?", [$dbStepId]);
$database->exe("INSERT INTO table (...) VALUES (...)", [...]);
```

**Recommendation:** Extract to helper method:
```php
private function replaceTableData(string $table, int $stepId, array $columns, array $values): void {
    $this->database->exe("DELETE FROM {$table} WHERE step_id = ?", [$stepId]);

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $columnList = implode(', ', $columns);

    $this->database->exe(
        "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})",
        $values
    );
}

// Usage
$this->replaceTableData('tutorial_step_ui', $dbStepId, [
    'step_id', 'target_selector', 'target_description', ...
], [
    $dbStepId,
    !empty($_POST['target_selector']) ? $_POST['target_selector'] : null,
    ...
]);
```

---

### 6. ❌ **God Method** (HIGH)
**Location:** tutorial-step-save.php:26-212 (entire try block)

**Problem:** 186 lines doing everything:
- Basic step save
- UI config save
- Validation config save
- Prerequisites save
- Interactions save
- Highlights save
- Context changes save
- Next preparation save
- Features save

**Single Responsibility Principle Violation:** This method does 9 different things.

**Recommendation:** Extract to separate methods:
```php
try {
    $database->beginTransaction();

    $dbStepId = $this->saveBasicStepData($isEdit, $dbStepId);
    $this->saveUIConfig($dbStepId);
    $this->saveValidationConfig($dbStepId);
    $this->savePrerequisites($dbStepId);
    $this->saveInteractions($dbStepId);
    $this->saveHighlights($dbStepId);
    $this->saveContextChanges($dbStepId);
    $this->saveNextPreparation($dbStepId);
    $this->saveFeatures($dbStepId);

    $database->commit();
} catch (Exception $e) {
    $database->rollback();
    // ...
}

private function saveBasicStepData(bool $isEdit, ?int $dbStepId): int {
    // 40 lines -> now in focused method
}

private function saveUIConfig(int $dbStepId): void {
    // 20 lines -> now in focused method
}
// ... etc
```

---

### 7. ❌ **Inconsistent NULL Handling** (MEDIUM)
**Location:** Throughout tutorial-step-save.php

**Problem:** Mix of patterns:
```php
// Pattern 1: Ternary with !empty
!empty($_POST['target_selector']) ? $_POST['target_selector'] : null

// Pattern 2: Ternary with ??
$_POST['tooltip_position'] ?? 'bottom'

// Pattern 3: isset() with ternary
isset($_POST['allow_manual_advance']) ? 1 : 0

// Pattern 4: Direct cast (WRONG!)
(int)$_POST['target_x']  // Empty string -> 0, not NULL!
```

**Recommendation:** Standardize on helper functions:
```php
function optionalString(string $key): ?string {
    return !empty($_POST[$key]) ? $_POST[$key] : null;
}

function optionalInt(string $key): ?int {
    return !empty($_POST[$key]) ? (int)$_POST[$key] : null;
}

function booleanCheckbox(string $key): bool {
    return isset($_POST[$key]);
}

function stringWithDefault(string $key, string $default): string {
    return $_POST[$key] ?? $default;
}

// Usage
$targetSelector = optionalString('target_selector');
$targetX = optionalInt('target_x');
$isActive = booleanCheckbox('is_active');
$tooltipPosition = stringWithDefault('tooltip_position', 'bottom');
```

---

### 8. ❌ **No Type Safety** (MEDIUM)
**Location:** All admin files

**Problem:** No type hints, no return types, no strict_types.

**Example:**
```php
// Current (weak typing)
<?php
require_once __DIR__ . '/layout.php';
use Classes\Db;
$database = new Db();

// Better (strict typing)
<?php declare(strict_types=1);

require_once __DIR__ . '/layout.php';

use Classes\Db;

final class TutorialAdminController {
    private Db $database;

    public function __construct(Db $database) {
        $this->database = $database;
    }

    public function index(): string {
        // Return HTML
    }
}
```

---

### 9. ❌ **Procedural in OOP Codebase** (LOW)
**Location:** All admin files are procedural scripts

**Problem:** Rest of codebase uses PSR-4 autoloading and OOP (src/), but admin is procedural.

**Inconsistency:** Makes code harder to test, maintain, and refactor.

**Recommendation:** Refactor to controller classes:
```
admin/
├── Controllers/
│   ├── TutorialAdminController.php
│   ├── TutorialStepEditorController.php
│   └── TutorialStepSaveController.php
├── Services/
│   ├── TutorialStepFormService.php
│   └── TutorialStepValidationService.php
└── Views/
    ├── tutorial-list.php
    └── tutorial-editor-form.php
```

---

### 10. ❌ **No Input Validation** (HIGH)
**Location:** tutorial-step-save.php (entire file)

**Problem:** No validation before database operations:
- `step_number` could be negative
- `xp_reward` could be negative
- `step_type` could be invalid enum value
- `validation_type` could be invalid
- Coordinates could be out of map bounds

**Example:**
```php
// Current (no validation)
(float)$_POST['step_number']  // Could be -999999.9

// Should be
$stepNumber = (float)$_POST['step_number'];
if ($stepNumber < 0 || $stepNumber > 999) {
    throw new InvalidArgumentException('Step number must be between 0 and 999');
}
```

**Recommendation:** Create validation service:
```php
class TutorialStepValidator {
    private const VALID_STEP_TYPES = [
        'info', 'welcome', 'dialog', 'movement', 'movement_limit',
        'action', 'action_intro', 'ui_interaction', 'combat',
        'combat_intro', 'exploration'
    ];

    private const VALID_VALIDATION_TYPES = [
        'any_movement', 'movements_depleted', 'position',
        'adjacent_to_position', 'action_used', 'ui_panel_opened',
        'ui_element_hidden', 'ui_interaction'
    ];

    public function validateStepNumber(float $number): float {
        if ($number < 0 || $number > 999) {
            throw new ValidationException('Step number must be between 0 and 999');
        }
        return $number;
    }

    public function validateStepType(string $type): string {
        if (!in_array($type, self::VALID_STEP_TYPES)) {
            throw new ValidationException("Invalid step type: {$type}");
        }
        return $type;
    }

    public function validateCoordinate(?int $coord): ?int {
        if ($coord !== null && ($coord < -100 || $coord > 100)) {
            throw new ValidationException('Coordinate must be between -100 and 100');
        }
        return $coord;
    }
}
```

---

### 11. ❌ **Inline SQL Strings** (MEDIUM)
**Location:** Multiple locations

**Problem:** SQL strings embedded in procedural code makes them hard to:
- Test
- Reuse
- Maintain
- Read

**Example:**
```php
// Current (inline SQL, hard to read)
$database->exe("
    INSERT INTO tutorial_step_ui (step_id, target_selector, target_description, highlight_selector,
        tooltip_position, interaction_mode, blocked_click_message, show_delay, auto_advance_delay,
        allow_manual_advance, auto_close_card, tooltip_offset_x, tooltip_offset_y)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
", [...13 parameters...]);
```

**Recommendation:** Use query builder or repository pattern:
```php
class TutorialStepRepository {
    public function saveUIConfig(int $stepId, UIConfig $config): void {
        $this->db->delete('tutorial_step_ui', ['step_id' => $stepId]);

        $this->db->insert('tutorial_step_ui', [
            'step_id' => $stepId,
            'target_selector' => $config->targetSelector,
            'target_description' => $config->targetDescription,
            // ... etc
        ]);
    }
}
```

---

### 12. ❌ **No Error Messages** (MEDIUM)
**Location:** tutorial-step-save.php:202-212

**Problem:** Generic error message:
```php
$_SESSION['flash'] = [
    'type' => 'danger',
    'message' => 'Error saving step: ' . $e->getMessage()
];
```

Shows raw exception message to user (could leak sensitive info).

**Recommendation:**
```php
} catch (ValidationException $e) {
    // User-friendly validation errors
    $_SESSION['flash'] = [
        'type' => 'warning',
        'message' => $e->getMessage()  // Safe, user-facing message
    ];
} catch (DatabaseException $e) {
    // Log technical details
    error_log("Database error saving step: " . $e->getMessage());

    // Generic message to user
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Failed to save step. Please try again or contact support.'
    ];
} catch (Exception $e) {
    // Unexpected errors
    error_log("Unexpected error: " . $e->getMessage());
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'An unexpected error occurred. Please try again later.'
    ];
}
```

---

## 🔒 SECURITY ISSUES SUMMARY

| Issue | Risk | Location | Priority |
|-------|------|----------|----------|
| No CSRF protection | HIGH | All forms | CRITICAL |
| Potential XSS | HIGH | Multiple locations | CRITICAL |
| No input validation | HIGH | tutorial-step-save.php | HIGH |
| SQL injection risk | MEDIUM | All database queries | MEDIUM |
| Error message leakage | MEDIUM | Exception handling | MEDIUM |

---

## 🎨 UI/UX ISSUES

### 1. ❌ **No Client-Side Validation**
Forms don't validate before submission:
- Required fields not marked with HTML5 `required`
- No min/max constraints on numbers
- No pattern validation on text fields

**Recommendation:**
```html
<input
    type="number"
    name="step_number"
    min="0"
    max="999"
    step="0.1"
    required
    oninvalid="this.setCustomValidity('Step number must be between 0 and 999')"
    oninput="this.setCustomValidity('')"
>
```

### 2. ❌ **No Unsaved Changes Warning**
User can navigate away from editor without warning, losing unsaved work.

**Recommendation:**
```javascript
let formModified = false;

document.getElementById('stepForm').addEventListener('change', () => {
    formModified = true;
});

window.addEventListener('beforeunload', (e) => {
    if (formModified) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});
```

### 3. ❌ **No Loading States**
Form submission has no loading indicator - users may click multiple times.

**Recommendation:**
```javascript
document.getElementById('stepForm').addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});
```

---

## 📈 PERFORMANCE ISSUES

### 1. ⚠️ **N+1 Query Problem**
**Location:** tutorial.php:53-56

**Problem:**
```php
// Load interactions
$result = $database->exe("SELECT * FROM tutorial_step_interactions WHERE step_id = ? ORDER BY id", [$stepId]);
```

This is called INSIDE the step loading loop, causing N+1 queries.

**Current:** 1 query for steps + N queries for interactions = N+1 queries
**Should be:** 2 queries total using JOIN or GROUP_CONCAT

**Recommendation:**
```php
SELECT
    ts.*,
    GROUP_CONCAT(tsi.selector) as interactions
FROM tutorial_steps ts
LEFT JOIN tutorial_step_interactions tsi ON ts.id = tsi.step_id
GROUP BY ts.id
```

---

## 🧪 TESTABILITY ISSUES

### 1. ❌ **Not Testable** (HIGH)
Procedural code with direct `$_POST`, `$_SESSION`, `header()` calls cannot be unit tested.

**Recommendation:** Refactor to dependency injection:
```php
class TutorialStepSaveController {
    public function __construct(
        private TutorialStepRepository $repository,
        private RequestInterface $request,
        private SessionInterface $session,
        private ResponseInterface $response
    ) {}

    public function save(): Response {
        $data = $this->request->post();

        try {
            $stepId = $this->repository->saveStep($data);
            $this->session->flash('success', 'Step saved!');
            return $this->response->redirect('/admin/tutorial.php');
        } catch (Exception $e) {
            $this->session->flash('error', $e->getMessage());
            return $this->response->redirect('/admin/tutorial-step-editor.php');
        }
    }
}

// Now testable:
public function testSaveStepSuccess(): void {
    $repository = $this->createMock(TutorialStepRepository::class);
    $request = new FakeRequest(['title' => 'Test']);
    $session = new FakeSession();
    $response = new FakeResponse();

    $controller = new TutorialStepSaveController($repository, $request, $session, $response);
    $result = $controller->save();

    $this->assertInstanceOf(RedirectResponse::class, $result);
    $this->assertEquals('/admin/tutorial.php', $result->getUrl());
}
```

---

## 🏆 RECOMMENDED REFACTORING PLAN

### Phase 1: Security (CRITICAL - Do First)
1. ✅ Add CSRF token generation and validation
2. ✅ Add input validation service
3. ✅ Escape all output consistently
4. ✅ Improve error handling (don't leak details)

### Phase 2: Code Quality (HIGH - Do Soon)
5. ✅ Extract god method into smaller methods
6. ✅ Create helper functions for NULL handling
7. ✅ Add type hints and strict_types
8. ✅ Remove code duplication

### Phase 3: Architecture (MEDIUM - Do Eventually)
9. ⏸️ Refactor to OOP controllers
10. ⏸️ Create repository layer
11. ⏸️ Add dependency injection
12. ⏸️ Make code unit testable

### Phase 4: UX (LOW - Nice to Have)
13. ⏸️ Add client-side validation
14. ⏸️ Add unsaved changes warning
15. ⏸️ Add loading states
16. ⏸️ Add step preview feature

---

## 📚 BEST PRACTICES TO ADOPT

### 1. **Defense in Depth**
- Never trust user input
- Validate at multiple layers (client, server, database)
- Use prepared statements + input validation + output escaping

### 2. **Fail Secure**
- Default to most restrictive behavior
- Require explicit opt-in for dangerous actions
- Log security-relevant events

### 3. **SOLID Principles**
- **S**ingle Responsibility: Each method does one thing
- **O**pen/Closed: Open for extension, closed for modification
- **L**iskov Substitution: Subtypes must be substitutable
- **I**nterface Segregation: Many specific interfaces > one general
- **D**ependency Inversion: Depend on abstractions, not concretions

### 4. **DRY (Don't Repeat Yourself)**
- Extract repeated patterns to functions/methods
- Use configuration over code duplication
- Share code via inheritance or composition

---

## 📊 METRICS

### Code Complexity
- **Cyclomatic Complexity:** HIGH (god method)
- **Lines of Code:** 746 total, 186 in one method (too high)
- **Depth of Nesting:** 3-4 levels (acceptable)

### Maintainability Index
- **Current:** ~40/100 (NEEDS IMPROVEMENT)
- **Target:** 70+/100

### Test Coverage
- **Current:** 0% (no tests)
- **Target:** 80%+

---

## ✅ CONCLUSION

The admin interface **works**, but has significant technical debt:

**Critical Issues:**
- 🔴 No CSRF protection
- 🔴 Inconsistent XSS prevention
- 🔴 No input validation
- 🔴 God method (186 lines)

**Medium Issues:**
- 🟡 Code duplication
- 🟡 Procedural in OOP codebase
- 🟡 Not testable
- 🟡 Inconsistent NULL handling

**Low Issues:**
- 🟢 No client-side validation
- 🟢 No loading states
- 🟢 Magic numbers

**Priority:** Address critical security issues first, then refactor for maintainability.
