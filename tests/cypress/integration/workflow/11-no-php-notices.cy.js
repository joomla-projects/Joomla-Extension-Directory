// type definitions for Cypress object "cy"
// <reference types="cypress" />

/**
 * The install spec sets Joomla's error reporting to development, which prints PHP warnings,
 * notices and deprecations into the page. This visits the main front-end and administrator pages
 * and fails when any of them contains one, so a new "Undefined property" or a PHP deprecation is
 * caught when it is introduced instead of being found in a log.
 *
 * Needs the data of parts 1 and 2 (an extension and its review), so it runs after them.
 */

describe('Workflow part 11: no PHP warnings or deprecations on the main pages', { testIsolation: false }, () => {
  const phpMessage = /(Warning|Deprecated|Notice|Fatal error):\s+\S/

  let extension
  let review

  const visitClean = (path) => {
    cy.visit(path)
    cy.get('body').invoke('text').then((text) => {
      const match = text.match(new RegExp(`.{0,60}${phpMessage.source}.{0,120}`))
      expect(match ? match[0] : null, `PHP message on ${path}`).to.equal(null)
    })
  }

  before(() => {
    cy.loadJsonState('extension-submission').then((state) => { extension = state })
    cy.loadJsonState('review-workflow').then((state) => { review = state })
  })

  it('front-end pages', () => {
    cy.doFrontendLogin(Cypress.env('username'), Cypress.env('password'), false)

    cy.then(() => {
      [
        'index.php?option=com_jed&view=extensions',
        'index.php?option=com_jed&view=categories',
        `index.php?option=com_jed&view=category&id=${extension.extensionCatId}`,
        `index.php?option=com_jed&view=extension&catid=${extension.extensionCatId}&id=${extension.extensionId}`,
        `index.php?option=com_jed&view=review&id=${review.reviewId}`,
        'index.php?option=com_jed&view=reviews',
        'index.php?option=com_jed&view=dashboard',
        'index.php?option=com_jed&view=newextension',
        'index.php?option=com_tickets&view=tickets',
        'index.php?option=com_tickets&view=ticketform&layout=edit',
        `index.php?option=com_tickets&view=ticketform&layout=edit&litem=1&lid=${extension.extensionId}&vr=${extension.extensionId}`,
        'index.php?option=com_abandonware&view=abandoned',
        'index.php?option=com_abandonware&view=report',
      ].forEach(visitClean)
    })

    cy.doFrontendLogout()
  })

  it('administrator pages', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

    cy.then(() => {
      [
        'administrator/index.php?option=com_jed&view=extensions',
        `administrator/index.php?option=com_jed&task=extension.edit&id=${extension.extensionId}`,
        `administrator/index.php?option=com_jed&view=extension&layout=historylist&id=${extension.extensionId}`,
        `administrator/index.php?option=com_jed&view=extension&layout=compare&id=${extension.extensionId}`,
        'administrator/index.php?option=com_jed&view=reviews',
        `administrator/index.php?option=com_jed&task=review.edit&id=${review.reviewId}`,
        'administrator/index.php?option=com_jed&view=extensionimages',
        'administrator/index.php?option=com_jed&view=useraccess',
        'administrator/index.php?option=com_tickets&view=tickets',
        'administrator/index.php?option=com_abandonware&view=cases',
        'administrator/index.php?option=com_abandonware&view=reports',
      ].forEach(visitClean)
    })

    cy.doAdministratorLogout()
  })
})
