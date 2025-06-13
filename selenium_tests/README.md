# Selenium Testing Documentation

This guide explains how to set up and run Selenium tests for automated UI testing in both local and CI environments.

## Note

Currently, the full test automation works in the local environment, while CI implementation requires additional setup for data and app build configuration.

## Quick Start

### Prerequisites

- Node.js (v18.12 or higher recommended)
- npm (comes with Node.js)
- Chrome browser installed (for local testing)

### Installation

Add these dependencies to your project:

```json
{
  "devDependencies": {
    "selenium-webdriver": "^4.25.0",
    "chai": "^5.1.2",
    "chromedriver": "^137.0.0",
    "mocha": "^10.7.3"
  }
}
```

Install the dependencies:

```bash
npm install mocha chai selenium-webdriver chromedriver --save-dev
```

## Configuration

### Test Script Configuration

Add this to your `selenium_tests/package.json`:

```json
{
  "scripts": {
    "test": "mocha selenium_tests/tests/**/*.test.js"
  }
}
```

### WebDriver Configuration

`selenium_tests/config/webdriverConfig.js` allow to configure browser options and timeouts:

```javascript
import { Builder, By, until } from 'selenium-webdriver';
import chrome from 'selenium-webdriver/chrome.js';

const CONFIG = {
    baseUrl: process.env.CI ? 'http://selenium:4444/wd/hub' : 'http://localhost:9000',
    //baseUrl: process.env.CI ? 'http://selenium:4444/wd/hub' : 'http://localhost:80', => for docker container case

    timeouts: {
        implicit: 5000,
        explicit: 10000
    }
};

async function setupDriver(browser, headless = true) {
    let options = new chrome.Options();
    if (headless) {
        options.addArguments('--headless');
    }

    const builder = new Builder()
        .forBrowser(browser)
        .setChromeOptions(options);

    // This is required for CI environment (its the url to the selenium server(docker))
    if (process.env.CI) {
        builder.usingServer(`http://selenium:4444/wd/hub`);
    }

    return builder.build();
}

export { setupDriver, By, until, CONFIG };
```

### How skip the test ?

Example:

```javascript

describe.skip('Configuration Page Tests', function () {
    // Define your tests here
});

```

## Running Tests

### Local Environment

Run tests using npm:

```bash
make selenium-install # Install dependencies
make e2e
```

### Headless Mode

To run tests in headless mode, modify the driver setup call:

```javascript
await setupDriver(true);
```

## GitLab CI Integration

Add this configuration to your `.gitlab-ci.yml`:
NOTE: its not fully implemented yet, but it shows how to run tests in CI with Selenium.

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