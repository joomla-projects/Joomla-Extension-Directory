// <reference types="cypress" />

/**
 * Who may open and save the front end edit form of a listing, and what the form refuses.
 *
 * Self-contained: it registers its own owner and listing (earlier specs block or delete theirs),
 * grants the Registered group Edit Own for the duration, and registers a second, unrelated user
 * to be the person who must be turned away.
 */

describe('Extension edit form: access and validation', { testIsolation: false }, () => {
  const timestamp = Date.now()
  const ownerPassword = 'TestPassw0rd!1'

  const stranger = {
    name: 'JED Test Stranger',
    username: `jed-stranger-${timestamp}`,
    password: 'TestPassw0rd!1',
    email: `jed-stranger-${timestamp}@example.test`,
  }

  const owner = {
    name: 'JED Test Edit Owner',
    username: `jed-edit-owner-${timestamp}`,
    password: ownerPassword,
    email: `jed-edit-owner-${timestamp}@example.test`,
  }

  let extension

  // Absolute, because Cypress reads a relative URL with a dot before the first slash as a host
  const siteUrl = (path) => `${Cypress.config('baseUrl')}${path}`
  const editUrl = () => siteUrl(`index.php?option=com_jed&task=extensionform.edit&id=${extension.extensionId}`)
  const editFormShown = () => cy.get('#form-extension', { timeout: 10000 })

  // Undone in after(), so the install other specs run against is left as it was found
  before(() => {
    cy.setRegisteredEditOwn('1')
  })

  after(() => {
    cy.setRegisteredEditOwn('')
  })

  it('registers an owner who has a published listing', () => {
    cy.submitAndPublishExtension(owner, `JED Edit Access Extension ${timestamp}`).then((created) => {
      extension = created
    })
  })

  const detailUrl = () =>
    siteUrl(`index.php?option=com_jed&view=extension&catid=${extension.extensionCatId}&id=${extension.extensionId}`)

  // The edit form answers a person who may not use it with an error page, not with the form
  const editRefused = () => cy.request({ url: editUrl(), failOnStatusCode: false }).then((response) => {
    expect(response.status).to.be.oneOf([401, 403])
    expect(response.body).to.not.contain('id="form-extension"')
  })

  it('registers an unrelated user', () => {
    cy.clearCookies()
    cy.visit('index.php?option=com_users&view=registration')
    cy.get('#jform_name').type(stranger.name)
    cy.get('#jform_username').type(stranger.username)
    cy.get('#jform_password1').type(stranger.password)
    cy.get('#jform_password2').type(stranger.password)
    cy.get('#jform_email1').type(stranger.email)
    cy.get('#member-registration button[type=submit]').click()
  })

  it('does not show the edit form to a visitor who is not logged in', () => {
    cy.clearCookies()
    editRefused()
  })

  it('does not show the edit form to a user who does not own the listing', () => {
    cy.clearCookies()
    cy.doFrontendLogin(stranger.username, stranger.password, false)
    editRefused()
  })

  it('does not let that user save changes by posting the form directly', () => {
    // Still logged in as the stranger. Asking for the edit form puts the listing id in their
    // session (the form is refused, the id stays), so the save task has nothing left to stop it
    // but the permission check, which is what this step is about.
    editRefused()

    // The form token is on every page of a logged in user.
    cy.request(siteUrl('index.php')).then((page) => {
      const token = /name="([0-9a-f]{32})" value="1"/.exec(page.body)
      expect(token, 'a form token on the page').to.not.be.null

      cy.request({
        method: 'POST',
        url: siteUrl('index.php?option=com_jed&task=extensionform.save'),
        form: true,
        failOnStatusCode: false,
        body: {
          'jform[id]': extension.extensionId,
          'jform[name]': 'Defaced by a stranger',
          [token[1]]: '1',
        },
      }).then((response) => {
        expect(response.body).to.contain('Save not permitted')
      })
    })
  })

  it('shows the edit form to the owner', () => {
    cy.clearCookies()
    cy.doFrontendLogin(owner.username, ownerPassword, false)
    cy.visit(editUrl())

    editFormShown()
    cy.get('#jform_name').should('have.value', extension.extensionName)
  })

  it('refuses to save a listing without a name', () => {
    cy.visit(editUrl())
    editFormShown()

    cy.get('#jform_name').clear()
    cy.get('#form-extension button[type=submit]').click()

    // Still on the form, with the name flagged, and the listing keeps its name
    editFormShown()
    cy.get('#jform_name').should('have.class', 'invalid').and('have.attr', 'aria-invalid', 'true')
    cy.request(detailUrl()).its('body').should('contain', extension.extensionName)
  })

  it('refuses a support link that is not a web address', () => {
    cy.visit(editUrl())
    editFormShown()

    cy.get('#extensionformTab button[role="tab"]').filter((_, el) => el.textContent.trim() === 'Links').click()
    cy.get('#jform_support_url').clear().type('not a web address')
    cy.get('#form-extension button[type=submit]').click()

    // Either the browser side or the server side refuses; both leave the form on screen with an error
    editFormShown()
    cy.get('#jform_support_url').should('have.class', 'invalid')
    cy.doFrontendLogout()
  })
})
