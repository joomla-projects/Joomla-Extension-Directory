/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Layers 1 and 2 of P1-08, for every field of type `jedurl`.
 *
 * Layer 1 is the format check, and it runs here with no network at all. It does not carry its own
 * copy of the rules: the field publishes them as data-jedurl-* attributes and this reads them
 * from there. That is the whole reason the attributes exist - a rule restated in JavaScript is a
 * rule that drifts, and a browser that accepts what the server rejects sends the developer
 * looking for a mistake on their side.
 *
 * Layer 2 asks the server whether the URL answers. It is advisory: it produces a notice, never an
 * error, and it never prevents the form from being submitted (13.4 point 5). Legitimate URLs fail
 * this check routinely - bot protection, geoblocking, a 403 for an unusual user agent - and a
 * hard failure would lock developers out of their own listing over somebody else's WAF rule.
 *
 * Layer 3 runs on the server on save regardless of any of this, which is what makes the whole
 * file optional rather than load-bearing (4.9).
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 600;

    /**
     * The format rules, read off the field rather than restated here.
     */
    function formatErrors(input, value) {
        var raw = value;
        var trimmed = raw.trim();

        if (trimmed === '') {
            return input.dataset.jedurlRequired === '1' ? ['empty'] : [];
        }

        var errors = [];
        var schemes = (input.dataset.jedurlSchemes || 'http,https').split(',');
        var maxLength = parseInt(input.dataset.jedurlMaxlength || '255', 10);

        // eslint-disable-next-line no-control-regex
        if (/[\x00-\x1F\x7F]/.test(raw)) {
            errors.push('control');
        }

        if (/\s/.test(trimmed)) {
            errors.push('whitespace');
        }

        if (trimmed.length > maxLength) {
            errors.push('length');
        }

        var schemeMatch = trimmed.match(/^([a-z][a-z0-9+.-]*):/i);

        if (!schemeMatch) {
            errors.push('noscheme');

            return errors;
        }

        if (schemes.indexOf(schemeMatch[1].toLowerCase()) === -1) {
            errors.push('scheme');

            return errors;
        }

        var url;

        try {
            url = new URL(trimmed);
        } catch (e) {
            errors.push('malformed');

            return errors;
        }

        if (url.username !== '' || url.password !== '') {
            errors.push('credentials');
        }

        if (!plausibleHost(url.hostname)) {
            errors.push('host');
        }

        return errors;
    }

    /**
     * Mirrors UrlFormat::isPlausibleHost(). A bare address, in any notation, is not a host the
     * JED stores - which also keeps the whole decimal/octal/IPv6-literal family away from the
     * fetcher.
     */
    function plausibleHost(host) {
        if (!host || host.length > 253 || host.indexOf('[') === 0) {
            return false;
        }

        var labels = host.split('.');

        if (labels.length < 2) {
            return false;
        }

        for (var i = 0; i < labels.length; i++) {
            if (!/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i.test(labels[i]) || labels[i].length > 63) {
                return false;
            }
        }

        return /^[a-z]{2,63}$/i.test(labels[labels.length - 1]);
    }

    function statusElement(input) {
        return document.getElementById(input.dataset.jedurlStatus);
    }

    /**
     * Feedback goes through a live region and through aria-invalid, never through colour alone
     * (13.8). The describedby link is made here rather than in the field, so the id is appended
     * to whatever the layout already put there instead of replacing it.
     */
    function show(input, state, message) {
        var status = statusElement(input);

        if (!status) {
            return;
        }

        status.className = 'jedurl-status jedurl-status--' + state;
        status.textContent = message || '';

        var described = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);

        if (described.indexOf(status.id) === -1) {
            described.push(status.id);
            input.setAttribute('aria-describedby', described.join(' '));
        }

        if (state === 'error') {
            input.setAttribute('aria-invalid', 'true');
        } else {
            input.removeAttribute('aria-invalid');
        }

        var recheck = document.querySelector('.jedurl-recheck[data-jedurl-for="' + input.id + '"]');

        if (recheck) {
            recheck.classList.toggle('d-none', input.value.trim() === '');
        }
    }

    function text(key, fallback) {
        return (window.Joomla && Joomla.Text && Joomla.Text._(key)) || fallback;
    }

    /**
     * Layer 2. Only reached once layer 1 is satisfied, which is what keeps a half-typed URL from
     * costing anybody a request.
     */
    function checkRemote(input) {
        var endpoint = input.dataset.jedurlEndpoint;
        var validator = input.dataset.jedurlValidator;

        if (!endpoint || !validator) {
            return;
        }

        if (input.jedurlController) {
            input.jedurlController.abort();
        }

        input.jedurlController = new AbortController();

        show(input, 'checking', text('COM_JED_URLCHECK_CHECKING', 'Checking…'));

        var body = new FormData();
        body.append('url', input.value.trim());
        body.append('validator', validator);
        body.append('field', input.name.replace(/^.*\[(.+)\]$/, '$1'));
        body.append(input.dataset.jedurlToken, '1');

        var form = input.closest('form');
        var idField = form && form.querySelector('[name="jform[id]"]');

        if (idField) {
            body.append('extension_id', idField.value);
        }

        fetch(endpoint, {
            method: 'POST',
            body: body,
            signal: input.jedurlController.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                show(input, data.state || 'notice', data.message || '');
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                // The check itself failing says nothing about the developer's URL, so it must not
                // read as though it did.
                show(input, 'notice', text('COM_JED_URLCHECK_UNAVAILABLE', 'The check could not be run just now.'));
            });
    }

    function runLayerOne(input) {
        var errors = formatErrors(input, input.value);

        if (errors.length === 0) {
            return true;
        }

        show(input, 'error', text('COM_JED_URLCHECK_FORMAT_' + errors[0].toUpperCase(), ''));

        return false;
    }

    function attach(input) {
        if (input.jedurlAttached) {
            return;
        }

        input.jedurlAttached = true;

        var timer = null;

        var schedule = function () {
            window.clearTimeout(timer);

            if (input.jedurlController) {
                input.jedurlController.abort();
            }

            timer = window.setTimeout(function () {
                if (input.value.trim() === '') {
                    show(input, 'idle', '');

                    return;
                }

                // Ordering saves requests: layer 2 only runs once layer 1 is satisfied.
                if (runLayerOne(input)) {
                    checkRemote(input);
                }
            }, DEBOUNCE_MS);
        };

        input.addEventListener('input', schedule);
        input.addEventListener('blur', function () {
            window.clearTimeout(timer);

            if (input.value.trim() !== '' && runLayerOne(input)) {
                checkRemote(input);
            }
        });
    }

    /**
     * Hand layer 1 to Joomla's own validator, once, for every field carrying
     * class="validate-jedurl". Joomla calls it as exec(value, element), so the rules are still
     * read off the element being validated rather than from a copy here - and the error appears
     * in the same place and shape as every other field's, which is why this is a handler rather
     * than a standalone script.
     */
    function registerHandler() {
        var validator = window.document.formvalidator;

        if (!validator || validator.jedurlRegistered) {
            return;
        }

        validator.jedurlRegistered = true;
        validator.setHandler('jedurl', function (value, element) {
            return formatErrors(element, value).length === 0;
        }, true);
    }

    document.addEventListener('DOMContentLoaded', function () {
        registerHandler();
        document.querySelectorAll('input[data-jedurl-validator]').forEach(attach);

        document.addEventListener('click', function (event) {
            var button = event.target.closest('.jedurl-recheck');

            if (!button) {
                return;
            }

            var input = document.getElementById(button.dataset.jedurlFor);

            if (input && runLayerOne(input)) {
                // A manual re-check is a deliberate act, so it says so - the server may still
                // answer from its cache, which is correct and not worth explaining here.
                checkRemote(input);
            }
        });
    });
})();
