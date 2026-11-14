// <reference types="cypress" />

/**
 * The public catalogue as a visitor uses it: paging, page size, search, the Joomla version filter
 * and sorting. Nobody is logged in, and nothing here depends on one particular extension, only on
 * the sample data having more than one page of listings.
 */

const listUrl = (params = {}) => {
  const query = new URLSearchParams({ option: 'com_jed', view: 'extensions' })

  Object.entries(params).forEach(([key, value]) => query.set(key, value))

  // Absolute, because Cypress reads a relative URL with a dot before the first slash as a host
  return `${Cypress.config('baseUrl')}index.php?${query.toString()}`
}

const titles = () => cy.get('.card--extension .card__extension-title').then(($links) => (
  [...$links].map((link) => link.textContent.trim())
))

describe('Catalogue browsing', { testIsolation: true }, () => {
  it('shows a full first page of cards and says how many there are', () => {
    cy.visit(listUrl())

    cy.get('.card--extension').should('have.length', 36)
    cy.get('nav.pagination__wrapper').should('contain.text', '1 - 36 /')
  })

  it('offers the four page sizes and applies the chosen one', () => {
    cy.visit(listUrl())

    cy.get('#list_limit option').then(($options) => {
      expect([...$options].map((option) => option.value)).to.deep.equal(['18', '36', '54', '72'])
    })

    cy.visit(listUrl({ 'list[limit]': '18' }))
    cy.get('.card--extension').should('have.length', 18)
    cy.get('#list_limit').should('have.value', '18')
  })

  it('falls back to the default size for a size that is not offered', () => {
    cy.visit(listUrl({ 'list[limit]': '5000' }))

    cy.get('.card--extension').should('have.length', 36)
  })

  it('pages forward without repeating a listing', () => {
    cy.visit(listUrl({ 'list[fullordering]': 'a.name ASC', 'list[limit]': '18' }))

    titles().then((first) => {
      cy.get('nav.pagination__wrapper .page-item a').contains(/^\s*2\s*$/).click()

      cy.get('nav.pagination__wrapper').should('contain.text', '19 - 36 /')

      titles().then((second) => {
        expect(second).to.have.length(18)
        expect(second.filter((title) => first.includes(title)), 'listings on both pages').to.deep.equal([])
      })
    })
  })

  it('finds a listing by name from the search box', () => {
    cy.visit(listUrl())

    titles().then((all) => {
      const name = all[0]

      cy.get('#filter_search').type(`${name}{enter}`)

      cy.get('.card--extension').should('have.length.at.least', 1)
      cy.get('.card--extension .card__extension-title').first().should('contain.text', name)
      cy.get('#filter_search').should('have.value', name)
    })
  })

  it('shows no cards, and keeps the search term, when nothing matches', () => {
    cy.visit(listUrl({ 'filter[search]': 'zzzqqqxx-no-such-extension' }))

    cy.get('.card--extension').should('not.exist')
    cy.get('#filter_search').should('have.value', 'zzzqqqxx-no-such-extension')
  })

  it('treats a search that looks like markup or SQL as plain text', () => {
    ['<script>window.__jedxss=1</script>', "' OR 1=1 --", '%" OR "%"="'].forEach((term) => {
      cy.request({ url: listUrl({ 'filter[search]': term }), failOnStatusCode: false }).then((response) => {
        expect(response.status).to.be.lessThan(500)
        expect(response.body).to.not.contain('<script>window.__jedxss')
        expect(response.body).to.not.match(/You have an error in your SQL syntax/i)
      })
    })
  })

  it('filters on the Joomla version a listing declares', () => {
    cy.visit(listUrl({ 'filter[joomla_version]': '40', 'list[limit]': '18' }))

    cy.get('#filter_joomla_version').should('have.value', '40')
    cy.get('.card--extension').should('have.length.at.least', 1)
    cy.get('.card--extension').each(($card) => {
      expect($card.find('.joomla-version-badge[title="Joomla 4"]').length, 'Joomla 4 badge').to.be.greaterThan(0)
    })
  })

  it('lists no more with a version filter than without', () => {
    const total = (html) => Number(/\/\s*(\d+)\s*items/.exec(html.replace(/\s+/g, ' '))[1])

    cy.request(listUrl()).then((any) => {
      cy.request(listUrl({ 'filter[joomla_version]': '60' })).then((six) => {
        expect(total(six.body)).to.be.at.most(total(any.body))
      })
    })
  })

  it('sorts by name in both directions', () => {
    const sorted = (direction) => {
      cy.visit(listUrl({ 'list[fullordering]': `a.name ${direction}`, 'list[limit]': '18' }))

      return titles().then((names) => {
        const lower = names.map((name) => name.toLowerCase())
        const expected = [...lower].sort()

        expect(lower).to.deep.equal(direction === 'ASC' ? expected : expected.reverse())
      })
    }

    sorted('ASC')
    sorted('DESC')
  })

  it('opens a listing from its card', () => {
    cy.visit(listUrl())

    cy.get('.card--extension .card__extension-title').first().then(($link) => {
      const name = $link.text().trim()

      cy.wrap($link).click()
      cy.get('main h2').first().should('contain.text', name)
    })
  })
})
