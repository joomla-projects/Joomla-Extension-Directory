<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Reads the list of people the portal holds, as the portal team exports it.
 *
 * Two columns are needed: the GUID and the email address. A header row is used when there is one
 * (`guid`, `email` or `emailaddress`, in any order and with any other columns), otherwise the
 * first column is the GUID and the second the address. Comma, semicolon or tab separated.
 *
 * Nothing in here touches the database, so the rules for what a good row is can be tested alone.
 *
 * @since 4.0.0
 */
final class PortalExport
{
    /**
     * Parse an export.
     *
     * @param string $csv The file contents.
     *
     * @return array{rows: array<int, array{guid: string, email: string}>, errors: string[]}
     *
     * @since 4.0.0
     */
    public static function parse(string $csv): array
    {
        $csv   = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = preg_split('/\r\n|\n|\r/', $csv) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        $result = ['rows' => [], 'errors' => []];

        if (!$lines) {
            return $result;
        }

        $delimiter = self::delimiter($lines[0]);
        $guidAt    = 0;
        $emailAt   = 1;
        $first     = array_map(static fn ($c) => strtolower(trim((string) $c)), str_getcsv($lines[0], $delimiter, '"', ''));

        if (\in_array('guid', $first, true)) {
            $guidAt  = array_search('guid', $first, true);
            $emailAt = array_search('email', $first, true);

            if ($emailAt === false) {
                $emailAt = array_search('emailaddress', $first, true);
            }

            if ($emailAt === false) {
                $result['errors'][] = 'The header has a guid column but no email or emailaddress column.';

                return $result;
            }

            array_shift($lines);
            $offset = 2;
        } else {
            $offset = 1;
        }

        foreach ($lines as $i => $line) {
            $cells = str_getcsv($line, $delimiter, '"', '');
            $guid  = trim((string) ($cells[$guidAt] ?? ''));
            $email = trim((string) ($cells[$emailAt] ?? ''));

            if (!Guid::isValid($guid)) {
                $result['errors'][] = sprintf('Line %d: "%s" is not a GUID.', $i + $offset, $guid);

                continue;
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['errors'][] = sprintf('Line %d: "%s" is not an email address.', $i + $offset, $email);

                continue;
            }

            $result['rows'][] = ['guid' => Guid::normalise($guid), 'email' => strtolower($email)];
        }

        return $result;
    }

    /**
     * Guess the separator from the first line.
     *
     * @param string $line The first line.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private static function delimiter(string $line): string
    {
        $best  = ',';
        $count = -1;

        foreach ([',', ';', "\t"] as $candidate) {
            if (substr_count($line, $candidate) > $count) {
                $best  = $candidate;
                $count = substr_count($line, $candidate);
            }
        }

        return $best;
    }
}
