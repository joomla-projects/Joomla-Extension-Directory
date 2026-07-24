// <reference types="cypress" />

/**
 * Logs back in as the extension owner (registered in part 1) and posts a developer response
 * to the review published in part 2.
 *
 * Requires parts 1 and 2 to have already run in this session - reads
 * tests/cypress/output/extension-submission.json for the owner's username and
 * tests/cypress/output/review-workflow.json for the published review's id.
 */

describe('Workflow part 3: extension owner responds to the published review', { testIsolation: false }, () => {
  const ownerPassword = 'TestPassw0rd!1' // must match the password part 2 registered the owner with

  let extension
  let review

  it('lets the extension owner respond to the published review', () => {
    cy.loadJsonState('extension-submission').then((savedState) => {
      extension = savedState
    })
    cy.loadJsonState('review-workflow').then((savedState) => {
      review = savedState
    })

    // Deferred via .then() - see the equivalent comment in 03-review-workflow.cy.js for why:
    // the loadJsonState() calls above only resolve once Cypress reaches them in the command
    // queue, which is after this synchronous test body has already finished being defined.
    cy.then(() => {
      cy.doFrontendLogin(extension.ownerUsername, ownerPassword, false)
      cy.visit(`index.php?option=com_jed&view=review&id=${review.reviewId}`)
    })

    cy.get('textarea[name="developer_response"]')
      .should('be.visible')
      .type('Thanks so much for the kind words - glad it\'s working well for you!')
    cy.get('form').contains('button[type=submit]', /submit/i).click()

    cy.get('#system-message-container .alert-message').should('be.visible')

    // The response is now pending moderation, so it shouldn't be visible publicly yet - the
    // review's own detail page (for the owner) shows it as "awaiting review" instead of the
    // submission form re-appearing.
    cy.get('form textarea[name="developer_response"]').should('not.exist')

    cy.doFrontendLogout()
  })
})
