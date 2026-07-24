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
