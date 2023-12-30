<?php

/**
 * @package    Jed\Component\Jed\Administrator\Traits
 * @subpackage
 *
 * @copyright A copyright
 * @license   A "Slug" license name e.g. GPL2
 */

namespace Jed\Component\Jed\Administrator\Traits;

use Exception;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Service\Category;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Database\ParameterType;
use Michelf\Markdown;
use SimpleXMLElement;

/**
 * Utilities for working with extensions and extension categories
 *
 * @since 4.0.0
 */
trait ExtensionUtilities
{
    /**
     * Gets first paragraph of description as intro text
     *
     * @param string $d
     *
     * @return array
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function splitDescription(string $d): array
    {
        // Remove images
        $d = preg_replace("/\!\[(.*)\]\((.*)\)/", '', $d);
        // Remove links
        $d = preg_replace("/\[(.*)\]\((.*)\)/", '', $d);
        $d = Markdown::defaultTransform($d);

        $clean = (stripslashes(trim($d)));
        $xml   = new SimpleXMLElement('<div>' . $clean . '</div>');
        $ps    = $xml->xpath('//p');

        if (count($ps) > 0) {
            $ret['intro'] = htmlspecialchars_decode($ps[0]->asXml());


            if (count($ps) === 1) {
                // No more text (but might contain non-paragraphed text see JDEV-628
                $ret['body'] = str_replace($d, '', $d);
            } else {
                // Remove first paragraph from the text
                $dom = dom_import_simplexml($ps[0]);
                $dom->parentNode->removeChild($dom);
                $ret['body'] = htmlspecialchars_decode(str_replace('<?xml version="1.0"?>', '', $xml->asXml()));
            }
        } else {
            $seperator = stristr($d, '<br>') ? '<br>' : '<br />';
            $bits      = explode($seperator, $d);

            $o            = array_shift($bits);
            $ret['intro'] = $o;
            $ret['body']  = implode('<br />', $bits);
        }

        return $ret;
    }

    /**
     * Gets current extension category and hierarchy of parents as string
     *
     * @param int $category_id
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function getCategoryHierarchy(int $category_id): string
    {
        return LayoutHelper::render(
            'category.hierarchy',
            [
            'categories' => $this->getCategoryHierarchyStack($category_id),
            ]
        );
    }

    /**
     * Get a stack of Category tables with the hierarchy leading to the target category (ordered root towards leaf node)
     *
     * @param int $catId The category ID to search for
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getCategoryHierarchyStack(int $catId): array
    {
        $stack      = [];
        $catService = new Category();
        $rootNode   = $catService->get('root');
        $cat        = $catService->get($catId);

        do {
            if ($cat === null) {
                return $stack;
            }

            array_unshift($stack, $cat);

            $cat = $cat->getParent();
        } while ($cat !== null && $cat->id != $rootNode->id);

        return $stack;
    }

    /**
     * Get Developer Name from jed_developers table
     *
     * @since 4.0.0
     */
    public function getDeveloperName(int $uid): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.developer_name')
            ->from($db->quoteName('#__jed_developers', 'a'))
            ->where('a.user_id = :uid')
            ->bind(':uid', $uid, ParameterType::INTEGER);

        return $db->setQuery($query)->loadResult();
    }
}
