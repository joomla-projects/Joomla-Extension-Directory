#!/usr/bin/env php
<?php

/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

class PhpStormSourceHandling
{
    public function execute(string $rootFolder, bool $verbose = true)
    {
        if ($verbose) {
            echo "Detecting extensions under $rootFolder\n";
        }

        $namespaces = [];
        foreach (
            [
                     'component' => [
                         $rootFolder . '/administrator/components',
                         $rootFolder . '/component',
                     ],
                     'module' => [
                         $rootFolder . '/administrator/modules',
                         $rootFolder . '/modules',
                         $rootFolder . '/modules/site',
                         $rootFolder . '/modules/admin',
                     ],
                     'plugin' => [
                         $rootFolder . '/plugins',
                     ],
                     'library' => [
                         $rootFolder . '/libraries',
                         $rootFolder . '/src',
                     ],
                 ] as $type => $folders
        ) {
            foreach ($folders as $folder) {
                if (!file_exists($folder)) {
                    continue;
                }

                $namespaces = array_merge($namespaces, $this->getNamespaces($type, $folder));
            }
        }

        if (empty($namespaces)) {
            if ($verbose) {
                echo "No extensions found. Quitting.\n";
            }

            exit(1);
        } elseif (count($namespaces) === 1) {
            if ($verbose) {
                echo "One extension namespace found.\n";
            }
        } else {
            if ($verbose) {
                echo sprintf("%d extension namespaces found.\n", count($namespaces));
            }
        }

        $imlFiles = $this->getFiles($rootFolder . '/.idea', '\.iml$', false, true);

        if (empty($imlFiles)) {
            if ($verbose) {
                echo "Cannot find the .idea folder or any .iml files in it.\n";
            }

            exit(2);
        } elseif (count($imlFiles) === 1) {
            if ($verbose) {
                echo "One phpStorm .iml file found.\n";
            }
        } else {
            if ($verbose) {
                echo sprintf("%d phpStorm .iml files found.\n", count($imlFiles));
            }
        }

        if ($verbose) {
            echo "\n";
        }

        foreach ($imlFiles as $imlFile) {
            if ($verbose) {
                echo "Updating $imlFile\n";
            }
            $this->editPhpStormIML($imlFile, $namespaces, true);
        }

        if ($verbose) {
            echo "\n";
        }
    }

    private function getPathListing(
        string $path,
        string $filter,
        $recurse,
        bool $full,
        array $exclude,
        string $excludeFilterString,
        bool $findfiles
    ) {
        $arr = [];

        if (!($handle = @opendir($path))) {
            return $arr;
        }

        while (($file = readdir($handle)) !== false) {
            if (
                $file != '.' && $file != '..' && !\in_array($file, $exclude)
                && (empty($excludeFilterString) || !preg_match($excludeFilterString, $file))
            ) {
                $fullpath = $path . '/' . $file;
                $isDir    = is_dir($fullpath);

                if (($isDir xor $findfiles) && preg_match("/$filter/", $file)) {
                    $arr[] = $full ? $fullpath : $file;
                }

                if ($isDir && $recurse) {
                    $arr = array_merge(
                        $arr,
                        $this->getPathListing(
                            $fullpath,
                            $filter,
                            \is_int($recurse) ? ($recurse - 1) : $recurse,
                            $full,
                            $exclude,
                            $excludeFilterString,
                            $findfiles
                        )
                    );
                }
            }
        }

        closedir($handle);

        return $arr;
    }

    private function getFolders(
        string $path,
        string $filter = '.',
        $recurse = false,
        bool $full = false,
        array $exclude = ['.svn', 'CVS', '.DS_Store', '__MACOSX'],
        array $excludeFilter = ['^\..*']
    ) {
        // Is the path a folder?
        if (!is_dir($path)) {
            throw new \UnexpectedValueException(
                sprintf('%1$s: Path is not a folder. Path: %2$s', __METHOD__, $path)
            );
        }

        $excludeFilterString = \count($excludeFilter) ? '/(' . implode('|', $excludeFilter) . ')/' : '';
        $arr                 = $this->getPathListing($path, $filter, $recurse, $full, $exclude, $excludeFilterString, false);

        asort($arr);

        return array_values($arr);
    }

