/**
 * Loaded before every spec.
 */
import './commands';

// The progress page asks "leave anyway?" while a run is going (beforeunload). Cypress moves
// between pages and tests on its own, so the prompt is switched off in the pages it opens;
// the warning itself is covered by vel_tests/js.
Cypress.on('window:before:load', (win) => {
  const add = win.addEventListener.bind(win);

  win.addEventListener = (type, listener, options) => (type === 'beforeunload' ? undefined : add(type, listener, options));
});
