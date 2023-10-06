<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\mongodb_watchdog\Logger;
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

  const TYPE_MAP = [
    'root' => TopResult::class,
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
   * @param \Drupal\Core\Pager\PagerManagerInterface $pagerManager
   *   The core pager.manager service.
   *
   * @see https://jira.mongodb.org/browse/PHPLIB-177
   */
  public function __construct(
    LoggerInterface $logger,
    Logger $watchdog,
    ImmutableConfig $config,
    Database $database,
    PagerManagerInterface $pagerManager) {
    parent::__construct($logger, $watchdog, $pagerManager, $config);

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
   * @return array<string,mixed>
   *   A render array.
   */
  public function build(Request $request, string $type): array {
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
   * @param \stdClass[] $rows
   *   The event data.
   *
   * @return array<string,mixed>
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
   * @param \stdClass[] $counts
   *   The array of counts per 403/404 page.
   *
   * @return array<int,array{0:int,1:string}>
   *   A render array for a table.
   */
  protected function buildMainTableRows(array $counts): array {
    $rows = [];
    /** @var \Drupal\mongodb_watchdog\Controller\TopResult $result */
    foreach ($counts as $result) {
      $row = [
        $result->count,
        $result->uri,
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

    /** @var \Drupal\Core\Pager\PagerManagerInterface $pagerManager */
    $pagerManager = $container->get('pager.manager');

    return new static($logger, $watchdog, $config, $database, $pagerManager);
  }

  /**
   * Obtain the data from the logger.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request. Needed for paging.
   * @param string $type
   *   The type of top list to retrieve.
   *
   * @return \stdClass[]
   *   The data array.
   */
  protected function getRowData(Request $request, string $type): array {
    // Find _id for the error type.
    $templateCollection = $this->watchdog->templateCollection();
    $template = $templateCollection->findOne(['type' => $type], ['_id']);
    if (empty($template)) {
      return [];
    }

    // Find occurrences of error type.
    $collectionName = $template['_id'];
    $eventCollection = $this->watchdog->eventCollection($collectionName);

    $counts = $this->group($eventCollection, 'variables.@uri', []);

    $page = $this->setupPager($request, count($counts));
    $skip = $page * $this->itemsPerPage;
    $counts = array_slice($counts, $skip, $this->itemsPerPage);

    return $counts;
  }

  /**
   * Command wrapper for removed MongoDB group() method/command.
   *
   * @param \MongoDB\Collection $collection
   *   The collection on which to perform the command.
   * @param string $key
   *   The grouping key.
   * @param array<mixed,mixed> $cond
   *   The condition.
   *
   * @return \stdClass[]
   *   An array of stdClass rows with the following properties:
   *   - _id: the URL
   *   - count: the number of occurrences.
   *   It may be empty.
   *
   * @throws \MongoDB\Driver\Exception\RuntimeException
   * @throws \MongoDB\Exception\InvalidArgumentException
   * @throws \MongoDB\Exception\UnexpectedValueException
   * @throws \MongoDB\Exception\UnsupportedException
   */
  public function group(Collection $collection, string $key, array $cond): array {
    $pipeline = [];
    if (!empty($cond)) {
      $pipeline[] = ['$match' => $cond];
    }
    if (!empty($key)) {
      $pipeline[] = [
        '$group' => [
          '_id' => "\${$key}",
          'count' => ['$sum' => 1],
        ],
      ];
    }
    $pipeline[] = [
      '$sort' => [
        'count' => -1,
        '_id' => 1,
      ],
    ];

    // Aggregate always returns a cursor since MongoDB 3.6.
    /** @var \MongoDB\Driver\CursorInterface<array> $res */
    $res = $collection->aggregate($pipeline);
    $res->setTypeMap(static::TYPE_MAP);
    $ret = $res->toArray();
    return $ret;
  }

}
