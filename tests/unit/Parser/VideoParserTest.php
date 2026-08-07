<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Parser;

use Jed\Component\Jed\Administrator\Parser\Video;
use Jed\Component\Jed\Administrator\Parser\VideoParser;
use PHPUnit\Framework\TestCase;

/**
 * The video normalisation parser.
 *
 * Every fixture here is a real value from `wqyh6_jed_extensions.video` in the JED3 stock, or a
 * near neighbour of one. That is the point of `P0-03` being a hard prerequisite: a parser built
 * against a provider's documentation covers patterns nobody uses and misses the ones everybody
 * does.
 *
 * The parser is pure, so this is the one part of JED that can be tested properly without an
 * application fixture - which `P1-33` still owes the rest of the suite.
 *
 * @since 4.1.0
 */
final class VideoParserTest extends TestCase
{
    /**
     * Values that must convert, one entry per pattern `P0-03` counted.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     *
     * @since 4.1.0
     */
    public static function convertible(): array
    {
        return [
            // {youtube} tag - 515 rows
            'youtube tag'                 => ['{youtube}Zv1dMynbm2o{/youtube}', Video::YOUTUBE, 'Zv1dMynbm2o'],
            'youtube tag, mixed case'     => ['{YouTube}AsIqvWObfLc{/YouTube}', Video::YOUTUBE, 'AsIqvWObfLc'],
            'youtube tag holding a URL'   => ['{youtube}https://youtu.be/nqnO3fOSee0{/youtube}', Video::YOUTUBE, 'nqnO3fOSee0'],

            // watch URL - 574 rows
            'watch URL'                   => ['https://www.youtube.com/watch?v=MMD9LksoXmg', Video::YOUTUBE, 'MMD9LksoXmg'],
            'watch URL without a scheme'  => ['youtube.com/watch?v=P6qFVbklzGw', Video::YOUTUBE, 'P6qFVbklzGw'],
            'watch URL with a timestamp'  => ['https://www.youtube.com/watch?v=qa2mr8JAktQ&t=1s', Video::YOUTUBE, 'qa2mr8JAktQ'],
            'watch URL, v is not first'   => ['https://www.youtube.com/watch?feature=player_embedded&v=uE0FvJL4un8', Video::YOUTUBE, 'uE0FvJL4un8'],
            'watch URL after a list'      => ['https://www.youtube.com/watch?list=PLxCFSR0V-Zs093&v=jwH_zPkZvx8', Video::YOUTUBE, 'jwH_zPkZvx8'],
            'youtu.be'                    => ['https://youtu.be/nqnO3fOSee0', Video::YOUTUBE, 'nqnO3fOSee0'],
            'youtu.be over http'          => ['http://youtu.be/HjgH9crlMI0', Video::YOUTUBE, 'HjgH9crlMI0'],
            'youtu.be with tracking'      => ['https://youtu.be/Z5Gfw5_xksY?si=rlVTpXAK35Xf659P', Video::YOUTUBE, 'Z5Gfw5_xksY'],

            // embed URL - 20 rows
            'embed URL'                   => ['https://www.youtube.com/embed/PDrsU0u2l6A', Video::YOUTUBE, 'PDrsU0u2l6A'],
            'embed URL with parameters'   => ['https://www.youtube.com/embed/kuZtyHG0OGQ?VQ=HD720', Video::YOUTUBE, 'kuZtyHG0OGQ'],
            'embed URL, leading dash id'  => ['https://www.youtube.com/embed/-dbTlpr54mY', Video::YOUTUBE, '-dbTlpr54mY'],

            // bare id - 3 rows, of which exactly one is really an id
            'bare id'                     => ['Y9qdPldwDWw', Video::YOUTUBE, 'Y9qdPldwDWw'],

            // Decoration inside a {youtube} tag. Every one of these is a real row, and all of
            // them were being thrown away until the coverage run over the legacy stock showed
            // them sitting in the "unconvertible" bucket.
            'tag with a width parameter'  => ['{youtube}OYZ7oJPPlfY|650{/youtube}', Video::YOUTUBE, 'OYZ7oJPPlfY'],
            'tag with a v= prefix'        => ['{youtube}v=Fv_syJDNWoI{/youtube}', Video::YOUTUBE, 'Fv_syJDNWoI'],
            'tag with a timestamp'        => ['{youtube}yMPp1Cp3W_0?t=20m8s{/youtube}', Video::YOUTUBE, 'yMPp1Cp3W_0'],
            'tag with a stray ampersand'  => ['{youtube}c0EgfZxd5rM&t{/youtube}', Video::YOUTUBE, 'c0EgfZxd5rM'],
            'tag with the slash missing'  => ['{youtube}s8aAjTQqtXs{youtube}', Video::YOUTUBE, 's8aAjTQqtXs'],
            'tag with a typo in the close' => ['{youtube}https://youtu.be/dOkawN_Tq5A{(youtube}', Video::YOUTUBE, 'dOkawN_Tq5A'],
            'tag with surrounding space'  => [' {youtube}nxHSEJ1iQks{/youtube} ', Video::YOUTUBE, 'nxHSEJ1iQks'],

            // shorts, which the stock has one of
            'shorts URL'                  => ['https://www.youtube.com/shorts/87q2YbbRhho', Video::YOUTUBE, '87q2YbbRhho'],

            // {vimeo} tag - 28 rows
            'vimeo tag'                   => ['{vimeo}51714844{/vimeo}', Video::VIMEO, '51714844'],

            // vimeo URL - 24 rows
            'vimeo URL'                   => ['https://vimeo.com/64957551', Video::VIMEO, '64957551'],
            'vimeo player URL'            => ['https://player.vimeo.com/video/133804026', Video::VIMEO, '133804026'],
            'vimeo player with a width'   => ['https://player.vimeo.com/video/116544495?width=640', Video::VIMEO, '116544495'],

            // direct media file - 30 rows
            'mp4'                         => ['https://webxdesign.co/images/videos/Ark_Editor_pro.mp4', Video::FILE, 'https://webxdesign.co/images/videos/Ark_Editor_pro.mp4'],
            'mp4 over http'               => ['http://www.joomlarulez.com/images/stories/video/01QO7fTM-1753142.mp4', Video::FILE, 'http://www.joomlarulez.com/images/stories/video/01QO7fTM-1753142.mp4'],
            'webm'                        => ['https://example.org/media/demo.webm', Video::FILE, 'https://example.org/media/demo.webm'],
        ];
    }

