---
paths:
  - "js/**"
  - "css/**"
  - "src/View/**"
  - "Classes/Ui.php"
---

# JS and CSS changes

**CRITICAL**: When modifying JavaScript or CSS files, you MUST update version parameters to prevent browser caching issues.

## JavaScript Comments in Minified Output

**CRITICAL**: When writing inline JavaScript in PHP files that use `Str::minify()`:
- **NEVER use `//` single-line comments** - they will break minified code
- **ALWAYS use `/* */` multi-line comments** instead
- Minification puts code on one line, causing `//` to comment out everything after it
- This applies to any JavaScript in PHP files that gets minified (MenuView.php, etc.)

```php
// ❌ BAD - Will break when minified
echo '<script>
    // This comment will break minified code
    $(document).ready(function() { ... });
</script>';

// ✅ GOOD - Safe for minification
echo '<script>
    /* This comment is safe for minified code */
    $(document).ready(function() { ... });
</script>';
```

## Why This Matters
Browsers aggressively cache JS/CSS files. Without cache-busting, users will continue using old cached versions even after you deploy changes, leading to:
- Features not working
- JavaScript errors
- Confusing debugging sessions

## How to Handle JS/CSS Changes

**Always update the version parameter** when you modify a JS or CSS file:

```html
<!-- Before modifying js/tiled.js -->
<script src="js/tiled.js?v=20251111"></script>

<!-- After modifying js/tiled.js, increment the version -->
<script src="js/tiled.js?v=20251112"></script>
```

## Version Format
Use `YYYYMMDD` format (today's date) or increment existing version number. The important thing is that it changes.

## Common Files with Version Parameters
Check these files when looking for JS/CSS includes:
- Root `.php` page controllers (index.php, map.php, forum.php, tiled.php, etc.)
- `Classes/Ui.php` (handles some script loading)
- Template/view files in `src/View/`

## Example Workflow
1. Modify `js/tiled.js`
2. Find where it's loaded: `tiled.php` line 182
3. Update: `<script src="js/tiled.js?v=20251111"></script>` → `<script src="js/tiled.js?v=20251112"></script>`
4. Test in browser (hard refresh with Ctrl+F5 if needed)

**Remember**: Forgetting this step will make it appear like your changes don't work!
