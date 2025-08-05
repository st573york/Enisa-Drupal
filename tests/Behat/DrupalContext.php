<?php

declare(strict_types = 1);

namespace Digit\Site\Tests\Behat;

use Drupal\DrupalExtension\Context\DrupalContext as DrupalExtensionDrupalContext;

/**
 * Provides step definitions for interacting with Drupal.
 */
class DrupalContext extends DrupalExtensionDrupalContext {

  /**
   * {@inheritdoc}
   */
  public function loggedIn() {
    $session = $this->getSession();
    $session->visit($this->locatePath('/'));

    // Check if the 'logged-in' class is present on the page.
    $element = $session->getPage();

    return $element->find('css', 'body.user-logged-in');
  }

  /**
   * Visit a page with basic authentication.
   *
   * @When I visit :path with basic authentication as a\/an :role
   */
  public function iVisitWithBasicAuthenticationAs($path, $role) {
    // Create user.
    $name = $this->getRandom()->name(8);
    $pass = $this->getRandom()->name(16);
    $user = (object) [
      'name' => $name,
      'pass' => $pass,
      'role' => $role,
    ];
    $user->mail = "{$user->name}@example.com";

    $this->userCreate($user);

    $roles = explode(',', $role);
    $roles = array_map('trim', $roles);
    $authenticated = ['authenticated', 'authenticated user'];
    foreach ($roles as $role) {
      if (!in_array(mb_strtolower($role), $authenticated)) {
        // Only add roles other than 'authenticated user'.
        $this->getDriver()->userAddRole($user, $role);
      }
    }
    // Set the basic authentication.
    $this->getSession()->setBasicAuth($name, $pass);
    $this->visitPath($path);
  }

  /**
   * Click on text using xpath to find.
   *
   * @When I click on :arg1
   */
  public function iClickOn($arg1) {
    $element = $this->getSession()->getPage()->find('xpath', '//*[text() = "' . $arg1 . '"]');
    if (!$element) {
      throw new \Exception("$arg1 could not be found");
    }
    $element->click();
  }

  /**
   * Select the first autocomplete option.
   *
   * @When I select the first autocomplete option for :prefix on the :field field
   */
  public function iSelectFirstAutocomplete($prefix, $field) {
    $driver = $this->getSession()->getDriver();
    $page = $this->getSession()->getPage();

    $element = $page->findField($field);
    if (!$element) {
      throw new \Exception("$field could not be found");
    }
    $page->fillField($field, $prefix);

    $this->getSession()->wait(1000, 'jQuery(".ui-autocomplete").is(":visible") === true');

    $xpath = $element->getXpath();
    // Down key.
    $driver->keyDown($xpath, 40);
    $driver->keyUp($xpath, 40);
  }

  /**
   * Click on selector.
   *
   * @Then /^I click on element "([^"]*)"$/
   */
  public function iClickOnElement($element) {
    $findName = $this->getSession()->getPage()->find('css', $element);
    if (!$findName) {
      throw new \Exception("$element could not be found");
    }
    $findName->click();
  }

  /**
   * Fill text into wysiwyg fields.
   *
   * @Then I fill in wysiwyg on field :arg1 with :arg2
   */
  public function iFillInWysiwygOnFieldWith($arg1, $arg2) {
    $fieldSelector = ".$arg1 .ck-editor__editable";
    $this->getSession()->executeScript("
        const domEditableElement = document.querySelector(\"$fieldSelector\");
        if (domEditableElement.ckeditorInstance) {
          const editorInstance = domEditableElement.ckeditorInstance;
          if (editorInstance) {
            editorInstance.setData(\"$arg2\");
          } else {
            throw new Exception('Could not get the editor instance!');
          }
        } else {
          throw new Exception('Could not find the element!');
        }
        ");
  }

  /**
   * Login using drush.
   *
   * @Given I am logged in as user :name
   */
  public function iAmLoggedInAsUser($name) {
    $domain = $this->getMinkParameter('base_url');
    // Pass base url to drush command.
    $uli = $this->getDriver('drush')->drush('uli', [
      "--name '" . $name . "'",
      "--browser=0",
      "--uri=$domain",
    ]);
    // Trim EOL characters.
    $uli = trim($uli);
    // Log in.
    $this->getSession()->visit($uli);
    // Check if the current page contains the current username in the title as
    // the user is redirected to the user edit page.
    // The page title pattern is "<username> | <site_name>".
    $content = $this->getSession()->getPage()->getContent();
    if (!str_contains($content, "<title>$name | ")) {
      throw new \Exception('Login failed.');
    }
  }

}
