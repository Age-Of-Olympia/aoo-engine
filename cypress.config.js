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
    viewportWidth: 1920,
    viewportHeight: 1080,
    video: true,
    screenshotOnRunFailure: true,
    screenshotsFolder: `data_tests/cypress/screenshots/${timestamp}`,
    videosFolder: `data_tests/cypress/videos/${timestamp}`,
    defaultCommandTimeout: 10000,
    pageLoadTimeout: 30000,
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
});
