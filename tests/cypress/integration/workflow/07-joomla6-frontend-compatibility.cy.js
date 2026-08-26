/// <reference types="cypress" />

/**
 * Workflow part 7: an extension owner declares Joomla 6 compatibility from the front end.
 *
 * Registers a brand-new owner and submits a brand-new extension (self-contained, same manual-entry
 * path as part 1) so this spec does not depend on run order against the other numbered specs. The
 * owner then edits their own, already-approved listing twice through the site's own edit form -
 * `com_jed&task=extensionform.edit`, the same link the Dashboard's "Extensions (as Owner)" table and
 * the extension detail page's own Edit button both point at (ExtensionformModel::isAuthorised()
 * gates this on ownership, not an admin-only core.edit) - first to declare Joomla 4/5
 * compatibility, then to add Joomla 6.
 *
 * Every front-end save is a pending revision (P1-02): it only reaches the live, public row once an
 * admin approves it on the compare screen, exactly as in parts 1 and 6. The public detail page's
 * "Compatibility" fact (`JedtrophyHelper::getTrophyVersionsStringFull()`) is asserted after each
 * approval, so this exercises the real save -> moderate -> publish path rather than writing to the
 * database directly.
 */

describe('Workflow part 7: extension owner adds Joomla 6 compatibility from the front end', { testIsolation: false }, () => {
  const timestamp = Date.now()

  const owner = {
    name: 'JED Test J6 Owner',
    username: `jed-j6-owner-${timestamp}`,
    password: 'TestPassw0rd!3',
    email: `jed-j6-owner-${timestamp}@example.test`,
  }

  const state = {
    extensionId: null,
    extensionCatId: null,
    extensionName: `JED Test J6 Extension ${timestamp}`,
  }

  const detailUrl = () =>
    `index.php?option=com_jed&view=extension&catid=${state.extensionCatId}&id=${state.extensionId}`

  const editFormUrl = () =>
    `index.php?option=com_jed&task=extensionform.edit&id=${state.extensionId}`

  const openNewInfoTab = () =>
    cy.get('#newextensionTab button[role="tab"]').filter((_, el) => el.textContent.trim() === 'Info').click()

  const openEditInfoTab = () =>
    cy.get('#extensionformTab button[role="tab"]').filter((_, el) => el.textContent.trim() === 'Info').click()

  const setJoomlaVersion = (value) =>
    cy.get(`input[name="jform[joomla_versions][]"][value="${value}"]`).check()

  // A save is a pending revision; it reaches the live row only through approval - the same
  // compare screen the Pending column on the Extensions list links to.
  const approvePendingRevision = () => {
    cy.visit(`administrator/index.php?option=com_jed&view=extension&layout=compare&id=${state.extensionId}`)
    cy.contains('button, a', 'Approve').click()
    cy.get('#system-message-container .alert-message').should('be.visible')
  }

  // getTrophyVersionsStringFull() joins entries as "Joomla!&nbsp;<n>" - the regex below
  // matches that literal U+00A0 non-breaking space so assertions can use an ordinary space.
  const compatibilityText = () =>
    cy.contains('dt', 'Compatibility').next('dd').invoke('text').then((text) => text.replace(/ /g, ' '))

  it('registers the extension owner and submits a new extension manually', () => {
    cy.visit('index.php?option=com_users&view=registration')
    cy.get('#jform_name').type(owner.name)
    cy.get('#jform_username').type(owner.username)
    cy.get('#jform_password1').type(owner.password)
    cy.get('#jform_password2').type(owner.password)
    cy.get('#jform_email1').type(owner.email)
    cy.get('#member-registration button[type=submit]').click()

    cy.doFrontendLogin(owner.username, owner.password, false)

    cy.visit('index.php?option=com_jed&view=newextension')
    cy.contains('.newextension-picker a', 'Create Manually').click()

    cy.get('#jform_name', { timeout: 10000 }).type(state.extensionName)

    cy.get('#jform_catid').then(($select) => {
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

    openNewInfoTab()
    setJoomlaVersion('40')
    setJoomlaVersion('50')

    cy.get('#form-newextension button[type=submit]').click()

    cy.url({ timeout: 20000 }).should('include', 'extensionform').then((url) => {
      state.extensionId = new URL(url).searchParams.get('id')
      expect(state.extensionId, 'new extension id').to.not.be.null
    })

    cy.doFrontendLogout()
  })

  it('approves and publishes the new extension as admin', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

    cy.visit(`administrator/index.php?option=com_jed&view=extension&layout=compare&id=${state.extensionId}`)
    cy.contains('button, a', 'Approve').click()
    cy.get('#system-message-container .alert-message').should('be.visible')

    cy.visit('administrator/index.php?option=com_jed&view=extensions')
    cy.searchForItem(state.extensionName)
    cy.get('#cb0').click()
    cy.clickToolbarButton('action')
    cy.clickToolbarButton('publish')
    cy.checkForSystemMessage('published')

    cy.doAdministratorLogout()
  })

  it('approves that revision, and the public page shows Joomla 4 and 5', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)
    approvePendingRevision()
    cy.doAdministratorLogout()

    cy.visit(detailUrl())
    compatibilityText().then((text) => {
      expect(text).to.include('Joomla! 4')
      expect(text).to.include('Joomla! 5')
      expect(text).to.not.include('Joomla! 6')
    })
  })

  it('lets the owner add Joomla 6 compatibility from the front end edit form', () => {
    cy.doFrontendLogin(owner.username, owner.password, false)

    cy.visit(editFormUrl())
    openEditInfoTab()

    // The previous save's choices must still be there - this is an addition, not a replacement.
    cy.get('input[name="jform[joomla_versions][]"][value="40"]').should('be.checked')
    cy.get('input[name="jform[joomla_versions][]"][value="50"]').should('be.checked')

    setJoomlaVersion('60')

    cy.get('#form-extension button[type=submit]').click()
    cy.url().should('include', 'view=dashboard')

    cy.doFrontendLogout()
  })

  it('approves that revision, and the public page shows Joomla 4, 5, and 6', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)
    approvePendingRevision()
    cy.doAdministratorLogout()

    cy.visit(detailUrl())
    compatibilityText().then((text) => {
      expect(text).to.include('Joomla! 4')
      expect(text).to.include('Joomla! 5')
      expect(text).to.include('Joomla! 6')
    })
  })
})
