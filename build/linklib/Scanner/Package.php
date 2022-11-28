<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary\Scanner;

use Akeeba\LinkLibrary\MapResult;
use Akeeba\LinkLibrary\ScannerInterface;
use Akeeba\LinkLibrary\ScanResult;
use RuntimeException;

/**
 * Scanner class for Joomla! packages
 */
class Package extends AbstractScanner
{
	private $forcedXmlFile = '';

	/**
	 * Constructor.
	 *
	 * The languageRoot is optional and applies only if the languages are stored in a directory other than the one
	 * specified in the extension's XML file.
	 *
	 * @param   string       $extensionRoot  The absolute path to the extension's root folder
	 * @param   null         $languageRoot   The absolute path to the extension's language folder (optional)
	 * @param   string|null  $forcedXmlFile  Force an XML file name for the package
	 */
	public function __construct($extensionRoot, $languageRoot = null, ?string $forcedXmlFile = null)
	{
		$this->manifestExtensionType = 'package';

		if (!empty($forcedXmlFile))
		{
			$this->forcedXmlFile = $forcedXmlFile;
		}

		parent::__construct($extensionRoot, $languageRoot);
	}

	/**
	 * Detect extensions of type Package in the repository and return an array of ScannerInterface objects for them.
	 *
	 * @param   string  $repositoryRoot  The repository root to scan
	 *
	 * @return  ScannerInterface[]
	 */
	public static function detect(string $repositoryRoot): array
	{
		$possiblePaths = [
			$repositoryRoot                      => $repositoryRoot . '/build/templates/language',
			$repositoryRoot . '/build/templates' => null,
		];

		$extensions = [];

		foreach ($possiblePaths as $path => $languageRoot)
		{
			if (!is_dir($path))
			{
				continue;
			}

			// Loop all packages in the section
			$di = new \DirectoryIterator($path);

			/** @var \DirectoryIterator $file */
			foreach ($di as $file)
			{
				if ($file->isDir() || $file->getExtension() !== 'xml')
				{
					continue;
				}

				if (substr($file->getBasename('.' . $file->getExtension()), -5) === '_core')
				{
					continue;
				}

				// Is this the right kind of XML file?
				$xmlDoc = new \DOMDocument();
				$xmlDoc->load($file->getPathname(), LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

				$rootNodes = $xmlDoc->getElementsByTagname('extension');

				if ($rootNodes->length < 1)
				{
					unset($xmlDoc);
					continue;
				}

				$root = $rootNodes->item(0);

				if (!$root->hasAttributes())
				{
					unset($xmlDoc);
					continue;
				}

				if ($root->getAttribute('type') != 'package')
				{
					unset($xmlDoc);
					continue;
				}

				unset($xmlDoc);

				$basePath      = $file->getPath();
				$forcedXmlFile = $file->getBasename();

				// Get the extension ScannerInterface object
				$extensions[] = new Package($basePath, null, $forcedXmlFile);

				return $extensions;
			}
		}

		return $extensions;
	}

	/**
	 * Scans the extension for files and folders to link
	 *
	 * @return  ScanResult
	 */
	public function scan(): ScanResult
	{
		$xmlDoc = $this->getXMLManifest($this->forcedXmlFile);

		if (empty($xmlDoc))
		{
			throw new RuntimeException("Cannot get XML manifest for package in {$this->extensionRoot}");
		}

		// Initialize the result
		$result                = new ScanResult();
		$result->extensionType = 'package';

		// Get the extension name
		$packageName = strtolower($xmlDoc->getElementsByTagName('name')->item(0)->nodeValue);

		if (empty($packageName))
		{
			throw new RuntimeException("Cannot find the package name in the XML manifest for {$this->extensionRoot}");
		}

		// Some old packages had an invalid <name> tag. Let's figure it out based on their filename instead.
		if (substr($packageName, 0, 4) !== 'pkg_')
		{
			$packageName = basename($this->xmlManifestPath, '.xml');

			if (substr($packageName, -5) === '_core')
			{
				$packageName = substr($packageName, 0, -5);
			}
			elseif (substr($packageName, -4) === '_pro')
			{
				$packageName = substr($packageName, 0, -4);
			}
		}

		if (substr($packageName, 0, 4) !== 'pkg_')
		{
			throw new RuntimeException("Invalid package name “{$packageName}” in the XML manifest for {$this->extensionRoot}");
		}

		$result->extension = substr($packageName, 4);

		// Get ready to query the manifest
		$xpath = new \DOMXPath($xmlDoc);

		// Get the script filename
		$scriptNodes = $xpath->query('/extension/scriptfile');

		/** @var \DOMNode $node */
		foreach ($scriptNodes as $node)
		{
			$result->scriptFileName = $node->textContent;
		}

		// Get language files from the <languages> tag
		$result->adminLangPath  = null;
		$result->adminLangFiles = [];
		$backEndLanguageNodes   = $xpath->query('/extension/languages');

		foreach ($backEndLanguageNodes as $node)
		{
			[$languageRoot, $languageFiles] = $this->scanLanguageNode($node);

			if (!empty($languageFiles))
			{
				$result->adminLangFiles = $languageFiles;
				$result->adminLangPath  = $languageRoot;
			}
		}

		// Scan language files in a separate root, if one is specified
		if (!empty($this->languageRoot))
		{
			$langPath  = $this->languageRoot . '/packages/' . $packageName;
			$langFiles = $this->scanLanguageFolder($langPath);

			if (!empty($langFiles))
			{
				$result->adminLangPath  = $langPath;
				$result->adminLangFiles = $langFiles;
			}
		}

		return $result;
	}

	/**
	 * Parses the last scan and generates a link map
	 *
	 * @return  MapResult
	 */
	public function map(): MapResult
	{
		$scan   = $this->getScanResults();
		$result = parent::map();

		// Map XML manifest
		if (!empty($this->xmlManifestPath))
		{
			$result->files = array_merge($result->files, [
				$this->xmlManifestPath => $this->siteRoot . '/administrator/manifests/packages/' . $scan->getJoomlaExtensionName() . '.xml',
			]);
		}

		// Map script file
		if ($scriptPath = $this->getScriptAbsolutePath($scan))
		{
			$result->files = array_merge($result->files, [
				$scriptPath => $this->siteRoot . '/administrator/manifests/packages/' . $scan->getJoomlaExtensionName() . '/' . basename($scriptPath),
			]);
		}

		return $result;
	}
}