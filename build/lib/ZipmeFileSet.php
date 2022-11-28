<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

class ZipmeFileSet extends FileSet
{
	/**
	 * The files to include in the archive
	 *
	 * @var   array
	 */
	private $files = null;

	/**
	 * Constructor
	 *
	 * @param   FileSet  $fileset
	 */
	public function __construct(FileSet $fileset = null)
	{
		parent::__construct($fileset);

		/**
		 * Expand/dereference symbolic links in order to include nested symlinks inside the ZIP archive. This is also
		 * required to prevent archiving unwanted files. Otherwise symlinked files are detected as directories and our
		 * checks within main() fails!
		*/
		$this->setExpandSymbolicLinks(true);
	}

	/**
	 * Get a list of files and directories specified in the fileset.
	 *
	 * @param   Project  $p             A reference to the Phing project
	 * @param   bool     $includeEmpty  Should I include empty directories?
	 *
	 * @return  array  A list of file and directory names, relative to the baseDir for the project.
	 */
	public function getFiles(Project $p, $includeEmpty = true)
	{
		if ($this->files === null)
		{

			$ds          = $this->getDirectoryScanner($p);
			$this->files = $ds->getIncludedFiles();

			if ($includeEmpty)
			{
				// first any empty directories that will not be implicitly added by any of the files
				$implicitDirs = array();

				foreach ($this->files as $file)
				{
					$implicitDirs[] = dirname($file);
				}

				$incDirs = $ds->getIncludedDirectories();

				/**
				 * We'll need to add to that list of implicit dirs any directories that contain other *directories* (and
				 * not files), since otherwise we get duplicate directories in the resulting JPA archive.
				 */
				foreach ($incDirs as $dir)
				{
					foreach ($incDirs as $dircheck)
					{
						if (!empty($dir) && $dir == dirname($dircheck))
						{
							$implicitDirs[] = $dir;
						}
					}
				}

				$implicitDirs = array_unique($implicitDirs);

				// Now add any empty dirs (dirs not covered by the implicit dirs) to the files array.
				foreach ($incDirs as $dir)
				{
					// We cannot simply use array_diff() since we want to disregard empty/. dirs

					if ($dir != "" && $dir != "." && !in_array($dir, $implicitDirs))
					{
						// It's an empty dir, so we'll add it.
						$this->files[] = $dir;
					}
				}
			}
		}

		return $this->files;
	}
}
