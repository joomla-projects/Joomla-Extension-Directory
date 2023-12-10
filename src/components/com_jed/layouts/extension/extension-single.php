<?php

/**
 * @package JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Jed\Component\Jed\Site\View\Extension\HtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;

/**
 * @var array        $displayData
 */

?>

<div class="jed-cards-wrapper margin-bottom-half">
    <h2 class="heading heading--m">Other extensions by PWT Extensions (3)</h2>
    <div class="jed-container">
        <ul class="jed-grid jed-grid--1-1-1">
            <?php for ($i = 0; $i < 3; $i++) : ?>
                <?php echo LayoutHelper::render(
                    'cards.extension',
                    [
                                    'image'         => '',
                                    'title'         => 'Dummy Extension',
                                    'developer'     => 'Dummy Company',
                                    'rating'        => 5,
                                    'reviews'       => 1061,
                                    'compatibility' => ['3', '4 alpha'],
                                    'description'   => 'Dummy Text',
                                    'type'          => 'Free',
                                    'link'          => '#'
                                    ]
                ) ?>
            <?php endfor; ?>
        </ul>
    </div>
</div>

<div class="jed-cards-wrapper margin-bottom-half">
    <div class="jed-container">
        <h2 class="heading heading--m">You might also be interested in</h2>
        <ul class="jed-grid jed-grid--1-1-1">
            <?php for ($i = 0; $i < 3; $i++) : ?>
                <?php echo LayoutHelper::render(
                    'cards.extension',
                    'cards.extension',
                    [
                        'image'         => '',
                        'title'         => 'Dummy Extension',
                        'developer'     => 'Dummy Company',
                        'rating'        => 5,
                        'reviews'       => 1061,
                        'compatibility' => ['3', '4 alpha'],
                        'description'   => 'Dummy Text',
                        'type'          => 'Free',
                        'link'          => '#'
                    ]
                ) ?>
            <?php endfor; ?>
        </ul>
    </div>
</div>
