# Tutorial System - Testing Guide (Manual Testing - Part D)

**Date**: 2025-11-13
**Status**: Ready for Testing
**Tester**: You!

---

## ✅ What Was Implemented (A, B, C - Complete)

### A) Updated All 14 Tutorial Steps ✅
All steps now have:
- `allowed_interactions` - Specific clickable elements in semi-blocking mode
- `prerequisites` - Resource requirements (mvt, actions) with `auto_restore`
- `prepare_next_step` - Resource restoration for following step
- `blocked_click_message` - Helpful messages when clicking wrong areas
- `target_description` - Auto-generates messages

### B) Server-Side Resource Restoration ✅
New methods in `TutorialContext.php`:
- `ensurePrerequisites()` - Checks and auto-restores resources
- `prepareForNextStep()` - Prepares resources after step completes
- Called automatically by `TutorialManager` before/after each step

### C) Cache-Busting Updated ✅
- `TutorialView.php` - Updated to load new modular JS/CSS files
- Version: `?v=20251113` on all tutorial files
- Old `js/tutorial.js` replaced with:
  - `js/tutorial/TutorialUI.js`
  - `js/tutorial/TutorialHighlighter.js`
  - `js/tutorial/TutorialTooltip.js`
  - `js/tutorial/TutorialInit.js`
  - `css/tutorial/tutorial.css`

---

## 🧪 Testing Instructions

### Pre-Testing Setup

#### 1. Repopulate Tutorial Steps in Database

The database still has old step configurations. Update them:

```bash
cd /var/www/html
php scripts/tutorial/populate_tutorial_steps.php
```

**Expected Output**:
```
=== Populating Tutorial Steps ===

Clearing existing steps for version 1.0.0...
✓ Step 0: Bienvenue! (5 XP)
✓ Step 1: Un jeu au tour par tour (5 XP)
✓ Step 2: Vous voici! (5 XP)
...
✓ Step 13: Tutoriel terminé! (50 XP)

=== Summary ===
Successfully inserted: 14 steps
Errors: 0
Total XP available: 175 XP

=== Verification ===
Steps in database: 14

✓ Tutorial steps population complete!
```

#### 2. Clear Browser Cache

**CRITICAL**: Clear your browser cache or use hard refresh:
- Chrome/Edge: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
- Firefox: `Ctrl+F5` (Windows) or `Cmd+Shift+R` (Mac)

Or use DevTools:
- Open DevTools (F12)
- Right-click refresh button → "Empty Cache and Hard Reload"

---

## 🎯 Test Cases

### Test 1: Blocking Mode (Info Steps)

**Steps:**
1. Start tutorial (Step 0 or 1)
2. Try clicking on the map
3. Try clicking on UI elements

**Expected Behavior:**
- ✅ Dark overlay blocks clicks (60% opacity)
- ✅ Tooltip shakes when you click blocked area
- ✅ Warning message appears in tooltip: "Lisez les instructions et cliquez sur 'Suivant'..."
- ✅ Pulsating ⚔️ icon appears
- ✅ Message auto-dismisses after 4 seconds
- ✅ Only "Suivant" button works

**How to Verify:**
- Open browser console (F12)
- Look for: `[TutorialUI] Applying interaction mode: blocking`

---

### Test 2: Semi-Blocking Mode (Movement Step)

**Steps:**
1. Advance to Step 4 (Votre premier mouvement)
2. Try clicking on non-adjacent tiles
3. Try clicking on UI elements
4. Click on adjacent tile (should work)

**Expected Behavior:**
- ✅ Medium overlay visible (50% opacity)
- ✅ Adjacent tiles are highlighted/clickable
- ✅ Clicking non-adjacent area → tooltip shakes + message
- ✅ Message: "Pour vous déplacer, cliquez sur une case adjacente (en vert)"
- ✅ Clicking adjacent tile works and advances step

**How to Verify:**
- Console: `[TutorialUI] Applying interaction mode: semi-blocking`
- Console: `[TutorialUI] Allowing interactions: [".tile.adjacent", ".go"]`

---

### Test 3: Resource Prerequisites (Step 4)

**Steps:**
1. Reach Step 4 (first movement)
2. Check console for resource logs

**Expected Behavior:**
- ✅ Console shows: `[TutorialContext] Restored movement: 0 → 1`
- ✅ Player has at least 1 movement to complete step
- ✅ Step can be completed

**How to Verify:**
- Console: `[TutorialUI] Checking prerequisites: {mvt: 1, auto_restore: true}`
- Console: `[TutorialUI] Step requires 1 movement(s)`

---

### Test 4: Movement Depletion (Step 5)

