// type definitions for Cypress object "cy"
// <reference types="cypress" />

/**
 * The sample-data installer wipes and reloads the directory, so it is a Super User tool: an
 * ordinary registered user must not be able to run any of its steps through com_ajax.
 */

describe('Workflow part 9: sample-data access', { testIsolation: false }, () => {
  const timestamp = Date.now()
  const password = 'TestPassw0rd!1'
  const user = { name: 'JED Sample B', username: `jed-sample-${timestamp}`, email: `jed-sample-${timestamp}@example.test` }

  // cy.request() does not resolve a bare relative path against baseUrl the way cy.visit() does.
  const url = (path) => Cypress.config('baseUrl') + path

  it('refuses the sample-data installer to an ordinary user', () => {
    cy.visit('index.php?option=com_users&view=registration')
    cy.get('#jform_name').type(user.name)
    cy.get('#jform_username').type(user.username)
    cy.get('#jform_password1').type(password)
    cy.get('#jform_password2').type(password)
    cy.get('#jform_email1').type(user.email)
    cy.get('#member-registration button[type=submit]').click()

    cy.doFrontendLogin(user.username, password, false)

    ;['jed', 'jed2'].forEach((type) => {
      cy.request({
        url: url(`index.php?option=com_ajax&group=sampledata&plugin=SampledataApplyStep1&type=${type}&format=json`),
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.body.success).to.equal(false)
      })
    })

    cy.doFrontendLogout()
  })
})
