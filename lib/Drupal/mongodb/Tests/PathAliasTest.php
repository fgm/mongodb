<?php

/**
 * @file
 * Definition of Drupal\mongodb\Tests\PathAliasTest.
 */

namespace Drupal\mongodb\Tests;
use Drupal\path\Tests\PathAliasTest as OriginalPathAliasTest;

/**
 * Tests MongoDB path alias functionality.
 */
class PathAliasTest extends OriginalPathAliasTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('mongodb', 'path');

  public static function getInfo() {
    return array(
      'name' => 'Path alias functionality',
      'description' => 'Add, edit, delete, and change alias and verify its consistency in the MongoDB.',
      'group' => 'MongoDB',
    );
  }

  /**
   * {@inheritdoc}
   */
  function getPID($alias) {
    $result = \Drupal::service('mongo')->get('url_alias')->findOne(array('alias' => $alias), array('_id' => TRUE));
    if (!empty($result)) {
      return $result['_id'];
    }
    return FALSE;
  }
}
