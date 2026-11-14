/**
 * A real feed run, start to finish, against the real cvelistV5 and NVD - what a person sees
 * after pressing Run. Every step the page makes is watched (not changed), and its report is
 * printed: the phase, the counts, each outbound request the server made (URL, status, size,
 * time) and each record it stored.
 *
 * It changes data like a person pressing Run does (feed items, the queue, a feed run row).
 * LIVE_MAX_STEPS (cypress.env.json, default 400) caps a run; past it the run is stopped, and
 * the next run carries on from there.
 *
 *   npx cypress run --spec e2e/feedrun-live.cy.js
 */
const STEP = /[?&]task=feeditems\.step\b/;
const MAX_STEPS = Number(Cypress.env('LIVE_MAX_STEPS') || 400);

/** Follow the page's steps until the run is done; yields the last report. */
const follow = (feed, n = 1) => cy.wait('@step', { timeout: 180000 }).then(({ response }) => {
  if (!response) {
    throw new Error(`${feed} step ${n}: no response`);
  }

  const body = response.body;

  if (!body || typeof body !== 'object') {
    // A gateway timeout or PHP error page: the page retries it, and so do we - but say so.
    cy.task('log', `${feed} step ${n}: HTTP ${response.statusCode}, not a report: ${String(body).slice(0, 300)}`);

    if (response.statusCode === 200) {
      throw new Error(`${feed} step ${n}: the server sent a page instead of a report - is the session still valid?`);
    }

    return follow(feed, n + 1);
  }

  if (!body.success) {
    cy.task('log', `${feed} step ${n}: HTTP ${response.statusCode}: ${body.message}`);

    if (response.statusCode === 403 || response.statusCode === 404) {
      throw new Error(`${feed} step ${n}: ${body.message}`);
    }

    return follow(feed, n + 1);
  }

  const report = body.data;

  cy.logReport(`${feed} step ${n}`, report);

  if (report.done) {
    return cy.wrap(report, { log: false });
  }

  if (n >= MAX_STEPS) {
    cy.task('log', `${feed}: ${MAX_STEPS} steps - stopping; the next run carries on from here.`);
    cy.get('#vel-feedrun-stop').click();

    return cy.wrap(report, { log: false });
  }

  return follow(feed, n + 1);
});

describe('Feed runs: live', () => {
  beforeEach(() => {
    cy.adminLogin();
    cy.openFeedRuns();
  });

  ['cna', 'nvd'].forEach((feed) => {
    it(`runs the ${feed.toUpperCase()} feed to the end, showing every step`, () => {
      cy.intercept('POST', STEP).as('step');

      cy.pressRun(feed);
      cy.get('#vel-feedrun-waiting').should('not.exist');

      follow(feed).then((report) => {
        expect(report.status, `the run ended: ${report.message}`).not.to.eq('failed');
      });

      cy.get('#vel-feedrun-close', { timeout: 60000 }).should('be.visible');
      cy.get('#vel-feedrun-status').invoke('text').then((status) => cy.task('log', `${feed}: the page shows "${status}"`));
    });
  });
});
