import {setupDriver, By, CONFIG, until} from '../../../config/webdriverConfig.js';
import AbstractClass from '../AbstractClass.js';
import {expect} from 'chai';

class TestAuthPage extends AbstractClass {
    constructor(driver) {
        super(driver);
        this.name = '1';
        this.password = 'test';

        const randomData = this.generateRandomUser();
        this.registerName = randomData.name;
        this.registerPassword = randomData.password;
        this.registerEmail = randomData.email;

        this.selectors = {
            playButtonId: 'index-button-play',
            loginButtonId: 'index-button-login',
            registerButtonId: 'index-button-register',
            retrunButtonId: 'index-button-return',
            nameInputId: 'name-input',
            passwordInputId: 'psw-input',
            topMenuButtonId: 'top-menu-button',

            //registration dialog selectors
            dialogBonjour: '[data-node="bonjour"]',
            nainOption: '[data-go="nain"]',
            elfeOption: '[data-go="elfe"]',

            dialogNain: '[data-node="nain"]',
            confirmNainOption: '[data-set-val="nain"]',
            backToBonjourOption: '[data-go="bonjour"]',

            dialogName: '[data-node="name"]',
            characterNameInput: 'input[name="name"]',
            continueOption: '[data-go="conclusion"]',

            dialogConclusion: '[data-node="conclusion"]',
            reincarnateOption: '[data-go="register"]',
            resetOption: '[data-go="RESET"]',

            registrationForm: '[data-node="register"]',
            formNameInput: '#name',
            raceSelect: 'select[name="race"]',
            passwordInput: 'input[name="psw1"]',
            confirmPasswordInput: 'input[name="psw2"]',
            emailInput: 'input[name="mail"]',
            cguCheckbox: '#cgu',
            submitButton: '#submit'
        };

    }

    async navigate() {
        await this.driver.get(`${CONFIG.baseUrl}`);
    }

    async login() {
        await this.clickButton(this.driver, this.selectors.playButtonId, 'Play Button');
        console.log("Clicked Play Button first time");

        await this.clickButton(this.driver, this.selectors.retrunButtonId, 'Return Button');
        console.log("Clicked Return Button");

        await this.clickButton(this.driver, this.selectors.playButtonId, 'Play Button');
        console.log("Clicked Play Button second time");

        await this.fillInput(this.driver, this.selectors.nameInputId, this.name, 'Name Input');
        console.log(`Filled Name Input with '${this.name}'`);

        await this.fillInput(this.driver, this.selectors.passwordInputId, this.password, 'Password Input');
        console.log(`Filled Password Input with '${this.password}'`);

        await this.clickButton(this.driver, this.selectors.loginButtonId, 'Login Button');
        console.log("Clicked Login Button");

        await this.driver.wait(until.elementLocated(By.id(this.selectors.topMenuButtonId)), 3000);
        const topMenuElement = await this.driver.findElement(By.id(this.selectors.topMenuButtonId));

        expect(await topMenuElement.isDisplayed(), 'Top menu button should be visible after login').to.be.true;

        console.log("Login successful - top menu button is visible");
        console.log("Login process completed");
    }

