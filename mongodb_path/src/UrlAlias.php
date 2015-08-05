<?php
/**
 * @file
 * Contains UrlAlias.
 *
 * A simple typed object mapping to the {url_alias} table columns.
 */

namespace Drupal\mongodb_path;

/**
 * Class UrlAlias.
 *
 * @package Drupal\mongodb_path
 */
class UrlAlias {
  /**
   * The id for the alias.
   *
   * @var int
   */
  public $pid;

  /**
   * The system path which this alias represents.
   *
   * @var string
   */
  public $source;

  /**
   * The language code for the language to which this alias applies.
   *
   * @var string
   */
  public $language;

  /**
   * The alias string itself.
   *
   * @var string
   */
  public $alias;

  public function __toString() {
    return implode(' / ', $this->asArray()) . "\n";
  }

  public function asArray() {
    $ret = [
      'pid' => $this->pid,
      'source' => $this->source,
      'language' => $this->language,
      'alias' => $this->alias,
    ];

    return $ret;
  }
}
