import { Builder, By, until } from 'selenium-webdriver';
import chrome from 'selenium-webdriver/chrome.js';

const CONFIG = {
    baseUrl: process.env.CI ? 'http://selenium:4444/wd/hub' : 'http://localhost:9000/',
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

    if (process.env.CI) {
        builder.usingServer('http://selenium:4444/wd/hub');
    }

    return builder.build();
}

export { setupDriver, By, until, CONFIG };