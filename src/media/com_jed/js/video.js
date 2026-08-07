/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Click-to-load for listing videos (P1-11).
 *
 * The page ships a button, not an iframe. Only the click replaces it with the embed, so a visitor
 * who never plays anything never talks to YouTube or Vimeo at all. autoplay=1 is on the URL
 * because the click already was the play instruction - without it, the visitor has to click twice.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.jed-video__placeholder');

        if (!button) {
            return;
        }

        var src = button.dataset.jedVideoEmbed;

        if (!src) {
            return;
        }

        var iframe = document.createElement('iframe');

        iframe.src = src + (src.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
        iframe.title = button.dataset.jedVideoTitle || '';
        iframe.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('referrerpolicy', 'no-referrer');
        iframe.className = 'rounded w-100 h-100';
        iframe.style.border = '0';

        button.replaceWith(iframe);
        iframe.focus();
    });
})();
