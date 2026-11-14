/**
 * The progress panel on the Feed Runs page. The run is started for real (which makes no outbound
 * request); its steps are answered from fixtures/feedrun-cna.json - reports the real FeedRunner
 * produced - so what the page shows is known in advance and no feed is fetched. Stop goes to the
 * real server.
 *
 * fixtures/feedrun-cna.json is written by tests/js/build-fixtures.php.
 */
const STEP = /[?&]task=feeditems\.step\b/;

const json = (data) => ({ statusCode: 200, body: { success: true, message: null, messages: null, data } });

describe('Feed runs: the progress panel', () => {
  beforeEach(() => {
    cy.adminLogin();
    cy.openFeedRuns();
    cy.fixture('feedrun-cna.json').as('reports');
  });

  afterEach(() => {
    // The run was really started: close it (a run stopped while reading changes nothing).
    cy.get('body').then(($body) => {
      if ($body.find('#vel-feedrun').length) {
        cy.stopRunOnServer();
      }
    });
  });

  it('shows every step as it happens, then the result', function () {
    const reports = this.reports;
    const last = reports[reports.length - 1];
    let n = 0;

    cy.intercept('POST', STEP, (req) => req.reply(json(reports[Math.min(++n, reports.length - 1)]))).as('step');

    cy.pressRun('cna');

    cy.get('#vel-feedrun-waiting').should('not.exist');
    cy.wait('@step').its('request.headers').should('have.property', 'x-csrf-token');

    cy.get('#vel-feedrun-activity').should('contain', 'Fetching queued CVE records');
    cy.get('#vel-feedrun-bar').should('have.attr', 'style').and('match', /width: \d+%/);

    cy.get('#vel-feedrun-close', { timeout: 30000 }).should('be.visible');
    cy.get('#vel-feedrun-stop').should('not.be.visible');
    cy.get('#vel-feedrun-status').should('have.text', last.statusLabel);
    cy.get('#vel-feedrun-seen').should('have.text', String(last.seen));
    cy.get('#vel-feedrun-new').should('have.text', String(last.new));
    cy.get('#vel-feedrun-message').should('contain', last.message);

    // The activity log lists what the server asked of cvelistV5, and what it stored.
    cy.get('#vel-feedrun-log').should('contain', 'GET cvelistV5/cves/deltaLog.json');
    reports.flatMap((report) => report.events).forEach((event) => cy.get('#vel-feedrun-log').should('contain', event.id));
  });

  it('Stop while the log is being read closes the run and changes nothing (real server)', function () {
    const reading = this.reports[0];

    cy.intercept('POST', STEP, json({ ...reading, events: [], requests: [] })).as('step');

    cy.pressRun('cna');
    cy.wait('@step');

    cy.get('#vel-feedrun-stop').click();   // Cypress accepts the confirm()

    cy.get('#vel-feedrun-close', { timeout: 30000 }).should('be.visible');
    cy.get('#vel-feedrun-status').should('have.text', 'Partial');
    cy.get('#vel-feedrun-message').should('contain', 'nothing was changed');
  });

  it('retries when the server times out', function () {
    const reports = this.reports;
    let n = 0;

    cy.intercept('POST', STEP, (req) => {
      n += 1;

      if (n === 1) {
        req.reply({ statusCode: 504, headers: { 'content-type': 'text/html' }, body: '<html><body>504 Gateway Time-out</body></html>' });
      } else {
        req.reply(json(reports[Math.min(n - 1, reports.length - 1)]));
      }
    }).as('step');

    cy.pressRun('cna');

    cy.get('#vel-feedrun-log').should('contain', 'HTTP 504').and('contain', 'Trying again in 2 seconds');
    cy.get('#vel-feedrun-close', { timeout: 30000 }).should('be.visible');
  });

  it('says what to check when the progress script does not load', () => {
    cy.intercept('GET', '**/media/com_vel/js/feedrun*.js*', { statusCode: 404, body: '' });

    cy.pressRun('cna');

    cy.get('#vel-feedrun-waiting').should('be.visible').and('contain', 'F12');
    cy.get('#vel-feedrun-activity').should('contain', 'Reading the CVE');
  });

  it('says the session has expired when a step gets the login page', () => {
    cy.intercept('POST', STEP, { statusCode: 200, headers: { 'content-type': 'text/html' }, body: '<!doctype html><title>Joomla - Administrator Login</title>' });

    cy.pressRun('cna');

    cy.get('#vel-feedrun-message').should('contain', 'session may have expired');
    cy.get('#vel-feedrun-close').should('be.visible');
  });
});
