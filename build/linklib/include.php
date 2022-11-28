<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary;

use Composer\Autoload\ClassLoader;

if (version_compare(PHP_VERSION, '7.0', 'lt'))
{
	echo <<< END

********************************************************************************
**                                   WARNING                                  **
********************************************************************************

The link library REQUIRES PHP 7.0 or later.

END;

	throw new \RuntimeException("Wrong PHP version");
}

$autoloaderFile = __DIR__ . '/../../component/backend/vendor/autoload.php';

if (!file_exists($autoloaderFile))
{
	echo <<< END

********************************************************************************
**                                   WARNING                                  **
********************************************************************************

You have NOT initialized Composer on repository.

--------------------------------------------------------------------------------
HOW TO FIX
--------------------------------------------------------------------------------

Go to the repository and run:

  composer install


END;

	throw new \RuntimeException("Composer is not initialized repository");
}

// Get a reference to Composer's autloader
/** @var ClassLoader $composerAutoloader */
if (class_exists('Composer\\Autoload\\ClassLoader'))
{
	$composerAutoloader = new \Composer\Autoload\ClassLoader();
	$composerAutoloader->register();
}
else
{
	$composerAutoloader = require($autoloaderFile);
}

// Register this directory as the PSR-4 source for our namespace prefix
$composerAutoloader->addPsr4('Akeeba\\LinkLibrary\\', __DIR__);
