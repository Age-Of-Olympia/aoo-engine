const { defineConfig } = require('cypress');

/* Create timestamped folder for each test run */
const timestamp = new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19);

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost',
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
