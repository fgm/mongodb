<?php

/**
 * @file
 * Contains MongoDB Logger.
 */

namespace Drupal\mongodb_watchdog;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Logger\LogMessageParserInterface;
use Psr\Log\AbstractLogger;

/**
 * Class Logger is a PSR/3 Logger using a MongoDB data store.
 *
 * @package Drupal\mongodb_watchdog
 */
class Logger extends AbstractLogger {

  const TEMPLATE_COLLECTION = 'watchdog';
  const EVENT_COLLECTION_PREFIX = 'watchdog_event_';
  const EVENT_COLLECTIONS_PATTERN = '/watchdog_event_[[:xdigit:]]{32}$/';


  /**
   * The logger storage.
   *
   * @var \MongoDB
   */
  protected $database;

  /**
   * The collection holding message templates.
   *
   * @var \MongoCollection
   */
  protected $templatesCollection;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs a Logger object.
   *
   * @param \MongoDB $database
   *   The database object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(\MongoDB $database, LogMessageParserInterface $parser) {
    $this->database = $database;
    $this->parser = $parser;
    $this->templatesCollection = $database->selectCollection(static::TEMPLATE_COLLECTION);
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    // Remove any backtraces since they may contain an unserializable variable.
    unset($context['backtrace']);

    // Convert PSR3-style messages to SafeMarkup::format() style, so they can be
    // translated too in runtime.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message,
      $context);

    $this->database
      ->selectCollection(static::TEMPLATE_COLLECTION)
      ->insert([
        'uid' => $context['uid'],
        'type' => Unicode::substr($context['channel'], 0, 64),
        'message' => $message,
        'variables' => serialize($message_placeholders),
        'severity' => $level,
        'link' => $context['link'],
        'location' => $context['request_uri'],
        'referer' => $context['referer'],
        'hostname' => Unicode::substr($context['ip'], 0, 128),
        'timestamp' => $context['timestamp'],
      ]);
  }

  /**
   * List the event collections.
   *
   * @return \MongoCollection[]
   *   The collections with a name matching the event pattern.
   */
  public function eventCollections() {
    $result = [];
    foreach ($this->database->listCollections() as $collection) {
      $name = $collection->getName();
      if (preg_match(static::EVENT_COLLECTIONS_PATTERN, $name)) {
        $result[] = $collection;
      }
    }

    return $result;
  }

  /**
   * Return a collection, given its template id.
   *
   * @param string $template_id
   *   The string representation of a template \MongoId.
   *
   * @return \MongoCollection
   *   A collection object for the specified template id.
   */
  public function eventCollection($template_id) {
    $collection_name = static::EVENT_COLLECTION_PREFIX . $template_id;
    assert('preg_match(static::EVENT_COLLECTIONS_PATTERN, $collection_name)');
    return $this->database->selectCollection($collection_name);
  }

  /**
   * Ensure indexes are set on the collections.
   *
   * First index is on <line, timestamp> instead of <function, line, timestamp>,
   * because we write to this collection a lot, and the smaller index on two
   * numbers should be much faster to create than one with a string included.
   */
  public function ensureIndexes() {
    $templates = $this->templatesCollection;
    $indexes = [
      // Index for adding/updating increments.
      [
        'line' => 1,
        'timestamp' => -1
      ],
      // Index for admin page without filters.
      [
        'timestamp' => -1
      ],
      // Index for admin page filtering by type.
      [
        'type' => 1,
        'timestamp' => -1
      ],
      // Index for admin page filtering by severity.
      [
        'severity' => 1,
        'timestamp' => -1
      ],
      // Index for admin page filtering by type and severity.
      [
        'type' => 1,
        'severity' => 1,
        'timestamp' => -1
      ],
    ];

    foreach ($indexes as $index) {
      $templates->ensureIndex($index);
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
  public function eventLoad($id) {
    $criteria = ['_id' => $id];
    $result = new Event($this->templatesCollection->findOne($criteria));
    $result = $result ?: FALSE;
    return $result;
  }

  /**
   * Drop the logger collections.
   *
   * @return int
   *   The number of collections dropped.
   */
  public function uninstall() {
    $count = 0;

    $collections = $this->eventCollections();
    foreach ($collections as $collection) {
      $status = $collection->drop();
      if ($status['ok'] == 1) {
        ++$count;
      }
    }

    $status = $this->templatesCollection->drop();
    if ($status['ok'] == 1) {
      ++$count;
    }

    return $count;
  }
}
