/**
 * Cypress tests of the VEL Feed Runs page, run against a real Joomla site with com_vel.
 *
 * Where: the site's address comes from CYPRESS_BASE_URL (or --config baseUrl=...).
 * Who:   a Super User (or anybody with the vel.feeds permission) without two-factor login,
 *        from cypress.env.json or CYPRESS_ADMIN_USER / CYPRESS_ADMIN_PASSWORD.
 *
 * See vel_tests/README.md.
 */
const { defineConfig } = require('cypress');

module.exports = defineConfig({
  e2e: {
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost',
    specPattern: 'e2e/**/*.cy.js',
    supportFile: 'support/e2e.js',
    fixturesFolder: 'fixtures',
    screenshotsFolder: 'screenshots',
    videosFolder: 'videos',
    downloadsFolder: 'downloads',
    video: false,
    // Wide enough that Atum shows the toolbar buttons rather than its collapsed toolbar menu.
    viewportWidth: 1400,
    viewportHeight: 1000,
    defaultCommandTimeout: 15000,
    setupNodeEvents(on) {
      // cy.task('log', ...) prints to the terminal, so `cypress run` shows what the feed did.
      on('task', {
        log(message) {
          // eslint-disable-next-line no-console
          console.log(message);

          return null;
        },
      });
    },
  },
});
