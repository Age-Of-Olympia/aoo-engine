# Tutorial Admin Interface Issues and Improvements

**Date:** 2025-11-20
**Context:** Phase 4 - Admin interface not properly reflecting normalized database schema

---

## 🐛 CRITICAL ISSUES

### 1. **Missing `next_step` Field**
**Impact:** HIGH - Steps cannot link to each other, breaking tutorial flow

**Problem:**
- Database has `tutorial_steps.next_step` field (VARCHAR(50))
- Admin editor form DOES NOT have input for `next_step`
- Admin save handler DOES NOT save `next_step`

**Current Behavior:**
- All steps have `next_step = NULL`
- Tutorial cannot advance to next step
- Flow is broken

**Fix Required:**
```php
// In tutorial-step-editor.php (Basic Info tab)
<div class="form-group">
    <label for="next_step">Next Step ID</label>
    <input type="text" class="form-control" id="next_step" name="next_step"
           value="<?= $isEdit ? htmlspecialchars($step['next_step'] ?? '') : '' ?>">
    <small class="form-text text-muted">Step ID to advance to after completing this step (leave empty for final step)</small>
</div>

// In tutorial-step-save.php
// UPDATE query should include next_step
UPDATE tutorial_steps SET
    version = ?, step_id = ?, next_step = ?, step_number = ?, ...
WHERE id = ?

// INSERT query should include next_step
INSERT INTO tutorial_steps (version, step_id, next_step, step_number, ...)
VALUES (?, ?, ?, ?, ...)
```

---

### 2. **Missing UI Fields**
**Impact:** MEDIUM - Cannot configure all UI options

**Missing Fields:**
1. **`target_description`** (VARCHAR(255))
   - Human-readable description of target element
   - Used for accessibility and admin reference

2. **`highlight_selector`** (VARCHAR(500))
   - Alternative CSS selector for highlighting
   - Different from `target_selector` in some cases

3. **`allow_manual_advance`** (TINYINT(1))
   - Whether "Next" button should be shown
   - Important for auto-advance steps

**Current Status:**
- ❌ NOT in form
- ❌ NOT saved to database
- Default values used (may not be correct)

**Fix Required:**
```php
// In tutorial-step-editor.php (UI Config tab)
<div class="form-group">
    <label for="target_description">Target Description</label>
    <input type="text" class="form-control" id="target_description" name="target_description"
           value="<?= $isEdit && $stepUi ? htmlspecialchars($stepUi['target_description'] ?? '') : '' ?>">
    <small class="form-text text-muted">Human-readable description (e.g., "Characteristics button")</small>
</div>

<div class="form-group">
    <label for="highlight_selector">Highlight Selector</label>
    <input type="text" class="form-control font-monospace" id="highlight_selector" name="highlight_selector"
           value="<?= $isEdit && $stepUi ? htmlspecialchars($stepUi['highlight_selector'] ?? '') : '' ?>">
    <small class="form-text text-muted">Alternative CSS selector for highlighting (if different from target)</small>
</div>

<div class="form-check mb-3">
    <input type="checkbox" class="form-check-input" id="allow_manual_advance" name="allow_manual_advance" value="1"
           <?= $isEdit && $stepUi && $stepUi['allow_manual_advance'] ? 'checked' : 'checked' ?>>
    <label class="form-check-label" for="allow_manual_advance">
        Allow manual advance (show "Next" button)
    </label>
</div>

// In tutorial-step-save.php
INSERT INTO tutorial_step_ui (step_id, target_selector, target_description, highlight_selector,
    tooltip_position, interaction_mode, blocked_click_message, show_delay,
    auto_advance_delay, allow_manual_advance, auto_close_card, tooltip_offset_x, tooltip_offset_y)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
```

---

### 3. **Missing Validation Fields**
**Impact:** MEDIUM - Cannot configure all validation types

**Missing Fields:**
1. **`movement_count`** (INT)
   - Required number of movements for `specific_count` validation
   - Currently: No way to set this value

2. **`element_selector`** (VARCHAR(255))
   - CSS selector for `ui_element_hidden` validation
   - Currently: No way to set this value

