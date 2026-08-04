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
use Joomla\CMS\Image\Image;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use RuntimeException;

/**
 * Takes an uploaded file and turns it into the stored image set for a listing.
 *
 * Everything a developer uploads passes through here. The rules, in order (P1-10 item 3):
 *
 *  1. The type is read from the file's own bytes, never from its name or from the browser's
 *     Content-Type. A `.png` that is really a PHP script is rejected here.
 *  2. Byte size and pixel dimensions are capped. The pixel cap is not cosmetic: a small file
 *     can decode into gigabytes of memory, so it is checked before the image is loaded.
 *  3. The image is re-encoded rather than copied. That is the normalisation which strips EXIF
 *     - third-party uploads carry GPS coordinates and camera serials - and it also destroys
 *     any payload smuggled inside an otherwise valid image container.
 *  4. Every variant in ImageSize::variants() is written next to the original.
 *
 * A rejected upload throws; the caller decides whether that fails the save or just skips one
 * row of a gallery.
 *
 * @since 4.1.0
 */
final class ImagePipeline
{
    /**
     * Image types accepted for upload, mapped to the extension they are stored with.
     *
     * WebP and AVIF are readable by GD but are not accepted for upload: the JED3 stock is
     * PNG/JPEG/GIF only, and a narrower allowlist is a smaller attack surface.
     *
     * @since 4.1.0
     */
    private const ACCEPTED = [
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_GIF  => 'gif',
    ];

    /**
     * Fallback limits, used when the component configuration says nothing.
     *
     * @since 4.1.0
     */
    private const DEFAULT_MAX_KILOBYTES = 2048;
    private const DEFAULT_MAX_PIXELS    = 4000;

    /**
     * Validate an upload, store it under $directory and write every variant.
     *
     * @param array  $upload    Array with name/tmp_name/size, as extractUploadedFile() returns.
     * @param string $directory Absolute path of the target directory.
     * @param string $basename  Filename stem to store under, without an extension.
     *
     * @return string  The stored original's basename, e.g. "1754300000-logo.png".
     *
     * @throws RuntimeException  When the upload is not an acceptable image, or cannot be written.
     *
     * @since 4.1.0
     */
    public function store(array $upload, string $directory, string $basename): string
    {
        $type = $this->validate($upload);

        if (!Folder::exists($directory) && !Folder::create($directory)) {
            throw new RuntimeException(Text::_('COM_JED_IMAGE_ERROR_DIRECTORY'));
        }

        $filename = $basename . '.' . self::ACCEPTED[$type];
        $target   = Path::clean($directory . '/' . $filename);

        // Re-encode instead of moving the uploaded file into place. This is the step that
        // makes the stored file a known-good image rather than whatever was sent.
        $image = new Image($upload['tmp_name']);
        $this->write($image, $target, $type);
        $image->destroy();

        foreach (ImageSize::variants() as $variant) {
            $this->writeVariant($target, $variant, $type);
        }

        return $filename;
    }

    /**
     * Delete a stored image and every variant of it.
     *
     * Missing files are not an error: a listing imported from JED3 has no local file at all,
     * and a half-written upload may have produced the original but not its variants.
     *
     * @param string $rootRelativePath Path below JPATH_ROOT, as stored in the database.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function delete(string $rootRelativePath): void
    {
        if ($rootRelativePath === '' || !str_contains($rootRelativePath, '/')) {
            // A bare filename is a JED3 reference to a file this installation does not own.
            return;
        }

        foreach ([ImageSize::ORIGINAL, ...ImageSize::variants()] as $size) {
            $absolute = $this->resolveInsideRoot($size->applyTo($rootRelativePath));

            if ($absolute !== null && is_file($absolute)) {
                @unlink($absolute);
            }
        }
    }

    /**
     * Check an upload against the type, size and dimension rules.
     *
     * This checks the file's content only. That the path really is a file PHP received over
     * HTTP is the caller's guarantee, made in extractUploadedFile() where $_FILES is read -
     * keeping it there is what lets this class be exercised without a request.
     *
     * @param array $upload Array with name/tmp_name/size.
     *
     * @return int  The detected IMAGETYPE_* constant.
     *
     * @throws RuntimeException  When any rule rejects the upload.
     *
     * @since 4.1.0
     */
    public function validate(array $upload): int
    {
        $tmpName = (string) ($upload['tmp_name'] ?? '');

        if ($tmpName === '' || !is_file($tmpName)) {
            throw new RuntimeException(Text::_('COM_JED_IMAGE_ERROR_NO_UPLOAD'));
        }

        $params        = ComponentHelper::getParams('com_jed');
        $maxKilobytes  = (int) $params->get('image_max_kilobytes', self::DEFAULT_MAX_KILOBYTES);
        $maxPixels     = (int) $params->get('image_max_pixels', self::DEFAULT_MAX_PIXELS);
        $maxKilobytes  = $maxKilobytes > 0 ? $maxKilobytes : self::DEFAULT_MAX_KILOBYTES;
        $maxPixels     = $maxPixels > 0 ? $maxPixels : self::DEFAULT_MAX_PIXELS;

        if ((int) ($upload['size'] ?? 0) > $maxKilobytes * 1024) {
            throw new RuntimeException(
                Text::sprintf('COM_JED_IMAGE_ERROR_TOO_LARGE', $this->label($upload), $maxKilobytes)
            );
        }

        // getimagesize() parses the header, so it answers "is this really an image" from the
        // content. It returns false for anything it cannot decode, including a script.
        $properties = @getimagesize($tmpName);

        if ($properties === false || !isset($properties[2], self::ACCEPTED[$properties[2]])) {
            throw new RuntimeException(
                Text::sprintf('COM_JED_IMAGE_ERROR_NOT_AN_IMAGE', $this->label($upload))
            );
        }

        // Checked before the image is decoded - the point is to refuse the allocation, not to
        // survive it.
        if ($properties[0] > $maxPixels || $properties[1] > $maxPixels) {
            throw new RuntimeException(
                Text::sprintf('COM_JED_IMAGE_ERROR_TOO_MANY_PIXELS', $this->label($upload), $maxPixels)
            );
        }

        return $properties[2];
    }