**Steps:**
1. Reach Step 5 (Mouvements limités)
2. Check starting movement: should be 4
3. Move 4 times
4. Try to move again (should be blocked)

**Expected Behavior:**
- ✅ Step starts with exactly 4 movements
- ✅ Console: `[TutorialContext] Restored movement: X → 4`
- ✅ Can move 4 times
- ✅ After 4 moves, cannot move anymore
- ✅ Step advances when movements depleted

**How to Verify:**
- Console: `[TutorialContext] Prepared movement for next step: 4`
- Check resource display shows `0/4` after 4 moves

---

### Test 5: Resource Chain (Steps 5 → 6 → 7)

**Steps:**
1. Complete Step 5 (deplete movements)
2. Advance to Step 6 (info step - no resources needed)
3. Advance to Step 7 (action intro - needs resources)

**Expected Behavior:**
- ✅ Step 5 → depletes movements to 0
- ✅ Step 5 completion → prepares 4 mvt + 2 actions for next steps
- ✅ Step 6 → info only, no movement needed
- ✅ Step 7 → has 4 mvt + 2 actions available
- ✅ Console shows restoration logs

**How to Verify:**
- Console after Step 5: `[TutorialContext] Prepared movement for next step: 4`
- Console after Step 5: `[TutorialContext] Prepared actions for next step: 2`
- Console at Step 7: `[TutorialContext] Restored movement: X → 4`
- Console at Step 7: `[TutorialContext] Restored actions: X → 2`

---

### Test 6: Action Step (Step 9)

**Steps:**
1. Reach Step 9 (Fouiller action)
2. Try clicking other UI elements
3. Click "Fouiller" button

**Expected Behavior:**
- ✅ Only "Fouiller" button is clickable
- ✅ Clicking elsewhere → tooltip shakes
- ✅ Message: "Pour continuer, cliquez sur le bouton 'Fouiller'..."
- ✅ Clicking Fouiller executes action and advances
- ✅ Has 1 action point available (auto-restored)

**How to Verify:**
- Console: `[TutorialUI] Allowing interactions: [".action[data-action='fouiller']", ...]`
- Console: `[TutorialContext] Restored actions: X → 1`

---

### Test 7: Combat Step (Step 12)

**Steps:**
1. Reach Step 12 (combat)
2. Check if enemy is present
3. Try to attack

**Expected Behavior:**
- ✅ Enemy spawned: "Âme d'entraînement"
- ✅ Console: `[TutorialContext] Marking enemy to spawn: tutorial_dummy`
- ✅ Can click on enemy
- ✅ Can click attack button
- ✅ Other elements blocked

**How to Verify:**
- Console: `[TutorialContext] Will spawn enemy for next step: tutorial_dummy`
- Console: `[TutorialUI] Allowing interactions: [".enemy.tutorial", ...]`

**Note**: Actual enemy spawning needs game logic integration - may not work yet.

---

### Test 8: Complete Tutorial Flow (All 14 Steps)

**Steps:**
1. Start tutorial from Step 0
2. Complete all 14 steps sequentially
3. Verify each step can be completed

**Expected Behavior:**
- ✅ All 14 steps can be completed without errors
- ✅ No step fails due to missing resources
- ✅ Blocking overlay works on info steps
- ✅ Semi-blocking overlay works on action steps
- ✅ Resource chain is coherent throughout
- ✅ XP accumulates (should reach 175 XP total)

**How to Verify:**
- Complete without JavaScript errors
- Check final XP: 175
- No console errors about missing prerequisites

---

## 🐛 Common Issues & Solutions

### Issue 1: JavaScript Not Loading
**Symptom**: No overlay, tutorial doesn't start
**Solution**:
- Clear browser cache (Ctrl+Shift+R)
- Check console for 404 errors
- Verify files exist in `js/tutorial/` and `css/tutorial/`

### Issue 2: Old Behavior Still Showing
**Symptom**: Can click everywhere, no blocking
**Solution**:
- Browser cached old `tutorial.js`
- Hard refresh (Ctrl+Shift+R)
- Check DevTools → Network tab → verify `?v=20251113` is loading

### Issue 3: Steps Not Updated
**Symptom**: Missing new configuration, wrong messages
**Solution**:
- Run: `php scripts/tutorial/populate_tutorial_steps.php`
- Verify database updated: `SELECT COUNT(*) FROM tutorial_configurations`
- Check step has `config` JSON with new fields

### Issue 4: Resources Not Restored
**Symptom**: Step can't be completed, missing mvt/actions
**Solution**:
- Check console for: `[TutorialContext] Restored movement/actions`
- Verify step config has `prerequisites.auto_restore: true`
- Check previous step has `prepare_next_step` configuration

