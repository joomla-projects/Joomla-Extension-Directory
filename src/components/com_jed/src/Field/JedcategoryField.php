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
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
use stdClass;

/**
 * Builds sorted list of JED Categories
 *
 * @since 4.0.0
 */
class JedCategoryField extends ListField
{
    /**
     * The  field type.
     *
     * @var   string
     * @since 4.0.0
     */
    protected $type = 'jedcategory';

    /**
     * The sublayout to use when rendering the results.
     *
     * @var   string
     * @since 2.5
     */
    protected $layout = 'joomla.form.field.list-fancy-select';
    /**
     * category data
     *
     * @var  array
     *
     * @since 4.0.0
     */
    protected array $cats;

    /**
     * getInput
     *
     * @since 4.0.0
     * @throws Exception
     */
    protected function getInput($options = []): string
    {
        $rows = $this->getCategories();
        $data = $this->getLayoutData();

        $opts = [];

        foreach ($rows as $row) {
            if ($row->level == 1) {
                $row->text = ' - ' . $row->text;
            } else {
                $row->text = ' - - ' . $row->text;
            }

            $opt       = HTMLHelper::_('select.option', $row->value, $row->text, 'value', 'text');
            $opt->attr = ' data-level="' . $row->level . '"';
            $opts[]    = $opt;
        }



        $a                                 = '';
        $data['name']                      = $this->name;
        $data['select2']                   = new stdClass();
        $data['select2']->dropdownCssClass = 'category-options';
        $data['select2']->allowClear       = true;
        $data['options']                   = $opts;
        $data['value']                     = $this->value;
        $data['attribs']                   = $a;



        return $this->getRenderer($this->layout)->render($data);
    }

    /**
     * Method to get the field options for category
     * Use the extension attribute in a form to specify the.specific extension for
     * which categories should be displayed.
     * Use the show_root attribute to specify whether to show the global category root in the list.
     *
     * @return  object[]    The field option objects.
     *
     * @since   1.6
     */
    protected function getOptions(): array
    {
        return parent::getOptions();
    }

    /**
     * Get caegories
     *
     * @return array
     * @since 4.0.0
     * @throws Exception
     */
    private function getCategories(): array
    {
        if (isset($this->cats)) {
            return $this->cats;
        }


        $app    = Factory::getApplication();
        $user   = $app->getIdentity();
        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $option = $app->input->get('option');
        $query  = $db->getQuery(true);
        $query->select('a.id AS value, a.title AS text, a.level')->from('#__categories AS a')->leftJoin('#__categories AS b ON a.lft > b.lft AND a.rgt < b.rgt')->where('a.published = 1 AND a.level >= 1 AND a.extension = ' . $db->q($option))->group('a.id, a.title, a.level, a.lft, a.rgt, a.extension, a.parent_id')->order(' a.lft ASC ')->where('a.access IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')');
        $db->setQuery($query);
        $this->cats = $db->loadObjectList();

        $ordered  = [];
        $topTitle = '';

        foreach ($this->cats as $row) {
            if ($row->level == 1) {
                $topTitle                    = $row->text;
                $ordered[$row->text]         = $row;
                $ordered[$row->text]->level2 = [];
            } else {
                $ordered[$topTitle]->level2[$row->text] = $row;
                ksort($ordered[$topTitle]->level2);
            }
        }

        ksort($ordered);
        $this->cats = [];

        foreach ($ordered as $level1) {
            $level2s = $level1->level2;
            unset($level1->level2);
            $this->cats[] = $level1;

            foreach ($level2s as $level2) {
                $this->cats[] = $level2;
            }
        }

        return $this->cats;
    }
}