    /**
     * @dataProvider convertible
     *
     * @param string $raw      The stored value.
     * @param string $provider The provider it should resolve to.
     * @param string $id       The id it should resolve to.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testConvertsKnownPatterns(string $raw, string $provider, string $id): void
    {
        $video = VideoParser::parse($raw);

        $this->assertNotNull($video, $raw . ' should convert');
        $this->assertSame($provider, $video->provider, $raw);
        $this->assertSame($id, $video->id, $raw);
    }

    /**
     * Values the parser has to refuse rather than guess at.
     *
     * @return array<string, array{0: string|null}>
     *
     * @since 4.1.0
     */
    public static function notConvertible(): array
    {
        return [
            'empty'                    => [''],
            'null'                     => [null],
            'whitespace'               => ['   '],

            // The 5 channel rows, and their relatives. Not videos.
            'youtube channel'          => ['https://www.youtube.com/channel/UCabc123def456ghi789jkl'],
            'youtube user page'        => ['https://www.youtube.com/user/joomla'],
            'youtube vanity channel'   => ['https://www.youtube.com/c/JoomlaOfficial'],
            'youtube handle'           => ['https://www.youtube.com/@joomla'],
            'youtube playlist'         => ['https://www.youtube.com/playlist?list=PL3M1w_AGnChPWpp1'],
            'watch URL with no v'      => ['https://www.youtube.com/watch?list=PL3M1w_AGnChPWpp1'],

            // The two 13-character strings P0-03 counted as "bare video ids". They are channel
            // names. An id is exactly 11 characters, and treating these as ids would have
            // produced two embeds pointing at nothing.
            'channel name, not an id'  => ['davetechnosis'],
            'another channel name'     => ['eveeperfumery'],
            'too short for an id'      => ['abc123'],

            // Tags whose content is simply broken. These are the ones that must stay rejected
            // even though the tag itself is well formed - an id of the wrong length is not an id.
            'empty tag'                => ['{youtube}{/youtube}'],
            'tag with a 9-char id'     => ['{youtube}hAmFRMbQN{/youtube}'],
            'tag with a 10-char id'    => ['{youtube}Cdct5Xorks{/youtube}'],
            'vanity channel, no /c/'   => ['http://youtube.com/helalcoxsbazar'],

            // A user's page on Vimeo, not a video.
            'vimeo user page'          => ['https://vimeo.com/weeblr/4seointro'],
            'vimeo non-numeric'        => ['https://vimeo.com/channels/staffpicks'],

            // Pages that merely contain a video.
            'vendor documentation page' => ['https://www.akeeba.com/documentation/backup.html'],
            'facebook video page'      => ['https://www.facebook.com/watch/?v=123456789'],
            'plain text'               => ['see our website'],
            'not a media file'         => ['https://example.org/downloads/extension.zip'],
        ];
    }

    /**
     * @dataProvider notConvertible
     *
     * @param string|null $raw The stored value.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testRefusesWhatIsNotAVideo(?string $raw): void
    {
        $this->assertNull(
            VideoParser::parse($raw),
            var_export($raw, true) . ' must not be turned into a video'
        );
    }

    /**
     * The URLs built from a parsed value.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testBuildsWatchAndEmbedUrls(): void
    {
        $youtube = VideoParser::parse('https://www.youtube.com/watch?v=MMD9LksoXmg');
        $vimeo   = VideoParser::parse('https://vimeo.com/64957551');
        $file    = VideoParser::parse('https://example.org/media/demo.webm');

        $this->assertSame('https://www.youtube.com/watch?v=MMD9LksoXmg', $youtube->watchUrl());
        $this->assertSame('https://vimeo.com/64957551', $vimeo->watchUrl());
        $this->assertSame('https://example.org/media/demo.webm', $file->watchUrl());

        // youtube-nocookie, not youtube: the reduced-tracking domain costs nothing.
        $this->assertSame('https://www.youtube-nocookie.com/embed/MMD9LksoXmg', $youtube->embedUrl());
        $this->assertSame('https://player.vimeo.com/video/64957551', $vimeo->embedUrl());

        // A self-hosted file needs no iframe.
        $this->assertSame('', $file->embedUrl());
    }

    /**
     * Parsing an already-normalised value gives the same answer.
     *
     * The parser runs at import and again on every save, so a value that has been through it
     * once must survive going through it again unchanged.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testIsIdempotentThroughItsOwnOutput(): void
    {
        foreach (self::convertible() as $label => [$raw, $provider, $id]) {
            $first = VideoParser::parse($raw);
            $again = VideoParser::parse($first->watchUrl());

            $this->assertNotNull($again, $label . ': the watch URL must parse back');
            $this->assertSame($provider, $again->provider, $label);
            $this->assertSame($id, $again->id, $label);
        }
    }
}
