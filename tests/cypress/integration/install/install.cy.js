// type definitions for Cypress object "cy"
// <reference types="cypress" />

/**
 * Installs both bundled JED sample-data plugins (extensions/categories/reviews/tickets, then
 * the front-end main-menu items) and opens up self-registration - the shared prerequisite every
 * other part of this workflow depends on. Produces no downstream state file: everything it sets
 * up lives in the Joomla database itself (plugins, config, sample content), not anything a later
 * spec needs to be handed explicitly.
 */

describe('Install Joomla and JED sample data', () => {
  it('installs the JED sample data and opens up self-registration', function () {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)
    cy.cancelTour();
    cy.disableStatistics()
    cy.setErrorReportingToDevelopment()

    // Both sample-data plugins ship disabled; enable them before they show up on the
    // "Install Sample Data" dashboard widget.
    cy.enablePlugin('Sample Data - JED')
    cy.enablePlugin('Sample Data - JED Menu')

    // The "Install Sample Data" widget lives on the admin Home Dashboard, one row (and one
    // independent "Install" button) per enabled sample-data plugin.
    cy.visit('administrator/index.php?option=com_cpanel&view=cpanel')

    // "Sample Data - JED" (plg_sampledata_jed) - a 7-step installer for extensions,
    // categories, reviews, tickets, and VEL sample rows. The widget's own JS (mod_sampledata's
    // sampledata-process.js) chains every step automatically after a single click, gated by a
    // native confirm() dialog (Cypress auto-accepts those) - it never marks the row itself
    // "Success"; completion instead renders a "Sample data installed." system message once
    // the whole chain finishes (each intermediate per-step message auto-dismisses after 3s,
    // so it isn't a reliable thing to assert on).
    cy.get('li.sampledata-jed button.apply-sample-data[data-type="jed"]').click()
    cy.get('#system-message-container .alert-message', { timeout: 60000 }).should('contain.text', 'Sample data installed.')

    // "Sample Data - JED Menu" (plg_sampledata_jed2) - a single step that creates the
    // front-end main-menu items (Browse Extensions, Register, New Extension, Dashboard, ...).
    cy.get('li.sampledata-jed2 button.apply-sample-data[data-type="jed2"]').click()
    cy.get('#system-message-container .alert-message', { timeout: 30000 }).should('contain.text', 'Sample data installed.')

    // Allow front-end self-registration with no activation step, so the users registered in
    // later parts of this workflow can log straight in. (Joomla's own default is "Admin
    // Activation", which would otherwise block them.)
    // Joomla 6 routes component "Options" screens through com_config, not a com_users
    // view of its own (index.php?option=com_users&view=config is a stale Joomla 3-era URL).
    cy.visit('administrator/index.php?option=com_config&view=component&component=com_users')
    cy.get('#jform_allowUserRegistration1').click({ force: true }) // "Yes"
    cy.get('#jform_useractivation').select('0') // "None"
    cy.clickToolbarButton('save & close')
    cy.checkForSystemMessage('saved')

    cy.doAdministratorLogout()
  })
})