3. **`action_charges_required`** (INT)
   - Number of times action must be used
   - Currently: Defaults to 1, no way to change

4. **`combat_required`** (TINYINT(1))
   - Whether combat is required for completion
   - Currently: No way to set this checkbox

5. **`dialog_id`** (VARCHAR(50))
   - Dialog that must be completed
   - Field exists in DB but not in admin form

**Fix Required:**
```php
// In tutorial-step-editor.php (Validation tab, inside #validationFields)
<div class="form-group">
    <label for="movement_count">Movement Count</label>
    <input type="number" class="form-control" id="movement_count" name="movement_count"
           value="<?= $isEdit && $stepValidation && $stepValidation['movement_count'] !== null ? $stepValidation['movement_count'] : '' ?>">
    <small class="form-text text-muted">For specific_count validation (number of movements required)</small>
</div>

<div class="form-group">
    <label for="element_selector">Element Selector</label>
    <input type="text" class="form-control font-monospace" id="element_selector" name="element_selector"
           value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['element_selector'] ?? '') : '' ?>">
    <small class="form-text text-muted">For ui_element_hidden validation (CSS selector of element that should be hidden)</small>
</div>

<div class="form-group">
    <label for="action_charges_required">Action Charges Required</label>
    <input type="number" class="form-control" id="action_charges_required" name="action_charges_required"
           value="<?= $isEdit && $stepValidation ? $stepValidation['action_charges_required'] : 1 ?>">
    <small class="form-text text-muted">Number of times action must be used (default: 1)</small>
</div>

<div class="form-check mb-3">
    <input type="checkbox" class="form-check-input" id="combat_required" name="combat_required" value="1"
           <?= $isEdit && $stepValidation && $stepValidation['combat_required'] ? 'checked' : '' ?>>
    <label class="form-check-label" for="combat_required">
        Combat required
    </label>
</div>

<div class="form-group">
    <label for="dialog_id">Dialog ID</label>
    <input type="text" class="form-control" id="dialog_id" name="dialog_id"
           value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['dialog_id'] ?? '') : '' ?>">
    <small class="form-text text-muted">Dialog that must be completed (references tutorial_dialogs.dialog_id)</small>
</div>

// In tutorial-step-save.php
INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint,
    target_x, target_y, movement_count, panel_id, element_selector, element_clicked,
    action_name, action_charges_required, combat_required, dialog_id)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

// Update VALUES array
[
    $dbStepId,
    isset($_POST['requires_validation']) ? 1 : 0,
    $_POST['validation_type'] ?? null,
    $_POST['validation_hint'] ?? null,
    !empty($_POST['target_x']) ? (int)$_POST['target_x'] : null,
    !empty($_POST['target_y']) ? (int)$_POST['target_y'] : null,
    !empty($_POST['movement_count']) ? (int)$_POST['movement_count'] : null,
    $_POST['panel_id'] ?? null,
    $_POST['element_selector'] ?? null,
    $_POST['element_clicked'] ?? null,
    $_POST['action_name'] ?? null,
    !empty($_POST['action_charges_required']) ? (int)$_POST['action_charges_required'] : 1,
    isset($_POST['combat_required']) ? 1 : 0,
    $_POST['dialog_id'] ?? null
]
```

---

### 4. **Missing Prerequisites Fields**
**Impact:** MEDIUM - Cannot configure harvestable tree spawning

**Missing Fields:**
1. **`spawn_enemy`** (VARCHAR(50))
   - Enemy type to spawn for combat steps
   - Currently: No way to set this value

2. **`ensure_harvestable_tree_x`** (INT)
   - X coordinate for harvestable tree
   - Currently: No way to set this value

3. **`ensure_harvestable_tree_y`** (INT)
   - Y coordinate for harvestable tree
   - Currently: No way to set this value

