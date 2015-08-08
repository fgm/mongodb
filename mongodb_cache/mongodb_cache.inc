<?php
/**
 * @file
 * The previous name for the MongoDB cache plugin.
 *
 * Includes the new cache plugin to keep compatibility with existing sites
 * loading this plugin under its previous name. New sites should use the new
 * plugin name to skip one file system access.
 */

require_once __DIR__ . '/mongodb_cache_plugin.php';
