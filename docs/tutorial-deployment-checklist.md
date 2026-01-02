# Tutorial System - Production Deployment Checklist

## Pre-Deployment Verification

### Database Migration
- [ ] **Create `tutorial_enemies` table** (if not exists)
  ```sql
  CREATE TABLE IF NOT EXISTS tutorial_enemies (
      id INT AUTO_INCREMENT PRIMARY KEY,
      tutorial_session_id VARCHAR(255) NOT NULL,
      enemy_player_id INT NOT NULL,
      enemy_coords_id INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_session (tutorial_session_id),
      INDEX idx_enemy (enemy_player_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```

- [ ] **Verify all tutorial tables exist:**
  - `tutorial_configurations` ✓
  - `tutorial_progress` ✓
  - `tutorial_players` ✓
  - `tutorial_enemies` (new)

- [ ] **Check tutorial coordinates exist:**
  ```sql
  SELECT * FROM coords WHERE x=0 AND y=0 AND z=0 AND plan='tutorial';
  ```
  If not exists, will be auto-created on first tutorial start.

- [ ] **Verify character encoding:**
  ```sql
  SHOW CREATE TABLE tutorial_configurations;
  -- Should show: DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ```

- [ ] **Populate tutorial steps** (if not already done):
  ```bash
  php scripts/tutorial/populate_tutorial_steps.php
  ```

### Code Verification

- [ ] **Cache versions updated** (current: `20251115a`)
  - Check `src/View/TutorialView.php` lines 29-35

- [ ] **Database charset config** set to `utf8mb4`:
  - Check `config/db_constants.php` line with charset

- [ ] **Feature flag configured:**
  - Check `src/Tutorial/TutorialFeatureFlag.php`
  - Verify `TUTORIAL_ENABLED` constant
  - Verify `TUTORIAL_TEST_PLAYERS` array

### File Permissions

- [ ] **Tutorial files readable:**
  - `css/tutorial/tutorial.css`
  - `js/tutorial/*.js`
  - `api/tutorial/*.php`

- [ ] **Log directories writable:**
  - Apache error log (for error_log() calls)

## Feature Flag Strategy

### Gradual Rollout Plan

**Phase 1: Internal Testing** (Days 1-3)
- [ ] Enable for admin players only
  ```php
  const TUTORIAL_TEST_PLAYERS = [1, 2, 3]; // Admin matricules
  const TUTORIAL_ENABLED = false; // Still globally off
  ```

**Phase 2: Beta Testing** (Days 4-7)
- [ ] Enable for specific beta testers
  ```php
  const TUTORIAL_TEST_PLAYERS = [1, 2, 3, 42, 99, 150]; // Add beta testers
  ```

**Phase 3: Soft Launch** (Days 8-14)
- [ ] Enable globally for new players only
  ```php
  const TUTORIAL_ENABLED = true;
  // Modify isEnabledForPlayer() to check if player is new (created_at recent)
  ```

**Phase 4: Full Launch** (Day 15+)
- [ ] Enable for all players
  ```php
  const TUTORIAL_ENABLED = true;
  // Remove player restrictions
  ```

## Deployment Steps

### 1. Code Deployment

- [ ] **Merge feature branch to staging:**
  ```bash
  git checkout staging
  git merge 71-tuto-ameliore
  ```

- [ ] **Run tests:**
  ```bash
  make test
  make phpstan
  ```

- [ ] **Tag release:**
  ```bash
  git tag -a v4.2.0-tutorial -m "Tutorial System v1.0.0"
  git push origin v4.2.0-tutorial
  ```

- [ ] **Deploy to staging server:**
  ```bash
  # Follow your standard deployment procedure
  # Ensure Composer dependencies are updated
  composer install --no-dev --optimize-autoloader
  ```

### 2. Database Migration

- [ ] **Backup database before migration:**
  ```bash
  mysqldump -u root -p aoo_prod_20250821 > backup_pre_tutorial_$(date +%Y%m%d).sql
  ```

- [ ] **Run migration:**
  ```bash
  mysql -u root -p aoo_prod_20250821 < sql/create_tutorial_enemies_table.sql
  ```

- [ ] **Verify migration:**
  ```bash
  mysql -u root -p aoo_prod_20250821 -e "SHOW TABLES LIKE 'tutorial_%'"
  ```

### 3. Cache Clearing

- [ ] **Clear opcode cache (if using OPcache):**
  ```bash
  # Option 1: Restart PHP-FPM
  sudo systemctl restart php-fpm

  # Option 2: Touch files to invalidate
  find /var/www/html/src -name "*.php" -exec touch {} \;
  ```

- [ ] **Verify browser cache busting:**
  - Check that all tutorial CSS/JS have version `?v=20251115a`

### 4. Monitoring Setup

- [ ] **Enable error logging:**
  - Verify error_log() calls write to accessible log
  - Check Apache error log path

- [ ] **Set up monitoring for:**
  - Tutorial session creation rate
  - Tutorial completion rate
  - Error rate in tutorial APIs
  - Orphaned tutorial player count

- [ ] **Create monitoring SQL queries:**
  ```sql
  -- Active sessions
  SELECT COUNT(*) FROM tutorial_progress WHERE completed = 0;

  -- Completion rate (last 7 days)
  SELECT
      COUNT(*) as total,
      SUM(completed) as completed,
      (SUM(completed) / COUNT(*)) * 100 as completion_rate
  FROM tutorial_progress
  WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

  -- Orphaned players
  SELECT COUNT(*) FROM tutorial_players WHERE is_active = 1 AND deleted_at IS NULL;

  -- Orphaned enemies
  SELECT COUNT(*) FROM tutorial_enemies;
  ```