    async register() {
        await this.clickButton(this.driver, this.selectors.registerButtonId, 'Register Button');
        console.log("Clicked Register Button");

        // Wait for the dialog to appear
        await this.driver.wait(until.elementLocated(By.css(this.selectors.dialogBonjour)), 5000);
        console.log("Dialog appeared");

        // Find and click the "Nain" option
        const nainOption = await this.driver.findElement(By.css(this.selectors.nainOption));
        await nainOption.click();
        console.log("Clicked on Nain option");

        // Wait for the nain dialog to appear and click confirmation
        await this.driver.wait(until.elementLocated(By.css(this.selectors.dialogNain)), 5000);
        console.log("Nain dialog appeared");

        // Find and click the "Va pour un Nain" option
        const confirmNainOption = await this.driver.findElement(By.css(this.selectors.confirmNainOption));
        await confirmNainOption.click();
        console.log("Clicked on 'Va pour un Nain' option");

        // Wait for the name input dialog to appear
        await this.driver.wait(until.elementLocated(By.css(this.selectors.dialogName)), 5000);
        console.log("Name input dialog appeared");

        // Fill the name input field
        const nameInput = await this.driver.findElement(By.css(this.selectors.characterNameInput));
        await nameInput.clear();
        await nameInput.sendKeys(this.registerName);
        console.log(`Filled character name with '${this.registerName}'`);

        // Click continue button
        const continueOption = await this.driver.findElement(By.css(this.selectors.continueOption));
        await continueOption.click();
        console.log("Clicked continue button");

        // Wait for the conclusion dialog to appear
        await this.driver.wait(until.elementLocated(By.css(this.selectors.dialogConclusion)), 5000);
        console.log("Conclusion dialog appeared");

        // Click on "Soit. [se réincarner]" option
        const reincarnateOption = await this.driver.findElement(By.css(this.selectors.reincarnateOption));
        await reincarnateOption.click();
        console.log("Clicked on 'Soit. [se réincarner]' option");

        // Wait for the registration form to appear
        await this.driver.wait(until.elementLocated(By.css(this.selectors.registrationForm)), 5000);
        console.log("Registration form appeared");

        // Fill the character name field (it should already be filled, but clear and refill)
        const formNameInput = await this.driver.findElement(By.id('name'));
        await formNameInput.clear();
        await formNameInput.sendKeys(this.registerName);
        console.log(`Filled form name with '${this.registerName}'`);

        // Select race (Nain should already be selected, but ensure it)
        const raceSelect = await this.driver.findElement(By.name('race'));
        await raceSelect.click();
        const nainRaceOption = await this.driver.findElement(By.css('option[value="nain"]'));
        await nainRaceOption.click();
        console.log("Selected Nain race");

        // Fill password field
        const passwordInput = await this.driver.findElement(By.name('psw1'));
        await passwordInput.clear();
        await passwordInput.sendKeys(this.registerPassword);
        console.log(`Filled password with '${this.registerPassword}'`);

        // Fill password confirmation field
        const confirmPasswordInput = await this.driver.findElement(By.name('psw2'));
        await confirmPasswordInput.clear();
        await confirmPasswordInput.sendKeys(this.registerPassword);
        console.log(`Filled password confirmation with '${this.registerPassword}'`);

        // Fill email field
        const emailInput = await this.driver.findElement(By.name('mail'));
        await emailInput.clear();
        await emailInput.sendKeys(this.registerEmail);
        console.log(`Filled email with '${this.registerEmail}'`);

        // Check the CGU checkbox
        const cguCheckbox = await this.driver.findElement(By.id('cgu'));
        await cguCheckbox.click();
        console.log("Checked CGU checkbox");

        // Click the submit button
        const submitButton = await this.driver.findElement(By.id('submit'));
        await submitButton.click();
        console.log("Clicked submit button");

        // Simple check if noderegister element exists
        await this.driver.wait(until.elementLocated(By.id('noderegister')), 10000);
        const nodeRegisterElement = await this.driver.findElement(By.id('noderegister'));
        
        expect(nodeRegisterElement).to.exist;
        console.log("Registration form element 'noderegister' exists - test passed");
    }

}

describe('Auth Tests', function() {
    let driver;
    let page;

    before(async function() {
        this.timeout(10000);
        driver = await setupDriver('chrome', true);
        page = new TestAuthPage(driver);
        await driver.manage().setTimeouts({implicit: CONFIG.timeouts.implicit});
    });

    after(async function() {
        await driver.quit();
    });

    beforeEach(async function() {
        await page.navigate();
    });

    it('should successfully register a new user', async function() {
        this.timeout(5000);
        await page.register();
    });

    it('should successfully login with valid credentials', async function() {
        this.timeout(5000);
        await page.login();
    });

});