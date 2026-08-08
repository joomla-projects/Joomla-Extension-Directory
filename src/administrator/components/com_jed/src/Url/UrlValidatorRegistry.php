<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Url;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Update\UpdateServerXmlParser;
use Jed\Component\Jed\Administrator\Url\Validator\ChangelogValidator;
use Jed\Component\Jed\Administrator\Url\Validator\DownloadValidator;
use Jed\Component\Jed\Administrator\Url\Validator\GitValidator;
use Jed\Component\Jed\Administrator\Url\Validator\ReachableValidator;
use Jed\Component\Jed\Administrator\Url\Validator\UpdateServerValidator;
use RuntimeException;

/**
 * Maps a form field's `validator` key to the class that performs that check.
 *
 * Deliberately the same shape as {@see \Jed\Component\Jed\Administrator\Queue\JobHandlerRegistry},
 * which already solves this problem in this codebase: a string from data resolves to a service,
 * the wiring lives in one provider, and nothing outside the registry can name a class.
 *
 * The allow-list property matters more here than it does for queue jobs. The key arrives from a
 * form field and, through the AJAX endpoint, from the request - so `get()` on an unknown key has
 * to fail rather than fall back to something, or the endpoint becomes "run any check on any URL".
 *
 * @since 4.1.0
 */
class UrlValidatorRegistry
{
    /**
     * @var array<string, UrlValidatorInterface>
     *
     * @since 4.1.0
     */
    private array $validators = [];

    /**
     * The registry as the JED ships it — the complete list of keys form XML may name.
     *
     * A named constructor rather than a container binding, unlike its sibling in the queue. The
     * queue's registry is assembled in the task plugin's provider because that plugin is the only
     * thing that ever runs a job; these validators are needed by a controller in the site
     * application and a controller in the administrator, and a component service provider
     * registers into the component's own container, which those controllers cannot reach without
     * threading it through the MVC factory. One method that everybody calls keeps the allow-list
     * in one place, which is the property that matters.
     *
     * @param SafeHttpFetcher       $fetcher The guarded fetcher every validator shares.
     * @param UpdateServerXmlParser $parser  The feed parser the update check already uses.
     *
     * @return self
     *
     * @since 4.1.0
     */
    public static function withDefaults(SafeHttpFetcher $fetcher, UpdateServerXmlParser $parser): self
    {
        $registry = new self();

        $registry->register('reachable', new ReachableValidator($fetcher));
        $registry->register('updateserver', new UpdateServerValidator($fetcher, $parser));
        $registry->register('changelog', new ChangelogValidator($fetcher));
        $registry->register('download', new DownloadValidator($fetcher));
        $registry->register('git', new GitValidator($fetcher));

        return $registry;
    }

    /**
     * Register a validator under a key.
     *
     * @param string                $key       The key used in form XML.
     * @param UrlValidatorInterface $validator The implementation.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function register(string $key, UrlValidatorInterface $validator): void
    {
        $this->validators[$key] = $validator;
    }

    /**
     * Get the validator for a key.
     *
     * @param string $key The key.
     *
     * @return UrlValidatorInterface
     *
     * @throws RuntimeException If nothing is registered under that key.
     *
     * @since 4.1.0
     */
    public function get(string $key): UrlValidatorInterface
    {
        if (!isset($this->validators[$key])) {
            throw new RuntimeException(\sprintf('No URL validator registered for "%s".', $key));
        }

        return $this->validators[$key];
    }

    /**
     * Whether a key is known.
     *
     * @param string $key The key.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function has(string $key): bool
    {
        return isset($this->validators[$key]);
    }

    /**
     * Every registered key.
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public function keys(): array
    {
        return array_keys($this->validators);
    }
}
