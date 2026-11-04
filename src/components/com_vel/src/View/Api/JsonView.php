<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Api;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Cve\CveRecordBuilder;
use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Jed\Component\Vel\Site\Model\ApiModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\AbstractView;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The JSON v2 endpoints (VEL2-PRD §6.8.2). One view, dispatched on `resource`:
 *
 *   index | advisories | advisories/<id> | cve/<CVE> | dataset | check (POST)
 *
 * Writes the response itself and closes the application, rather than going through the JSON
 * document: that is what lets it answer 304 to If-None-Match, send its own Cache-Control, and
 * avoid the `Content-Disposition: attachment` the core JSON document adds.
 *
 * @since 4.0.0
 */
class JsonView extends AbstractView
{
    /**
     * @param string|null $tpl Unused.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $app      = Factory::getApplication();
        $input    = $app->getInput();
        $resource = strtolower((string) $input->getCmd('resource', 'index'));
        $id       = (string) $input->getString('id', '');

        /** @var ApiModel $model */
        $model = $this->getModel();

        try {
            switch ($resource) {
                case 'index':
                    $this->respond($model->getIndex());

                    return;

                case 'advisories':
                    if ($id !== '') {
                        $one = $model->getOne($id);

                        if ($one === null) {
                            $this->error(404, 'not_found', 'No published advisory matches ' . $id . '.');

                            return;
                        }

                        $this->respond($one);

                        return;
                    }

                    /** @var \Jed\Component\Vel\Site\Model\AdvisoriesModel $list */
                    $list = $app->bootComponent('com_vel')->getMVCFactory()->createModel('Advisories', 'Site', ['ignore_request' => false]);
                    $this->respond($model->getList($list));

                    return;

                case 'cve':
                    $this->cve($id);

                    return;

                case 'dataset':
                    $this->respondRaw($model->getDatasetJson(), $model->getDatasetVersion());

                    return;

                case 'check':
                    $this->check($model);

                    return;

                default:
                    $this->error(404, 'unknown_resource', 'Unknown resource: ' . $resource . '.');

                    return;
            }
        } catch (\InvalidArgumentException $e) {
            $this->error(400, 'bad_request', $e->getMessage());
        } catch (Throwable $e) {
            \Joomla\CMS\Log\Log::add('VEL API: ' . $e->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_vel');
            $this->error(500, 'server_error', 'The request could not be completed.');
        }
    }

    /**
     * @param string $id A CVE id.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function cve(string $id): void
    {
        if (!preg_match('/^CVE-\d{4}-\d{4,}$/i', $id)) {
            $this->error(400, 'bad_request', 'A CVE id is required.');

            return;
        }

        $repository = new AdvisoryRepository(Factory::getContainer()->get(DatabaseInterface::class));
        $advisory   = $repository->findByCve($id, true);

        if ($advisory === null) {
            $this->error(404, 'not_found', 'No published advisory carries ' . strtoupper($id) . '.');

            return;
        }

        if (!class_exists(CveRecordBuilder::class)) {
            $this->error(501, 'not_implemented', 'CVE record generation is not available.');

            return;
        }

        $builder = new CveRecordBuilder(VelHelper::serializer());
        $record  = $builder->record($advisory);

        $this->respond($record, hash('sha256', json_encode($record)));
    }

    /**
     * @param ApiModel $model The model.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function check(ApiModel $model): void
    {
        $app = Factory::getApplication();

        if ($app->getInput()->getMethod() !== 'POST') {
            $this->error(405, 'method_not_allowed', 'POST a JSON body with an "extensions" array.', ['Allow' => 'POST, OPTIONS']);

            return;
        }

        $raw  = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);

        if (!\is_array($body)) {
            // A form post with a JSON string in "payload" is accepted for clients that cannot
            // set a request body type.
            $payload = (string) $app->getInput()->post->get('payload', '', 'raw');
            $body    = json_decode($payload, true);
        }

        if (!\is_array($body)) {
            $this->error(400, 'bad_request', 'The body is not valid JSON.');

            return;
        }

        $this->respond($model->check($body), null, false);
    }

    /**
     * @param array       $data     The document.
     * @param string|null $etag     An ETag, or null to hash the output.
     * @param bool        $cachable Whether the response may be cached publicly.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function respond(array $data, ?string $etag = null, bool $cachable = true): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->respondRaw($json, $etag ?? hash('sha256', $json), $cachable);
    }

    /**
     * @param string $json     The JSON document.
     * @param string $etag     The ETag value (unquoted).
     * @param bool   $cachable Whether the response may be cached publicly.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function respondRaw(string $json, string $etag, bool $cachable = true): void
    {
        $app    = Factory::getApplication();
        $quoted = '"' . $etag . '"';
        $maxAge = max(0, (int) VelHelper::option('api_cache_seconds', 300));

        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Access-Control-Allow-Origin', '*', true);
        $app->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS', true);
        $app->setHeader('Access-Control-Allow-Headers', 'Content-Type, If-None-Match', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $app->setHeader('Vary', 'Accept-Encoding', true);

        if ($cachable) {
            $app->setHeader('ETag', $quoted, true);
            $app->setHeader('Cache-Control', 'public, max-age=' . $maxAge, true);

            $ifNoneMatch = (string) ($app->getInput()->server->getString('HTTP_IF_NONE_MATCH', ''));

            if ($ifNoneMatch !== '' && \in_array($quoted, array_map('trim', explode(',', $ifNoneMatch)), true)) {
                $app->setHeader('status', '304', true);
                $app->sendHeaders();
                $app->close();
            }
        } else {
            $app->setHeader('Cache-Control', 'no-store', true);
        }

        $app->setHeader('Content-Length', (string) \strlen($json), true);
        $app->sendHeaders();

        echo $json;

        $app->close();
    }

    /**
     * @param int    $status  HTTP status.
     * @param string $code    A machine-readable code.
     * @param string $message A sentence.
     * @param array  $headers Extra headers.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function error(int $status, string $code, string $message, array $headers = []): void
    {
        $app  = Factory::getApplication();
        $json = json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);

        $app->setHeader('status', (string) $status, true);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Access-Control-Allow-Origin', '*', true);
        $app->setHeader('Cache-Control', 'no-store', true);

        foreach ($headers as $name => $value) {
            $app->setHeader($name, $value, true);
        }

        $app->sendHeaders();

        echo $json;

        $app->close();
    }
}
