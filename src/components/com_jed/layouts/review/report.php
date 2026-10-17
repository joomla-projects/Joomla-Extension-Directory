<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
*
 *
 * @var array $displayData
*/
?>
<hr>

<h2>Do you want to report this extension?</h2>
<p>If you need to report a listing, you must use the <b>Report Extension</b> button on the listing, and not this review page.</p>
<h2>Are you having problems with this extension?</h2>
<?php $supportUrl = (string) ($displayData->support_url ?? ''); ?>
<p>If you're having problems with the extension, please contact the developer<?php if ($supportUrl !== '') : ?> through their website (<a href="<?php echo htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8'); ?></a>)<?php endif; ?>.</p>
<p><br></p>
<div class="alert alert-info">
    Please remember, the Joomla! Extensions Directory is not responsible for any extensions listed, nor does the JED Team provide support for extensions. All reviews submissions are subject to the <a href=/community/terms-of-service>Terms of Service</a></div>
