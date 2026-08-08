<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * A single report.
 *
 * There is no edit form and no backend editor for reports on purpose. A report is a record of what
 * one person said at one moment, and the only reason to keep the rejected ones is that the pattern
 * across them is the abuse signal 4.10 asks for - editing them would destroy exactly that. What
 * this model exists for is the list controller's state changes: accepting a submission, setting it
 * aside, or trashing one that should never have been stored.
 *
 * @since 4.0.0
 */
class ReportModel extends AdminModel
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_ABANDONWARE_REPORT';

    /**
     * @param string $type   The table type.
     * @param string $prefix The class prefix.
     * @param array  $config Configuration array.
     *
     * @return Table
     *
     * @since 4.0.0
     */
    public function getTable($type = 'Report', $prefix = 'Administrator', $config = []): Table
    {
        return $this->getMVCFactory()->createTable($type, $prefix, $config);
    }

    /**
     * @param array $data     Data for the form.
     * @param bool  $loadData Whether to load the data.
     *
     * @return Form|false
     *
     * @since 4.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return false;
    }

    /**
     * @return array
     *
     * @since 4.0.0
     */
    protected function loadFormData(): array
    {
        return [];
    }
}
