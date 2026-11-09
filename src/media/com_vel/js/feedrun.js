/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Drives a feed run from the Feed Runs page and shows it as it goes.
 *
 * A run is done in short steps (FeedRunner::step(), ~10 s of work each) because one request
 * doing all of it outlasts the web server's gateway timeout and shows nothing until it fails.
 * This POSTs feeditems.step until the report says the run is done, and after every step shows
 * what the run is doing, the counts, and the records it stored.
 *
 * Everything the server needs is in the run row, not in this page: reloading the page, or
 * opening it again from the "Continue" link in the list, carries on with the same run. If two
 * pages step one run, the server lets one step at a time and tells the other it is busy.
 *
 * Options (Joomla.getOptions('com_vel.feedrun')): id, report, stepUrl, stopUrl, listUrl.
 */
((document, Joomla) => {
  'use strict';

  /** Seconds to wait after a request that failed, one entry per consecutive failure. */
  const RETRY_DELAYS = [2, 5, 10, 20, 30];

  /** Seconds to wait when another request holds the run. */
  const BUSY_DELAY = 3;

  /** Milliseconds between two steps that went fine. */
  const PAUSE = 200;

  /** Lines kept in the activity log. */
  const MAX_LOG = 300;

  /** Characters of a record's title shown in the activity log. */
  const TITLE_LENGTH = 110;

  const BADGE = {
    running: 'bg-info', ok: 'bg-success', partial: 'bg-warning text-dark', failed: 'bg-danger',
  };
  const ALERT = { ok: 'alert-success', partial: 'alert-warning', failed: 'alert-danger' };
  const BAR = { ok: 'bg-success', partial: 'bg-warning', failed: 'bg-danger' };

  /**
   * A language string, with %s / %d / %1$s placeholders filled in.
   */
  const text = (key, ...args) => {
    let next = 0;

    return Joomla.Text._(key, key).replace(/%(?:(\d+)\$)?[sd]/g, (match, position) => {
      const value = position ? args[Number(position) - 1] : args[next++];

      return value === undefined ? match : String(value);
    });
  };

  /** A failure worth retrying: the network, a gateway timeout, a 5xx page. */
  class TransientError extends Error {}

  /** A failure that retrying will not mend: no permission, bad token, session gone. */
  class FatalError extends Error {}

  const init = () => {
    const panel = document.getElementById('vel-feedrun');
    const options = Joomla.getOptions('com_vel.feedrun');

    if (!panel || !options || !options.id) {
      return;
    }

    const el = (id) => document.getElementById(`vel-feedrun-${id}`);
    const ui = {
      status: el('status'),
      activity: el('activity'),
      progress: el('progress'),
      bar: el('bar'),
      seen: el('seen'),
      new: el('new'),
      updated: el('updated'),
      elapsed: el('elapsed'),
      message: el('message'),
      log: el('log'),
      stop: el('stop'),
      cont: el('continue'),
      close: el('close'),
    };

    ui.log.style.maxHeight = '16rem';

    // The token travels in the header Session::checkToken() looks at first; the form's hidden
    // token field is the fallback when the option is missing.
    const tokenField = Array.from(document.querySelectorAll('#adminForm input[type="hidden"]'))
      .find((input) => /^[0-9a-f]{32}$/i.test(input.name) && input.value === '1');
    const token = Joomla.getOptions('csrf.token', '') || (tokenField ? tokenField.name : '');

    let startedAt = Date.now() - ((options.report && options.report.elapsed) || 0) * 1000;
    let lastPhase = '';
    let failures = 0;
    let stopping = false;
    let finished = false;
    let inFlight = false;
    let timer = null;
    let clock = null;

    const formatElapsed = (ms) => {
      const total = Math.max(0, Math.floor(ms / 1000));
      const hours = Math.floor(total / 3600);
      const minutes = Math.floor((total % 3600) / 60);
      const seconds = String(total % 60).padStart(2, '0');

      return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${seconds}` : `${minutes}:${seconds}`;
    };

    const tickClock = () => {
      ui.elapsed.textContent = formatElapsed(Date.now() - startedAt);
    };

    const log = (line, className = '') => {
      const item = document.createElement('li');
      const stamp = new Date().toLocaleTimeString();

      item.textContent = `${stamp}  ${line}`;

      if (className) {
        item.className = className;
      }

      ui.log.appendChild(item);

      while (ui.log.children.length > MAX_LOG) {
        ui.log.removeChild(ui.log.firstChild);
      }

      ui.log.scrollTop = ui.log.scrollHeight;
    };

    /** A note under the activity line that is not part of the server's report. */
    const note = (message) => {
      ui.activity.textContent = message;
    };

    const setBar = (report) => {
      const { bar, progress } = ui;

      bar.classList.remove('w-100', 'bg-success', 'bg-warning', 'bg-danger');

      if (report.done) {
        bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
        bar.classList.add(BAR[report.status] || 'bg-secondary');
        bar.style.width = '100%';
        bar.textContent = '';
        progress.setAttribute('aria-valuenow', '100');

        return;
      }

      if (report.total > 0) {
        const percent = Math.min(100, Math.round((report.current / report.total) * 100));

        bar.classList.remove('progress-bar-animated');
        bar.classList.add('progress-bar-striped');
        bar.style.width = `${percent}%`;
        bar.textContent = `${percent}%`;
        progress.setAttribute('aria-valuenow', String(percent));

        return;
      }

      // A phase with no count yet (reading the delta log, the first NVD page): indeterminate.
      bar.classList.add('progress-bar-striped', 'progress-bar-animated');
      bar.style.width = '100%';
      bar.textContent = '';
      progress.removeAttribute('aria-valuenow');
    };

    const render = (report) => {
      ui.status.className = `badge ${BADGE[report.status] || 'bg-secondary'}`;
      ui.status.textContent = report.statusLabel;
      ui.activity.textContent = report.activity;
      ui.seen.textContent = report.seen;
      ui.new.textContent = report.new;
      ui.updated.textContent = report.updated;

      setBar(report);

      if (report.phase !== lastPhase) {
        lastPhase = report.phase;

        if (!report.done) {
          log(report.activity, 'text-muted');
        }
      }

      (report.events || []).forEach((event) => {
        const outcome = text(event.outcome === 'new' ? 'COM_VEL_FEEDRUN_EVENT_NEW' : 'COM_VEL_FEEDRUN_EVENT_UPDATED');
        // NVD records have no title and use the description: keep the log to one line each.
        const title = String(event.title || '');
        const short = title.length > TITLE_LENGTH ? `${title.slice(0, TITLE_LENGTH - 1)}…` : title;

        log(`${event.id}  ${outcome}  ${short}`.trim(), event.outcome === 'new' ? 'text-success' : '');
      });

      if (report.done) {
        const message = report.message || report.activity;

        ui.message.className = `alert ${ALERT[report.status] || 'alert-info'}`;
        ui.message.textContent = message;
        log(`${report.statusLabel}: ${message}`, 'fw-bold');
      }
    };

    const leaveWarning = (event) => {
      event.preventDefault();
      // Older browsers show this; current ones show their own wording.
      event.returnValue = text('COM_VEL_FEEDRUN_LEAVE_WARNING');

      return event.returnValue;
    };

    const finish = (report, quietly = false) => {
      finished = true;

      window.clearInterval(clock);
      window.removeEventListener('beforeunload', leaveWarning);

      if (report && report.elapsed) {
        startedAt = Date.now() - report.elapsed * 1000;
      }

      tickClock();
      ui.stop.classList.add('d-none');
      ui.cont.classList.add('d-none');
      ui.close.classList.remove('d-none');

      if (!quietly) {
        ui.close.focus();
      }
    };

    const schedule = (fn, ms) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(fn, ms);
    };

    const post = async (url) => {
      const body = new FormData();

      body.append('id', String(options.id));

      if (!Joomla.getOptions('csrf.token', '') && token) {
        body.append(token, '1');
      }

      let response;

      try {
        response = await fetch(url, {
          method: 'POST',
          body,
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
      } catch (error) {
        throw new TransientError(error.message || 'network error');
      }

      let payload = null;

      try {
        payload = await response.json();
      } catch (error) {
        // A gateway timeout or a PHP fatal is an HTML page with a 5xx status: try again. A 200
        // that is not JSON is the login page after the session ran out: do not.
        if (!response.ok) {
          throw new TransientError(`HTTP ${response.status}`);
        }

        throw new FatalError(text('COM_VEL_FEEDRUN_BAD_RESPONSE'));
      }

      if (!payload || typeof payload !== 'object') {
        throw new FatalError(text('COM_VEL_FEEDRUN_BAD_RESPONSE'));
      }

      if (!payload.success) {
        // 403: token or permission; 404: no such run. Anything else (a database hiccup while
        // saving a step) is worth another go: the step lock and the saved progress make a
        // repeated step safe.
        if (response.status === 403 || response.status === 404) {
          throw new FatalError(payload.message || `HTTP ${response.status}`);
        }

        throw new TransientError(payload.message || `HTTP ${response.status}`);
      }

      return payload.data;
    };

    let step;
    let stopNow;

    /** What to do after a request failed: retry a few times, then hand over to the person. */
    const failed = (error, again) => {
      if (error instanceof FatalError) {
        ui.message.className = 'alert alert-danger';
        ui.message.textContent = error.message;
        log(error.message, 'text-danger');
        finish(null);

        return;
      }

      if (failures >= RETRY_DELAYS.length) {
        failures = 0;
        ui.message.className = 'alert alert-warning';
        ui.message.textContent = text('COM_VEL_FEEDRUN_GAVE_UP');
        log(text('COM_VEL_FEEDRUN_GAVE_UP'), 'text-danger');
        ui.cont.classList.remove('d-none');
        ui.cont.focus();

        return;
      }

      const delay = RETRY_DELAYS[failures];

      failures += 1;
      note(text('COM_VEL_FEEDRUN_RETRY', error.message, delay));
      log(text('COM_VEL_FEEDRUN_RETRY', error.message, delay), 'text-warning');
      schedule(again, delay * 1000);
    };

    step = async () => {
      if (finished) {
        return;
      }

      if (stopping) {
        stopNow();

        return;
      }

      let report;

      inFlight = true;

      try {
        report = await post(options.stepUrl);
      } catch (error) {
        inFlight = false;
        failed(error, step);

        return;
      }

      inFlight = false;
      failures = 0;
      ui.message.className = 'alert d-none';

      render(report);

      if (report.done) {
        finish(report);

        return;
      }

      if (stopping) {
        stopNow();

        return;
      }

      if (report.busy) {
        note(text('COM_VEL_FEEDRUN_BUSY'));
        schedule(step, BUSY_DELAY * 1000);

        return;
      }

      schedule(step, PAUSE);
    };

    stopNow = async () => {
      let report;

      inFlight = true;

      try {
        report = await post(options.stopUrl);
      } catch (error) {
        inFlight = false;
        failed(error, stopNow);

        return;
      }

      inFlight = false;
      render(report);

      if (report.busy) {
        note(text('COM_VEL_FEEDRUN_STOPPING'));
        schedule(stopNow, BUSY_DELAY * 1000);

        return;
      }

      finish(report);
    };

    ui.stop.addEventListener('click', () => {
      // eslint-disable-next-line no-alert
      if (stopping || !window.confirm(text('COM_VEL_FEEDRUN_CONFIRM_STOP'))) {
        return;
      }

      stopping = true;
      ui.stop.disabled = true;
      ui.cont.classList.add('d-none');
      note(text('COM_VEL_FEEDRUN_STOPPING'));

      // Between steps: stop now. During one: the step's answer triggers the stop.
      if (!inFlight) {
        window.clearTimeout(timer);
        stopNow();
      }
    });

    ui.cont.addEventListener('click', () => {
      ui.cont.classList.add('d-none');
      ui.message.className = 'alert d-none';
      step();
    });

    // Start from the state the page was rendered with.
    render(options.report);

    if (options.report.done) {
      finish(options.report, true);

      return;
    }

    window.addEventListener('beforeunload', leaveWarning);
    clock = window.setInterval(tickClock, 1000);
    tickClock();
    step();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(document, Joomla);
