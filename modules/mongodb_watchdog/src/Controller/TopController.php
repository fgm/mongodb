<?php

declare(strict_types = 1);

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\mongodb_watchdog\Logger;
use MongoDB\BSON\Javascript;
use MongoDB\Collection;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Top403/Top404 controllers.
 */
class TopController extends ControllerBase {
  const TYPES = [
    'page not found',
    'access denied',
  ];

  /**
   * The database holding the logger collections.
   *
   * @var \MongoDB\Database
   */
  protected $database;

  /**
   * TopController constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service, to log intervening events.
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger, to load stored events.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   * @param \MongoDB\Database $database
   *   Needed because there is no group() command in phplib yet.
   *
   * @see https://jira.mongodb.org/browse/PHPLIB-177
   */
  public function __construct(
    LoggerInterface $logger,
    Logger $watchdog,
    ImmutableConfig $config,
    Database $database) {
    parent::__construct($logger, $watchdog, $config);

    $this->database = $database;
  }

  /**
   * Controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $type
   *   The type of top report to produce.
   *
   * @return array
   *   A render array.
   */
  public function build(Request $request, $type): array {
    $top = $this->getTop();

    $rows = $this->getRowData($request, $type);
    $main = empty($rows)
      ? $this->buildEmpty($this->t('No "%type" message found', ['%type' => $type]))
      : $this->buildMainTable($rows);

    $ret = $this->buildDefaults($main, $top);
    return $ret;
  }

  /**
   * Build the main table.
   *
   * @param array $rows
   *   The event data.
   *
   * @return array
   *   A render array for the main table.
   */
  protected function buildMainTable(array $rows): array {
    $ret = [
      '#header' => $this->buildMainTableHeader(),
      '#rows' => $this->buildMainTableRows($rows),
      '#type' => 'table',
    ];
    return $ret;
  }

  /**
   * Build the main table header.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   A table header array.
   */
  protected function buildMainTableHeader(): array {
    $header = [
      $this->t('#'),
      $this->t('Paths'),
    ];

    return $header;
  }

  /**
   * Build the main table rows.
   *
   * @param array[] $counts
   *   The array of counts per 403/404 page.
   *
   * @return array
   *   A render array for a table.
   */
  protected function buildMainTableRows(array $counts): array {
    $rows = [];
    foreach ($counts as $count) {
      $row = [
        $count['count'],
        $count['variables.@uri'],
      ];
      $rows[] = $row;
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.mongodb_watchdog');

    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get(Logger::SERVICE_LOGGER);

    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $container->get('config.factory')->get('mongodb_watchdog.settings');

    /** @var \MongoDB\Database $database */
    $database = $container->get('mongodb.watchdog_storage');

    return new static($logger, $watchdog, $config, $database);
  }

  /**
   * Obtain the data from the logger.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request. Needed for paging.
   * @param string $type
   *   The type of top list to retrieve.
   *
   * @return array
   *   The data array.
   */
  protected function getRowData(Request $request, $type): array {
    // Find _id for the error type.
    $templateCollection = $this->watchdog->templateCollection();
    $template = $templateCollection->findOne(['type' => $type], ['_id']);
    if (empty($template)) {
      return [];
    }

    // Find occurrences of error type.
    $collectionName = $template['_id'];
    $eventCollection = $this->watchdog->eventCollection($collectionName);

    $key = ['variables.@uri' => 1];
    $cond = [];
    $reducer = <<<JAVASCRIPT
function reducer(doc, accumulator) {
  accumulator.count++;
}
JAVASCRIPT;

    $initial = ['count' => 0];
    $counts = $this->group($eventCollection, $key, $cond, $reducer, $initial);
    if (empty($counts['ok'])) {
      return [];
    }

    $counts = $counts['retval'];
    usort($counts, [$this, 'topSort']);

    $page = $this->setupPager($request, count($counts));
    $skip = $page * $this->itemsPerPage;
    $counts = array_slice($counts, $skip, $this->itemsPerPage);

    return $counts;
  }

  /**
   * Command wrapper for missing MongoDB group() implementation in PHPlib.
   *
   * @param \MongoDB\Collection $collection
   *   The collection on which to perform the command.
   * @param \stdClass $key
   *   The grouping key.
   * @param \stdClass $cond
   *   The condition.
   * @param string $reduce
   *   The reducer function: must be valid JavaScript code.
   * @param \stdClass $initial
   *   The initial document.
   *
   * @return array|null
   *   Void in case of error, otherwise an array with the following keys:
   *   - waitedMS: time spent waiting
   *   - retval: an array of command results, containing at least the key
   *   - count: the total number of documents matched
   *   - keys: the number of different keys, normally matching count(retval)
   *   - ok: 1.0 in case of success.
   */
  public function group(Collection $collection, \stdClass $key, \stdClass $cond, string $reduce, \stdClass $initial): ?array {
    $cursor = $this->database->command([
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
   * @param array $first
   *   The first value to compare.
   * @param array $second
   *   The second value to compare.
   *
   * @return int
   *   The comparison result.
   *
   * @see \Drupal\mongodb_watchdog\Controller\TopController::build()
   */
  protected function topSort(array $first, array $second): int {
    return $second['count'] <=> $first['count'];
  }

}
