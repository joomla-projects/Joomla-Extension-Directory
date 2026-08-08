<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Update\UpdateServerXmlParser;
use Jed\Component\Jed\Administrator\Url\SafeHttpFetcher;
use Jed\Component\Jed\Administrator\Url\UrlCheckResult;
use Jed\Component\Jed\Administrator\Url\UrlCheckService;
use Jed\Component\Jed\Administrator\Url\UrlValidatorRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * The one AJAX endpoint behind layer 2.
 *
 * One endpoint for every check, because the validator arrives as a *key* that the registry
 * resolves - see {@see UrlValidatorInterface}. That is what keeps this a single place to secure
 * rather than one controller per kind of check.
 *
 * It makes the JED's server issue an HTTP request to an address the caller chose, so the guards
 * are not optional. They sit in three places and each is here for a different attacker:
 *
 *  - **this controller** decides *who* may ask: logged in, holding a valid CSRF token, and
 *    allowed to edit listings at all (`P1-03`). Without that it is an open proxy;
 *  - **{@see UrlCheckService}** decides *how often* and reuses recent answers, so it cannot be
 *    turned into an amplifier against a third party;
 *  - **{@see SafeHttpFetcher}** decides *where* the request may go, which is the SSRF boundary
 *    proper.
 *
 * `P1-08` notes this belongs behind the Phase 2 API layer eventually. It ships as a component
 * task now rather than blocking a Phase 1 item on a Phase 2 dependency, and the shape - a thin
 * controller over a service that holds every rule - is what makes moving it later a rewiring
 * rather than a rewrite. Recorded in `P2-01`.
 *
 * @since 4.0.0
 */
class UrlcheckController extends BaseController
{
    /**
     * Run one check and answer with JSON.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function check(): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $app->allowCache(false);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        // POST only. A GET endpoint that makes an outbound request is one an attacker can fire
        // from an <img> tag on any page the victim visits, token or not.
        if (strtoupper($app->getInput()->getMethod()) !== 'POST') {
            $this->respond(405, ['message' => Text::_('JINVALID_TOKEN_NOTICE')]);

            return;
        }

        if (!$this->checkToken('post', false)) {
            $this->respond(403, ['message' => Text::_('JINVALID_TOKEN_NOTICE')]);

            return;
        }

        if ($user === null || $user->guest) {
            $this->respond(401, ['message' => Text::_('JERROR_ALERTNOAUTHOR')]);

            return;
        }

        $input        = $app->getInput();
        $url          = (string) $input->getString('url', '');
        $validatorKey = (string) $input->getCmd('validator', '');
        $extensionId  = (int) $input->getInt('extension_id', 0);
        $field        = (string) $input->getCmd('field', '');

        if (!$this->mayCheck($extensionId)) {
            $this->respond(403, ['message' => Text::_('JERROR_ALERTNOAUTHOR')]);

            return;
        }

        $service = new UrlCheckService(
            Factory::getContainer()->get(DatabaseInterface::class),
            UrlValidatorRegistry::withDefaults(new SafeHttpFetcher(), new UpdateServerXmlParser())
        );

        try {
            $result = $service->check($url, $validatorKey, $user, $this->context($extensionId) + [
                'extension_id' => $extensionId,
                'field'        => $field,
            ]);
        } catch (Throwable $e) {
            // The rate limit is the only expected throw; anything else is a bug and must not
            // reach the browser as a message about the developer's URL.
            $this->respond(
                $e->getMessage() === 'rate_limit' ? 429 : 400,
                [
                    'state'   => UrlCheckResult::STATE_NOTICE,
                    'message' => Text::_(
                        $e->getMessage() === 'rate_limit'
                            ? 'COM_JED_URLCHECK_RATE_LIMIT'
                            : 'COM_JED_URLCHECK_UNAVAILABLE'
                    ),
                ]
            );

            return;
        }

        $this->respond(200, [
            'state'   => $result->state,
            'message' => Text::sprintf($result->message, ...array_values($result->params)),
            'status'  => $result->status,
            'detail'  => $result->detail,
            'checked' => Factory::getDate()->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Whether this user may have the JED make a request on their behalf.
     *
     * Tied to editing rather than to a right of its own: the endpoint exists to support the
     * extension form, so whoever may fill that form in may use it, and nobody else.
     *
     * In the administrator that is the JED team, and `core.edit` on the component is the whole
     * question - a team member with the right to edit any listing has no per-listing narrowing to
     * apply. The site controller answers the same question differently, through owner-or-
     * maintainer (`P1-03`), which is why this is not shared code.
     *
     * @param int $extensionId The listing being edited, 0 for a new one.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    protected function mayCheck(int $extensionId): bool
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user === null || $user->guest) {
            return false;
        }

        return $user->authorise('core.edit', 'com_jed') || $user->authorise('core.create', 'com_jed');
    }

    /**
     * What the validators need to know about the listing.
     *
     * @param int $extensionId The listing.
     *
     * @return array<string, mixed>
     *
     * @since 4.0.0
     */
    protected function context(int $extensionId): array
    {
        if ($extensionId <= 0) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['extension_version', 'extension_types', 'requires_registration']))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadAssoc();

        return $row ?: [];
    }

    /**
     * Send the JSON answer and stop.
     *
     * @param int                  $status HTTP status.
     * @param array<string, mixed> $data   The payload.
     *
     * @return void
     *
     * @since 4.0.0
     */
    protected function respond(int $status, array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('status', (string) $status, true);
        $app->sendHeaders();

        echo json_encode($data, JSON_UNESCAPED_SLASHES);

        $app->close();
    }
}
