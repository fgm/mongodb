<?php

namespace Drupal\mongodb_watchdog;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use MongoDB\BSON\Unserializable;

/**
 * Class EventTemplate models an event template.
 *
 * Templates are the invariant part of events.
 */
class EventTemplate implements Unserializable {
  use StringTranslationTrait;

  /**
   * The event identifier.
   *
   * @var string
   */
  public $_id;

  /**
   * Latest event insertion for the template.
   *
   * @var int
   */
  public $changed;

  /**
   * Event count for the template.
   *
   * @var int
   */
  public $count;

  /**
   * The message "type": a Drupal logger "channel".
   *
   * @var string
   */
  public $type;

  /**
   * The event template message with placeholders, not substituted.
   *
   * @var string
   */
  public $message;

  /**
   * The RFC 5424 severity level.
   *
   * @var int
   */
  public $severity;

  /**
   * EventTemplate constructor.
   *
   * @param array $data
   *   The raw properties.
   */
  public function __construct(array $data) {
    $this->bsonUnserialize($data);
  }

  /**
   * List the template keys and their behaviours.
   *
   * @return array
   *   A properties by key array.
   */
  public static function keys() {
    $ret = [
      '_id' => [
        'label' => t('ID'),
      ],
      'changed' => [
        'label' => t('Changed'),
        'creation_callback' => 'intval',
      ],
      'count' => [
        'label' => t('Count'),
        'creation_callback' => 'intval',
      ],
      'type' => [
        'label' => t('Type'),
      ],
      'message' => [
        'label' => t('Message'),
      ],
      'severity' => [
        'label' => t('Severity'),
        'creation_callback' => 'intval',
        'display_callback' => function ($level) {
          return RfcLogLevel::getLevels()[$level];
        },
      ],
    ];
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function bsonUnserialize(array $data) {
    foreach (static::keys() as $key => $info) {
      $datum = $data[$key] ?? NULL;
      $this->{$key} = isset($info['creation_callback'])
        ? $info['creation_callback']($datum)
        : $datum;
    }
  }

  /**
   * Returns the message with its variables substituted into it.
   *
   * This code passes a variable to $this->t() because the "variable" ultimately
   * comes from a module code, not from user input. This assumes modules
   * correctly pass only template messages to PSR-3 methods, instead of already
   * assembled messages.
   *
   * XXX babysit broken modules using messages containing user input.
   *
   * @param array $variables
   *   The event variables.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The template message with its variables substituted.
   */
  public function asString(array $variables) {
    return $this->t($this->message, $variables);
  }

}