    /**
     * Scale the stored original into one variant's bounding box and write it.
     *
     * A variant is only written when it would actually be smaller. Upscaling a 40 x 40 logo to
     * 720 x 376 produces a blurry file that is larger than the original for no benefit; when it
     * is skipped, JedHelper::formatImage() falls back to the original.
     *
     * @param string    $originalPath Absolute path of the stored original.
     * @param ImageSize $variant      The variant to write.
     * @param int       $type         IMAGETYPE_* of the stored original.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function writeVariant(string $originalPath, ImageSize $variant, int $type): void
    {
        [$width, $height] = $variant->getMaximumDimensions();

        $image = new Image($originalPath);

        if ($image->getWidth() <= $width && $image->getHeight() <= $height) {
            $image->destroy();

            return;
        }

        $resized = $image->resize($width, $height, true, Image::SCALE_INSIDE);
        $this->write($resized, $variant->applyTo($originalPath), $type);

        $resized->destroy();
        $image->destroy();
    }

    /**
     * Write an image handle to disk with sensible encoder settings.
     *
     * Image::toFile() defaults PNG quality to 0, which means "no compression" and produces
     * files several times larger than they need to be, so the level is set explicitly.
     *
     * @param Image  $image The loaded image.
     * @param string $path  Absolute target path.
     * @param int    $type  IMAGETYPE_* to encode as.
     *
     * @return void
     *
     * @throws RuntimeException  When the file cannot be written.
     *
     * @since 4.1.0
     */
    private function write(Image $image, string $path, int $type): void
    {
        $options = match ($type) {
            IMAGETYPE_PNG  => ['quality' => 6],
            IMAGETYPE_JPEG => ['quality' => 85],
            default        => [],
        };

        if ($type === IMAGETYPE_PNG) {
            // imagecreatefrompng() does not set these, and Image::toFile() does not set them
            // either, so a re-encoded PNG comes out with its transparency flattened to black.
            // Most extension logos are transparent PNGs, so this matters on nearly every logo.
            imagealphablending($image->getHandle(), false);
            imagesavealpha($image->getHandle(), true);
        }

        if (!$image->toFile($path, $type, $options)) {
            throw new RuntimeException(Text::_('COM_JED_IMAGE_ERROR_WRITE'));
        }
    }

    /**
     * Resolve a root-relative path to an absolute one, refusing anything that escapes the site.
     *
     * @param string $rootRelativePath Path below JPATH_ROOT.
     *
     * @return string|null  The absolute path, or null when it points outside JPATH_ROOT.
     *
     * @since 4.1.0
     */
    private function resolveInsideRoot(string $rootRelativePath): ?string
    {
        try {
            $absolute = Path::clean(JPATH_ROOT . '/' . ltrim($rootRelativePath, '/\\'));
        } catch (\Throwable) {
            return null;
        }

        return str_starts_with($absolute, Path::clean(JPATH_ROOT)) ? $absolute : null;
    }

    /**
     * The uploaded file's name, for an error message the developer can act on.
     *
     * @param array $upload Array with name/tmp_name/size.
     *
     * @return string
     *
     * @since 4.1.0
     */
    private function label(array $upload): string
    {
        return htmlspecialchars((string) ($upload['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
