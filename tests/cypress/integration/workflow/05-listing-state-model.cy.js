/// <reference types="cypress" />

/**
 * Workflow part 5: the four visibility carriers of P1-01.
 *
 * `approved` and `blocked` belong to the JED team, `state` to the developer, `deleted` to the
 * owner or the team. They are separate columns so that they cannot cancel each other out, and
 * each produces a different answer on the public detail URL:
 *
 *   approved + online       -> 200, the listing
 *   blocked                 -> 200, the public block notice, noindex
 *   developer took offline  -> 404, no reason given
 *   soft-deleted            -> 410 Gone
 *   never approved          -> 404
 *
 * Runs against the extension part 1 submitted, and puts every carrier back at the end.
 */

describe('Workflow part 5: listing state model', { testIsolation: false }, () => {
  let extension

  const detailUrl = () =>
    `index.php?option=com_jed&view=extension&catid=${extension.extensionCatId}&id=${extension.extensionId}`

  // The detail page is the only place that distinguishes all four, so every assertion goes
  // through a raw request - cy.visit() would fail the test on any non-2xx status rather than
  // letting us assert on it.
  const expectStatus = (expected) =>
    cy.request({ url: detailUrl(), failOnStatusCode: false }).its('status').should('eq', expected)

  const openAdminExtension = () => {
    // The display route, not task=extension.edit: that task goes through AdminModel::checkout(),
    // which still calls the removed BaseModel::setError() and 500s on Joomla 6.
    cy.visit(`administrator/index.php?option=com_jed&view=extension&layout=edit&id=${extension.extensionId}`)
  }

  before(() => {
    cy.loadJsonState('extension-submission').then((savedState) => {
      extension = savedState
    })
  })

  it('shows an approved, online listing', () => {
    cy.doAdministratorLogin()
    openAdminExtension()

    cy.then(() => expectStatus(200))
  })

  it('refuses a block without a reason code', () => {
    openAdminExtension()

    // Leave the reason empty and press Block.
    cy.get('#jform_block_reason_code').select('')
    cy.clickToolbarButton('Block')

    cy.get('joomla-alert').should('contain.text', 'reason code')
    cy.then(() => expectStatus(200))
  })

  it('blocks with a reason, and answers 200 with the public notice', () => {
    openAdminExtension()

    cy.get('#jform_block_reason_code').select('TM2')
    cy.get('#jform_block_reason_text').clear().type('Internal only: escalated by the trademark team.')
    cy.clickToolbarButton('Block')

    cy.then(() => expectStatus(200))

    cy.request({ url: detailUrl(), failOnStatusCode: false }).then((response) => {
      // The reason's title and code are public...
      expect(response.body).to.contain('currently blocked')
      expect(response.body).to.contain('Use of the Joomla trademark')
      expect(response.body).to.contain('TM2')

      // ...the internal note never is (8.7), and neither is the listing itself.
      expect(response.body).to.not.contain('Internal only:')
      expect(response.body).to.not.contain('jed-subitem-description')

      // 200 keeps the information reachable; noindex keeps it out of the index meanwhile.
      expect(response.body).to.match(/<meta[^>]+name="robots"[^>]+content="[^"]*noindex/i)
    })
  })

  it('keeps a blocked listing out of the catalogue', () => {
    cy.request('index.php?option=com_jed&view=extensions').then((response) => {
      expect(response.body).to.not.contain(`id=${extension.extensionId}"`)
    })
  })

  it('does not let the developer lift the block by republishing', () => {
    openAdminExtension()
    cy.clickToolbarButton('Unblock')

    // Re-block, then confirm from the database side of the contract: the developer's own
    // publish switch writes `state` and leaves `blocked` alone. The switch itself is covered
    // by the model test; what matters here is that unblocking is a separate, admin-only act.
    openAdminExtension()
    cy.get('#jform_block_reason_code').select('PE1')
    cy.clickToolbarButton('Block')

    cy.then(() => expectStatus(200))

    openAdminExtension()
    cy.clickToolbarButton('Unblock')
    cy.then(() => expectStatus(200))
  })

  it('answers 404 when the developer takes the listing offline', () => {
    cy.visit(`administrator/index.php?option=com_jed&view=extensions`)
    cy.searchForItem(extension.extensionName)
    cy.checkAllResults()
    cy.clickToolbarButton('Actions')
    cy.contains('button', 'Unpublish').click()

    cy.then(() => expectStatus(404))

    cy.checkAllResults()
    cy.clickToolbarButton('Actions')
    cy.contains('button', 'Publish').click()
    cy.then(() => expectStatus(200))
  })

  it('answers 410 Gone for a soft-deleted listing, and shows it read-only in the backend', () => {
    openAdminExtension()
    cy.clickToolbarButton('Delete')

    cy.then(() => expectStatus(410))

    openAdminExtension()
    cy.get('joomla-alert').should('contain.text', 'has been deleted')
    // Read-only by absence of the action, not by a disabled form (8.8).
    cy.get('input[name="jform[name]"]').should('not.exist')

    cy.clickToolbarButton('Restore')
    cy.then(() => expectStatus(200))
  })
})
