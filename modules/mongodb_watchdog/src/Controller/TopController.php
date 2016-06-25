<?php

namespace Drupal\mongodb_watchdog\Controller;

/**
 * Class TopController implements the Top403/Top404 controllers.
 */
class TopController {
  public function top403() {
    return ['#markup' => "403"];
  }

  public function top404() {
    return ['#markup' => "404"];
  }
  /**
   * Page callback for "admin/reports/[access-denied|page-not-found]".
   *
   * @return array
   */
  function mongodb_watchdog_page_top($type) {
    $ret = array();
    $type_param = array('%type' => $type);
    $limit = 50;

    // Safety net
    $types = array(
      'page not found',
      'access denied',
    );
    if (!in_array($type, $types)) {
      drupal_set_message(t('Unknown top report type: %type', $type_param), 'error');
      watchdog('mongodb_watchdog', 'Unknown top report type: %type', $type_param, WATCHDOG_WARNING);
      $ret = '';
      return $ret;
    }

    // Find _id for the error type.
    $watchdog = mongodb_collection(variable_get('mongodb_watchdog', 'watchdog'));
    $template = $watchdog->findOne(array('type' => $type), array('_id'));

    // findOne() will return NULL if no row is found
    if (empty($template)) {
      $ret['empty'] = array(
        '#markup' => t('No "%type" message found', $type_param),
        '#prefix' => '<div class="mongodb-watchdog-message">',
        '#suffix' => '</div>',
      );
      $ret = drupal_render($ret);
      return $ret;
    }

    // Find occurrences of error type.
    $key = $template['_id'];
    $event_collection = mongodb_collection('watchdog_event_' . $key);
    $reduce = <<<EOT
function (doc, accumulator) {
  accumulator.count++;
}
EOT;

    $counts = $event_collection->group(
      array('variables.@param' => 1),
      array('count' => array()),
      $reduce
    );
    if (!$counts['ok']) {
      drupal_set_message(t('No "%type" occurrence found', $type_param), 'error');
      $ret = '';
      return $ret;
    }
    $counts = $counts['retval'];
    usort($counts, '_mongodb_watchdog_sort_top');
    $counts = array_slice($counts, 0, $limit);

    $header = array(
      t('#'),
      t('Paths'),
    );
    $rows = array();
    foreach ($counts as $count) {
      $rows[] = array(
        $count['variables.@param'],
        $count['count'],
      );
    }

    $ret = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    );
    return $ret;
  }

  /**
   * usort() helper function to sort top entries returned from a group query.
   *
   * @param array $x
   * @param array $y
   *
   * @return boolean
   */
  function _mongodb_watchdog_sort_top($x, $y) {
    $cx = $x['count'];
    $cy = $y['count'];
    return $cy - $cx;
  }

}
