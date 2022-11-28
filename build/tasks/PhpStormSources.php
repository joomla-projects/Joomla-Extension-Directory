<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

class PhpStormSources extends Task
{
	/**
	 * The path to the repository root
	 *
	 * @var   string|null
	 */
	private $repository = null;

	/**
	 * Set the repository root folder
	 *
	 * @param   string  $repository  The new repository root folder
	 *
	 * @return  void
	 */
	public function setRepository(string $repository)
	{
		$this->repository = $repository;
	}

	/**
	 * @inheritDoc
	 */
	public function main()
	{
		$this->log("Processing PhpStorm sources for " . $this->repository, Project::MSG_INFO);

		if (!class_exists(PhpStormSourceHandling::class))
		{
			defined('AKEEBA_PHPSTORMSOURCES_INCLUDE_ONLY') || define('AKEEBA_PHPSTORMSOURCES_INCLUDE_ONLY', 1);

			require_once __DIR__ . '/../lib/phpStormSourceHandling.php';
		}

		$o = new PhpStormSourceHandling();

		$o->execute($this->repository, true);

		return true;
	}
}