## Post-Deployment Verification

### Smoke Tests

- [ ] **Test new player tutorial start:**
  1. Create test account (or use matricule 1, 2, 3)
  2. Navigate to index.php
  3. Click "Commencer le tutoriel"
  4. Verify tutorial starts successfully
  5. Check database for session creation
  6. Check that enemy spawned

- [ ] **Test tutorial progression:**
  1. Complete first 3 steps
  2. Verify XP increments
  3. Verify movements/actions restore correctly
  4. Check that blocked interactions work

- [ ] **Test tutorial cancellation:**
  1. Start tutorial
  2. Click "Abandonner"
  3. Verify session marked complete
  4. Verify tutorial player deactivated
  5. Verify enemy cleaned up

- [ ] **Test tutorial completion:**
  1. Complete all 16 steps
  2. Verify completion modal appears
  3. Verify XP/PI transferred to main player
  4. Verify tutorial player deleted
  5. Verify enemy cleaned up
  6. Verify redirect to main game

- [ ] **Test admin dashboard:**
  1. Login as admin
  2. Navigate to `/admin_tutorial_debug.php`
  3. Verify stats display correctly
  4. Test cleanup button

### Database Verification

- [ ] **Check for orphaned data:**
  ```sql
  -- Should be 0 after cleanup
  SELECT * FROM tutorial_players WHERE is_active = 1 AND deleted_at IS NOT NULL;

  -- Check enemy cleanup
  SELECT te.*, p.name
  FROM tutorial_enemies te
  LEFT JOIN players p ON te.enemy_player_id = p.id
  WHERE p.id IS NULL; -- Should be empty
  ```

- [ ] **Verify character encoding:**
  ```sql
  SELECT title, config->>'$.text' as text
  FROM tutorial_configurations
  WHERE step_id = 'gaia_welcome';
  -- Should show proper French characters (é, à, etc.)
  ```

### Performance Checks

- [ ] **Check page load times:**
  - index.php with tutorial active: < 2s
  - API endpoints response time: < 500ms

- [ ] **Check database query performance:**
  ```sql
  EXPLAIN SELECT * FROM tutorial_progress WHERE player_id = 1 AND completed = 0;
  -- Should use index
  ```

- [ ] **Monitor memory usage:**
  - Tutorial player objects should not cause memory spikes

## Rollback Plan

### If Critical Issues Detected:

**Immediate Rollback:**
- [ ] Disable tutorial feature flag:
  ```php
  const TUTORIAL_ENABLED = false;
  ```

**Full Rollback:**
- [ ] Revert code deployment:
  ```bash
  git checkout v4.1.0-previous-stable
  # Redeploy
  ```

- [ ] **DO NOT** drop tutorial tables (preserves data for debugging)

- [ ] **DO** clean up orphaned data:
  ```sql
  UPDATE tutorial_players SET is_active = 0, deleted_at = NOW() WHERE is_active = 1;
  UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE completed = 0;
  -- Run enemy cleanup via admin dashboard
  ```

## Maintenance Tasks

### Weekly Cleanup (Automated via Cron)

```bash
# Add to crontab (runs every Monday at 3am)
0 3 * * 1 php /var/www/html/scripts/tutorial/cleanup_orphans.php
```

Create `/var/www/html/scripts/tutorial/cleanup_orphans.php`:
```php
<?php
require_once(__DIR__ . '/../../config.php');

$db = new Classes\Db();
$em = App\EntityManagerFactory::getEntityManager();
$conn = $em->getConnection();

// Clean up completed sessions older than 7 days
$sql = 'DELETE FROM tutorial_enemies
        WHERE tutorial_session_id IN (
            SELECT tutorial_session_id FROM tutorial_progress
            WHERE completed = 1 AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        )';
$db->exe($sql);

// Clean up abandoned sessions (inactive > 24 hours)
$sql2 = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW()
         WHERE completed = 0 AND started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)';
$db->exe($sql2);

echo "Cleanup completed\n";
```

### Monthly Review

- [ ] Review tutorial completion rates
- [ ] Analyze drop-off points (which steps players abandon)
- [ ] Check for common error patterns in logs
- [ ] Update tutorial text based on player feedback

## Success Metrics

### Week 1 Targets:
- [ ] **Activation Rate:** >50% of new players start tutorial
- [ ] **Completion Rate:** >30% complete all 16 steps
- [ ] **Error Rate:** <5% of sessions encounter errors
- [ ] **Average Duration:** 10-15 minutes

### Week 4 Targets:
- [ ] **Activation Rate:** >70%
- [ ] **Completion Rate:** >50%
- [ ] **Error Rate:** <2%
- [ ] **Retention:** Players who complete tutorial have 30%+ higher D7 retention

## Contacts & Escalation

### Primary Contact:
- **Developer:** [Your Name]
- **Issues:** Report via GitHub Issues

### Escalation Path:
1. Check logs in Apache error log
2. Check admin dashboard for orphaned data
3. Check database for consistency
4. If critical: Disable feature flag immediately
5. Report issue with full context (steps to reproduce, error logs, database state)

## Sign-Off

- [ ] **Developer Sign-Off:** All code reviewed and tested ___________
- [ ] **QA Sign-Off:** Smoke tests passed ___________
- [ ] **Database Sign-Off:** Migration verified ___________
- [ ] **Product Sign-Off:** Feature ready for release ___________

---

**Deployment Date:** _____________
**Deployed By:** _____________
**Rollback Executed:** ☐ Yes ☐ No
**Notes:** _____________________________________________
