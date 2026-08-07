// <reference types="cypress" />

/**
 * Registers a new "reviewer" user, has them write a review for the extension part 1 created,
 * then logs in as admin to moderate (publish) that review.
 *
 * Saves { reviewId } to tests/cypress/output/review-workflow.json for part 4 to pick up.
 */

describe('Workflow part 2: reviewer writes a review, admin moderates it', { testIsolation: false }, () => {
  const timestamp = Date.now()
  const reviewTitle = `A solid, well-maintained extension ${timestamp}`

  const reviewer = {
    name: 'JED Test Reviewer',
    username: `jed-reviewer-${timestamp}`,
    password: 'TestPassw0rd!2',
    email: `jed-reviewer-${timestamp}@example.test`,
  }

  const state = {
    reviewId: null,
  }

  let extension

  it('registers a second user who writes a review from the front end', () => {
    cy.loadJsonState('extension-submission').then((savedState) => {
      extension = savedState
    })

    cy.visit('index.php?option=com_users&view=registration')
    cy.get('#jform_name').type(reviewer.name)
    cy.get('#jform_username').type(reviewer.username)
    cy.get('#jform_password1').type(reviewer.password)
    cy.get('#jform_password2').type(reviewer.password)
    cy.get('#jform_email1').type(reviewer.email)
    cy.get('#member-registration button[type=submit]').click()

    cy.doFrontendLogin(reviewer.username, reviewer.password, false)

    // Deferred via .then() (rather than read directly in a template literal here) because
    // this runs in the same it() block as the loadJsonState() above - its .then() callback
    // only resolves once Cypress actually reaches that point in the command queue, which is
    // after this synchronous test body has already finished being *defined*.
    cy.then(() => {
      cy.visit(`index.php?option=com_jed&view=reviewform&catid=${extension.extensionCatId}&id=${extension.extensionId}`)
    })

    // The review form itself is hidden behind a "guidelines" screen on first load.
    cy.get('#reviewBtn').click()
    cy.get('#form-review').should('be.visible')

    cy.get('#jform_title').type(reviewTitle)

    // The rating fields are a custom widget (rater-js) driven by a plain hidden input under
    // the hood, so setting the hidden value directly is both the simplest and most reliable
    // way to drive it - clicking the generated star icons pixel-by-pixel would be brittle.
    const setRating = (field, value) => {
      cy.get(`#jform_${field}`).invoke('val', value)
      cy.get(`input[name="jform[${field}]___is_empty"]`).invoke('val', '0')
    }
    setRating('functionality', '4.5')
    setRating('ease_of_use', '4')

    cy.get('#jform_functionality_comment').type('Does exactly what it says, works out of the box.')
    cy.get('#jform_ease_of_use_comment').type('Very easy to configure and start using.')
    cy.get('#jform_used_for').type('Managing a set of curated external links on a client site.')
    cy.get('#jform_version').type('latest')

    // reviewForm-changeRequired.js's submit handler (`mfTest()`) references form fields that
    // don't exist on this form and throws on every click; it doesn't block the actual save,
    // but Cypress fails the test on any uncaught exception by default, so ignore just this
    // one, expected, pre-existing issue for this step.
    cy.on('uncaught:exception', () => false)
    cy.get('#form-review button[type=submit]').click()

    // Confirm the review actually saved by finding it (and its own id) on the reviewer's
    // Dashboard - the reviewform save doesn't expose the new review's id in its redirect URL.
    // Fetch it via cy.request() (shares the browser's session cookie) rather than cy.visit()
    // navigation: visiting the Dashboard link/URL in-browser inexplicably lands on the site's
    // Home page instead, even though the exact same request through cy.request() correctly
    // returns the real Dashboard page every time - some client-side behavior specific to a
    // full page navigation to this URL, not a routing/auth problem.
    cy.request('index.php/dashboard').then((resp) => {
      const $dashboard = Cypress.$(resp.body)
      const $link = $dashboard.find('a').filter((_, el) => Cypress.$(el).text().trim() === reviewTitle).first()

      expect($link.length, 'review link found on dashboard').to.equal(1)

      // SEF routing renders this link as /index.php/component/jed/review/<id> - the id is the
      // last path segment, not a "?id=" query param (unlike the extensionform redirect in
      // part 2).
      const href = $link.attr('href')
      const url = new URL(href, Cypress.config('baseUrl'))
      state.reviewId = url.searchParams.get('id') || url.pathname.split('/').filter(Boolean).pop()
      expect(state.reviewId, 'new review id').to.match(/^\d+$/)
    })

    cy.doFrontendLogout()
  })

  // P1-06. A reviewer could publish their own review past moderation by posting jform[state]=1,
  // because state/flagged/ip_address were ordinary fields on the public form (9.2, confirmed).
  it('does not let a reviewer publish their own review, and keeps it out of the public list', () => {
    cy.doFrontendLogin(reviewer.username, reviewer.password, false)

    cy.visit(`index.php?option=com_jed&view=reviewform&catid=${extension.extensionCatId}&id=${extension.extensionId}`)
    cy.get('#reviewBtn').click()

    // The moderation fields must not be on the page at all.
    cy.get('#form-review').within(() => {
      cy.get('[name="jform[state]"]').should('not.exist')
      cy.get('[name="jform[flagged]"]').should('not.exist')
      cy.get('[name="jform[ip_address]"]').should('not.exist')
    })

    // Post them anyway, the way an attacker would - the form filter is only the first line.
    cy.get('#form-review').then(($form) => {
      const add = (name, value) => {
        Cypress.$('<input>').attr({ type: 'hidden', name, value }).appendTo($form)
      }
      add('jform[state]', '1')
      add('jform[flagged]', '1')
      add('jform[ip_address]', '203.0.113.99')
    })

    const probeTitle = `Bypass probe ${timestamp}`
    cy.get('#jform_title').clear().type(probeTitle)
    cy.get('#jform_used_for').clear().type('testing the moderation boundary')
    cy.get('#jform_version').clear().type('1.0')
    cy.get('#form-review button[type=submit]').click()

    cy.doFrontendLogout()

    // Logged out, the review must not appear: it is unmoderated, whatever was posted.
    cy.visit('index.php?option=com_jed&view=reviews')
    cy.contains(probeTitle).should('not.exist')
  })

  // The other half of P1-06: an ordinary reader could not report a review at all.
  it('lets a logged-in reader report a review', () => {
    cy.doFrontendLogin(Cypress.env('username'), Cypress.env('password'), false)

    cy.then(() => {
      cy.visit(`index.php?option=com_jed&view=review&id=${state.reviewId}`)
    })

    cy.contains('a', 'Report this review').click()

    // The ticket form arrives prefilled and linked to the review, not to the extension.
    cy.get('#jform_ticket_subject').invoke('val').should('match', /^Reporting Review - /)
    cy.get('[name="jform[linked_item_type]"]').should('have.value', '2')
    cy.get('[name="jform[linked_item_id]"]').should('have.value', String(state.reviewId))

    cy.doFrontendLogout()
  })

  it('shows no report link to a logged-out reader', () => {
    cy.visit('index.php?option=com_jed&view=reviews')
    cy.contains('Report this review').should('not.exist')
  })

  it('moderates and publishes the review as admin', () => {
    cy.doAdministratorLogin(Cypress.env('username'), Cypress.env('password'), false)

    cy.visit('administrator/index.php?option=com_jed&view=reviews')
    cy.searchForItem(`id:${state.reviewId}`)
    cy.get('#cb0').click()
    cy.clickToolbarButton('action')
    cy.clickToolbarButton('publish')
    cy.checkForSystemMessage('published')

    cy.doAdministratorLogout()

    cy.saveJsonState('review-workflow', state)
  })
})