**Fix Required:**
```php
// In tutorial-step-editor.php (Prerequisites tab)
<hr>
<h5>Entity Setup</h5>

<div class="form-group">
    <label for="spawn_enemy">Spawn Enemy</label>
    <input type="text" class="form-control" id="spawn_enemy" name="spawn_enemy"
           value="<?= $isEdit && $stepPrerequisites ? htmlspecialchars($stepPrerequisites['spawn_enemy'] ?? '') : '' ?>">
    <small class="form-text text-muted">Enemy type to spawn (e.g., "tutorial_dummy")</small>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="ensure_harvestable_tree_x">Harvestable Tree X</label>
            <input type="number" class="form-control" id="ensure_harvestable_tree_x" name="ensure_harvestable_tree_x"
                   value="<?= $isEdit && $stepPrerequisites && $stepPrerequisites['ensure_harvestable_tree_x'] !== null ? $stepPrerequisites['ensure_harvestable_tree_x'] : '' ?>">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="ensure_harvestable_tree_y">Harvestable Tree Y</label>
            <input type="number" class="form-control" id="ensure_harvestable_tree_y" name="ensure_harvestable_tree_y"
                   value="<?= $isEdit && $stepPrerequisites && $stepPrerequisites['ensure_harvestable_tree_y'] !== null ? $stepPrerequisites['ensure_harvestable_tree_y'] : '' ?>">
        </div>
    </div>
</div>
<small class="form-text text-muted">Ensure a harvestable tree exists at these coordinates for gathering steps</small>

// In tutorial-step-save.php
$hasPrereq = !empty($_POST['mvt_required']) || !empty($_POST['pa_required']) ||
             isset($_POST['consume_movements']) || isset($_POST['unlimited_mvt']) || isset($_POST['unlimited_pa']) ||
             !empty($_POST['spawn_enemy']) || !empty($_POST['ensure_harvestable_tree_x']);

if ($hasPrereq) {
    $database->exe("
        INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore,
            consume_movements, unlimited_mvt, unlimited_pa, spawn_enemy,
            ensure_harvestable_tree_x, ensure_harvestable_tree_y)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $dbStepId,
        !empty($_POST['mvt_required']) ? (int)$_POST['mvt_required'] : null,
        !empty($_POST['pa_required']) ? (int)$_POST['pa_required'] : null,
        isset($_POST['auto_restore']) ? 1 : 0,
        isset($_POST['consume_movements']) ? 1 : 0,
        isset($_POST['unlimited_mvt']) ? 1 : 0,
        isset($_POST['unlimited_pa']) ? 1 : 0,
        !empty($_POST['spawn_enemy']) ? $_POST['spawn_enemy'] : null,
        !empty($_POST['ensure_harvestable_tree_x']) ? (int)$_POST['ensure_harvestable_tree_x'] : null,
        !empty($_POST['ensure_harvestable_tree_y']) ? (int)$_POST['ensure_harvestable_tree_y'] : null
    ]);
}
```

---

## 🔍 DATA TYPE ISSUES

### 5. **Incorrect NULL Handling in Save Handler**
**Impact:** MEDIUM - Empty numeric fields saved as 0 instead of NULL

**Problem:**
```php
// Line 100-101 in tutorial-step-save.php
(int)($_POST['target_x'] ?? null),  // ❌ This evaluates to 0, not NULL
(int)($_POST['target_y'] ?? null),  // ❌ This evaluates to 0, not NULL
```

**Issue:**
- `(int)(null)` = `0`
- Empty string '' becomes 0, not NULL
- Database expects NULL for "not set", not 0
- Target (0, 0) is a valid coordinate!

**Fix Required:**
```php
// Correct NULL handling
!empty($_POST['target_x']) ? (int)$_POST['target_x'] : null,
!empty($_POST['target_y']) ? (int)$_POST['target_y'] : null,
```

**Apply to ALL numeric fields that should be NULL when empty:**
- `target_x`, `target_y`
- `mvt_required`, `pa_required`
- `movement_count`
- `action_charges_required` (but default to 1, not NULL)
- `auto_advance_delay`
- `redirect_delay`
- `ensure_harvestable_tree_x`, `ensure_harvestable_tree_y`

---

## ⚠️ VALIDATION ISSUES

### 6. **No Client-Side Field Visibility Logic**
**Impact:** LOW - Confusing UX but no data loss

**Problem:**
- Validation parameters shown even when not relevant to validation type
- Example: `target_x`, `target_y` shown for `action_used` validation (not needed)
- Example: `action_name` shown for `position` validation (not needed)

