<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary;

interface ScannerInterface
{
	/**
	 * ScannerInterface constructor.
	 *
	 * The languageRoot is optional and applies only if the languages are stored in a directory other than the one
	 * specified in the extension's XML file.
	 *
	 * @param   string  $extensionRoot  The absolute path to the extension's root folder
	 * @param   string  $languageRoot   The absolute path to the extension's language folder (optional)
	 */
	public function __construct($extensionRoot, $languageRoot = null);

	/**
	 * Set the Joomla! site root path
	 *
	 * @param   string  $path
	 *
	 * @return  void
	 */
	public function setSiteRoot(string $path);

	/**
	 * Get the currently configured Joomla! site root path
	 *
	 * @return  string
	 */
	public function getSiteRoot(): string;

	/**
	 * Scans the extension for files and folders to link
	 *
	 * @return  ScanResult
	 */
	public function scan();

	/**
	 * Retrieves the scan results
	 *
	 * @return  ScanResult
	 */
	public function getScanResults(): ScanResult;

	/**
	 * Parses the last scan and generates a link map
	 *
	 * @return  MapResult
	 */
	public function map();

	/**
	 * Returns the link map. If the link map does not exist it will be created first.
	 *
	 * @return  MapResult
	 */
	public function getLinkMap(): MapResult;

	/**
	 * Removes the link map targets. If the link map does not exist it will be created first.
	 *
	 * IMPORTANT: This removes the map targets no matter if they are links or real folders / files.
	 *
	 * @return  void
	 */
	public function unlink();

	/**
	 * Links the map targets. If the link map does not exist it will be created first.
	 *
	 * @return  void
	 */
	public function relink();

	/**
	 * Get a unique extension name. For modules and templates this includes the indicator site_ or admin_ before the
	 * actual name of the extension.
	 *
	 * @return  string
	 */
	public function getKeyName();

	/**
	 * Set the verbose output flag.
	 *
	 * @param   bool  $value  The flag value to set.
	 *
	 * @return  void
	 */
	public function setVerbose(bool $value): void;

	/**
	 * Set the Dry Run flag.
	 *
	 * @param   bool  $value  The flag value to set.
	 *
	 * @return  void
	 */
	public function setDryRun(bool $value): void;
}
