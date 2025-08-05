@api @clone @clean
Feature: User authentication
  In order to protect the integrity of the website
  As a product owner
  I want to make sure users with various roles can only access pages they are authorized to

  Scenario: Authenticated user cannot access site administration
    And I am not logged in
    When I go to "admin"
    Then I should get a "403" HTTP response
    When I go to "admin/appearance"
    Then I should get a "403" HTTP response
    When I go to "admin/config"
    Then I should get a "403" HTTP response
    When I go to "admin/content"
    Then I should get a "403" HTTP response
    When I go to "admin/people"
    Then I should get a "403" HTTP response
    When I go to "admin/structure"
    Then I should get a "403" HTTP response
    When I go to "node/add"
    Then I should get a "403" HTTP response
    When I go to "user/1"
    Then I should get a "403" HTTP response

  Scenario: User creation should force EU login username
    Given users:
      | name              | status | roles         |
      | testadministrator | 1      | administrator |
    And I am logged in as user "testadministrator"
    When I visit "admin/people/create"
    Then I should see the "input" element with the "required" attribute set to "required" in the user_register_form region
