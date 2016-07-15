<?php

/**
 * @file
 * Contains mongodb_watchdog_event_template.page.inc.
 *
 * Page callback for Event template entities.
 */

use Drupal\Core\Render\Element;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Prepares variables for Event template templates.
 *
 * Default template: mongodb_watchdog_event_template.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_mongodb_watchdog_event_template(array &$variables) {
  // Fetch EventTemplate Entity Object.
  $mongodb_watchdog_event_template = $variables['elements']['#mongodb_watchdog_event_template'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
