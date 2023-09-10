<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * Download robo.phar from http://robo.li/robo.phar and type in the root of the repo: $ php robo.phar
 * Or do: $ composer update, and afterwards you will be able to execute robo like $ php vendor/bin/robo
 *
 * @package     Joomla.Site
 * @subpackage  RoboFile
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\Jorobo\Tasks\Tasks as loadReleaseTasks;
use Robo\Tasks;

require_once 'vendor/autoload.php';

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', __DIR__);
}

/**
 * Modern php task runner for Joomla! Browser Automated Tests execution
 *
 * @package  RoboFile
 *
 * @since    1.0
 */
class RoboFile extends \Robo\Tasks
{
    // Load tasks from composer, see composer.json
    use loadReleaseTasks;

    /**
     * File extension for executables
     *
     * @var string
     */
    private $executableExtension = '';

    /**
     * Local configuration parameters
     *
     * @var array
     */
    private $configuration = array();

    /**
     * Path to the local CMS root
     *
     * @var string
     */
    private $cmsPath = '';

    /**
     * @var array | null
     * @since  version
     */
    private $suiteConfig;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->configuration = $this->getConfiguration();

        $this->cmsPath = $this->getCmsPath();

        $this->executableExtension = $this->getExecutableExtension();

        // Set default timezone (so no warnings are generated if it is not set)
        date_default_timezone_set('UTC');
    }

    /**
     * Get the executable extension according to Operating System
     *
     * @return string
     */
    private function getExecutableExtension()
    {
        if ($this->isWindows()) {
            return '.exe';
        }

        return '';
    }

    /**
     * Run the specified checker tool. Valid options are phpmd, phpcs, phpcpd
     *
     * @param   string  $tool  The tool
     *
     * @return  bool
     */
    public function runChecker($tool = null)
    {
        if ($tool === null) {
            $this->say('You have to specify a tool name as argument. Valid tools are phpmd, phpcs, phpcpd.');

            return false;
        }

        if (!in_array($tool, array('phpmd', 'phpcs', 'phpcpd'))) {
            $this->say('The tool you required is not known. Valid tools are phpmd, phpcs, phpcpd.');

            return false;
        }

        switch ($tool) {
            case 'phpmd':
                return $this->runPhpmd();

            case 'phpcs':
                return $this->runPhpcs();

            case 'phpcpd':
                return $this->runPhpcpd();
        }
    }

    /**
     * Creates a testing Joomla site for running the tests (use it before run:test)
     *
     * @param   bool  $use_htaccess  (1/0) Rename and enable embedded Joomla .htaccess file
     *
     * @return  bool
     */
    public function createTestingSite($use_htaccess = false)
    {
        if (!empty($this->configuration->skipClone)) {
            $this->say('Reusing Joomla CMS site already present at ' . $this->cmsPath);

            return;
        }

        // Caching cloned installations locally
        if (!is_dir('tests/cache') || (time() - filemtime('tests/cache') > 60 * 60 * 24)) {
            if (file_exists('tests/cache')) {
                $this->taskDeleteDir('tests/cache')->run();
            }

            $this->_exec($this->buildGitCloneCommand());
        }

        // Get Joomla Clean Testing sites
        if (is_dir($this->cmsPath)) {
            try {
                $this->taskDeleteDir($this->cmsPath)->run();
            } catch (Exception $e) {
                // Sorry, we tried :(
                $this->say('Sorry, you will have to delete ' . $this->cmsPath . ' manually. ');
                exit(1);
            }
        }

        $this->_copyDir('tests/cache', $this->cmsPath);

        // Optionally change owner to fix permissions issues
        if (!empty($this->configuration->localUser) && !$this->isWindows()) {
            $this->_exec('chown -R ' . $this->configuration->localUser . ' ' . $this->cmsPath);
        }

        // Copy current package
        if (!file_exists('dist/pkg-weblinks-current.zip')) {
            $this->build(true);
        }

        $this->_copy('dist/pkg-weblinks-current.zip', $this->cmsPath . "/pkg-weblinks-current.zip");

        $this->say('Joomla CMS site created at ' . $this->cmsPath);

        // Optionally uses Joomla default htaccess file. Used by TravisCI
        if ($use_htaccess == true) {
            $this->_copy('./tests/joomla/htaccess.txt', './tests/joomla/.htaccess');
            $this->_exec('sed -e "s,# RewriteBase /,RewriteBase /tests/joomla/,g" -in-place tests/joomla/.htaccess');
        }
    }

    /**
     * Get (optional) configuration from an external file
     *
     * @return \stdClass|null
     */
    public function getConfiguration()
    {
        $configurationFile = __DIR__ . '/RoboFile.ini';

        if (!file_exists($configurationFile)) {
            $this->say("No local configuration file");

            return null;
        }

        $configuration = parse_ini_file($configurationFile);

        if ($configuration === false) {
            $this->say('Local configuration file is empty or wrong (check is it in correct .ini format');

            return null;
        }

        return json_decode(json_encode($configuration));
    }

    /**
     * Build correct git clone command according to local configuration and OS
     *
     * @return string
     */
    private function buildGitCloneCommand()
    {
        $branch = empty($this->configuration->branch) ? '4.0-dev' : $this->configuration->branch;

        return "git" . $this->executableExtension . " clone -b $branch --single-branch --depth 1 https://github.com/joomla/joomla-cms.git tests/cache";
    }

    /**
     * Check if local OS is Windows
     *
     * @return bool
     */
    private function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Get the correct CMS root path
     *
     * @return string
     */
    private function getCmsPath()
    {
        if (empty($this->configuration->cmsPath)) {
            return 'tests/joomla';
        }

        if (!file_exists(dirname($this->configuration->cmsPath))) {
            $this->say("Cms path written in local configuration does not exists or is not readable");

            return 'tests/joomla';
        }

        return $this->configuration->cmsPath;
    }

    /**
     * Downloads Composer
     *
     * @return void
     */
    private function getComposer()
    {
        // Make sure we have Composer
        if (!file_exists('./composer.phar')) {
            $insecure = '';

            if (!empty($this->configuration->insecure)) {
                $insecure = '--insecure';
            }

            $this->_exec('curl ' . $insecure . ' --retry 3 --retry-delay 5 -sS https://getcomposer.org/installer | php');
        }
    }

    /**
     * Run the phpmd tool
     *
     * @return  void
     */
    private function runPhpmd()
    {
        return $this->_exec('phpmd' . $this->extension . ' ' . __DIR__ . '/src xml cleancode,codesize,controversial,design,naming,unusedcode');
    }

    /**
     * Run the phpcs tool
     *
     * @return  void
     */
    private function runPhpcs()
    {
        $this->_exec('phpcs' . $this->extension . ' ' . __DIR__ . '/src');
    }

    /**
     * Run the phpcpd tool
     *
     * @return  void
     */
    private function runPhpcpd()
    {
        $this->_exec('phpcpd' . $this->extension . ' ' . __DIR__ . '/src');
    }

    /**
     * Build the joomla extension package
     *
     * @param   array  $params  Additional params
     *
     * @return  void
     */
    public function build($params = ['dev' => false])
    {
        if (!file_exists('jorobo.ini')) {
            $this->_copy('jorobo.dist.ini', 'jorobo.ini');
        }

        $this->taskBuild($params)->run();
    }

    /**
     * Update copyright headers for this project. (Set the text up in the jorobo.ini)
     *
     * @return  void
     */
    public function headers()
    {
        if (!file_exists('jorobo.ini')) {
            $this->_copy('jorobo.dist.ini', 'jorobo.ini');
        }

        (new \Joomla\Jorobo\Tasks\CopyrightHeader())->run();
    }

    /**
     * Get the suite configuration
     *
     * @param   string  $suite  The suite
     *
     * @return array
     */
    private function getSuiteConfig($suite = 'acceptance')
    {
        if (!$this->suiteConfig) {
            $this->suiteConfig = Symfony\Component\Yaml\Yaml::parse(file_get_contents("tests/{$suite}.suite.yml"));
        }

        return $this->suiteConfig;
    }

    /**
     * Return the os name
     *
     * @return string
     *
     * @since version
     */
    private function getOs()
    {
        $os = php_uname('s');

        if (strpos(strtolower($os), 'windows') !== false) {
            $os = 'windows';
        } elseif (strpos(strtolower($os), 'darwin') !== false) {
            // Who have thought that Mac is actually Darwin???
            $os = 'mac';
        } else {
            $os = 'linux';
        }

        return $os;
    }

    /**
     * Update Version __DEPLOY_VERSION__ in Weblinks. (Set the version up in the jorobo.ini)
     *
     * @return  void
     */
    public function bump()
    {
        (new \Joomla\Jorobo\Tasks\BumpVersion())->run();
    }

    /**
     * Map into Joomla installation.
     *
     * @param   String  $target  The target joomla instance
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     *
     */
    public function map($target)
    {
        (new \Joomla\Jorobo\Tasks\Map($target))->run();
    }
}
