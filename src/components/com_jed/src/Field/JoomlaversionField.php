<?php
/**
 * @package       JED
 *
 * @subpackage    Tickets
 *
 * @copyright (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\CheckboxesField;
use Joomla\Database\DatabaseInterface;

/**
 * Gets list of joomla versions from tables and makes a multi select checkbox list
 *
 * @since 4.0.0
 */
class JoomlaVersionField extends CheckboxesField
{
    /**
     * The  field type.
     *
     * @var   string
     * @since 4.0.0
     */
    protected $type = 'joomlaversion';

    /**
     * Method to get the field options for category
     * Use the extension attribute in a form to specify the.specific extension for
     * which categories should be displayed.
     * Use the show_root attribute to specify whether to show the global category root in the list.
     *
     * @return  object[]    The field option objects.
     *
     * @since   1.6
     * @throws Exception
     */
    protected function getOptions(): array
    {
        $app    = Factory::getApplication();
        $user   = $app->getIdentity();
        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $option = $app->input->get('option');
        $query  = $db->getQuery(true);
        $query->select('a.id AS value, a.label AS text')->from('#__jed_joomla_versions AS a')->where('a.published=1');
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        foreach ($rows as $r) {
            $r->text = '<span class="joomla_versionsbadge">' . $r->text . '</span>';
        }

        return $rows;
    }
}
