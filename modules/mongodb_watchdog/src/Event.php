<?php

namespace Drupal\mongodb_watchdog;

use MongoDB\BSON\Unserializable;

/**
 * Class Event.
 *
 * @package Drupal\mongodb_watchdog
 */
class Event implements Unserializable {
  const KEYS = [
    '_id',
    'hostname',
    'link',
    'location',
    'message',
    'referrer',
    'severity',
    'timestamp',
    'type',
    'uid',
    'variables',
    'requestTracking_id',
    'requestTracking_sequence',
  ];

  // @codingStandardsIgnoreStart
  /**
   * The string representation of a MongoId.
   *
   * @var int
   */
  public $_id;
  // @codingStandardsIgnoreEnd

  /**
   * User id.
   *
   * @var int
   */
  public $uid;

  /**
   * Event type, often a module name.
   *
   * @var string
   */
  public $type;

  /**
   * Event template.
   *
   * @var string
   */
  public $message;

  /**
   * The identifier of the request during which this event occurred.
   *
   * @var string
   */
  public $requestTracking_id;

  /**
   * The sequence number of the event during the request when it happened.
   *
   * @var int
   */
  public $requestTracking_sequence;

  /**
   * The template parameters.
   *
   * @var array
   */
  public $variables;

  /**
   * A RFC5424 severity level.
   *
   * @var int
   */
  public $severity;

  /**
   * A link provided by the event emitter. Optional.
   *
   * @var string
   */
  public $link;

  /**
   * The absolute URL for the path on which the event was logged.
   *
   * @var string
   */
  public $location;

  /**
   * A HTTP referrer for the path on which the event was logged. Optional.
   *
   * @var string
   */
  public $referrer;

  /**
   * The server host.
   *
   * @var string
   */
  public $hostname;

  /**
   * The timestamp at which the event was logged.
   *
   * @var int
   */
  public $timestamp;

  /**
   * Constructor.
   *
   * @param array $event
   *   The event in array form.
   */
  public function __construct(array $event) {
    $this->bsonUnserialize($event);
  }

  /**
   * {@inheritdoc}
   */
  public function bsonUnserialize(array $data) {
    foreach (static::KEYS as $key) {
      if (isset($data[$key])) {
        $this->{$key} = $data[$key];
      }
    }
  }

}
