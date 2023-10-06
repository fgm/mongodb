<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use MongoDB\Driver\Cursor;

/**
 * Class EventController provides query and render logic for Event occurrences.
 *
 * It is not a page/Response controller, hence its location outside the
 * Controller namespace.
 */
class EventController {
  use StringTranslationTrait;

  /**
   * The name of the anonymous user account.
   *
   * @var string
   */
  protected $anonymous;

  /**
   * The length of the absolute home URL.
   *
   * @var int
   */
  protected $baseLength;

  /**
   * The date.formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The absolute path to the site home.
   *
   * @var string
   */
  protected $front;

  /**
   * An instance cache for user accounts, which are used in a loop.
   *
   * @var array<int,\Drupal\Core\Link|string>
   */
  protected $userCache = [];

  /**
   * The MongoDB logger service, to load events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * EventController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config.factory service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The core data.formatter service.
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger service, to load events.
   */
  public function __construct(
    ConfigFactoryInterface $config,
    DateFormatterInterface $dateFormatter,
    Logger $watchdog) {
    // Needed for other values so build it first.
    $this->front = Url::fromRoute('<front>', [], ['absolute' => TRUE])
      ->toString();

    $this->anonymous = $config->get('user.settings')->get('anonymous');
    $this->baseLength = mb_strlen($this->front) - 1;
    $this->dateFormatter = $dateFormatter;
    $this->watchdog = $watchdog;
  }

  /**
   * Provide a table row representation of an event occurrence.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The template for which the occurrence exists.
   * @param \Drupal\mongodb_watchdog\Event $event
   *   The event occurrence to represent.
   *
   * @return array<int,\Drupal\Core\Link|\Drupal\Core\StringTranslation\TranslatableMarkup|string|null>
   *   A render array for a table row.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function asTableRow(EventTemplate $template, Event $event): array {
    $uid = $event->uid();
    if (!isset($this->userCache[$uid])) {
      $this->userCache[$uid] = $uid ? User::load($uid)->toLink() : $this->anonymous;
    }

    $location = $event->location();
    $ret = [
      $this->dateFormatter->format($event->timestamp, 'short'),
      $this->userCache[$uid],
      $template->asString($event->variables),
      // Locations generated from Drush will not necessarily match the
      // site home URL, and will not therefore not necessarily be reachable, so
      // we only generate a link if the location is "within" the site.
      (mb_strpos($location, $this->front) === 0)
        ? Link::fromTextAndUrl(mb_substr($location, $this->baseLength), Url::fromUri($location))
        : $location,
      empty($event->referrer) ? '' : Link::fromTextAndUrl($event->referrer, Url::fromUri($event->referrer)),
      $event->hostname,
      (isset($event->requestTracking_id) && $event->requestTracking_id !== Logger::INVALID_REQUEST)
        ? Link::createFromRoute($this->t('Request'),
          'mongodb_watchdog.reports.request',
          ['uniqueId' => $event->requestTracking_id])
        : '',
    ];

    return $ret;
  }

  /**
   * Load MongoDB watchdog events for a given event template.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The template for which to find events.
   * @param int $skip
   *   The number of events to skip.
   * @param int $limit
   *   The limit on the number of events to return.
   *
   * @return \MongoDB\Driver\Cursor
   *   A cursor to the event occurrences.
   */
  public function find(EventTemplate $template, int $skip, int $limit): Cursor {
    $collection = $this->watchdog->eventCollection($template->_id);
    $selector = [];
    $options = [
      'skip' => $skip,
      'limit' => $limit,
      'sort' => ['$natural' => -1],
      'typeMap' => [
        'array' => 'array',
        'document' => 'array',
        'root' => 'Drupal\mongodb_watchdog\Event',
      ],
    ];

    $result = $collection->find($selector, $options);
    return $result;
  }

}
