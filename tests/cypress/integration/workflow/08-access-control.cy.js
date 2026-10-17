// type definitions for Cypress object "cy"
// <reference types="cypress" />

/**
 * Negative-path checks: things a visitor or an ordinary registered user must NOT be able to do.
 * Covers ticket ownership and the moderation data on the public review page.
 */

describe('Workflow part 8: access control', { testIsolation: false }, () => {
  const timestamp = Date.now()
  const password = 'TestPassw0rd!1'

  const userA = { name: 'JED Sec A', username: `jed-sec-a-${timestamp}`, email: `jed-sec-a-${timestamp}@example.test` }
  const userB = { name: 'JED Sec B', username: `jed-sec-b-${timestamp}`, email: `jed-sec-b-${timestamp}@example.test` }

  let ticketId

  // cy.request() does not resolve a bare relative path against baseUrl the way cy.visit() does.
  const url = (path) => Cypress.config('baseUrl') + path

  const register = (user) => {
    cy.visit('index.php?option=com_users&view=registration')
    cy.get('#jform_name').type(user.name)
    cy.get('#jform_username').type(user.username)
    cy.get('#jform_password1').type(password)
    cy.get('#jform_password2').type(password)
    cy.get('#jform_email1').type(user.email)
    cy.get('#member-registration button[type=submit]').click()
  }

  // Reads the form token from a page the logged-in user can open.
  const formToken = () => cy.request(url('index.php?option=com_users&view=profile')).then((res) => {
    const match = res.body.match(/name="([a-f0-9]{32})" value="1"/)
    expect(match, 'form token on the profile page').to.not.equal(null)
    return match[1]
  })

  const postTicket = (token, fields) => cy.request({
    method: 'POST',
    url: url('index.php?option=com_tickets&task=ticketform.save'),
    form: true,
    body: { option: 'com_tickets', task: 'ticketform.save', [token]: '1', 'jform[ticket_category_type]': '0', ...fields },
    failOnStatusCode: false,
  })

  const lastTicketIdOnList = () => cy.request(url('index.php?option=com_tickets&view=tickets')).then((res) => {
    const ids = [...res.body.matchAll(/\/tickets\/(\d+)/g)].map((m) => Number(m[1]))
    expect(ids.length, 'tickets visible in the list').to.be.greaterThan(0)
    return Math.max(...ids)
  })

  it('registers two ordinary users', () => {
    register(userA)
    register(userB)
  })

  it('does not let another user overwrite a ticket or set its staff fields', () => {
    cy.doFrontendLogin(userA.username, password, false)
    formToken().then((token) => postTicket(token, {
      'jform[id]': '0',
      'jform[ticket_subject]': 'Ticket from A',
      'jform[ticket_text]': 'private text from A',
    }))
    lastTicketIdOnList().then((id) => { ticketId = id })
    cy.doFrontendLogout()

    cy.doFrontendLogin(userB.username, password, false)
    cy.then(() => formToken().then((token) => postTicket(token, {
      'jform[id]': String(ticketId),
      'jform[ticket_subject]': 'Overwritten by B',
      'jform[ticket_text]': 'text from B',
      'jform[internal_notes]': 'injected note',
      'jform[ticket_status]': '3',
      'jform[allocated_to]': '5',
    })))
    cy.doFrontendLogout()

    // A's ticket is untouched.
    cy.doFrontendLogin(userA.username, password, false)
    cy.then(() => cy.request(url(`index.php?option=com_tickets&view=ticket&id=${ticketId}`)).then((res) => {
      expect(res.body).to.contain('Ticket from A')
      expect(res.body).to.not.contain('Overwritten by B')
      expect(res.body).to.not.contain('injected note')
    }))
    cy.doFrontendLogout()
  })

  it('does not show the reviewer IP address or moderation fields to the public', () => {
    cy.request(url('index.php?option=com_jed&view=reviews')).then((res) => {
      const match = res.body.match(/view=review&(?:amp;)?id=(\d+)/) || res.body.match(/\/review\/(\d+)/)
      expect(match, 'a published review link').to.not.equal(null)
      cy.request(url(`index.php?option=com_jed&view=review&id=${match[1]}`)).then((page) => {
        expect(page.body).to.contain('Overall Score')
        expect(page.body).to.not.contain('IP Address')
        expect(page.body).to.not.contain('Flagged')
      })
    })
  })
})
