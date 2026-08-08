<?php

/**
 * Joomla.org site template
 *
 * Standalone renderer for the CDN hosted template sections. This file is deliberately free of any Joomla API so it can
 * be served directly from the CDN.
 *
 * @copyright   Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Get the section from the request. Only word characters are ever valid, this also keeps traversal out of the path.
$section = isset($_REQUEST['section']) ? (string) $_REQUEST['section'] : 'display';

if (!preg_match('/^[a-z0-9_-]+$/i', $section)) {
    echo 'Invalid request';

    exit(1);
}

$sectionDir = __DIR__ . "/layouts/$section";

if (!is_dir($sectionDir)) {
    echo 'Invalid request';

    exit(1);
}

// Get the language from the request, it must be a well formed language tag such as en-GB
$language = isset($_REQUEST['language']) ? (string) $_REQUEST['language'] : 'en-GB';

if (!preg_match('/^([a-z]{2,3})-([a-z]{2,4})$/i', $language, $langParts)) {
    $language = 'en-GB';
} else {
    // Take the language and normalise the casing, eg en-gb becomes en-GB
    $language = strtolower($langParts[1]) . '-' . strtoupper($langParts[2]);
}

// Build the filename to lookup
$includeFile = "$sectionDir/$language.$section.html";

// If the locale aware version of the file doesn't exist, fallback to English
if (!file_exists($includeFile)) {
    $includeFile = "$sectionDir/en-GB.$section.html";
}

if (!file_exists($includeFile)) {
    echo 'Invalid request';

    exit(1);
}

include $includeFile;
