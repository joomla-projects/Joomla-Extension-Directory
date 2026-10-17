// <reference types="cypress" />

/**
 * Joomla's own "Install from Web" tab, running its real client script, against com_apps.
 *
 * The plugin has the directory's address built in (https://appscdn.joomla.org/webapps/), so the
 * requests are redirected to this site with cy.intercept. Everything else - the script, the
 * sanitizer, the tab - is Joomla's. Nothing is installed: the test stops at the confirmation
 * form the client shows before it would.
 *
 * filter_version=all is added to the requests because the sample data does not declare any
 * extension for the Joomla version the test site runs; which versions are listed is covered by
 * the unit tests.
 */

// The client covers the tab with a loader while a request is in flight; wait for it to go.
const loaded = () => cy.get('joomla-core-loader', { timeout: 15000 }).should('not.exist')

describe('Install from Web tab', { testIsolation: false }, () => {
  beforeEach(() => {
    cy.intercept('https://appscdn.joomla.org/webapps/**', (request) => {
      const url = new URL(request.url)
      const target = new URL('index.php', Cypress.config('baseUrl'))

      target.search = url.search
      target.searchParams.set('filter_version', 'all')
      // Cypress's proxy hands the client an empty body for a 304, so never revalidate.
      target.searchParams.set('nocache', String(Date.now()))
      request.url = target.toString()
    }).as('directory')
  })

  before(() => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)
  })

  after(() => {
    cy.doAdministratorLogout()
  })

  it('shows the home page of the directory', () => {
    cy.visit('administrator/index.php?option=com_installer&view=install')
    cy.contains('joomla-tab button[role="tab"]', 'Install from Web', { timeout: 15000 }).click()
    cy.wait('@directory').its('response.statusCode').should('eq', 200)
    cy.get('#jed-container #com-apps-searchbox', { timeout: 15000 }).should('be.visible')
    cy.get('#jed-container .load-extension').should('have.length.greaterThan', 10)
    cy.get('#jed-container .com-apps-sidebar a.transcode').should('have.length.greaterThan', 3)
  })

  it('opens a category from the sidebar', () => {
    cy.get('#jed-container .com-apps-sidebar a.transcode').eq(1).click()
    cy.wait('@directory').its('request.url').should('include', 'view=category')
    loaded()
    cy.get('#jed-container .com-apps-sidebar a.active').should('exist')
    cy.get('#jed-container .breadcrumb a.transcode').should('exist')
  })

  it('searches, and says so when nothing matches', () => {
    cy.get('#com-apps-searchbox').clear()
    cy.get('#com-apps-searchbox').type('backup{enter}')
    cy.wait('@directory').its('request.url').should('include', 'filter_search=backup')
    loaded()

    cy.get('#com-apps-searchbox').clear()
    cy.get('#com-apps-searchbox').type('qqqzzzxxx{enter}')
    cy.wait('@directory')
    loaded()
    cy.get('#jed-container').should('contain.text', 'Cannot find any extensions')
  })

  it('sorts', () => {
    cy.get('#com-apps-searchbox').clear()
    cy.get('#com-apps-searchbox').type('{enter}')
    cy.wait('@directory')
    loaded()
    cy.get('#com-apps-ordering').select('score')
    cy.wait('@directory').its('request.url').should('include', 'ordering=score')
    loaded()
    cy.get('#com-apps-ordering').should('have.value', 'score')
  })

  it('opens an extension and offers its update server to the installer, without installing', () => {
    cy.get('#jed-container .load-extension').first().click()
    cy.wait('@directory').its('request.url').should('include', 'view=extension')
    loaded()
    cy.get('#jed-container .item-view h2').should('exist')

    cy.get('#jed-container').then(($container) => {
      const button = $container.find('#install-extension')

      if (!button.length) {
        cy.log('this listing has no update server')

        return
      }

      const updateUrl = button.attr('data-downloadurl')

      cy.wrap(button).click()
      cy.get('#uploadform-web-url').should('have.text', updateUrl)
      cy.get('#install_url').should('have.value', updateUrl)
      cy.get('#uploadform-web-cancel').click()
    })
  })

  it('is a response a CDN may keep', () => {
    cy.request({ url: `${Cypress.config('baseUrl')}index.php?option=com_apps&format=json&view=dashboard&release=6.0&filter_version=all` }).then((response) => {
      expect(response.headers['cache-control']).to.match(/public.*s-maxage=\d+/)
      expect(response.headers).to.not.have.property('set-cookie')
      expect(response.headers['access-control-allow-origin']).to.eq('*')
    })
  })
})
