import { until, CONFIG, By } from '../../config/webdriverConfig.js';
import { expect } from 'chai';

class AbstractClass {
  constructor(driver) {
    if (new.target === AbstractClass) {
      throw new TypeError("Cannot construct AbstractPage instances directly");
    }
    this.driver = driver;
  }

  // Method to click a button
  async clickButton(driver, button, logMessage = null) {
    try {
      if (typeof button === 'string') {
        button = await driver.findElement(By.id(button));
      }
      const isButtonPresent = await button.isDisplayed();
      expect(isButtonPresent).to.be.true; // Assertion to check if the button is present

      // Check if the button is enabled
      const isButtonEnabled = await button.isEnabled();
      expect(isButtonEnabled).to.be.true; // Assertion to check if the button is enabled

      await this.driver.wait(until.elementIsVisible(button), CONFIG.timeouts.explicit);
      await this.driver.wait(until.elementIsEnabled(button), CONFIG.timeouts.explicit);
      await button.click();
      if (logMessage) {
        console.log(`Clicked on ${logMessage}.`);
      }
    } catch (error) {
      console.error(`Error clicking button: ${error.message}`);
    }
  }

  // Method to wait for the modal to be displayed
  async waitForModal(modalId) {
    const modal = await this.driver.wait(
      until.elementLocated(By.id(modalId)),
      CONFIG.timeouts.explicit
    );
    await this.driver.wait(until.elementIsVisible(modal), CONFIG.timeouts.explicit);
  }

  // Method to verify the alert message
  async verifyAlertMessage(alertElement, expectedMessage) {
    const alertText = await alertElement.getText();
    if (alertText.includes(expectedMessage)) {
      console.log(`Alert contains the expected message: ${alertText}`);
    } else {
      throw new Error(`Alert does not contain the expected message. Actual message: ${alertText}`);
    }
  }

// Method to fill an input field
async fillInput(driver, selector, value, description = '') {
    try {
        const element = await driver.findElement(By.id(selector));
        await element.clear();
        await element.sendKeys(value);
        console.log(`Filled ${description} with value: ${value}`);
    } catch (error) {
        console.error(`Failed to fill ${description}:`, error);
        throw error;
    }
}

// Method to wait for an element to be present
async delay(milliseconds) {
    return new Promise(resolve => setTimeout(resolve, milliseconds));
}

// Method to generate a random user with a name without numbers
    generateRandomUser() {
        const firstNames = ['Gandalf', 'Legolas', 'Gimli', 'Thorin', 'Balin', 'Dwalin', 'Bifur', 'Bofur', 'Bombur', 'Ori'];
        const lastNames = ['Ironforge', 'Stonebeard', 'Goldaxe', 'Firebeard', 'Rockbreaker', 'Silvermail', 'Hammerfist'];
        const domains = ['dwarf.net', 'mines.org', 'mountain.com', 'forge.co', 'hammer.io'];
        
        const firstName = firstNames[Math.floor(Math.random() * firstNames.length)];
        const lastName = lastNames[Math.floor(Math.random() * lastNames.length)];
        const domain = domains[Math.floor(Math.random() * domains.length)];
        
        // Generate name WITHOUT numbers - only letters
        const nameVariations = [
            firstName,
            `${firstName}${lastName}`,
            `${firstName.slice(0, 3)}${lastName.slice(0, 3)}`,
            `${firstName}le${lastName.slice(-4)}`,
            `${lastName}${firstName.slice(-3)}`
        ];
        
        const randomName = nameVariations[Math.floor(Math.random() * nameVariations.length)];
        
        // For email, we can still use numbers
        const randomNumber = Math.floor(Math.random() * 9999);
        const timestamp = Date.now().toString().slice(-4);
        
        return {
            name: randomName, // No numbers in character name
            password: `${firstName}${lastName}${timestamp}`,
            email: `${firstName.toLowerCase()}.${lastName.toLowerCase()}${randomNumber}@${domain}`
        };
    }
}

export default AbstractClass;