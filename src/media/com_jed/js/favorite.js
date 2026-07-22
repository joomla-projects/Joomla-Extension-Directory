/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Toggles a user's bookmark on an extension via AJAX (task=extension.addFavorite):
 * - .jed-favorite-btn (Extension page / cards): flips its own icon between fa-regular/fa-solid
 *   bookmark on success.
 * - .jed-favorite-remove-btn (Dashboard favorites list): every row there is already favorited,
 *   so a click always toggles it off - on success, removes the row from the table instead.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var config = document.getElementById('jed-favorite-i18n');

        if (!config) {
            return;
        }

        var i18n = config.dataset;

        document.querySelectorAll('.jed-favorite-btn').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                toggleFavorite(btn, i18n).then(function (result) {
                    if (result && result.success) {
                        setIconState(btn, result.data.favorited);
                    }
                });
            });
        });

        document.querySelectorAll('.jed-favorite-remove-btn').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();

                if (btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) {
                    return;
                }

                toggleFavorite(btn, i18n).then(function (result) {
                    if (result && result.success && !result.data.favorited) {
                        removeRow(btn.closest('tr'), i18n);
                    }
                });
            });
        });
    });

    /**
     * Posts the toggle request for the given button's data-extension-id and returns the parsed
     * JSON result (or null on network/decoding failure). Leaves the button disabled for the
     * duration of the request.
     */
    function toggleFavorite(btn, i18n) {
        if (btn.disabled) {
            return Promise.resolve(null);
        }

        btn.disabled = true;

        var formData = new FormData();
        formData.append('extension_id', btn.dataset.extensionId);
        formData.append(i18n.csrfToken, '1');

        return fetch(i18n.ajaxUrl + '&task=extension.addFavorite', {
            method: 'POST',
            body: formData,
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                btn.disabled = false;

                return result;
            })
            .catch(function () {
                btn.disabled = false;

                return null;
            });
    }

    /**
     * Removes a favorites-table row after a successful un-favorite, and if that was the last
     * row, replaces it with the "no entries" placeholder so the table doesn't look broken/empty.
     */
    function removeRow(row, i18n) {
        if (!row) {
            return;
        }

        var tbody = row.parentNode;

        row.remove();

        if (tbody && tbody.id === 'jed-favorites-tbody' && !tbody.children.length) {
            var placeholder = document.createElement('tr');
            var cell        = document.createElement('td');

            cell.colSpan   = 4;
            cell.className = 'text-center text-muted';
            cell.textContent = i18n.msgNoEntries || '';

            placeholder.appendChild(cell);
            tbody.appendChild(placeholder);
        }
    }

    function setIconState(btn, favorited) {
        var icon = btn.querySelector('i');

        if (!icon) {
            return;
        }

        icon.classList.toggle('fa-solid', favorited);
        icon.classList.toggle('fa-regular', !favorited);
        btn.setAttribute('aria-pressed', favorited ? 'true' : 'false');
    }
}());
