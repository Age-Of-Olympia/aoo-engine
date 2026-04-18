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

### When to Rollback (Triggers)

Rollback is justified when **any** of these thresholds is sustained for ≥1 hour
after deploy:

| Metric | Threshold | Source |
|---|---|---|
| Tutorial API error rate | > 5% of requests to `/api/tutorial/*` | apache logs / monitoring dashboard |
| Tutorial completion rate | < 20% of started sessions | `tutorial_progress` query (see Monitoring Queries below) |
| Fatal `error_log` rate | > 10/hour with `[Tutorial...]` prefix | apache error log |
| Player-reported regression | ≥ 3 reports of stuck/broken tutorial | support channel |

If less severe (slow but working), prefer to debug in place rather than roll back.

### Immediate Rollback (Disable, do not redeploy)

Most issues can be contained by disabling the new tutorial without touching code:

1. **Toggle feature flag OFF.** Edit `config.php`:
   ```php
   const TUTORIAL_V2_ENABLED = false;
   ```
   New players fall back to the legacy tutorial path immediately. No deploy needed
   (PHP picks up the constant change on next request).

2. **Drain in-flight sessions** (sessions started before the flag flip will keep
   running until the player completes/cancels — usually within minutes). Two
   options:
   - **Soft drain (recommended)**: leave running, let players finish naturally.
     New players use legacy tutorial. The two systems coexist by design.
   - **Hard drain (only if the bug is data-corrupting)**: force-complete all
     active sessions:
     ```sql
     UPDATE tutorial_progress
     SET completed = 1, completed_at = NOW(), tutorial_mode = 'practice'
     WHERE completed = 0;
     ```
     Then run the cleanup cron immediately:
     ```bash
     php /var/www/html/scripts/tutorial/cleanup_orphans.php --hours=0
     php /var/www/html/scripts/tutorial/cleanup_orphaned_instances.php
     ```

3. **Verify legacy tutorial still works** — register a fresh test account; the
   legacy "Commencer le tutoriel" flow should engage. If it does not, the
   feature-flag fallback is broken — escalate before further deploys.

### Full Code Rollback (Last Resort)

Only when the above does not contain the issue (e.g., the migration itself is
corrupting data):

- [ ] `git checkout <previous-stable-tag>` and redeploy.
- [ ] **DO NOT** drop tutorial tables — preserves data for post-mortem.
- [ ] Down-migrate only if schema is incompatible:
  ```bash
  vendor/bin/doctrine-migrations migrate prev --no-interaction
  ```
- [ ] Run cleanup script as in step 2 above.

## Monitoring Queries

Run these against the production DB (read-only) to feed a dashboard or
on-demand health check.

**Sessions in last 24h, by outcome:**
```sql
SELECT
    CASE
        WHEN completed = 1 THEN 'completed'
        WHEN created_at < NOW() - INTERVAL 24 HOUR THEN 'abandoned'
        ELSE 'in_progress'
    END AS status,
    COUNT(*) AS n
FROM tutorial_progress
WHERE created_at > NOW() - INTERVAL 24 HOUR
GROUP BY status;
```

**Drop-off funnel by step (last 7 days):**
```sql
SELECT current_step, COUNT(*) AS stuck_at
FROM tutorial_progress
WHERE completed = 0
  AND created_at > NOW() - INTERVAL 7 DAY
GROUP BY current_step
ORDER BY stuck_at DESC;
```

**Average completion time (completed sessions only, last 7 days):**
```sql
SELECT
    tutorial_version,
    COUNT(*) AS completed,
    AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) AS avg_minutes
FROM tutorial_progress
WHERE completed = 1
  AND completed_at > NOW() - INTERVAL 7 DAY
GROUP BY tutorial_version;
```

**Active orphan accumulation (table-growth canary):**
```sql
SELECT COUNT(*) AS orphans_over_24h
FROM tutorial_progress
WHERE completed = 0
  AND created_at < NOW() - INTERVAL 24 HOUR;
```
A non-trivial value indicates the cleanup cron is not running — investigate.

## Maintenance Tasks

### Daily Cleanup (Automated via Cron)

The two cleanup scripts are complementary — schedule both:

```cron
# 3:00 — clean up abandoned sessions (tutorial_progress + tutorial_players + tutorial_enemies)
0 3 * * * /usr/bin/php /var/www/html/scripts/tutorial/cleanup_orphans.php

# 3:05 — clean up orphaned map instances (tut_<uuid> plans + their coords)
5 3 * * * /usr/bin/php /var/www/html/scripts/tutorial/cleanup_orphaned_instances.php
```

`cleanup_orphans.php` accepts `--hours=N` (default 24, threshold for considering
a session abandoned), `--dry-run`, and `--quiet`. Both scripts emit one JSON
summary line to stdout suitable for log scraping; check exit code (0 = ok,
1 = partial failure).

To verify the cron is doing its job, run the "Active orphan accumulation"
monitoring query above — it should stay near 0.

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
