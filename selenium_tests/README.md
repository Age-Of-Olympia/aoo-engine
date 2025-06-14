# E2E Testing Documentation

E2E tests are base on selenium/mocha.

This guide explains how to set up and run Selenium tests for automated UI testing in both local and CI environments.

Note: Currently, the full test automation works in the local environment, while CI implementation requires additional setup for data and app build configuration.

# Quick Start

## System requirments

- Node.js (v22.10.0 or higher recommended)
- npm (comes with Node.js)
- Chrome browser installed (for local testing)
- mocha

## Running Tests

Note: Before running tests make sure you have defined correct port and host in the `selenium_tests/config/webdriverConfig.js` file.

```bash
make selenium-install` # Installs necessary dependencies
```

```bash
make e2e # Runs all E2E tests
```

```bash
make e2e-report #runs tests and generates a report in the folder /tmp/e2e_reports/e2e_report.json
```
    
Run single test using npx and mocha:
```bash
npx mocha selenium_tests/tests/pages/auht/TestAuthPage.test
```


Run tests in headless mode, modify the driver setup call:
    `await setupDriver(true);`

## Configuration

Configuration is localized here: `selenium_tests/config/webdriverConfig.js`

### How skip the test

Example:
```javascript
describe.skip('Configuration Page Tests', function () {
    ...
});
```

## GitLab CI Integration

Add this configuration to your `.gitlab-ci.yml`:
NOTE: This is example configuration for Gitlab CI , that not are fully implemented yet, but can be used as a starting point.

```yaml
SeleniumTest:
  stage: Test
  image: node:18.12-alpine
  services:
    - name: selenium/standalone-chrome:latest
      alias: selenium
  variables:
    FF_NETWORK_PER_BUILD: "true"
    SELENIUM_HOST: selenium
    SELENIUM_PORT: 4444
    CI: 'true'
  before_script:
    - npm install
  script:
    - npm run test --host=selenium
  # Uncomment to save test artifacts
  # artifacts:
  #   when: always
  #   paths:
  #     - test-results/
  dependencies:
    - Webpack
```
