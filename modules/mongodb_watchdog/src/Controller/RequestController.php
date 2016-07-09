<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implements the controller for the request events page.
 */
class RequestController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The core date.formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The length of the absolute path to the site root, in runes.
   *
   * @var int
   */
  protected $rootLength;

  /**
   * The MongoDB logger, to load events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * Controller constructor.
   *
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger service, to load event data.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The core date.formatter service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   */
  public function __construct(
    Logger $watchdog,
    DateFormatterInterface $date_formatter,
    ImmutableConfig $config) {
    parent::__construct($config);
    $this->dateFormatter = $date_formatter;
    $this->watchdog = $watchdog;

    // Add terminal "/".
    $this->rootLength = Unicode::strlen(DRUPAL_ROOT) + 1;
  }

  /**
   * Controller.
   *
   * @param string $uniqueId
   *   The unique request id from mod_unique_id. Unsafe.
   *
   * @return array
   *   A render array.
   */
  public function build(Request $request, $uniqueId) {
    if (!preg_match('/[\w-]+/', $uniqueId)) {
      return ['#markup' => ''];
    }

    $page = $this->setupPager($request, $uniqueId);
    $skip = $page * $this->itemsPerPage;
    $height = $this->itemsPerPage;

    $events = $this->watchdog->requestEvents($uniqueId, $skip, $height);
    $ret = [
      'request' => $this->buildRequest($uniqueId, $events),
      'events' => $this->buildRows($events),
      'pager' => [
        '#type' => 'pager',
      ],
      '#attached' => [
        'library' => ['mongodb_watchdog/styling'],
      ],
    ];

    return $ret;
  }

  /**
   * Build the top part of the page, about the request.
   *
   * @param string $unique_id
   *   The unique request id.
   * @param array<\Drupal\mongodb_watchdog\EventTemplate\Drupal\mongodb_watchdog\Event[]> $events
   *   A fully loaded array of events and their templates.
   *
   * @return array
   *   A render array for a table.
   */
  public function buildRequest($unique_id, array $events) {
    if ($events) {
      $row = array_slice($events, 0, 1);
      /** @var \Drupal\mongodb_watchdog\Event $first */
      list($template, $first) = reset($row);

      $location = $first->location;
      $timestamp = isset($first->timestamp)
        ? $this->dateFormatter->format($first->timestamp, 'long')
        : t('No information');
    }
    else {
      $location = $timestamp = t('No information');
    }

    $rows = [
      [t('Request ID'), $unique_id],
      [t('Location'), $location],
      [t('Date/time'), $timestamp],
    ];

    foreach ($rows as &$row) {
      $row[0] = [
        'data' => $row[0],
        'header' => TRUE,
      ];
    }

    $ret = [
      '#type' => 'table',
      '#rows' => $rows,
    ];
    return $ret;
  }

  /**
   * Build the bottom part of the page, about the events during the request.
   *
   * @param array<\Drupal\mongodb_watchdog\EventTemplate\Drupal\mongodb_watchdog\Event[]> $events
   *   A fully loaded array of events and their templates.
   *
   * @return array
   *   A render array for a table.
   */
  public function buildRows(array $events) {
    $header = [
      t('Sequence'),
      t('Type'),
      t('Severity'),
      t('Event'),
      t('File'),
      t('Line'),
    ];
    $rows = [];
    $levels = RfcLogLevel::getLevels();

    /** @var \Drupal\mongodb_watchdog\EventTemplate $template */
    /** @var \Drupal\mongodb_watchdog\Event $event */
    foreach ($events as list($template, $event)) {
      $row = [
        ['data' => $event->requestTracking_sequence],
        $template->type,
        [
          'data' => $levels[$template->severity],
          'class' => OverviewController::SEVERITY_CLASSES[$template->severity],
        ],
        [
          'data' => Link::createFromRoute($template->asString($event->variables), 'mongodb_watchdog.reports.detail', [
            'event_template' => $template->_id,
          ]),
        ],
        $this->simplifyPath($event->variables['%file']),
        $event->variables['%line'],
      ];
      $rows[] = $row;
    }

    $ret = [
      '#type' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get('mongodb.logger');

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = $container->get('date.formatter');

    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $container->get('config.factory')->get('mongodb_watchdog.settings');

    return new static($watchdog, $date_formatter, $config);
  }

  /**
   * Set up the templates pager.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param int $uniqueId
   *   The uniqueId of the current request.
   *
   * @return int
   *   The number of the page to display, starting at 0.
   */
  public function setupPager(Request $request, $uniqueId) {
    $count = $this->watchdog->requestEventsCount($uniqueId);
    $height = $this->itemsPerPage;
    pager_default_initialize($count, $height);

    $page = intval($request->query->get('page'));
    if ($page < 0) {
      $page = 0;
    }
    else {
      $page_max = intval(min(ceil($count / $height), PHP_INT_MAX) - 1);
      if ($page > $page_max) {
        $page = $page_max;
      }
    }

    return $page;
  }

  /**
   * Convert an absolute path to a relative one if below the site root.
   *
   * @param string $path
   *   An absolute path on the filesystem.
   *
   * @return string
   *   A relative path if possible, otherwise the input path.
   */
  public function simplifyPath($path) {
    $ret = (Unicode::strpos($path, DRUPAL_ROOT) === 0)
      ? Unicode::substr($path, $this->rootLength)
      : $path;
    return $ret;
  }

}
