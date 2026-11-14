<?php

/**
 * @package JED
 *
 * @subpackage Apps
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Apps\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What the endpoint answers: an HTTP status and the JSON body the client expects.
 *
 * @since 4.0.0
 */
final class AppsResult
{
    public const KIND_BROWSE = 'browse';
    public const KIND_SEARCH = 'search';
    public const KIND_ERROR  = 'error';

    private function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $kind
    ) {
    }

    public static function page(string $html, string $kind, int $pluginStatus = -1): self
    {
        return new self(200, self::encode([
            'success'  => true,
            'message'  => null,
            'messages' => null,
            'data'     => ['html' => $html, 'pluginuptodate' => $pluginStatus],
        ]), $kind);
    }

    public static function error(int $status, string $message): self
    {
        return new self($status, self::encode([
            'success'  => false,
            'message'  => $message,
            'messages' => null,
            'data'     => null,
        ]), self::KIND_ERROR);
    }

    public static function fromStored(array $stored): self
    {
        return new self((int) $stored['status'], (string) $stored['body'], (string) $stored['kind']);
    }

    public function toStored(): array
    {
        return ['status' => $this->status, 'body' => $this->body, 'kind' => $this->kind];
    }

    private static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
