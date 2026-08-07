<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Parser\Video;
use Joomla\CMS\Language\Text;

/**
 * A listing's video, rendered click-to-load (`P1-11`, item 6).
 *
 * Nothing here reaches YouTube or Vimeo until the visitor asks it to. An iframe in the markup
 * would contact the provider - and hand it the visitor's IP address, and let it set cookies -
 * for every single page view, including the overwhelming majority where nobody plays anything.
 * So the placeholder is a button, and the iframe is only written into the page on the click that
 * asks for it. That is also why the embed URL uses youtube-nocookie.
 *
 * The thumbnail is deliberately *not* fetched from the provider either: img.youtube.com is a
 * request to YouTube like any other, and pulling one on load would give away exactly what the
 * click-to-load is here to protect. This is the reason the schema stores the id rather than a
 * ready-made embed URL - once `P1-10`'s pipeline can cache a thumbnail locally, it goes here
 * without a schema change.
 *
 * A self-hosted file needs none of this: it is the developer's own server, the visitor is already
 * talking to no one new, and `preload="none"` keeps it from downloading until it is played.
 *
 * @var array $displayData
 */

/**
 * @param string $provider One of Video::YOUTUBE, Video::VIMEO, Video::FILE.
 * @param string $id       The provider's id, or the file URL for a self-hosted video.
 * @param string $name     The listing name, for the accessible label.
 */
extract($displayData);

$video = new Video((string) $provider, (string) $id);

if ($video->id === '') {
    return;
}

if ($video->provider === Video::FILE) : ?>
    <div class="jed-video ratio ratio-16x9 mb-3">
        <video controls preload="none" class="rounded w-100"
               aria-label="<?php echo htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <source src="<?php echo htmlspecialchars($video->id, ENT_QUOTES, 'UTF-8'); ?>">
        </video>
    </div>
    <?php return;
endif;

$privacy = $video->provider === Video::VIMEO
    ? Text::_('COM_JED_EXTENSION_VIDEO_PRIVACY_VIMEO')
    : Text::_('COM_JED_EXTENSION_VIDEO_PRIVACY_YOUTUBE');
?>
<div class="jed-video ratio ratio-16x9 mb-3">
    <button type="button" class="jed-video__placeholder btn btn-dark d-flex flex-column
                                 align-items-center justify-content-center gap-2 w-100 h-100 rounded"
            data-jed-video-embed="<?php echo htmlspecialchars($video->embedUrl(), ENT_QUOTES, 'UTF-8'); ?>"
            data-jed-video-title="<?php echo htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <span class="fa fa-play fa-2x" aria-hidden="true"></span>
        <span><?php echo Text::_('COM_JED_EXTENSION_VIDEO_PLAY'); ?></span>
        <small class="text-white-50 px-3"><?php echo $privacy; ?></small>
    </button>
</div>
