<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A video, normalised to what it actually is.
 *
 * Provider plus id rather than a ready-made embed URL (8.4, `P1-11` item 2). An embed URL is one
 * column and simpler, but it is the provider's URL scheme frozen into the database - and the card
 * view will want a thumbnail without an iframe, which needs the id on its own.
 *
 * `FILE` is the third case and is not a provider at all: a direct link to an .mp4/.webm/.ogv that
 * a developer hosts themselves. Keeping it here rather than dropping it is deliberate - accepting
 * only YouTube and Vimeo would exclude exactly the developers who avoid them on purpose.
 *
 * @since 4.1.0
 */
final class Video
{
    /**
     * @since 4.1.0
     */
    public const YOUTUBE = 'youtube';
    public const VIMEO   = 'vimeo';
    public const FILE    = 'file';

    /**
     * @param string $provider One of the constants above.
     * @param string $id       The video id, or the full URL for a self-hosted file.
     *
     * @since 4.1.0
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $id
    ) {
    }

    /**
     * The canonical URL for this video.
     *
     * What a link should point at - the page a human would expect, not the embed. For a
     * self-hosted file the id *is* the URL.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public function watchUrl(): string
    {
        return match ($this->provider) {
            self::YOUTUBE => 'https://www.youtube.com/watch?v=' . $this->id,
            self::VIMEO   => 'https://vimeo.com/' . $this->id,
            default       => $this->id,
        };
    }

    /**
     * The URL to put in an iframe, once the visitor has asked for it.
     *
     * `youtube-nocookie.com` is used rather than `youtube.com`: it is YouTube's own reduced
     * tracking domain, and it costs nothing. It does **not** make the embed free of third-party
     * requests, which is why nothing loads this until a visitor clicks (`P1-11` item 6).
     *
     * @return string  Empty for a self-hosted file, which needs no iframe.
     *
     * @since 4.1.0
     */
    public function embedUrl(): string
    {
        return match ($this->provider) {
            self::YOUTUBE => 'https://www.youtube-nocookie.com/embed/' . $this->id,
            self::VIMEO   => 'https://player.vimeo.com/video/' . $this->id,
            default       => '',
        };
    }
}
