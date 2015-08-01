<?php
/**
 * @file
 * Contains SafeMarkup.php
 *
 * A Drupal 7 subset of Drupal 8 SafeMarkup
 */

namespace Drupal\mongodb_path\Drupal8;


/**
 * Class SafeMarkup.
 *
 * A Drupal 7 subset of the Drupal 8 SafeMarkup service.
 *
 * @package Drupal\mongodb_path
 */
class SafeMarkup {
  /**
   * Encodes special characters in a plain-text string for display as HTML.
   *
   * Also validates strings as UTF-8. All processed strings are also
   * automatically flagged as safe markup strings for rendering.
   *
   * @param string $text
   *   The text to be checked or processed.
   *
   * @return string
   *   An HTML safe version of $text, or an empty string if $text is not valid
   *   UTF-8.
   *
   * @ingroup sanitization
   *
   * @see drupal_validate_utf8()
   */
  public static function checkPlain($text) {
    $string = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    static::$safeStrings[$string]['html'] = TRUE;
    return $string;
  }

}
