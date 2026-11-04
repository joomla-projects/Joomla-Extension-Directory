// Shared helpers for the JED extension/review/developer-response workflow specs
// (tests/cypress/integration/workflow/). Used by both the single long journey spec
// (extension-review-lifecycle.cy.js) and its split-up per-part equivalents.

/**
 * Persists a plain-object of state to tests/cypress/output/<name>.json, so a later spec
 * FILE (which runs in a fresh browser context with no shared JS state) can pick up where
 * an earlier one left off - e.g. the extension id a submission spec created, for a review
 * spec to write a review against. Only meaningful within a single `cypress run` invocation
 * where specs execute in filename order; not meant to persist across separate runs.
 */
Cypress.Commands.add('saveJsonState', (name, data) => {
  cy.writeFile(`tests/cypress/output/${name}.json`, data)
})

/**
 * Reads state a previous spec file saved via saveJsonState(). Fails clearly if that spec
 * hasn't run yet in this session - run the workflow specs in their numbered order.
 */
Cypress.Commands.add('loadJsonState', (name) => {
  return cy.readFile(`tests/cypress/output/${name}.json`)
})

/**
 * "1" = Allowed, "" = Not Set/Inherited: the Edit Own action of com_jed for the Registered group.
 * A clean install grants it to nobody, so a front end owner is refused the edit form until it is set.
 */
Cypress.Commands.add('setRegisteredEditOwn', (value) => {
  cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

  cy.visit('administrator/index.php?option=com_config&view=component&component=com_jed')
  cy.get('#configTabs button[role="tab"]').contains('Permissions').click()

  // Scoped off the clicked tab's own aria-controls: the group's numeric id is an install default
  cy.get('#permissions-sliders button[role="tab"]').contains('Registered')
    .click()
    .invoke('attr', 'aria-controls')
    .then((panelId) => {
      cy.get(`#${panelId}`).contains('tr', 'Edit Own').find('select').select(value)
    })

  cy.get('#toolbar-apply').click()
  cy.get('#system-message-container').should('contain.text', 'Configuration saved')

  cy.doAdministratorLogout()
})

/**
 * Registers `owner` ({ name, username, password, email }), has them submit a new listing called
 * `name` through the manual path, then approves and publishes it as admin. Yields
 * { extensionId, extensionCatId, extensionName }. Leaves nobody logged in.
 */
Cypress.Commands.add('submitAndPublishExtension', (owner, name) => {
  const result = { extensionId: null, extensionCatId: null, extensionName: name }

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
  cy.get('#jform_name', { timeout: 10000 }).type(name)

  cy.get('#jform_catid').then(($select) => {
    const fancyWrapper = $select.closest('joomla-field-fancy-select')

    if (fancyWrapper.length) {
      const firstRealOption = $select.find('option[value!=""]').first()
      result.extensionCatId = firstRealOption.val()

      cy.wrap(fancyWrapper).find('.choices__inner').click()
      cy.wrap(fancyWrapper).find('.choices__item').contains(firstRealOption.text().trim()).click()
    } else {
      cy.get('#jform_catid option[value!=""]').first().then(($option) => {
        result.extensionCatId = $option.val()
        cy.get('#jform_catid').select($option.val())
      })
    }
  })

  cy.get('#form-newextension button[type=submit]').click()

  cy.url({ timeout: 20000 }).should('include', 'extensionform').then((url) => {
    result.extensionId = new URL(url).searchParams.get('id')
    expect(result.extensionId, 'new extension id').to.not.be.null
  })

  cy.doFrontendLogout()

  cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

  // The id is only known once the commands above have run, so the URL is built inside then()
  cy.then(() => cy.visit(`administrator/index.php?option=com_jed&view=extension&layout=compare&id=${result.extensionId}`))
  cy.contains('button, a', 'Approve').click()
  cy.get('#system-message-container .alert-message').should('be.visible')

  cy.visit('administrator/index.php?option=com_jed&view=extensions')
  cy.searchForItem(name)
  cy.get('#cb0').click()
  cy.clickToolbarButton('action')
  cy.clickToolbarButton('publish')
  cy.checkForSystemMessage('published')
  cy.doAdministratorLogout()

  cy.wrap(result)
})
