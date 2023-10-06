<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use MongoDB\BSON\Unserializable;

/**
 * Class EventTemplate models an event template.
 *
 * Templates are the invariant part of events.
 *
 * Since this is essentially a value object, naming is constrained by the
 * property names in MongoDB, so ignore variable naming rules for fields.
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class EventTemplate implements Unserializable {
  use StringTranslationTrait;

  // @codingStandardsIgnoreStart
  /**
   * The event identifier.
   *
   * Coding standards are suspended to avoid a warning on _id, which is not
   * standards-compliant, but required by MongoDB.
   *
   * @var string
   */
  public $_id;
  // @codingStandardsIgnoreEnd

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
   * @param array<mixed,mixed> $data
   *   The raw properties.
   */
  public function __construct(array $data) {
    $this->bsonUnserialize($data);
  }

  /**
   * List the template keys and their behaviours.
   *
   * @return array<string, array<string, mixed>>
   *   A properties by key array.
   */
  public static function keys(): array {
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
   *
   * @param array<mixed,mixed> $data
   *   The raw data.
   */
  public function bsonUnserialize(array $data): void {
    foreach (static::keys() as $key => $info) {
      $datum = $data[$key] ?? NULL;
      $this->{$key} = isset($info['creation_callback'])
        ? $info['creation_callback']($datum)
        : $datum;
    }
    if (!is_string($this->message)) {
      $this->message = print_r($this->message, TRUE);
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
   * This code disables coding standards because the module relies on translated
   * event templates, which are known to be variable but - assuming no coding
   * errors - will always match a constant event template string found in code.
   *
   * @param array<string,string|\Stringable> $variables
   *   The event variables.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The template message with its variables substituted.
   */
  public function asString(array $variables): TranslatableMarkup {
    // @codingStandardsIgnoreStart
    return $this->t($this->message, $variables);
    // @codingStandardsIgnoreEnd
  }

}
