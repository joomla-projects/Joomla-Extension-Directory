/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Fills in the bookmark icons on a browse list after the page has loaded (P1-13).
 *
 * The list itself no longer knows who is looking at it. That was the point: the bookmark flag was
 * the only per-visitor thing in an otherwise identical document, and it alone kept Joomla's page
 * cache from ever serving the busiest pages on the site. So the cards render unbookmarked for
 * everybody, this asks once which of them belong to the visitor, and sets those.
 *
 * A signed-in visitor pays one small request for that. Everyone else - the large majority - gets
 * a page that can come out of a cache.
 *
 * If this never runs, the icons stay in their empty state and the toggle in favorite.js still
 * works. Nothing here is load-bearing.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var config = document.getElementById('jed-favorite-i18n');

        if (!config || !config.dataset.ajaxUrl) {
            // Not signed in: no configuration element is rendered, and there is nothing to fill.
            return;
        }

        var buttons = Array.prototype.slice.call(document.querySelectorAll('.jed-favorite-btn[data-extension-id]'));

        if (buttons.length === 0) {
            return;
        }

        var body = new FormData();
        body.append('option', 'com_jed');
        body.append('task', 'extension.favoriteState');

        buttons.forEach(function (button) {
            body.append('ids[]', button.dataset.extensionId);
        });

        fetch(config.dataset.ajaxUrl, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data) {
                    return;
                }

                var favorited = payload.data.favorited || [];

                buttons.forEach(function (button) {
                    if (favorited.indexOf(parseInt(button.dataset.extensionId, 10)) === -1) {
                        return;
                    }

                    button.setAttribute('aria-pressed', 'true');

                    var icon = button.querySelector('i');

                    if (icon) {
                        icon.classList.remove('fa-regular');
                        icon.classList.add('fa-solid');
                    }
                });
            })
            .catch(function () {
                // The icons simply stay empty. A bookmark that is not shown is a smaller problem
                // than a browse page that fails.
            });
    });
})();
