@api
Feature: Sharing content in social networks
  As a user of the platform
  I want to share content in my social networks
  So that useful information has more visibility

  Scenario Outline: Sharing content in Facebook, Twitter and Google +.
    Given the following collection:
      | title | Social networks |
      | state | validated       |
    And <content type> content:
      | title                 | collection      | state     |
      | Important information | Social networks | validated |

    When I am an anonymous user
    And I go to the content page of the type "<content type>" with the title "Important information"
    And I click "Share"
    Then I should see the heading "Share Important information in"
    And I should see the link "Facebook"
    And I should see the link "Twitter"
    And I should see the link "Google +"

    Examples:
      | content type |
      | event        |
      | document     |
      | discussion   |
      | news         |
