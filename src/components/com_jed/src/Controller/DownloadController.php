<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Hit\HitRecorder;
use Jed\Component\Jed\Administrator\Hit\HitType;
use Jed\Component\Jed\Administrator\Url\UrlFormat;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The download button: count the click, then send the visitor on.
 *
 * A recording redirect rather than JED3's "Please Note" interstitial. The interstitial costs every
 * visitor a click to read a sentence that fits next to the button, and a share of them abandon
 * there - the note is better placed where they already are. The counting point is the same either
 * way, which is what `P1-07` handed this task the decision for.
 *
 * The redirect is deliberately narrow. Anything that takes a URL out of the database and sends a
 * browser to it is an open redirect unless it proves otherwise, so:
 *
 *  - the target comes from the listing's own `download_url`, never from the request;
 *  - it goes through `UrlFormat` (`P1-08`) before being used, so a value that predates that rule
 *    or was written straight into the database cannot become an `http-equiv` to anywhere;
 *  - the listing has to be publicly visible (4.8) - a blocked or unapproved listing has no
 *    download link, and this must not be the way around that.
 *
 * @since 4.1.0
 */
class DownloadController extends BaseController
{
    /**
     * Count the click and redirect to the developer's download.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function go(): void
    {
        $app         = Factory::getApplication();
        $extensionId = $app->getInput()->getInt('id', 0);
        $listing     = $this->loadListing($extensionId);

        if ($listing === null) {
            $app->enqueueMessage(Text::_('COM_JED_ITEM_NOT_LOADED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=extensions', false));

            return;
        }

        $url = trim((string) $listing['download_url']);

        if ($url === '' || UrlFormat::check($url) !== []) {
            // Nothing usable to send them to. Back to the listing rather than to an error page:
            // everything else about it still works.
            $app->enqueueMessage(Text::_('COM_JED_DOWNLOAD_UNAVAILABLE'), 'warning');
            $this->setRedirect(Route::_(
                'index.php?option=com_jed&view=extension&catid=' . (int) $listing['catid'] . '&id=' . $extensionId,
                false
            ));

            return;
        }

        (new HitRecorder(Factory::getContainer()->get(DatabaseInterface::class)))
            ->record($extensionId, HitType::DOWNLOAD_CLICK);

        // 302, not 301: the target is a column a developer can change at any time, and a
        // permanent redirect would be cached by browsers long after it stopped being true.
        $app->redirect($url, 302);
    }

    /**
     * The listing, if it is one the public may see.
     *
     * @param int $extensionId The listing.
     *
     * @return array<string, mixed>|null
     *
     * @since 4.1.0
     */
    protected function loadListing(int $extensionId): ?array
    {
        if ($extensionId <= 0) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'catid', 'download_url', 'approved', 'state', 'blocked', 'deleted']))
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadAssoc();

        if ($row === null) {
            return null;
        }

        // The same four carriers the detail page consults (4.8, P1-01). Spelled out rather than
        // delegated because this is a redirect: it has to be obvious from here that a blocked
        // listing cannot be used as a hop.
        $visible = (int) $row['approved'] === 1
            && (int) $row['state'] === 1
            && (int) $row['blocked'] === 0
            && (int) $row['deleted'] === 0;

        return $visible ? $row : null;
    }
}
