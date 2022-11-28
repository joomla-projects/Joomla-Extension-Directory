<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

//require_once 'phing/Task.php';
require_once __DIR__ . '/../linklib/include.php';

/**
 * Class RelinkSiteTask
 *
 * Relinks the extensions contained in the repository to the defined Joomla! site.
 *
 * Example:
 *
 * <relink site="/Path/To/Your/Site" repository="/path/to/repository" />
 */
class RelinkSiteTask extends Task
{
	/**
	 * The path to the repository containing all the extensions
	 *
	 * @var   string
	 */
	private $repository = null;

	/**
	 * The path to the site's root.
	 *
	 * @var    string
	 */
	private $site = null;

	/**
	 * Set the site root folder
	 *
	 * @param   string  $siteRoot  The new site root
	 *
	 * @return  void
	 */
	public function setSite($siteRoot)
	{
		$this->site = $siteRoot;
	}

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
	 * Main entry point for task.
	 *
	 * @return    bool
	 */
	public function main()
	{
		$this->log("Processing links for " . $this->site, Project::MSG_INFO);

		if (empty($this->repository))
		{
			$this->repository = realpath($this->project->getBasedir() . '/../..');
		}

		if (in_array(substr($this->site, 0, 2), ['~/', '~' . DIRECTORY_SEPARATOR]))
		{
			$home = $this->getUserHomeDirectory();

			if (is_null($home))
			{
				throw new BuildException("Site root folder {$this->site} cannot be resolved: your environment does not return information on the user's Home folder location.");
			}

			$this->site = $home . DIRECTORY_SEPARATOR . substr($this->site, 2);
		}

		if (!is_dir($this->site))
		{
			throw new BuildException("Site root folder {$this->site} is not a valid directory");
		}

		if (!is_dir($this->repository))
		{
			throw new BuildException("Repository folder {$this->repository} is not a valid directory");
		}

		@error_reporting(E_ALL);
		try
		{
			$relink = new \Akeeba\LinkLibrary\Relink($this->repository);
			$relink->setVerbose(true);
			$relink->relink($this->site);
		}
		catch (Throwable $e)
		{
			echo $e->getMessage();

			die;
		}

		return true;
	}

	/**
	 * Returns the currently logged in OS user's home directory absolute path
	 *
	 * @return  string|null  Home directory absolute path. NULL if it cannot be determined.
	 */
	function getUserHomeDirectory(): ?string
	{
		// Try the UNIX method first. If it fails it will return either false or null. Normalize it to NULL.
		$home = @getenv('HOME');
		$home = ($home === false) ? null : $home;

		// Fallback to Windows method for determining the home
		if (is_null($home) && !empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH']))
		{
			$home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
		}

		// Early exit if everything failed
		if (is_null($home))
		{
			return $home;
		}

		// Remove the trailing slash / backslash
		return rtrim($home, '/' . DIRECTORY_SEPARATOR);
	}
}
