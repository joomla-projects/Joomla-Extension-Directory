<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The CWE reference list: refresh from MITRE (research task R-03 confirms the download URL).
 *
 * @since 4.0.0
 */
class CwesController extends BaseController
{
    public const MITRE_URL = 'https://cwe.mitre.org/data/xml/cwec_latest.xml.zip';

    /**
     * Download MITRE's XML and upsert every weakness.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function refresh(): void
    {
        $this->checkToken();

        $return = Route::_('index.php?option=com_vel&view=cwes', false);

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_vel')) {
            $this->setRedirect($return, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        try {
            $response = (new HttpFactory())->getHttp(['timeout' => 120])->get(self::MITRE_URL, ['User-Agent' => 'Joomla-VEL/2.0']);

            if ($response->code >= 400) {
                throw new \RuntimeException('MITRE answered ' . $response->code);
            }

            $tmp = tempnam(sys_get_temp_dir(), 'cwe');
            file_put_contents($tmp, (string) $response->body);

            $zip = new \ZipArchive();

            if ($zip->open($tmp) !== true || $zip->numFiles < 1) {
                throw new \RuntimeException('The download is not a zip archive.');
            }

            $xml = $zip->getFromIndex(0);
            $zip->close();
            @unlink($tmp);

            $count = $this->import((string) $xml);
        } catch (Throwable $e) {
            $this->setRedirect($return, Text::sprintf('COM_VEL_ERROR_CWE_REFRESH', $e->getMessage()), 'error');

            return;
        }

        $this->setRedirect($return, Text::sprintf('COM_VEL_MSG_CWE_REFRESHED', $count));
    }

    /**
     * @param string $xml MITRE's CWE XML.
     *
     * @return int  Weaknesses upserted.
     *
     * @since 4.0.0
     */
    private function import(string $xml): int
    {
        $reader = new \XMLReader();

        if (!$reader->XML($xml)) {
            throw new \RuntimeException('The XML could not be read.');
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $count = 0;

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'Weakness') {
                continue;
            }

            $id     = 'CWE-' . (int) $reader->getAttribute('ID');
            $name   = mb_substr((string) $reader->getAttribute('Name'), 0, 255);
            $status = strtolower((string) $reader->getAttribute('Status')) ?: 'stable';

            if ($name === '' || (int) $reader->getAttribute('ID') <= 0) {
                continue;
            }

            $db->setQuery(
                'INSERT INTO ' . $db->quoteName('#__vel_cwe') . ' (' . $db->quoteName(['cwe_id', 'name', 'status']) . ') VALUES (:id, :name, :status)'
                . ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('name') . ' = :name2, ' . $db->quoteName('status') . ' = :status2'
            )->bind(':id', $id)->bind(':name', $name)->bind(':status', $status)->bind(':name2', $name)->bind(':status2', $status)->execute();

            $count++;
        }

        $reader->close();

        return $count;
    }
}
