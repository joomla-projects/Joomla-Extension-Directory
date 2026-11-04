<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Mail\ThreadResolver;
use Jed\Component\Vel\Administrator\Mail\WebhookReceiver;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The inbound-mail webhook: POST /vulnerable-extensions?task=inbound.receive (VEL2-PRD D-4).
 *
 * No CSRF token (the caller is a machine), an HMAC instead; JSON in, JSON out.
 *
 * @since 4.0.0
 */
class InboundController extends BaseController
{
    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function receive(): void
    {
        $params = ComponentHelper::getParams('com_vel');

        if ((string) $params->get('inbound_mode', 'off') !== 'webhook') {
            $this->answer(404, ['error' => 'webhook_disabled']);

            return;
        }

        if ($this->input->getMethod() !== 'POST') {
            $this->answer(405, ['error' => 'method_not_allowed']);

            return;
        }

        $raw       = (string) file_get_contents('php://input');
        $signature = (string) $this->input->server->getString('HTTP_X_VEL_SIGNATURE', '');

        if (!WebhookReceiver::verify($raw, $signature, (string) $params->get('webhook_secret', ''))) {
            Log::add('VEL webhook: bad signature from ' . $this->input->server->getString('REMOTE_ADDR', ''), Log::WARNING, 'com_vel');
            $this->answer(401, ['error' => 'bad_signature']);

            return;
        }

        $payload = json_decode($raw, true);

        if (!\is_array($payload)) {
            $this->answer(400, ['error' => 'bad_json']);

            return;
        }

        try {
            $mail   = WebhookReceiver::parse($payload);
            $result = (new ThreadResolver(Factory::getContainer()->get(DatabaseInterface::class)))->handle($mail);
        } catch (\InvalidArgumentException $e) {
            $this->answer(400, ['error' => 'bad_payload', 'message' => $e->getMessage()]);

            return;
        } catch (Throwable $e) {
            Log::add('VEL webhook: ' . $e->getMessage(), Log::ERROR, 'com_vel');
            $this->answer(500, ['error' => 'server_error']);

            return;
        }

        $this->answer(200, $result);
    }

    /**
     * @param int   $status HTTP status.
     * @param array $body   JSON body.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function answer(int $status, array $body): void
    {
        $this->app->setHeader('status', (string) $status, true);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();

        echo json_encode($body, JSON_UNESCAPED_SLASHES);

        $this->app->close();
    }
}
