<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Hit;

use Jed\Component\Jed\Administrator\Hit\BotDetector;
use Jed\Component\Jed\Administrator\Hit\HitType;
use PHPUnit\Framework\TestCase;

/**
 * Telling a crawler from a person.
 *
 * The consequence of getting this wrong is asymmetric but not dramatic in either direction, which
 * is exactly why it needs a test: nothing breaks visibly. Too eager and real visitors vanish from
 * the aggregate that decides which extensions get seen; too lax and the ranking is decided by
 * whoever is being crawled hardest. In JED3's stock 12.3% of hits were declared crawlers.
 *
 * @since 4.0.0
 */
final class BotDetectorTest extends TestCase
{
    /**
     * Agents that announce themselves as automated.
     *
     * @return array<string, array{0: string}>
     *
     * @since 4.0.0
     */
    public static function crawlers(): array
    {
        return [
            'Googlebot'       => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            'Bingbot'         => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
            'DuckDuckBot'     => ['DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)'],
            'Yandex'          => ['Mozilla/5.0 (compatible; YandexBot/3.0)'],
            'Baidu'           => ['Mozilla/5.0 (compatible; Baiduspider/2.0)'],
            'Yahoo'           => ['Mozilla/5.0 (compatible; Yahoo! Slurp)'],
            'AhrefsBot'       => ['Mozilla/5.0 (compatible; AhrefsBot/7.0)'],
            'Common Crawl'    => ['CCBot/2.0 (https://commoncrawl.org/faq/)'],
            'Facebook'        => ['facebookexternalhit/1.1'],
            'Internet Archive' => ['ia_archiver'],

            // Not search engines, but not people either.
            'curl'            => ['curl/8.4.0'],
            'wget'            => ['Wget/1.21.3'],
            'python-requests' => ['python-requests/2.31.0'],
            'Go'              => ['Go-http-client/2.0'],
            'Java'            => ['Java/17.0.1'],
            'OkHttp'          => ['okhttp/4.12.0'],
            'headless Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/120.0.0.0'],
            'uptime monitor'  => ['Mozilla/5.0 (compatible; UptimeRobot/2.0)'],
            'Pingdom'         => ['Pingdom.com_bot_version_1.4'],
            'a link checker'  => ['LinkChecker/10.2.1'],
        ];
    }

    /**
     * @dataProvider crawlers
     *
     * @param string $agent The user agent.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testRecognisesDeclaredCrawlers(string $agent): void
    {
        $this->assertTrue(BotDetector::isRobot($agent), $agent);
    }

    /**
     * Agents belonging to people.
     *
     * @return array<string, array{0: string}>
     *
     * @since 4.0.0
     */
    public static function browsers(): array
    {
        return [
            'Firefox on Linux'  => ['Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0'],
            'Chrome on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
            'Safari on macOS'   => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15'],
            'Safari on iPhone'  => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 Version/17.2 Mobile/15E148 Safari/604.1'],
            'Chrome on Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'],
            'Edge'              => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0'],
        ];
    }

    /**
     * @dataProvider browsers
     *
     * @param string $agent The user agent.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testLeavesRealBrowsersAlone(string $agent): void
    {
        $this->assertFalse(BotDetector::isRobot($agent), $agent);
        $this->assertFalse(
            BotDetector::isSuspicious($agent, true, false, 1),
            $agent . ': an ordinary visit must not be suspicious'
        );
    }

    /**
     * No user agent is not a declared crawler — it is something that did not say.
     *
     * The distinction matters because the two columns mean different things, and conflating them
     * would make `is_robot` mean "we were unsure".
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testAnAbsentAgentIsSuspiciousRatherThanRobot(): void
    {
        foreach (['', '   ', null] as $agent) {
            $this->assertFalse(BotDetector::isRobot($agent), var_export($agent, true));
            $this->assertTrue(BotDetector::isSuspicious($agent, true, false, 0), var_export($agent, true));
        }
    }

    /**
     * The weaker signals, and what each is worth on its own.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testWeakSignalsOnlyCountTogether(): void
    {
        $browser = 'Mozilla/5.0 (X11; Linux x86_64) Gecko/20100101 Firefox/128.0';

        // A missing referrer is completely ordinary - a bookmark, a typed address, a link out of
        // a mail client, or any site with a strict referrer policy. On its own it means nothing.
        $this->assertFalse(BotDetector::isSuspicious($browser, false, false, 1));
        $this->assertFalse(BotDetector::isSuspicious($browser, false, false, 5));

        // Combined with a rate that is already high, it does count.
        $this->assertTrue(BotDetector::isSuspicious($browser, false, false, (int) (BotDetector::RATE_LIMIT / 2)));

        // The rate on its own is enough once it passes the limit, referrer or not.
        $this->assertFalse(BotDetector::isSuspicious($browser, true, false, BotDetector::RATE_LIMIT - 1));
        $this->assertTrue(BotDetector::isSuspicious($browser, true, false, BotDetector::RATE_LIMIT));

        // An address in a range the JED team flagged (P1-05) counts on its own.
        $this->assertTrue(BotDetector::isSuspicious($browser, true, true, 1));
    }

    /**
     * The rate limit has to be well above what a person can produce.
     *
     * A whole office behind one NAT address shares an IP hash, and labelling that would quietly
     * write off a company's traffic.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testTheRateLimitLeavesRoomForRealPeople(): void
    {
        $this->assertGreaterThanOrEqual(30, BotDetector::RATE_LIMIT);
        $this->assertGreaterThanOrEqual(1, BotDetector::RATE_MINUTES);
    }

    /**
     * The two things the JED counts, and the columns they land in.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testTheMetricsAreNamedHonestly(): void
    {
        $this->assertSame('views', HitType::VIEW->statsColumn());
        $this->assertSame('download_clicks', HitType::DOWNLOAD_CLICK->statsColumn());

        // There is no case for anything the JED cannot substantiate - no "downloads", and above
        // all no "active installs" (13.4.4, 13.8).
        $this->assertSame(['view', 'download_click'], array_column(HitType::cases(), 'value'));
    }
}
