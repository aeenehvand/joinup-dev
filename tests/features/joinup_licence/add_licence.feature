@api
Feature: Add licence through UI
  In order to manage licences
  As a moderator
  I need to be able to add licences through the UI.

  Scenario: "Add licence" button should be shown only to the moderators.
    When I am logged in as a "moderator"
    # The dashboard link is visible by clicking the user icon.
    And I am on the homepage
    Then I should see the link "Dashboard"
    When I click "Dashboard"
    Then I should see the link "Licences overview"
    When I click "Licences overview"
    Then I should see the link "Add licence"

  Scenario: Add licence as a moderator.
    Given I am logged in as a moderator
    And I am on the homepage
    When I click "Dashboard"
    When I click "Licences overview"
    When I click "Add licence"
    Then I should see the heading "Add Licence"
    When I fill in "Title" with "This is a random licence"
    And I fill in "Description" with "Licence details go here.."
    And I press "Save"
    Then I should have 1 licence
    Then I delete the "This is a random licence" licence