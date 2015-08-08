<?php

/**
 * @file
 * Contains CacheTestTrait.
 */

namespace Drupal\mongodb_cache\Tests;


/**
 * Class CacheTest provides getInfo() replacement.
 *
 * @package Drupal\mongodb_cache
 */
trait CacheTestTrait {
  /**
   * Declare the test to Simpletest.
   *
   * @return string[]
   *   The test information as expected by Simpletest for Drupal 7.
   */
  public static function getInfo() {
    $class = get_called_class();
    $reflected = new \ReflectionClass($class);

    $name = $reflected->getShortName();

    $comment = $reflected->getDocComment();
    $matches = [];
    $error_arg = ['@class' => $class];

    $sts = preg_match('/^\/\*\*[\s]*\n[\s]*\*[\s]([^\n]*)/s', $comment, $matches);
    $description = $sts ? $matches[1] : strtr("MongoDB: FIXME Missing name for class @class", $error_arg);

    $sts = preg_match('/^[\s]+\*[\s]+@group[\s]+(.*)$/m', $comment, $matches);
    $group = $sts ? $matches[1] : strtr("MongoDB: FIXME Missing group for class @class.", $error_arg);

    return [
      'name' => $name,
      'description' => $description,
      'group' => $group,
    ];
  }

}
