// <reference types="cypress" />

/**
 * Registers a new "owner" user from the front end, has them submit a brand-new extension via
 * the Newextension wizard's manual-entry path, then logs in as admin to approve and publish it.
 *
 * Saves { ownerUsername, extensionId, extensionCatId, extensionName } to
 * tests/cypress/output/extension-submission.json for parts 3 and 4 to pick up.
 */

describe('Workflow part 1: extension owner registers and submits a new extension', { testIsolation: false }, () => {
  const timestamp = Date.now()

  const owner = {
    name: 'JED Test Owner',
    username: `jed-owner-${timestamp}`,
    password: 'TestPassw0rd!1',
    email: `jed-owner-${timestamp}@example.test`,
  }

  const state = {
    ownerUsername: owner.username,
    extensionId: null,
    extensionCatId: null,
    extensionName: `JED Test Extension ${timestamp}`,
  }

  it('registers the extension owner and submits a new extension manually', () => {
    cy.visit('index.php?option=com_users&view=registration')
    cy.get('#jform_name').type(owner.name)
    cy.get('#jform_username').type(owner.username)
    cy.get('#jform_password1').type(owner.password)
    cy.get('#jform_password2').type(owner.password)
    cy.get('#jform_email1').type(owner.email)
    cy.get('#member-registration button[type=submit]').click()

    // With activation disabled the account is enabled immediately, but Joomla core doesn't
    // guarantee an automatic login on every version - log in explicitly so the rest of this
    // block can rely on being authenticated.
    cy.doFrontendLogin(owner.username, owner.password, false)

    // The GitHub-URL detection step only understands a single, simple, top-level extension
    // manifest (Installer::findManifest() searches one level deep into the extracted zip).
    // Real Joomla-core-team repos - including joomla-extensions/weblinks - are multi-extension
    // package monorepos with the manifest nested several levels down (e.g.
    // administrator/components/com_weblinks/weblinks.xml), so detection can't resolve a single
    // extension out of them. Use the wizard's manual-entry path instead, which needs no
    // external repository at all.
    cy.visit('index.php?option=com_jed&view=newextension')
    cy.contains('.newextension-picker a', 'Create Manually').click()

    cy.get('#jform_name', { timeout: 10000 }).type(state.extensionName)

    cy.get('#jform_catid').then(($select) => {
      // The category field may render as a plain <select> or be wrapped by Joomla's
      // "fancy select" (choices.js) web component depending on the active template -
      // handle both so this step doesn't depend on that detail.
      const fancyWrapper = $select.closest('joomla-field-fancy-select')

      if (fancyWrapper.length) {
        const firstRealOption = $select.find('option[value!=""]').first()
        state.extensionCatId = firstRealOption.val()
        const optionText = firstRealOption.text().trim()

        cy.wrap(fancyWrapper).find('.choices__inner').click()
        cy.wrap(fancyWrapper).find('.choices__item').contains(optionText).click()
      } else {
        cy.get('#jform_catid option[value!=""]').first().then(($option) => {
          state.extensionCatId = $option.val()
          cy.get('#jform_catid').select($option.val())
        })
      }
    })

    cy.get('#form-newextension button[type=submit]').click()

    // NewextensionController::save() redirects into the (edit-only) Extensionform view for
    // the freshly-created extension, carrying its new id in the URL. SEF routing (on for this
    // site) rewrites this to /index.php/component/jed/extensionform?layout=edit&id=<n> - no
    // literal "view=extensionform" in the URL - so just match the view name itself and pull
    // "id" out of the query string, which is unaffected by SEF path rewriting either way.
    cy.url({ timeout: 20000 }).should('include', 'extensionform').then((url) => {
      state.extensionId = new URL(url).searchParams.get('id')
      expect(state.extensionId, 'new extension id').to.not.be.null
    })

    cy.doFrontendLogout()
  })

  it('approves and publishes the new extension as admin', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

    // The compare/approve screen for a specific extension can be reached directly - it's
    // exactly where the Pending-column icon on the Extensions list links to.
    cy.visit(`administrator/index.php?option=com_jed&view=extension&layout=compare&id=${state.extensionId}`)

    // Approve copies the pending submission's content onto the live row (it does not, by
    // itself, publish it - the new row is created unpublished).
    cy.contains('button, a', 'Approve').click()
    cy.get('#system-message-container .alert-message').should('be.visible')

    // Publish it via the Extensions list's bulk "Change Status" action.
    cy.visit('administrator/index.php?option=com_jed&view=extensions')
    cy.searchForItem(state.extensionName)
    cy.get('#cb0').click()
    cy.clickToolbarButton('action')
    cy.clickToolbarButton('publish')
    cy.checkForSystemMessage('published')

    cy.doAdministratorLogout()

    cy.saveJsonState('extension-submission', state)
  })
})
