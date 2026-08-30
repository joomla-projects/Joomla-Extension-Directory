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
 *
 * A clean install ships no ACL mapping for `core.edit.own` on `com_jed` at all (a real deployment
 * is expected to grant it to its developer-facing group), so this
 * spec grants it to the Registered group itself before the owner's edit-form step - otherwise
 * ExtensionformModel::isAuthorised() refuses every front-end owner edit with a 401. `after()`
 * reverts that grant once the spec is done, win or lose, so it doesn't linger on an install other
 * specs also run against.
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

  // Cypress usually resolves a bare "index.php?..." against the configured baseUrl itself, but
  // around a login/session boundary it can fall back to a hard top-level navigation that hands
  // the raw string straight to the browser - which then applies its own address-bar fixup and
  // treats a scheme-less, dot-containing token like "index.php" as a hostname to look up rather
  // than a path (`getaddrinfo ENOTFOUND index.php`). Building the absolute URL from
  // Cypress.config('baseUrl') up front leaves nothing for that fallback to misinterpret, and
  // still respects a subpath baseUrl (e.g. CI's `http://localhost/<db>`) rather than assuming
  // root, which a leading "/" would not.
  const siteUrl = (path) => `${Cypress.config('baseUrl').replace(/\/$/, '')}/${path}`

  const detailUrl = () =>
    `index.php?option=com_jed&view=extension&catid=${state.extensionCatId}&id=${state.extensionId}`

  const editFormUrl = () =>
    siteUrl(`index.php?option=com_jed&task=extensionform.edit&id=${state.extensionId}`)

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

  // "1" = Allowed, "" = Not Set/Inherited (rules.php's own option values) - shared by the
  // setup step below and its teardown, which puts the install back exactly how it found it.
  const setOwnerEditOwnPermission = (value) => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

    cy.visit('administrator/index.php?option=com_config&view=component&component=com_jed')
    cy.get('#configTabs button[role="tab"]').contains('Permissions').click()

    // Scoped off the clicked tab's own aria-controls rather than a hardcoded group id, since the
    // "Registered" group's numeric id is an install default, not something to assume here.
    cy.get('#permissions-sliders button[role="tab"]').contains('Registered')
      .click()
      .invoke('attr', 'aria-controls')
      .then((panelId) => {
        cy.get(`#${panelId}`).contains('tr', 'Edit Own').find('select').select(value)
      })

    cy.get('#toolbar-apply').click()
    cy.get('#system-message-container').should('contain.text', 'Configuration saved')

    cy.doAdministratorLogout()
  }

  // ExtensionformModel::isAuthorised() requires core.edit.own on com_jed for anyone who is not
  // already covered by the blanket core.edit - a clean install ships no group-to-action mapping
  // for it at all (access.xml's own comment: that mapping is "per-installation data, set in
  // Global Configuration"), so a freshly registered owner is refused with a 401 until a real
  // deployment grants it once to its developer-facing group, same as here. Undone in after().
  before(() => setOwnerEditOwnPermission('1'))

  // Undoes the grant below regardless of how the spec finishes, so this spec doesn't leave a
  // permanent ACL change behind on an install other specs also run against.
  after(() => setOwnerEditOwnPermission(''))

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

    // This isn't ideal as it relies on SEF URLs being enabled and our sample data structure
    cy.url().should('include', 'dashboard')
    cy.get('joomla-alert').should('not.contain.text', 'error')
    cy.get('joomla-alert').should('not.contain.text', 'danger')

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
