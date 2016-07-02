<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the controller for the request events page.
 */
class RequestController implements ContainerInjectionInterface {

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
   * The mongodb_watchdog logger, to access events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * RequestController constructor.
   *
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger service, to load event data.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The core date.formatter service.
   */
  public function __construct(Logger $watchdog, DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
    $this->watchdog = $watchdog;

    // Add terminal "/".
    $this->rootLength = Unicode::strlen(DRUPAL_ROOT) + 1;
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
  public function buildTrackRequest($unique_id, array $events) {
    if ($events) {
      /** @var \Drupal\mongodb_watchdog\Event $first */
      $first = $events[0][1];
      $location = $first->location;
      $timestamp = $this->dateFormatter->format($first->timestamp, 'long');
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
  public function buildTrackRows(array $events) {
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

    /**
     * @var \Drupal\mongodb_watchdog\EventTemplate $template
     * @var \Drupal\mongodb_watchdog\Event $event
     */
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

    return new static($watchdog, $date_formatter);
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

  /**
   * Controller.
   *
   * @param string $unique_id
   *   The unique request id from mod_unique_id.
   *
   * @return array
   *   A render array.
   */
  public function track($unique_id) {
    $events = $this->watchdog->requestEvents($unique_id);
    $ret = [
      '#attached' => [
        'library' => ['mongodb_watchdog/styling'],
      ],
      'request' => $this->buildTrackRequest($unique_id, $events),
      'events' => $this->buildTrackRows($events),
    ];
    return $ret;
  }

}
