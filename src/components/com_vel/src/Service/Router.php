<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * VEL router (VEL2-PRD §8.4).
 *
 * Hangs everything off the `advisories` menu item, which the sample data creates at
 * /vulnerable-extensions - the path com_abandonware's router and the JED's sample data were
 * written to leave free:
 *
 *   /vulnerable-extensions/                    advisories (list)
 *   /vulnerable-extensions/42-some-alias       advisory
 *   /vulnerable-extensions/report              report form
 *   /vulnerable-extensions/fixnotice           fix-notice form
 *   /vulnerable-extensions/case/<token>        participant case page
 *   /vulnerable-extensions/json-feed           legacy JSON feed
 *   /vulnerable-extensions/api/v2/<resource>   JSON v2 (ApiRules)
 *
 * @since 4.0.0
 */
class Router extends RouterView
{
    use DatabaseAwareTrait;

    /**
     * @param SiteApplication $app  The application.
     * @param AbstractMenu    $menu The menu.
     *
     * @since 4.0.0
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        parent::__construct($app, $menu);
        $this->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));

        $advisories = new RouterViewConfiguration('advisories');
        $this->registerView($advisories);

        $advisory = new RouterViewConfiguration('advisory');
        $advisory->setKey('id')->setParent($advisories);
        $this->registerView($advisory);

        $report = new RouterViewConfiguration('report');
        $report->setParent($advisories);
        $this->registerView($report);

        $fixnotice = new RouterViewConfiguration('fixnotice');
        $fixnotice->setParent($advisories);
        $this->registerView($fixnotice);

        $case = new RouterViewConfiguration('case');
        $case->setKey('token')->setParent($advisories);
        $this->registerView($case);

        // Both handled by ApiRules, which owns their segments ("json-feed", "api/v2/...").
        // Registered so that MenuRules knows they belong under the advisories menu item.
        $legacy = new RouterViewConfiguration('legacyfeed');
        $legacy->setParent($advisories);
        $this->registerView($legacy);

        $api = new RouterViewConfiguration('api');
        $api->setParent($advisories);
        $this->registerView($api);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new ApiRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * @param string $segment The segment ("42:alias", "42-alias", "42", "VEL-0042", "CVE-…").
     * @param array  $query   The query.
     *
     * @return int
     *
     * @since 4.0.0
     */
    public function getAdvisoryId($segment, $query): int
    {
        if (preg_match('/^(\d+)(?:[:\-].*)?$/', (string) $segment, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^VEL-?0*(\d+)$/i', (string) $segment, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^CVE-\d{4}-\d{4,}$/i', (string) $segment)) {
            $db  = $this->getDatabase();
            $cve = strtoupper((string) $segment);

            return (int) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__vel_advisories'))
                    ->where($db->quoteName('cve_id') . ' = :cve')
                    ->bind(':cve', $cve)
            )->loadResult();
        }

        // A bare word is one of the sibling views (report, fixnotice, case...), never an alias:
        // advisory URLs always carry the id.
        return 0;
    }

    /**
     * @param mixed $id    The advisory id, possibly already "42:alias".
     * @param array $query The query.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getAdvisorySegment($id, $query): array
    {
        if (strpos((string) $id, ':')) {
            return [(int) $id => $id];
        }

        $id        = (int) $id;
        $numericId = $id;
        $db        = $this->getDatabase();

        $alias = (string) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('alias'))
                ->from($db->quoteName('#__vel_advisories'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadResult();

        return [$numericId => $alias !== '' ? $id . ':' . $alias : (string) $id];
    }

    /**
     * @param string $segment The token.
     * @param array  $query   The query.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function getCaseId($segment, $query): string
    {
        return preg_match('/^[a-f0-9]{32,64}$/i', (string) $segment) ? (string) $segment : '';
    }

    /**
     * @param string $token The token.
     * @param array  $query The query.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getCaseSegment($token, $query): array
    {
        return [(string) $token => (string) $token];
    }
}
