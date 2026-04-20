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

        /* Read a player's current turn state from disk.
         *
         * Turn data lives in JSON files, not the `players` table (the `turn`
         * column from older CLAUDE.md docs does not exist). Classes/Player
         * writes datas/private/players/<id>.turn.json after every get_caracs()
         * call, containing the post-bonus remaining values.
         *
         * Returns { mvt, pa } with:
         *   mvt = turn.mvt if set, else caracs.mvt (race max)
         *   pa  = turn.a   if set, else caracs.a   (race max)
         * This mirrors Player::getRemaining() semantics.
         */
        readPlayerTurn({ playerId }) {
          const playersDir = path.join(__dirname, 'datas/private/players');
          const turnPath = path.join(playersDir, `${playerId}.turn.json`);
          const caracsPath = path.join(playersDir, `${playerId}.caracs.json`);

          const readJson = (p) => {
            if (!fs.existsSync(p)) return { __missing: true };
            const raw = fs.readFileSync(p, 'utf8').trim();
            if (!raw) return {};
            try { return JSON.parse(raw); } catch { return {}; }
          };

          const turn = readJson(turnPath);
          const caracs = readJson(caracsPath);

          const pick = (key, fallbackKey = key) =>
            turn[key] !== undefined ? turn[key]
              : caracs[fallbackKey] !== undefined ? caracs[fallbackKey]
              : 0;

          const result = {
            mvt: pick('mvt'),
            pa: pick('a'),
            turnMissing: turn.__missing === true,
            caracsMissing: caracs.__missing === true
          };
          /* Log inputs/outputs so test output exposes player-id mismatches */
          console.log(`[readPlayerTurn] playerId=${playerId} → ${JSON.stringify(result)}`);
          return result;
        }
      });

      return config;
    },
  },
});
