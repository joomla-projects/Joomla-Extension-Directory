<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\MediaHandling;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;

/**
 * The set of stored variants of an extension image.
 *
 * Every case other than ORIGINAL is a file that ImagePipeline writes at upload time, named
 * after the original with the case's suffix inserted before the extension - `logo.png` becomes
 * `logo-small.png`. Generating them on upload rather than on request is deliberate: on-request
 * resizing is a denial-of-service surface on a public site (P1-10 item 4).
 *
 * The dimensions come from the component configuration, not from this file, because the design
 * will change and a re-render should not be a code change (P1-10 item 2). The defaults are the
 * measured values from `P0-06` section 6:
 *
 *  - SMALL is the card media slot, 360 x 188, ratio 1.91:1 - which is also the OpenGraph ratio.
 *  - LARGE is its 2x variant, 720 x 376, used for the detail page and for high-density screens.
 *
 * Gallery and detail-page screenshot variants are deliberately absent: `P0-06` section 6 records
 * that the design file does not specify them, and inventing dimensions here would be guessing.
 * They follow the detail-page design.
 *
 * @since 4.0.0
 */
enum ImageSize: string
{
    /**
     * The uploaded image itself, normalised but not scaled down.
     *
     * @since 4.0.0
     */
    case ORIGINAL = 'original';

    /**
     * Card media. Used by every list, card and thumbnail.
     *
     * @since 4.0.0
     */
    case SMALL = 'small';

    /**
     * Twice the card media. Used by the detail page and for 2x displays.
     *
     * @since 4.0.0
     */
    case LARGE = 'large';

    /**
     * Default dimensions per case, used when the component configuration says nothing.
     *
     * @since 4.0.0
     */
    private const DEFAULTS = [
        'small' => [360, 188],
        'large' => [720, 376],
    ];

    /**
     * The filename marker that distinguishes this variant from the original.
     *
     * @return string  Empty for ORIGINAL, which keeps the plain filename.
     *
     * @since 4.0.0
     */
    public function suffix(): string
    {
        return $this === self::ORIGINAL ? '' : '-' . $this->value;
    }

    /**
     * The cases that produce a file of their own.
     *
     * @return ImageSize[]
     *
     * @since 4.0.0
     */
    public static function variants(): array
    {
        return [self::SMALL, self::LARGE];
    }

    /**
     * The bounding box this variant is scaled into.
     *
     * Scaling is "inside" the box: the aspect ratio is kept and nothing is cropped, so the
     * stored file is at most this size rather than exactly it. `P0-06` asks for FIT rather
     * than FILL behaviour, and for padding instead of distortion - the padding half of that
     * is done in CSS with `object-fit: contain` on a neutral tile, so that a logo with a
     * transparent background does not get a background colour baked into the file.
     *
     * @return array{0: int|null, 1: int|null}  Width and height, both null for ORIGINAL.
     *
     * @since 4.0.0
     */
    public function getMaximumDimensions(): array
    {
        if ($this === self::ORIGINAL) {
            return [null, null];
        }

        $params  = ComponentHelper::getParams('com_jed');
        $default = self::DEFAULTS[$this->value];

        $width  = (int) $params->get('image_' . $this->value . '_width', $default[0]);
        $height = (int) $params->get('image_' . $this->value . '_height', $default[1]);

        return [
            $width > 0 ? $width : $default[0],
            $height > 0 ? $height : $default[1],
        ];
    }

    /**
     * Insert this variant's suffix into a filename.
     *
     * @param string $filename A filename or path, e.g. "images/jed_extensions/7/images/logo.png".
     *
     * @return string  The variant's filename, e.g. ".../logo-small.png".
     *
     * @since 4.0.0
     */
    public function applyTo(string $filename): string
    {
        if ($this === self::ORIGINAL || $filename === '') {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $filename . $this->suffix();
        }

        return substr($filename, 0, -(\strlen($extension) + 1)) . $this->suffix() . '.' . $extension;
    }
}