    private function getFiles(
        string $path,
        string $filter = '.',
        $recurse = false,
        bool $full = false,
        array $exclude = ['.svn', 'CVS', '.DS_Store', '__MACOSX'],
        array $excludeFilter = ['^\..*', '.*~',],
        bool $naturalSort = false
    ) {
        // Is the path a folder?
        if (!is_dir($path)) {
            throw new \UnexpectedValueException(
                sprintf('%1$s: Path is not a folder. Path: %2$s', __METHOD__, $path)
            );
        }

        $excludeFilterString = \count($excludeFilter) ? '/(' . implode('|', $excludeFilter) . ')/' : '';
        $arr                 = $this->getPathListing($path, $filter, $recurse, $full, $exclude, $excludeFilterString, true);

        if ($naturalSort) {
            natsort($arr);
        } else {
            asort($arr);
        }

        return array_values($arr);
    }

    private function getNamespaces(string $type, string $folder): array
    {
        $directories = [$folder];

        if ($type === 'plugin') {
            $directories = $this->getFolders($folder, '.', false, true);
        }

        $extensions = [];

        foreach ($directories as $directory) {
            try {
                $extensionFolders = $this->getFolders($directory);
            } catch (Exception $e) {
                continue;
            }

            foreach ($extensionFolders as $extension) {
                $extensionPath = $directory . '/' . $extension . '/';
                $name          = str_replace('com_', '', $extension, $count);
                $xmlFiles      = $this->getFiles(rtrim($extensionPath, '/'), '\.xml$', false, true);

                foreach ($xmlFiles as $file) {
                    if (!($xml = simplexml_load_file($file))) {
                        continue;
                    }

                    if ($xml->getName() != 'extension') {
                        continue;
                    }

                    $namespaceNode = $xml->namespace;
                    $namespace     = (string) $namespaceNode;
                    $nameNode      = $xml->name;
                    $name          = ((string) $nameNode) ?: $name;

                    if (!$namespace) {
                        continue;
                    }

                    $namespace     = trim(str_replace('\\\\', '\\', $namespace), '\\') . '\\';
                    $namespacePath = rtrim($extensionPath . $namespaceNode->attributes()->path, '/');

                    switch ($type) {
                        case 'plugin':
                        case 'library':
                            $extensions[$namespace] = $namespacePath;

                            continue 2;

                        case 'component':
                            if (strpos($namespacePath, '/administrator/components/' . $name . '/') !== false) {
                                $sitePath = str_replace(
                                    '/administrator/components/' . $name . '/',
                                    '/components/' . $name . '/',
                                    $namespacePath
                                );

                                $apiPath = str_replace(
                                    '/administrator/components/' . $name . '/',
                                    '/api/components/' . $name . '/',
                                    $namespacePath
                                );
                            } else {
                                $sitePath = str_replace(
                                    '/component/backend/',
                                    '/component/frontend/',
                                    $namespacePath
                                );

                                $apiPath = str_replace(
                                    '/component/backend/',
                                    '/component/api/',
                                    $namespacePath
                                );
                            }

                            $extensions[$namespace . 'Api\\']           = $apiPath;
                            $extensions[$namespace . 'Site\\']          = $sitePath;
                            $extensions[$namespace . 'Administrator\\'] = $namespacePath;
                            break;

                        case 'module':
                            $isAdministrator =
                                strpos($namespacePath, '/administrator/modules' . $name) !== false
                                || strpos($namespacePath, '/modules/admin/' . $name) !== false;

                            $extensions[$namespace . ($isAdministrator ? 'Administrator' : 'Site') . '\\'] =
                                $namespacePath;
                            break;
                    }
                }
            }
        }

        return $extensions;
    }

