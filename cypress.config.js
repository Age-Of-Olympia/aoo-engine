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

      /* Database query task for validation */
      on('task', {
        async queryDatabase({ query, params = [] }) {
          const connection = await mysql.createConnection({
            host: 'mariadb-aoo4',
            user: 'root',
            password: 'passwordRoot',
            database: 'aoo4_test',
            charset: 'utf8mb4'
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
