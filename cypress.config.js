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
        }
      });

      return config;
    },
  },
});
