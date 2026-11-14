/**
 * Is the feed run set up on this site? Run this first when the Feed Runs page does not update.
 *
 * Checks, in the order things break:
 *  1. the progress script and its asset entry are on the server (media/com_vel/...);
 *  2. pressing Run opens the progress page (it does not when the database was not updated);
 *  3. the script starts in the browser;
 *  4. the step endpoint answers JSON, and the server can reach cvelistV5 (GitHub) and the NVD -
 *     one real step of each feed, with the requests it made printed - then the run is stopped,
 *     which changes nothing (a run stopped while reading the log is discarded).
 */
describe('Feed runs: set-up on this site', () => {
  beforeEach(() => {
    cy.adminLogin();
  });

  it('has the progress script and its asset entry deployed (media/com_vel)', () => {
    cy.request({ url: '/media/com_vel/joomla.asset.json', failOnStatusCode: false }).then((response) => {
      expect(response.status, '/media/com_vel/joomla.asset.json is on the server').to.eq(200);

      const assets = typeof response.body === 'string' ? JSON.parse(response.body).assets : response.body.assets;

      expect(assets.map((asset) => asset.name), 'joomla.asset.json is the updated one').to.include('com_vel.feedrun');
    });

    cy.request({ url: '/media/com_vel/js/feedrun.js', failOnStatusCode: false }).then((response) => {
      expect(response.status, '/media/com_vel/js/feedrun.js is on the server').to.eq(200);
      expect(response.body, 'and is the progress script').to.include('com_vel.feedrun');
    });
  });

  ['cna', 'nvd'].forEach((feed) => {
    it(`starts a ${feed.toUpperCase()} run, the script takes over, and the server reaches the feed`, () => {
      // Keep the page's own script from stepping (it is told the run is busy and waits), so
      // this test makes exactly one step itself.
      cy.fixture('feedrun-cna.json').then((reports) => {
        cy.intercept('POST', /[?&]task=feeditems\.step\b/, { statusCode: 200, body: { success: true, data: { ...reports[0], busy: true, events: [], requests: [] } } });
      });

      cy.openFeedRuns();
      cy.pressRun(feed);

      cy.get('.alert-danger').should('not.exist');
      cy.get('#vel-feedrun-waiting', { timeout: 15000 }).should('not.exist');

      cy.postRun('step').then((report) => {
        cy.logReport(`${feed} step 1`, report);

        expect(report.status, `the step did not fail: ${report.message}`).not.to.eq('failed');
        expect(report.requests, 'the step made an outbound request').to.have.length.greaterThan(0);

        report.requests.forEach((request) => {
          expect(request.status, `${request.url} answered (${request.error || request.status})`).to.be.within(200, 399);
        });
      });

      cy.stopRunOnServer().then((report) => {
        cy.logReport(`${feed} stopped`, report);
        expect(report.done).to.eq(true);
      });
    });
  });
});
