<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\mongodb_watchdog\Logger;
use MongoDB\BSON\Javascript;
use MongoDB\Collection;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TopController implements the Top403/Top404 controllers.
 */
class TopController implements ContainerInjectionInterface {
  const TYPES = [
    'page not found',
    'access denied',
  ];

  /**
   * The database holding the logger collections.
   *
   * @var \MongoDB\Database
   */
  protected $db;

  /**
   * The logger channel service, used to log events.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The concrete logger instance, used to access data.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * TopController constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel service, to log errors occurring.
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB Logger service, to load the events.
   * @param \MongoDB\Database $db
   *   Needed because there is no group() command in phplib yet.
   *
   * @see https://jira.mongodb.org/browse/PHPLIB-177
   */
  public function __construct(LoggerInterface $logger, Logger $watchdog, Database $db) {
    $this->db = $db;
    $this->logger = $logger;
    $this->watchdog = $watchdog;
  }

  /**
   * The controller for /admin/reports/mongodb/access-denied.
   *
   * @return array
   *   A render array.
   */
  public function top403() {
    $ret = $this->top('access denied');
    return $ret;
  }

  /**
   * The controller for /admin/reports/mongodb/page-not-found.
   *
   * @return array
   *   A render array.
   */
  public function top404() {
    $ret = $this->top('page not found');
    return $ret;
  }

  /**
   * Command wrapper for missing MongoDB group() implementation in PHPlib.
   *
   * @param \MongoDB\Collection $collection
   *   The collection on which to perform the command.
   * @param object $key
   *   The grouping key.
   * @param object $cond
   *   The condition.
   * @param string $reduce
   *   The reducer function: must be valid JavaScript code.
   * @param object $initial
   *   The initial document.
   *
   * @return array|void
   *   Void in case of error, otherwise an array with the following keys:
   *   - waitedMS: time spent waiting
   *   - retval: an array of command results, containing at least the key
   *   - count: the total number of documents matched
   *   - keys: the number of different keys, normally matching count(retval)
   *   - ok: 1.0 in case of success.
   */
  public function group(Collection $collection, $key, $cond, $reduce, $initial) {
    $cursor = $this->db->command([
      'group' => [
        'ns' => $collection->getCollectionName(),
        'key' => $key,
        'cond' => $cond,
        'initial' => $initial,
        '$reduce' => new Javascript($reduce),
      ],
    ], Logger::LEGACY_TYPE_MAP);

    $ret = $cursor->toArray();
    $ret = reset($ret);
    return $ret;
  }

  /**
   * Callback for usort() to sort top entries returned from a group query.
   *
   * @param array $x
   *   The first value to compare.
   * @param array $y
   *   The second value to compare.
   *
   * @return bool
   *   The comparison result.
   *
   * @see \Drupal\mongodb_watchdog\Controller\TopController::top()
   */
  protected function topSort(array $x, array $y) {
    $cx = $x['count'];
    $cy = $y['count'];
    return $cy - $cx;
  }

  /**
   * Generic controller for admin/reports/mongodb/<top report>.
   *
   * @param string $type
   *   The type of top report to produce.
   *
   * @return array
   *   A render array.
   */
  protected function top($type) {
    $ret = [];
    $type_param = ['%type' => $type];
    $limit = 50;

    if (!in_array($type, static::TYPES)) {
      $error = t('Unknown top report type: %type', $type_param);
      drupal_set_message($error, 'error');
      $this->logger->warning('Unknown top report type: %type', $type_param);
      $ret = [
        '#markup' => '',
      ];
      return $ret;
    }

    // Find _id for the error type.
    $template_collection = $this->watchdog->templateCollection();
    $template = $template_collection->findOne(['type' => $type], ['_id']);

    // Method findOne() will return NULL if no row is found.
    $ret['empty'] = array(
      '#markup' => t('No "%type" message found', $type_param),
      '#prefix' => '<div class="mongodb-watchdog-message">',
      '#suffix' => '</div>',
    );
    if (empty($template)) {
      return $ret;
    }

    // Find occurrences of error type.
    $collection_name = $template['_id'];
    $event_collection = $this->watchdog->eventCollection($collection_name);

    $key = ['variables.@uri' => 1];
    $cond = [];
    $reduce = <<<EOT
function (doc, accumulator) { 
  accumulator.count++; 
}
EOT;
    $initial = ['count' => 0];
    $counts = $this->group($event_collection, $key, $cond, $reduce, $initial);

    if (empty($counts['ok'])) {
      return $ret;
    }

    $counts = $counts['retval'];
    usort($counts, [$this, 'topSort']);
    $counts = array_slice($counts, 0, $limit);

    $header = array(
      t('#'),
      t('Paths'),
    );
    $rows = array();
    foreach ($counts as $count) {
      $rows[] = array(
        $count['count'],
        $count['variables.@uri'],
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.factory')->get('mongodb_watchdog');

    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get('mongodb.logger');

    /** @var \MongoDB\Database $db */
    $db = $container->get('mongodb.watchdog_storage');
    return new static($logger, $watchdog, $db);
  }

}
