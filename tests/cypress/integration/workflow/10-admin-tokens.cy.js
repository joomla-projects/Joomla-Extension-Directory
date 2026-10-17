// type definitions for Cypress object "cy"
// <reference types="cypress" />

/**
 * Admin tasks that change state must carry a form token. The drag-and-drop ordering endpoints of
 * the JED lists had overridden core's saveOrderAjax() and dropped the check, so a forged request
 * could reorder records for a logged-in administrator.
 */

describe('Workflow part 10: admin tokens', { testIsolation: false }, () => {
  // cy.request() does not resolve a bare relative path against baseUrl the way cy.visit() does.
  const url = (path) => Cypress.config('baseUrl') + path

  const endpoints = [
    ['reviews', 'com_jed', 'reviews.saveOrderAjax'],
    ['extensions', 'com_jed', 'extensions.saveOrderAjax'],
    ['extension images', 'com_jed', 'extensionimages.saveOrderAjax'],
    ['tickets', 'com_tickets', 'tickets.saveOrderAjax'],
  ]

  it('refuses a saveOrderAjax request that carries no token', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

    endpoints.forEach(([label, option, task]) => {
      cy.request({
        method: 'POST',
        url: url('administrator/index.php'),
        form: true,
        body: { option, task, format: 'raw', 'cid[]': '1', 'order[]': '777' },
        followRedirect: false,
        failOnStatusCode: false,
      }).then((res) => {
        expect(res.body, `${label}: ordering saved without a token`).to.not.equal('1')
        expect(res.status, label).to.not.equal(200)
      })
    })

    cy.doAdministratorLogout()
  })
})
