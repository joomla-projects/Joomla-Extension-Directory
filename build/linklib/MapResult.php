<?php

/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary;

/**
 * Describes a folder mapping result returned by Scanner classes
 *
 * @property  array  $dirs       The symlink directories map. Each entry is in the format realPath => symlinkPath.
 * @property  array  $files      The symlink files map. Each entry is in the format realPath => symlinkPath.
 * @property  array  $hardfiles  The hard link files map. Each entry is in the format realPath => linkPath.
 */
class MapResult
{
    private $symlinkFiles = [];

    private $hardLinkFiles = [];

    private $symlinkFolders = [];

    public function __get(string $name)
    {
        switch ($name) {
            case 'dirs':
                return $this->getSymlinkFolders();
                break;

            case 'files':
                return $this->getSymlinkFiles();
                break;

            case 'hardfiles':
                return $this->getHardLinkFiles();
                break;
        }

        return null;
    }

    public function __set(string $name, $value)
    {
        switch ($name) {
            case 'dirs':
                $this->setSymlinkFolders($value);
                break;

            case 'files':
                $this->setSymlinkFiles($value);
                break;

            case 'hardfiles':
                $this->setHardLinkFiles($value);
                break;
        }
    }

    /**
     * @return array
     */
    public function getSymlinkFiles()
    {
        return $this->symlinkFiles;
    }

    /**
     * @param array $symlinkFiles
     */
    public function setSymlinkFiles(array $symlinkFiles)
    {
        $this->symlinkFiles = $symlinkFiles;
    }

    /**
     * @return array
     */
    public function getHardLinkFiles()
    {
        return $this->hardLinkFiles;
    }

    /**
     * @param array $hardLinkFiles
     */
    public function setHardLinkFiles(array $hardLinkFiles)
    {
        $this->hardLinkFiles = $hardLinkFiles;
    }

    /**
     * @return array
     */
    public function getSymlinkFolders()
    {
        return $this->symlinkFolders;
    }

    /**
     * @param array $symlinkFolders
     */
    public function setSymlinkFolders(array $symlinkFolders)
    {
        $this->symlinkFolders = $symlinkFolders;
    }
}
