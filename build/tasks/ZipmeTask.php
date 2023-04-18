<?php

/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// phpcs:disable PSR1.Files.SideEffects
require_once __DIR__ . '/../lib/ZipmeFileSet.php';
// phpcs:enable PSR1.Files.SideEffects

/**
 * Creates a ZIP archive using ZipArchive.
 *
 * This works around some issues in the original ZipTask which won't let it add empty folders in the archive.
 */
class ZipmeTask extends MatchingTask
{
    /**
     * The output file
     *
     * @var   PhingFile
     */
    private $zipFile;

    /**
     * The directory that holds the data to include in the archive
     *
     * @var   PhingFile
     */
    private $baseDir;

    /**
     * File path prefix in ZIP archive
     *
     * @var   string
     */
    private $prefix = null;

    /**
     * Should I include empty dirs in the archive.
     *
     * @var   bool
     */
    private $includeEmpty = true;

    /**
     * The filesets to include to the archive
     *
     * @var   array
     */
    private $filesets = [];

    /**
     * Add a new fileset.
     *
     * @return  FileSet
     */
    public function createFileSet()
    {
        $this->fileset    = new ZipmeFileSet();
        $this->filesets[] = $this->fileset;

        return $this->fileset;
    }

    /**
     * Add a new fileset.
     *
     * @return  FileSet
     */
    public function createZipmeFileSet()
    {
        $this->fileset    = new ZipmeFileSet();
        $this->filesets[] = $this->fileset;

        return $this->fileset;
    }

    /**
     * Set the name/location of where to create the JPA file.
     *
     * @param   PhingFile  $destFile  The location of the output JPA file
     */
    public function setDestFile(PhingFile $destFile)
    {
        $this->zipFile = $destFile;
    }

    /**
     * Set the include empty directories flag.
     *
     * @param   boolean  $bool  Should empty directories be included in the archive?
     *
     * @return  void
     */
    public function setIncludeEmptyDirs($bool)
    {
        $this->includeEmpty = (bool)$bool;
    }

    /**
     * This is the base directory to look in for files to archive.
     *
     * @param   PhingFile  $baseDir  The base directory to scan
     *
     * @return  void
     */
    public function setBasedir(PhingFile $baseDir)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * Sets the file path prefix for files in the JPA archive
     *
     * @param   string  $prefix  Prefix
     *
     * @return  void
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Do the work
     *
     * @throws BuildException
     */
    public function main()
    {
        if ($this->zipFile === null) {
            throw new BuildException("zipFile attribute must be set!", $this->getLocation());
        }

        if ($this->zipFile->exists() && $this->zipFile->isDirectory()) {
            throw new BuildException("zipFile is a directory!", $this->getLocation());
        }

        if ($this->zipFile->exists() && !$this->zipFile->canWrite()) {
            throw new BuildException("Can not write to the specified zipFile!", $this->getLocation());
        }

        $savedFileSets = $this->filesets;

        try {
            if (empty($this->filesets)) {
                throw new BuildException("You must supply some nested filesets.", $this->getLocation());
            }

            $this->log("Building ZIP: " . $this->zipFile->__toString(), Project::MSG_INFO);

            $absolutePath = $this->zipFile->getAbsolutePath();

            if (!is_dir(dirname($absolutePath))) {
                throw new BuildException("ZIP file path $absolutePath is not a path.", $this->getLocation());
            }

            $zip        = new ZipArchive();
            $openResult = $zip->open($this->zipFile->getAbsolutePath(), ZipArchive::CREATE);

            if ($openResult !== true) {
                switch ($openResult) {
                    case ZipArchive::ER_EXISTS:
                        $message = 'File already exists.';
                        break;

                    case ZipArchive::ER_INCONS:
                        $message = 'Zip archive inconsistent.';
                        break;

                    case ZipArchive::ER_INVAL:
                        $message = 'Invalid argument.';
                        break;

                    case ZipArchive::ER_MEMORY:
                        $message = 'Malloc failure.';
                        break;

                    case ZipArchive::ER_NOENT:
                        $message = 'No such file.';
                        break;

                    case ZipArchive::ER_NOZIP:
                        $message = 'Not a zip archive.';
                        break;

                    case ZipArchive::ER_OPEN:
                        $message = 'Can\'t open file.';
                        break;

                    case ZipArchive::ER_READ:
                        $message = 'Read error.';
                        break;

                    case ZipArchive::ER_SEEK:
                        $message = 'Seek error.';
                        break;
                }
                throw new BuildException("ZipArchive::open() failed: " . $message);
            }

            foreach ($this->filesets as $fs) {
                $files     = $fs->getFiles($this->project, $this->includeEmpty);
                $fsBasedir = (null != $this->baseDir) ? $this->baseDir : $fs->getDir($this->project);
                $removeDir = str_replace('\\', '/', $fsBasedir->getPath());

                $filesToZip = [];

                foreach ($files as $file) {
                    $f = new PhingFile($fsBasedir, $file);

                    $fileAbsolutePath = $f->getPath();
                    $fileDir          = rtrim(dirname($fileAbsolutePath), '/\\');
                    $fileBase         = basename($fileAbsolutePath);

                    // Only use lowercase for $disallowedBases because we'll convert $fileBase to lowercase
                    $disallowedBases = ['.ds_store', '.svn', '.gitignore', 'thumbs.db'];
                    $fileBaseLower   = strtolower($fileBase);

                    if (in_array($fileBaseLower, $disallowedBases)) {
                        continue;
                    }

                    if (substr($fileDir, -4) == '.svn') {
                        continue;
                    }

                    if (substr(rtrim($fileAbsolutePath, '/\\'), -4) == '.svn') {
                        continue;
                    }

                    $fileRelativePath = str_replace('\\', '/', $fileAbsolutePath);

                    if (substr($fileRelativePath, 0, strlen($removeDir)) === $removeDir) {
                        $fileRelativePath = substr($fileRelativePath, strlen($removeDir) + 1);
                    }

                    $fileRelativePath = empty($this->prefix) ? $fileRelativePath : ($this->prefix . '/' . $fileRelativePath);

                    if (!file_exists($fileAbsolutePath) || !is_readable($fileAbsolutePath)) {
                        continue;
                    }

                    if (is_dir($fileAbsolutePath)) {
                        $zip->addEmptyDir($fileRelativePath);
                    } else {
                        $zip->addFile($fileAbsolutePath, $fileRelativePath);
                        //$zip->addFile($fileAbsolutePath, $fileRelativePath, 0, 0, ZipArchive::FL_ENC_UTF_8);
                        // Try to change the compression mode of every file to DEFLATE (max compatiblity)
                        $zip->setCompressionName($fileRelativePath, ZipArchive::CM_DEFLATE);
                    }
                }
            }
        } catch (IOException $ioe) {
            $msg            = "Problem creating ZIP: " . $ioe->getMessage();
            $this->filesets = $savedFileSets;

            throw new BuildException($msg, $ioe, $this->getLocation());
        } finally {
            $zip->close();
        }

        $this->filesets = $savedFileSets;
    }
}
