<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Field;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/**
 * A searchable select over `#__vel_cwe`. Rendered with the fancy-select layout so that typing
 * "script" finds CWE-79 - a plain select of 150 entries is a list nobody scrolls.
 *
 * A stored value that is not in the reference list (an import carrying a CWE the seed does not
 * have) is kept as an option of its own rather than blanked on the next save.
 *
 * @since 4.0.0
 */
class CweField extends ListField
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $type = 'Cwe';

    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $layout = 'joomla.form.field.list-fancy-select';

    /**
     * The reference list, loaded once per request.
     *
     * @var array<string, string>|null
     *
     * @since 4.0.0
     */
    private static ?array $list = null;

    /**
     * @return array
     *
     * @since 4.0.0
     */
    protected function getOptions(): array
    {
        if (self::$list === null) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            self::$list = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['cwe_id', 'name']))
                    ->from($db->quoteName('#__vel_cwe'))
                    ->order('CAST(SUBSTRING(' . $db->quoteName('cwe_id') . ', 5) AS UNSIGNED) ASC')
            )->loadAssocList('cwe_id', 'name') ?: [];
        }

        $options   = [];
        $options[] = HTMLHelper::_('select.option', '', \Joomla\CMS\Language\Text::_('COM_VEL_FIELD_CWE_SELECT'));

        $current = strtoupper(trim((string) $this->value));

        if ($current !== '' && !isset(self::$list[$current])) {
            $options[] = HTMLHelper::_('select.option', $current, $current);
        }

        foreach (self::$list as $id => $name) {
            $options[] = HTMLHelper::_('select.option', $id, $id . ' - ' . $name);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
