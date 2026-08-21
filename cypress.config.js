const { defineConfig } = require('cypress');

/* Create timestamped folder for each test run */
const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);

/*
 * Auto-detect environment:
 * - Inside container: use http://localhost (port 80)
 * - Outside container: use http://localhost:9000
 * Can be overridden with CYPRESS_BASE_URL env variable
 */
const isInsideContainer = process.env.CYPRESS_CONTAINER === 'true' || process.env.DOCKER_CONTAINER === 'true';
const baseUrl = process.env.CYPRESS_BASE_URL || (isInsideContainer ? 'http://localhost' : 'http://localhost:9000');

module.exports = defineConfig({
  e2e: {
    baseUrl: baseUrl,
    viewportWidth: 1280,
    viewportHeight: 800,
    video: true,
    screenshotOnRunFailure: true,
    screenshotsFolder: `data_tests/cypress/screenshots/${timestamp}`,
    videosFolder: `data_tests/cypress/videos/${timestamp}`,
    defaultCommandTimeout: 10000,
    pageLoadTimeout: 30000,
    setupNodeEvents(on, config) {
      const mysql = require('mysql2/promise');
      const fs = require('fs');
      const path = require('path');

      /* Database query task for validation.
       *
       * Defaults match the devcontainer (mariadb-aoo4 / root / passwordRoot /
       * aoo4_test). CI overrides via TEST_DB_HOST / TEST_DB_USER /
       * TEST_DB_PASS / TEST_DB_NAME — same env-var contract as
       * scripts/testing/reset_test_database.sh and db/init_test_from_dump.sh
       * (added in #342). The CI service alias is `mariadb`, not the
       * devcontainer's `mariadb-aoo4`.
       */
      on('task', {
        async queryDatabase({ query, params = [] }) {
          const connection = await mysql.createConnection({
            host:     process.env.TEST_DB_HOST || 'mariadb-aoo4',
            user:     process.env.TEST_DB_USER || 'root',
            password: process.env.TEST_DB_PASS || 'passwordRoot',
            database: process.env.TEST_DB_NAME || 'aoo4_test',
            charset:  'utf8mb4'
          });

          try {
            const [rows] = await connection.execute(query, params);
            await connection.end();
            return rows;
          } catch (error) {
            await connection.end();
            throw error;
          }
        },

        /* Read a player's current turn state.
         *
         * Remaining = caracs base + players_bonus rows (consumption is a
         * negative `n`), the exact computation of Player::get_caracs. Read
         * from the DATABASE: the .turn.json files are a per-request cache
         * only refreshed by the next get_caracs() call — reading them races
         * the game and shows stale values.
         *
         * Caracs base still comes from the .caracs.json cache (it carries
         * the computed values, passives included) with the races table as
         * fallback for a player whose cache was never written.
         */
        async readPlayerTurn({ playerId }) {
          const playersDir = path.join(__dirname, 'datas/private/players');
          const caracsPath = path.join(playersDir, `${playerId}.caracs.json`);

          const readJson = (p) => {
            if (!fs.existsSync(p)) return null;
            const raw = fs.readFileSync(p, 'utf8').trim();
            if (!raw) return null;
            try { return JSON.parse(raw); } catch { return null; }
          };

          const connection = await mysql.createConnection({
            host:     process.env.TEST_DB_HOST || 'mariadb-aoo4',
            user:     process.env.TEST_DB_USER || 'root',
            password: process.env.TEST_DB_PASS || 'passwordRoot',
            database: process.env.TEST_DB_NAME || 'aoo4_test',
            charset:  'utf8mb4'
          });

          try {
            let caracs = readJson(caracsPath);
            if (!caracs) {
              const [raceRows] = await connection.execute(
                'SELECT r.mvt, r.a FROM races r JOIN players p ON p.race = r.name WHERE p.id = ?',
                [playerId]
              );
              caracs = raceRows[0] || {};
            }

            const [bonusRows] = await connection.execute(
              'SELECT name, n FROM players_bonus WHERE player_id = ? AND name IN (\'mvt\', \'a\')',
              [playerId]
            );
            await connection.end();

            const bonus = { mvt: 0, a: 0 };
            for (const row of bonusRows) {
              bonus[row.name] += Number(row.n);
            }

            const result = {
              mvt: Number(caracs.mvt || 0) + bonus.mvt,
              pa: Number(caracs.a || 0) + bonus.a
            };
            /* Log inputs/outputs so test output exposes player-id mismatches */
            console.log(`[readPlayerTurn] playerId=${playerId} → ${JSON.stringify(result)}`);
            return result;
          } catch (error) {
            await connection.end();
            throw error;
          }
        }
      });

      return config;
    },
  },
});
