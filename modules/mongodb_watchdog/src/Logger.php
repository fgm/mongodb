<?php

namespace Drupal\mongodb_watchdog;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Logger\LogMessageParserInterface;
use MongoDB\Database;
use MongoDB\Driver\Exception\InvalidArgumentException;
use Psr\Log\AbstractLogger;

/**
 * Class Logger is a PSR/3 Logger using a MongoDB data store.
 *
 * @package Drupal\mongodb_watchdog
 */
class Logger extends AbstractLogger {

  const TEMPLATE_COLLECTION = 'watchdog';
  const EVENT_COLLECTION_PREFIX = 'watchdog_event_';
  const EVENT_COLLECTIONS_PATTERN = '^watchdog_event_[[:xdigit:]]{24}$';

  /**
   * The logger storage.
   *
   * @var \MongoDB\Database
   */
  protected $database;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs a Logger object.
   *
   * @param \MongoDB\Database $database
   *   The database object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(Database $database, LogMessageParserInterface $parser) {
    $this->database = $database;
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    // Remove any backtraces since they may contain an unserializable variable.
    unset($context['backtrace']);

    // Convert PSR3-style messages to SafeMarkup::format() style, so they can be
    // translated too in runtime.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);

    $template_result = $this->database
      ->selectCollection(static::TEMPLATE_COLLECTION)
      ->insertOne([
        'type' => Unicode::substr($context['channel'], 0, 64),
        'message' => $message,
        'severity' => $level,
      ]);
    $template_id = $template_result->getInsertedId();

    $event_collection = $this->eventCollection($template_id);
    $event = [
      'hostname' => Unicode::substr($context['ip'], 0, 128),
      'link' => $context['link'],
      'location' => $context['request_uri'],
      'referer' => $context['referer'],
      'timestamp' => $context['timestamp'],
      'user' => ['uid' => $context['uid']],
      'variables' => $message_placeholders,
    ];
    $event_collection->insertOne($event);
  }

  /**
   * List the event collections.
   *
   * @return \MongoDB\Collection[]
   *   The collections with a name matching the event pattern.
   */
  public function eventCollections() {
    echo static::EVENT_COLLECTIONS_PATTERN;
    $options = [
      'filter' => [
        'name' => ['$regex' => static::EVENT_COLLECTIONS_PATTERN],
      ],
    ];
    $result = iterator_to_array($this->database->listCollections($options));
    return $result;
  }

  /**
   * Return a collection, given its template id.
   *
   * @param string $template_id
   *   The string representation of a template \MongoId.
   *
   * @return \MongoDB\Collection
   *   A collection object for the specified template id.
   */
  public function eventCollection($template_id) {
    $collection_name = static::EVENT_COLLECTION_PREFIX . $template_id;
    if (!preg_match('/' . static::EVENT_COLLECTIONS_PATTERN . '/', $collection_name)) {
      throw new InvalidArgumentException(t('Invalid watchdog template id `@id`.', [
        '@id' => $collection_name,
      ]));
    }
    $collection = $this->database->selectCollection($collection_name);
    return $collection;
  }

  /**
   * Ensure indexes are set on the collections.
   *
   * First index is on <line, timestamp> instead of <function, line, timestamp>,
   * because we write to this collection a lot, and the smaller index on two
   * numbers should be much faster to create than one with a string included.
   */
  public function ensureIndexes() {
    $templates = $this->database->selectCollection(static::TEMPLATE_COLLECTION);
    $indexes = [
      // Index for adding/updating increments.
      [
        'name' => 'for-increments',
        'key' => ['line' => 1, 'timestamp' => -1],
      ],

      // Index for admin page without filters.
      [
        'name' => 'admin-no-filters',
        'key' => ['timestamp' => -1],
      ],

      // Index for admin page filtering by type.
      [
        'name' => 'admin-by-type',
        'key' => ['type' => 1, 'timestamp' => -1],
      ],

      // Index for admin page filtering by severity.
      [
        'name' => 'admin-by-severity',
        'key' => ['severity' => 1, 'timestamp' => -1],
      ],

      // Index for admin page filtering by type and severity.
      [
        'name' => 'admin-by-both',
        'key' => ['type' => 1, 'severity' => 1, 'timestamp' => -1],
      ],
    ];
    $templates->createIndexes($indexes);
  }

}