### Issue 5: Tooltip Doesn't Shake
**Symptom**: No visual feedback when clicking blocked areas
**Solution**:
- Check CSS loaded: `tutorial.css?v=20251113`
- Verify overlay has class: `#tutorial-overlay.blocking` or `.semi-blocking`
- Check console: `[TutorialUI] Applying interaction mode: ...`

---

## 📋 Testing Checklist

Use this to track your testing:

### Setup
- [ ] Run `populate_tutorial_steps.php` to update database
- [ ] Clear browser cache / hard refresh
- [ ] Open browser console (F12)

### Blocking Mode Tests
- [ ] Step 1-3: Info steps block all clicks
- [ ] Clicking blocked area shows warning in tooltip
- [ ] Tooltip shakes with pulsating icon
- [ ] Message auto-dismisses after 4 seconds
- [ ] "Suivant" button always works

### Semi-Blocking Mode Tests
- [ ] Step 4: Movement step - only adjacent tiles clickable
- [ ] Step 5: Movement limit - can move 4 times, then blocked
- [ ] Step 9: Action step - only Fouiller button clickable
- [ ] Step 12: Combat step - only enemy clickable
- [ ] Blocked clicks show helpful messages

### Resource Management Tests
- [ ] Step 4: Auto-restores 1 movement
- [ ] Step 5: Starts with exactly 4 movements
- [ ] Step 5→6→7: Resource chain works (deplete → restore)
- [ ] Step 7: Has 4 mvt + 2 actions
- [ ] Step 9: Has 1 action point
- [ ] Step 12: Has 1 action point

### Console Logging Tests
- [ ] See `[TutorialUI]` logs for interaction modes
- [ ] See `[TutorialContext]` logs for resource restoration
- [ ] See prerequisites being checked/restored
- [ ] See resources being prepared for next steps
- [ ] No JavaScript errors

### Full Flow Test
- [ ] Can complete all 14 steps without errors
- [ ] Each step has correct interaction mode
- [ ] No step fails due to missing resources
- [ ] XP accumulates correctly (175 total)
- [ ] Tutorial completes successfully

---

## 🔍 What to Look For in Console

**Good Logs** (expected):
```javascript
[TutorialUI] Applying interaction mode: blocking
[TutorialUI] Rendering step {...}
[TutorialUI] Checking prerequisites: {mvt: 4, auto_restore: true}
[TutorialContext] Restored movement: 0 → 4
[TutorialContext] Prepared movement for next step: 4
```

**Bad Logs** (problems):
```javascript
[TutorialContext] ERROR: Insufficient movement (need 4, have 0)
[TutorialManager] WARNING: Step 5 prerequisites not met!
Uncaught TypeError: ...
404 (Not Found) - tutorial.css
```

---

## 📝 Reporting Issues

When you find issues, please note:

1. **Step number** where issue occurred
2. **What you did** (actions taken)
3. **Expected behavior** (what should happen)
4. **Actual behavior** (what actually happened)
5. **Console logs** (any errors or warnings)
6. **Browser** (Chrome, Firefox, etc.)

### Example Report:
```
Step: 5 (Movement limit)
Action: Clicked on overlay after 2 movements
Expected: Tooltip shakes + shows message
Actual: Nothing happens, no feedback
Console: No errors
Browser: Chrome 120
```

---

## 🎯 Success Criteria

Tutorial interaction system is working correctly if:

✅ **Blocking mode works**:
- Info steps block all clicks
- Helpful messages shown
- Visual feedback (shake, icon)

✅ **Semi-blocking mode works**:
- Action steps allow specific elements
- Other elements blocked with feedback

✅ **Resource management works**:
- Prerequisites auto-restore resources
- Steps prepare resources for next steps
- No step fails due to missing resources

✅ **Full flow completes**:
- All 14 steps can be completed
- Resource chain is coherent
- No JavaScript errors

---

## 🚀 Next Steps After Testing

If testing reveals issues:
1. Document the issues
2. We'll fix them together
3. Re-test affected areas

If testing is successful:
1. System is ready for production
2. Can expand from 14 to 47 steps
3. Can integrate with main game

---

## Quick Start Command

```bash
# 1. Update database
php scripts/tutorial/populate_tutorial_steps.php

# 2. Start Apache (if not running)
apache2-foreground

# 3. Open browser and test!
# http://localhost:9000
# Login as Cradek (password: test)
# Start tutorial
```

---

**Good luck with testing! 🎓**

If you find any issues or have questions, let me know what you see in the console and I'll help debug!
