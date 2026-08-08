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
 * Turns whatever is in the legacy `video` column into a {@see Video}, or into nothing.
 *
 * Pure and static: a string in, a normalised value or null out. That is what makes it testable,
 * and it matters here more than usual - this is exactly the kind of code that quietly mis-parses
 * one pattern in a thousand and nobody notices until a page is blank.
 *
 * It runs at import and again on save, never on render (8.4). Parsing on every page view would
 * be the same work repeated forever, and it would leave the database holding values nobody had
 * ever looked at.
 *
 * **Refusing is a result.** A channel URL, a playlist with no video in it, a vendor page that
 * merely contains a video - these are not videos, and guessing at them would put a broken embed
 * on a listing. They come back as null and land on the clean-up report instead.
 *
 * The patterns covered are the ones `P0-03` counted in the real stock, not the ones a provider's
 * documentation lists:
 *
 * | Pattern                          | Rows | Example                                        |
 * | -------------------------------- | ---- | ---------------------------------------------- |
 * | `{youtube}…{/youtube}`           |  515 | `{youtube}Zv1dMynbm2o{/youtube}`                |
 * | YouTube watch URL                |  574 | `https://www.youtube.com/watch?v=MMD9LksoXmg`   |
 * | `{vimeo}…{/vimeo}`               |   28 | `{vimeo}51714844{/vimeo}`                        |
 * | Vimeo URL                        |   24 | `https://player.vimeo.com/video/116544495`      |
 * | YouTube embed URL                |   20 | `https://www.youtube.com/embed/PDrsU0u2l6A`     |
 * | Bare id                          |    3 | `Y9qdPldwDWw`                                   |
 * | Direct media file                |   30 | `https://webxdesign.co/…/Ark_Editor_pro.mp4`    |
 *
 * @since 4.0.0
 */
final class VideoParser
{
    /**
     * A YouTube id is exactly 11 characters. Not "8 to 15".
     *
     * This is the one place the `P0-03` survey was too generous, and it matters: of the three
     * "bare video id" rows it counted, two are `davetechnosis` and `eveeperfumery` - channel
     * names, 13 characters, not videos. A looser pattern would have turned both into embeds
     * pointing at nothing.
     *
     * @since 4.0.0
     */
    private const YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/';

    /**
     * Vimeo ids are numeric. `vimeo.com/weeblr/4seointro` is a user's page, not a video.
     *
     * @since 4.0.0
     */
    private const VIMEO_ID = '/^[0-9]+$/';

    /**
     * File extensions accepted as a self-hosted video.
     *
     * @since 4.0.0
     */
    private const MEDIA_EXTENSIONS = ['mp4', 'webm', 'ogv'];

    /**
     * Normalise one stored value.
     *
     * @param string|null $raw Whatever is in the column.
     *
     * @return Video|null  Null when the value is not a video this can be sure about.
     *
     * @since 4.0.0
     */
    public static function parse(?string $raw): ?Video
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        // {youtube}…{/youtube} and {vimeo}…{/vimeo}. The closing tag only has to contain the same
        // provider name, because the stock has rows that close with `{youtube}` (slash missing)
        // and one that closes with `{(youtube}` - and throwing away a perfectly good id over a
        // typo somebody made in 2014 helps nobody. It stays safe because what is between the tags
        // still has to be an id or a URL; a loose tag cannot turn nonsense into a video.
        if (preg_match('/^\{(youtube|vimeo)\}(.*?)\{[^{}]*\1[^{}]*\}$/is', $value, $m)) {
            $provider = strtolower($m[1]);
            $inner    = self::cleanTagContent($m[2]);

            if ($inner === '') {
                return null;
            }

            if ($provider === Video::YOUTUBE && preg_match(self::YOUTUBE_ID, $inner)) {
                return new Video(Video::YOUTUBE, $inner);
            }

            if ($provider === Video::VIMEO && preg_match(self::VIMEO_ID, $inner)) {
                return new Video(Video::VIMEO, $inner);
            }

            // Still not an id - it may be a whole URL inside the tag.
            return self::parse($inner);
        }

        // A bare id, with nothing else to go on. Only YouTube: a bare number would be
        // indistinguishable from any other number and is not worth guessing at.
        if (preg_match(self::YOUTUBE_ID, $value)) {
            return new Video(Video::YOUTUBE, $value);
        }