    private function editPhpStormIML(string $imlFile, array $namespaceMap, bool $overwriteExisting = false)
    {
        if (!($xml = simplexml_load_file($imlFile))) {
            return;
        }

        if (
            $xml->getName() != 'module'
            || (string) $xml->attributes()->type != 'WEB_MODULE'
            || (string) $xml->attributes()->version != 4
        ) {
            return;
        }

        $knownSources    = $xml->xpath('//component[@name="NewModuleRootManager"]/content[@url="file://$MODULE_DIR$"]/sourceFolder[@isTestSource="false"]');
        $knownNamespaces = [];

        if (!$overwriteExisting) {
            foreach ($knownSources as $node) {
                $namespace = (string) $node->attributes()->packagePrefix;

                if ($namespace) {
                    $knownNamespaces[] = $namespace;
                }
            }

            $knownNamespaces = array_map(
                function ($x) {
                    return trim($x, '/');
                },
                $knownNamespaces
            );

            $knownNamespaces = array_merge(
                $knownNamespaces,
                array_map(
                    function ($x) {
                        return $x . '\\';
                    },
                    $knownNamespaces
                )
            );

            $namespaceMap = array_filter(
                $namespaceMap,
                function ($prefix) use ($knownNamespaces) {
                    return !in_array($prefix, $knownNamespaces);
                },
                ARRAY_FILTER_USE_KEY
            );
        } else {
            $removeNodes = [];

            foreach ($knownSources as $node) {
                $namespace = (string) $node->attributes()->packagePrefix;
                $namespace = trim($namespace, '\\') . '\\';

                if (empty($namespace) || !array_key_exists($namespace, $namespaceMap)) {
                    continue;
                }

                $removeNodes[] = $node;
            }

            foreach ($removeNodes as $node) {
                unset($node[0]);
            }
        }

        $contentNode   = $xml->xpath('//component[@name="NewModuleRootManager"]/content[@url="file://$MODULE_DIR$"]')[0];
        $basePath      = realpath(dirname($imlFile));
        $basePathParts = explode(DIRECTORY_SEPARATOR, $basePath);
        array_pop($basePathParts);
        $basePath = implode('/', $basePathParts);

        foreach ($namespaceMap as $namespace => $path) {
            $realPath = realpath($path);

            /**
             * The extension does not exist.
             *
             * We get that when the developer starts working towards namespacing an extension but has not yet created the
             * folder structure for the namespaced code. Early Joomla 4 is full of that in its core modules and plugins.
             */
            if (empty($realPath)) {
                continue;
            }

            /**
             * Out-of-project path.
             *
             * This should never happen!
             */
            if (!str_starts_with($realPath, $basePath)) {
                die('Invalid our out-of-project path encountered. This should have never happened.');
            }

            $relativeUrl = 'file://$MODULE_DIR$' . substr($realPath, strlen($basePath));

            $sourceNode = $contentNode->addChild('sourceFolder');
            $sourceNode->addAttribute('url', $relativeUrl);
            $sourceNode->addAttribute('packagePrefix', $namespace);
            $sourceNode->addAttribute('isTestSource', 'false');
        }

        $dom                     = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->loadXML($xml->asXML());

        file_put_contents($imlFile, $dom->saveXML());
    }
}

if (defined('AKEEBA_PHPSTORMSOURCES_INCLUDE_ONLY')) {
    return;
}

$year = gmdate('Y');
echo <<< TEXT
Akeeba BuildFiles — IML Namespaces Updater
Updates phpStorm IML files with the extensions' namespace definitions
--------------------------------------------------------------------------------
Copyright ©2022-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

TEXT;

$rootFolder = $argv[1] ?? getcwd();

$o = new PhpStormSourceHandling();
$o->execute($rootFolder);