**Fix Suggestion:**
Add JavaScript to show/hide validation parameter fields based on `validation_type` selection:

```javascript
// In tutorial-step-editor.php
document.getElementById('validation_type').addEventListener('change', function() {
    const type = this.value;

    // Hide all
    document.querySelectorAll('.validation-param').forEach(el => el.style.display = 'none');

    // Show relevant fields
    if (type === 'position' || type === 'adjacent_to_position') {
        document.querySelector('.param-coords').style.display = 'block';
    } else if (type === 'action_used') {
        document.querySelector('.param-action').style.display = 'block';
    } else if (type === 'specific_count') {
        document.querySelector('.param-movement-count').style.display = 'block';
    } else if (type === 'ui_panel_opened') {
        document.querySelector('.param-panel').style.display = 'block';
    } else if (type === 'ui_interaction') {
        document.querySelector('.param-element-clicked').style.display = 'block';
    } else if (type === 'ui_element_hidden') {
        document.querySelector('.param-element-selector').style.display = 'block';
    }
});
```

---

## 📊 DISPLAY ISSUES

### 7. **Dashboard Query Missing `next_step`**
**Impact:** LOW - Admin cannot see tutorial flow at a glance

**Problem:**
```php
// admin/tutorial.php line 84
SELECT
    ts.id, ts.version, ts.step_number, ts.step_id, ts.step_type, ...
FROM tutorial_steps ts
```

`next_step` is not in SELECT, so admins cannot see step flow.

**Fix Required:**
```php
SELECT
    ts.id,
    ts.version,
    ts.step_number,
    ts.step_id,
    ts.next_step,  // ADD THIS
    ts.step_type,
    ...
```

Add column to table display:
```php
<th>Next Step</th>
...
<td><code><?= $step['next_step'] ?? '<em>end</em>' ?></code></td>
```

---

## 🎨 UX IMPROVEMENTS

### 8. **No Visual Step Flow Diagram**
**Impact:** LOW - Nice to have

**Suggestion:**
Add a visual flow diagram showing step connections:
- Each step as a node
- Arrows showing `next_step` relationships
- Color-coded by `step_type`
- Could use Mermaid.js or simple SVG

### 9. **No Bulk Operations**
**Impact:** LOW - Nice to have

**Suggestions:**
- Bulk enable/disable steps
- Duplicate step feature
- Reorder steps (change step_number)
- Export/Import steps as JSON

### 10. **No Step Preview**
**Impact:** MEDIUM - Hard to test changes

**Suggestion:**
Add "Preview Step" button in editor:
- Opens modal with simulated tutorial tooltip
- Shows how step will appear to user
- Tests CSS selectors against current DOM

---

## 🚀 PRIORITY FIX LIST

### Critical (Must Fix)
1. ✅ Add `next_step` field to form and save handler
2. ✅ Fix NULL handling for numeric fields

### High Priority
3. ✅ Add missing UI fields (target_description, highlight_selector, allow_manual_advance)
4. ✅ Add missing validation fields (movement_count, element_selector, action_charges_required, combat_required, dialog_id)
5. ✅ Add missing prerequisite fields (spawn_enemy, ensure_harvestable_tree_*)

### Medium Priority
6. ⏸️ Add conditional field visibility based on validation_type
7. ⏸️ Show next_step in dashboard table

### Low Priority (Nice to Have)
8. ⏸️ Visual flow diagram
9. ⏸️ Bulk operations
10. ⏸️ Step preview feature

---

## 📝 TESTING CHECKLIST

After fixes, test:
- [ ] Create new step with all fields populated
- [ ] Edit existing step and verify all fields load correctly
- [ ] Save step and verify all tables are populated
- [ ] Verify NULL values saved correctly (not 0)
- [ ] Test step flow (step A → next_step → step B)
- [ ] Test harvestable tree prerequisites
- [ ] Test spawn_enemy prerequisites
- [ ] Test all validation types
- [ ] Verify dashboard shows complete information

---

## Status

🔴 **Critical issues identified** - Admin interface incomplete
🟡 **Partial functionality** - Basic steps can be created but missing key fields
🟢 **Ready for fixes** - All issues documented with solutions
