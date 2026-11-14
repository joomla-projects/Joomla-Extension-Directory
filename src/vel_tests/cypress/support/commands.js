/**
 * Commands for the VEL Feed Runs specs.
 */

/**
 * Signs in to the administrator once per spec run (cy.session keeps the cookie).
 */
Cypress.Commands.add('adminLogin', () => {
  const user = Cypress.env('ADMIN_USER');
  const password = Cypress.env('ADMIN_PASSWORD');

  if (!user || !password) {
    throw new Error('Set ADMIN_USER and ADMIN_PASSWORD in vel_tests/cypress/cypress.env.json (or CYPRESS_ADMIN_USER / CYPRESS_ADMIN_PASSWORD).');
  }

  cy.session(['joomla-administrator', user], () => {
    cy.visit('/administrator/index.php');
    cy.get('#mod-login-username').clear().type(user);
    cy.get('#mod-login-password').clear().type(password, { log: false });
    cy.get('#btn-login-submit').click();
    cy.get('#mod-login-username', { timeout: 30000 }).should('not.exist');
  }, {
    validate() {
      cy.request('/administrator/index.php?option=com_vel&view=feedruns').its('body').should('not.include', 'mod-login-username');
    },
  });
});

Cypress.Commands.add('openFeedRuns', () => {
  cy.visit('/administrator/index.php?option=com_vel&view=feedruns');
  cy.get('#adminForm').should('exist');
});

/**
 * Presses "Run CVE feed now" / "Run NVD feed now" (feed: 'cna' | 'nvd') and waits for the
 * progress page. If Joomla answered with a message instead (schema not updated, no permission,
 * feed disabled, another run going), the test fails with that message.
 */
Cypress.Commands.add('pressRun', (feed) => {
  // Mark this page, so the checks below wait for the one Joomla redirects to.
  cy.window().then((win) => {
    win.velBeforeRun = true;
  });

  cy.get(`#toolbar-run-${feed} button`).should('be.visible').click();

  cy.window({ timeout: 30000 }).should((win) => {
    expect(win.velBeforeRun, 'the page after pressing Run').to.be.undefined;
  });
  cy.get('#adminForm').should('exist');
  cy.get('body').then(($body) => {
    if (!$body.find('#vel-feedrun').length) {
      const message = $body.find('#system-message-container').text().replace(/\s+/g, ' ').trim();

      throw new Error(`Pressing Run did not open the progress panel. Joomla said: ${message || '(nothing)'}`);
    }
  });
  cy.location('search').should('match', /[?&]run=\d+/);
});

/**
 * The run shown on the page, as the page's script sees it: { id, stepUrl, stopUrl, token }.
 */
Cypress.Commands.add('currentRun', () => cy.window().then((win) => {
  const options = win.Joomla.getOptions('com_vel.feedrun');

  return { ...options, token: win.Joomla.getOptions('csrf.token') };
}));

/**
 * POSTs feeditems.step or feeditems.stop for the current run, as the page's script does, and
 * yields the JSON report.
 */
Cypress.Commands.add('postRun', (what) => cy.currentRun().then((run) => cy.request({
  method: 'POST',
  url: what === 'stop' ? run.stopUrl : run.stepUrl,
  form: true,
  body: { id: run.id },
  headers: { 'X-CSRF-Token': run.token },
  failOnStatusCode: false,
  timeout: 120000,
}).then((response) => {
  expect(response.headers['content-type'], `feeditems.${what} answers JSON`).to.include('application/json');
  expect(response.body.success, `feeditems.${what}: ${response.body.message || 'ok'}`).to.eq(true);

  return response.body.data;
})));

/** Closes the current run on the server, so a test leaves nothing running. */
Cypress.Commands.add('stopRunOnServer', () => cy.postRun('stop'));

/** Writes one step report, and the requests the server made during it, to the terminal and the command log. */
Cypress.Commands.add('logReport', (label, report) => {
  const lines = [`${label} [${report.phase}] ${report.activity} | ${report.statusLabel} | seen ${report.seen}, new ${report.new}, updated ${report.updated}`];

  (report.requests || []).forEach((request) => {
    const outcome = request.status ? `${request.status}, ${request.bytes} bytes` : `no response: ${request.error}`;

    lines.push(`    ${request.method} ${request.url}${request.range ? ` [${request.range}]` : ''} -> ${outcome}, ${request.ms} ms`);
  });

  (report.events || []).forEach((event) => lines.push(`    stored ${event.id} (${event.outcome}) ${event.title}`));

  if (report.done) {
    lines.push(`    RESULT: ${report.statusLabel}${report.message ? ` - ${report.message}` : ''}`);
  }

  cy.log(lines[0]);
  cy.task('log', lines.join('\n'), { log: false });
});
