<?php

namespace Drupal\mongodb_watchdog;

use MongoDB\BSON\Unserializable;

/**
 * Class Event.
 *
 * @package Drupal\mongodb_watchdog
 */
class Event implements Unserializable {

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
    $keys = [
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
    ];
    foreach ($keys as $key) {
      if (isset($event[$key])) {
        $this->$key = $event[$key];
      }
    }
  }

  /**
   * Load a MongoDB watchdog event.
   *
   * @param string $id
   *   The string representation of a MongoId.
   *
   * @return \Drupal\mongodb_watchdog\Event|bool
   *   FALSE if the event cannot be loaded.
   */
  public function find($template_id) {
    $criteria = ['_id' => new ObjectID($id)];
    $options = [
      'typeMap' => [
        'array' => 'array',
        'document' => 'array',
        'root' => 'Drupal\mongodb_watchdog\Event',
      ],
    ];

    $result = $this->templatesCollection->findOne($criteria, $options);
    var_dump($result);
    return $result;
  }

  function bsonUnserialize(array $data) {
    $event = new static($data);
    return $event;
  }
}
