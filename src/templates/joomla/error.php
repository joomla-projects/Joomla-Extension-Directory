<?php
/**
 * Joomla.org site template
 *
 * @copyright   Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Document\ErrorDocument $this */

// Set the base URL
$this->setBase(htmlspecialchars(Uri::current(), ENT_QUOTES, 'UTF-8'));

// Load the template helper
require_once __DIR__ . '/helpers/template.php';

$app   = Factory::getApplication();
$input = $app->getInput();
$wa    = $this->getWebAssetManager();

// Detecting Active Variables
$option   = $input->getCmd('option', '');
$view     = $input->getCmd('view', '');
$layout   = $input->getCmd('layout', 'default');
$task     = $input->getCmd('task', 'display');
$itemid   = $input->getUint('Itemid', 0);
$sitename = $app->get('sitename');

$templateBaseUrl = $this->baseurl . '/templates/' . $this->template;

// Load template stylesheet and javascript
$wa->useStyle('template.joomla.custom.' . ($this->direction === 'rtl' ? 'rtl' : 'ltr'))
    ->useScript('template.joomla');

// Override 'template.active' asset to set correct ltr/rtl dependency and with CDN Dependency
$wa->registerStyle('template.active', '', [], [], [((!JDEBUG && $this->params->get('useCdn', '1')) ? 'cdn.' : '') . 'template.joomla.' . ($this->direction === 'rtl' ? 'rtl' : 'ltr')]);

$fontName        = 'Open Sans';
$escapedFontName = str_replace(' ', '+', $fontName);

$this->getPreloadManager()->preconnect('https://fonts.googleapis.com/', ['crossorigin' => 'anonymous']);
$this->getPreloadManager()->preconnect('https://fonts.gstatic.com/', ['crossorigin' => 'anonymous']);
$paramsFontScheme = 'https://fonts.googleapis.com/css2?family=' . $escapedFontName . ':wght@300;400;700&display=swap';
$this->getPreloadManager()->preload($paramsFontScheme, ['as' => 'style', 'crossorigin' => 'anonymous']);
$wa->registerAndUseStyle('googlefonts', $paramsFontScheme, [], ['crossorigin' => 'anonymous']);

$this->setMetaData('viewport', 'width=device-width, initial-scale=1.0');
$this->addHeadLink($templateBaseUrl . '/favicon.ico', 'icon', 'rel', ['type' => 'image/vnd.microsoft.icon']);

// Set the replacement for the position-0 module loaded from the CDN'd menu
$search      = '<jdoc:include type="modules" name="position-0" style="none" />';
$replacement = '';

// The module renderer will not work properly due to incomplete Application initialisation
$renderModules = $app->getIdentity() && $app->getLanguage();

if ($renderModules) {
    foreach (ModuleHelper::getModules('position-0') as $module) {
        $replacement .= ModuleHelper::renderModule($module, ['style' => 'none']);
    }
}

// Get the GTM property ID
$gtmId = JoomlaTemplateHelper::getGtmId(Uri::getInstance()->toString(['host']));

// If Cookie Control is enabled, we expose the GTM ID as a JavaScript var versus registering GTM directly
$hasCookieControl = $this->params->get('cookieControlActive', 0);

