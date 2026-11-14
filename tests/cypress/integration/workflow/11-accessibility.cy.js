// <reference types="cypress" />
import 'cypress-axe'

/**
 * An axe scan of the public pages a visitor meets first, against WCAG 2.1 A and AA.
 *
 * Only serious and critical findings fail the run. A failure prints a table of the rule, how many
 * elements it hit and the first selector, so the log says what to fix.
 */

const siteUrl = (path) => `${Cypress.config('baseUrl')}${path}`

const scan = () => {
  cy.injectAxe()
  cy.checkA11y(
    'main',
    {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
      includedImpacts: ['serious', 'critical'],
    },
    (violations) => {
      cy.task('log', `${violations.length} accessibility violation(s)`)
      cy.task('table', violations.map(({ id, impact, nodes }) => ({
        rule: id,
        impact,
        elements: nodes.length,
        first: nodes[0].target.join(' '),
      })))
    }
  )
}

describe('Accessibility of the public pages', () => {
  it('has no serious problem on the catalogue', () => {
    cy.visit(siteUrl('index.php?option=com_jed&view=extensions'))
    cy.get('.card--extension').should('exist')
    scan()
  })

  it('has no serious problem on a listing page', () => {
    cy.visit(siteUrl('index.php?option=com_jed&view=extensions'))
    cy.get('.card--extension .card__extension-title').first().click()
    cy.get('main h2').should('exist')
    scan()
  })

  it('has no serious problem on the categories page', () => {
    cy.visit(siteUrl('index.php?option=com_jed&view=categories'))
    scan()
  })

  it('has no serious problem on the registration form', () => {
    cy.visit(siteUrl('index.php?option=com_users&view=registration'))
    scan()
  })
})