        return self::parseUrl($value);
    }

    /**
     * Strip the decoration people put inside a `{youtube}` tag.
     *
     * The Joomla YouTube plugin accepted `{youtube}id|width|height{/youtube}`, and developers
     * pasted the rest in by hand. What actually occurs in the stock, and is recovered here:
     *
     *  - `id|650`                 - the plugin's width parameter (2 rows)
     *  - `v=Fv_syJDNWoI`          - the query key pasted along with the id (14 rows)
     *  - `id?t=20m8s`, `id&t`     - a timestamp (2 rows)
     *
     * Left alone if what remains is not an id: the caller re-parses it as a URL, so a tag
     * holding a full link still works.
     *
     * @param string $inner The text between the tags.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private static function cleanTagContent(string $inner): string
    {
        $inner = trim($inner);

        // A full URL inside the tag keeps its query string - parseUrl() needs it to find `v`.
        if (preg_match('#^(https?:)?//#i', $inner) || str_contains($inner, '.com')) {
            return $inner;
        }

        $inner = explode('|', $inner)[0];
        $inner = preg_replace('/^v=/', '', trim($inner));

        return trim(preg_split('/[?&]/', (string) $inner)[0]);
    }

    /**
     * Normalise a URL.
     *
     * @param string $value The URL, with or without a scheme.
     *
     * @return Video|null
     *
     * @since 4.0.0
     */
    private static function parseUrl(string $value): ?Video
    {
        // 12 rows in the legacy stock are written without a scheme - "youtube.com/watch?v=…".
        // parse_url() would read the whole thing as a path, so the scheme is supplied first.
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            if (!preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $value)) {
                return null;
            }

            $value = 'https://' . $value;
        }

        $parts = parse_url($value);

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = trim((string) ($parts['path'] ?? ''), '/');

        parse_str((string) ($parts['query'] ?? ''), $query);

        $video = self::fromYouTube($host, $path, $query)
            ?? self::fromVimeo($host, $path);

        if ($video !== null) {
            return $video;
        }

        return self::fromMediaFile($value, $path);
    }

    /**
     * @param string $host  Lowercased host.
     * @param string $path  Path without surrounding slashes.
     * @param array  $query Parsed query string.
     *
     * @return Video|null
     *
     * @since 4.0.0
     */
    private static function fromYouTube(string $host, string $path, array $query): ?Video
    {
        $host = preg_replace('/^(www|m)\./', '', $host);

        if ($host === 'youtu.be') {
            // The id is the whole path; ?si= and ?hd= tracking parameters are dropped with it.
            $id = explode('/', $path)[0] ?? '';

            return preg_match(self::YOUTUBE_ID, $id) ? new Video(Video::YOUTUBE, $id) : null;
        }

        if ($host !== 'youtube.com' && $host !== 'youtube-nocookie.com') {
            return null;
        }

        // Channels, users and handles are not videos, whatever else is in the URL.
        if (preg_match('#^(channel|user|c|@|playlist|results)#i', $path)) {
            return null;
        }

        $segments = explode('/', $path);

        if (\in_array($segments[0], ['embed', 'v', 'shorts', 'live'], true)) {
            $id = $segments[1] ?? '';

            return preg_match(self::YOUTUBE_ID, $id) ? new Video(Video::YOUTUBE, $id) : null;
        }

        // watch?v=…. Read from the parsed query rather than by position: real values put `v`
        // after `feature=player_embedded` and after `list=…`, so "the first parameter" is wrong.
        if ($segments[0] === 'watch') {
            $id = (string) ($query['v'] ?? '');

            return preg_match(self::YOUTUBE_ID, $id) ? new Video(Video::YOUTUBE, $id) : null;
        }

        return null;
    }

    /**
     * @param string $host Lowercased host.
     * @param string $path Path without surrounding slashes.
     *
     * @return Video|null
     *
     * @since 4.0.0
     */
    private static function fromVimeo(string $host, string $path): ?Video
    {
        $host = preg_replace('/^(www|player)\./', '', $host);

        if ($host !== 'vimeo.com') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path)));

        // player.vimeo.com/video/<id>
        if (($segments[0] ?? '') === 'video') {
            $id = $segments[1] ?? '';

            return preg_match(self::VIMEO_ID, $id) ? new Video(Video::VIMEO, $id) : null;
        }

        // vimeo.com/<id>. A non-numeric first segment is a user or a channel page -
        // "vimeo.com/weeblr/4seointro" is somebody's profile, not a video.
        $id = $segments[0] ?? '';

        return preg_match(self::VIMEO_ID, $id) ? new Video(Video::VIMEO, $id) : null;
    }

    /**
     * A file the developer hosts themselves.
     *
     * @param string $url  The full URL.
     * @param string $path The path, for the extension test.
     *
     * @return Video|null
     *
     * @since 4.0.0
     */
    private static function fromMediaFile(string $url, string $path): ?Video
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return \in_array($extension, self::MEDIA_EXTENSIONS, true) ? new Video(Video::FILE, $url) : null;
    }
}