if ($gtmId && $hasCookieControl) {
    // Purposefully declare a global variable versus using the Joomla.options JavaScript API for compatibility with non-Joomla (CMS) installations
    $wa->addInlineScript('var propertyGtmId = ' . json_encode($gtmId) . ';');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>
<body class="<?php echo "site error $option view-$view layout-$layout task-$task itemid-$itemid" . ($this->direction == 'rtl' ? ' rtl' : ''); ?>">
<?php
// Add Google Tag Manager code if one is set
if ($gtmId && !$hasCookieControl) : ?>
    <!-- Google Tag Manager -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo $gtmId; ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo $gtmId; ?>');</script>
    <!-- End Google Tag Manager -->
<?php endif; ?>
<!-- Top Nav -->
<nav class="navigation py-md-1" aria-label="<?php echo Text::_('TPL_JOOMLA_NAV_CROSS_SITE'); ?>">
    <div id="mega-menu" class="navbar navbar-expand-md">
        <div class="container-xxl">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryMenu" aria-controls="primaryMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <?php echo str_replace($search, $replacement, JoomlaTemplateHelper::getTemplateMenu($this->language, (bool) $this->params->get('useCdn', '1'))); ?>
        </div>
    </div>
</nav>
<!-- Header -->
<header class="header">
    <div class="container-md">
        <div class="row">
            <div class="col-md-7">
                <h1 class="page-title">
                    <a href="<?php echo Uri::root(); ?>"><span aria-hidden="true" class="icon-joomla me-2"></span><?php echo HTMLHelper::_('string.truncate', $sitename, 40, false, false); ?></a>
                </h1>
            </div>
            <div class="col-md-5">
                <div class="btn-toolbar pt-md-1 row">
                    <div class="btn-group col-6">
                        <a href="https://downloads.joomla.org/" class="btn btn-lg btn-warning"><?php echo Text::_('TPL_JOOMLA_DOWNLOAD_BUTTON'); ?></a>
                    </div>
                    <div class="btn-group col-6">
                        <a href="https://launch.joomla.org" class="btn btn-lg btn-primary"><?php echo Text::_('TPL_JOOMLA_DEMO_BUTTON'); ?><span aria-hidden="true" class="fa-solid fa-rocket ms-2"></span></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<?php if ($renderModules && $this->countModules('position-1')) : ?>
    <nav class="subnav-wrapper">
        <div class="subnav">
            <div class="container-md">
                <jdoc:include type="modules" name="position-1" style="none" />
            </div>
        </div>
    </nav>
<?php endif; ?>
<!-- Body -->
<div class="body">
    <div class="container">
        <div class="row">
            <main id="content" class="col-md-12">
                <!-- Begin Content -->
                <div class="marge">
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <img class="mw-100" src="<?php echo $templateBaseUrl; ?>/images/error.jpg" alt="<?php echo Text::_('TPL_JOOMLA_ERROR_LAYOUT_ERROR_HAS_OCCURRED'); ?>">
                        </div>
                        <div class="col-md-6">
                            <div class="errorborder">
                                <h2><?php echo Text::_('TPL_JOOMLA_ERROR_LAYOUT_ERROR_HAS_OCCURRED'); ?></h2>
                                <p><?php echo Text::_('TPL_JOOMLA_ERROR_LAYOUT_DONT_WORRY'); ?></p>
                            </div>
                            <?php if ($renderModules) : ?>
                                <?php $searchModule = ModuleHelper::getModule($this->params->get('searchModule', 'search')); ?>
                                <?php if (!empty($searchModule->id)) : ?>
                                    <h3><?php echo Text::_('TPL_JOOMLA_ERROR_LAYOUT_SEARCH'); ?></h3>
                                    <p><?php echo Text::_('TPL_JOOMLA_ERROR_LAYOUT_SEARCH_SITE'); ?></p>
                                    <?php echo ModuleHelper::renderModule($searchModule); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <p><?php echo Text::_('TPL_JOOMLA_ERROR_LAYOUT_START_AGAIN'); ?></p>
                            <p><a href="<?php echo Uri::root(); ?>" class="btn btn-primary btn-lg error"> <?php echo Text::_('JERROR_LAYOUT_HOME_PAGE'); ?></a></p>
                        </div>
                    </div>
                    <hr />
                    <p><?php echo Text::_('JERROR_LAYOUT_PLEASE_CONTACT_THE_SYSTEM_ADMINISTRATOR'); ?></p>
                    <blockquote>
                        <span class="badge bg-dark"><?php echo $this->error->getCode(); ?></span> <?php echo htmlspecialchars($this->error->getMessage(), ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($this->debug) : ?>
                            in <?php echo $this->error->getFile(); ?> on line <?php echo $this->error->getLine(); ?>
                        <?php endif; ?>
                    </blockquote>
                    <?php if ($this->debug) : ?>
                        <div>
                            <?php echo $this->renderBacktrace(); ?>
                            <?php // Check if there are more Exceptions and render their data as well ?>
                            <?php // The chain is walked with a local variable; setError() is still called for each one because renderBacktrace() reads the document's current error ?>
                            <?php $previous = $this->error->getPrevious(); ?>
                            <?php while ($previous !== null) : ?>
                                <?php $this->setError($previous); ?>
                                <p><strong><?php echo Text::_('JERROR_LAYOUT_PREVIOUS_ERROR'); ?></strong></p>
                                <blockquote>
                                    <span class="badge bg-dark"><?php echo $previous->getCode(); ?></span> <?php echo htmlspecialchars($previous->getMessage(), ENT_QUOTES, 'UTF-8'); ?> in <?php echo $previous->getFile(); ?> on line <?php echo $previous->getLine(); ?>
                                </blockquote>
                                <?php echo $this->renderBacktrace(); ?>
                                <?php $previous = $previous->getPrevious(); ?>
                            <?php endwhile; ?>
                            <?php // Reset the main error object to the base error ?>
                            <?php $this->setError($this->error); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- End Content -->
            </main>
        </div>
    </div>
</div>
<!-- Footer -->
<footer class="footer text-center">
    <div class="container">
        <hr />

        <?php echo JoomlaTemplateHelper::getTemplateFooter($this->language, (bool) $this->params->get('useCdn', '1')); ?>
    </div>
</footer>
</body>
</html>
