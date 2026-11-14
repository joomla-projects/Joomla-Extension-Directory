<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Stand-ins for the CMS services the feed code touches, and for the advisory side of the VEL.
 * Loaded by tests/feeds/bootstrap.php only - never together with the real classes.
 *
 * Text returns the language key (and `KEY|arg|arg` for sprintf/plural) so the tests can assert
 * on which message a run chose; set Cms::$translate to true to get the en-GB strings instead.
 */

namespace Jed\Tests\Feeds\Support {
    /**
     * The state of the stand-ins, reset by FeedTestCase::setUp().
     */
    final class Cms
    {
        /** @var array com_vel options */
        public static array $params = [];

        /** @var list<array{0:int,1:string}> Log::add() calls */
        public static array $log = [];

        /** @var callable|null fn (string $url, array $headers, ?int $timeout): array{0:int,1:string,2?:array} */
        public static $http = null;

        /** @var list<array{url:string, timeout:?int, headers:array, at:float}> */
        public static array $calls = [];

        public static bool $translate = false;

        private static ?array $strings = null;

        public static function reset(array $params = []): void
        {
            self::$params    = $params;
            self::$log       = [];
            self::$http      = null;
            self::$calls     = [];
            self::$translate = false;
        }

        public static function string(string $key): string
        {
            if (!self::$translate) {
                return $key;
            }

            self::$strings ??= parse_ini_file(JPATH_ADMINISTRATOR . '/components/com_vel/language/en-GB/com_vel.ini') ?: [];

            return self::$strings[$key] ?? $key;
        }

        public static function has(string $key): bool
        {
            self::string('');

            return isset(self::$strings[$key]);
        }
    }
}

namespace Joomla\CMS\Language {
    use Jed\Tests\Feeds\Support\Cms;

    final class Text
    {
        public static function _($key, ...$rest)
        {
            return Cms::string((string) $key);
        }

        public static function sprintf($key, ...$args)
        {
            return Cms::$translate ? vsprintf(Cms::string((string) $key), $args) : $key . '|' . implode('|', array_map('strval', $args));
        }

        public static function plural($key, ...$args)
        {
            if (!Cms::$translate) {
                return $key . '|' . implode('|', array_map('strval', $args));
            }

            $n = (int) ($args[0] ?? 0);

            foreach ([$key . '_' . $n, $key . '_' . ($n === 1 ? '1' : 'MORE')] as $candidate) {
                if (Cms::has($candidate)) {
                    return vsprintf(Cms::string($candidate), $args);
                }
            }

            return vsprintf(Cms::string((string) $key), $args);
        }

        public static function script($key)
        {
        }
    }
}

namespace Joomla\CMS\Log {
    use Jed\Tests\Feeds\Support\Cms;

    final class Log
    {
        public const ERROR   = 8;
        public const WARNING = 16;
        public const INFO    = 64;

        public static function add($message, $priority = self::INFO, $category = '')
        {
            Cms::$log[] = [$priority, (string) $message];
        }
    }
}

namespace Joomla\CMS\Component {
    use Jed\Tests\Feeds\Support\Cms;
    use Joomla\Registry\Registry;

    final class ComponentHelper
    {
        public static function getParams($option)
        {
            return new Registry(Cms::$params);
        }

        public static function isEnabled($option)
        {
            return true;
        }
    }
}

namespace Joomla\CMS\Http {
    use Jed\Tests\Feeds\Support\Cms;
    use Joomla\Http\Response;
    use Joomla\Http\TransportInterface;
    use Joomla\Uri\UriInterface;

    /** Never used: FakeHttp answers every request itself. */
    final class NullTransport implements TransportInterface
    {
        public function __construct($options = [])
        {
        }

        public function request($method, UriInterface $uri, $data = null, array $headers = [], $timeout = null, $userAgent = null)
        {
            throw new \LogicException('The feed tests make no network requests.');
        }

        public static function isSupported()
        {
            return true;
        }
    }

    /** The real Joomla\Http\Http, with GET answered by Cms::$http. */
    final class FakeHttp extends \Joomla\Http\Http
    {
        public function __construct(private readonly ?int $defaultTimeout = null)
        {
            parent::__construct([], new NullTransport());
        }

        public function get($url, array $headers = [], $timeout = null)
        {
            Cms::$calls[] = ['url' => (string) $url, 'timeout' => $timeout ?? $this->defaultTimeout, 'headers' => $headers, 'at' => microtime(true)];

            if (Cms::$http === null) {
                throw new \LogicException('No fake server installed for ' . $url);
            }

            $answer = (Cms::$http)((string) $url, $headers, $timeout);

            if ($answer instanceof \Throwable) {
                throw $answer;
            }

            [$status, $body, $responseHeaders] = $answer + [2 => []];

            $response = new Response('php://memory', $status, $responseHeaders);
            $response->getBody()->write($body);

            return $response;
        }
    }

    final class HttpFactory
    {
        public static function getHttp($options = [], $adapters = null)
        {
            return new FakeHttp($options['timeout'] ?? null);
        }
    }
}

namespace Joomla\CMS {
    final class Factory
    {
        public static function getApplication()
        {
            return new class () {
                public function getLanguage()
                {
                    return new class () {
                        public function load(...$args)
                        {
                            return true;
                        }
                    };
                }
            };
        }

        public static function getDate($time = 'now', $tz = null)
        {
            $ts = $time === 'now' ? time() : (int) strtotime((string) $time);

            return new class ($ts) {
                public function __construct(private int $ts)
                {
                }

                public function toSql($local = false, $db = null)
                {
                    return gmdate('Y-m-d H:i:s', $this->ts);
                }
            };
        }
    }
}

namespace Jed\Component\Vel\Administrator\Service {
    /** The lifecycle service: only the note the feed appends for a rejected CVE. */
    class VelService
    {
        public static array $notes = [];

        public function __construct(...$args)
        {
        }

        public function appendNote(int $advisoryId, string $note): void
        {
            self::$notes[] = [$advisoryId, $note];
        }
    }
}

namespace Jed\Component\Vel\Administrator\Feed {
    /** The merger: no advisory matches; a Joomla CNA record "creates" a draft with a made-up id. */
    final class AdvisoryMerger
    {
        private static int $next = 1000;

        public function __construct(...$args)
        {
        }

        public function findExisting(FeedRecord $record): ?object
        {
            return null;
        }

        public function create(FeedRecord $record, int $actorId = 0): int
        {
            return self::$next++;
        }
    }
}
