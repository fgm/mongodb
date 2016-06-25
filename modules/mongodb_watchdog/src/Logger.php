<?php

namespace Drupal\mongodb_watchdog;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
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
  const EVENT_COLLECTIONS_PATTERN = '^watchdog_event_[[:xdigit:]]{32}$';

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
   * Fill in the log_entry function, file, and line.
   *
   * @param array $log_entry
   *   An event information to be logger.
   * @param array $backtrace
   *   A call stack.
   */
  function enhanceLogEntry(&$log_entry, $backtrace) {
    // Create list of functions to ignore in backtrace.
    static $ignored = array(
      'call_user_func_array' => 1,
      '_drupal_log_error' => 1,
      '_drupal_error_handler' => 1,
      '_drupal_error_handler_real' => 1,
      // 'theme_render_template' => 1,
      'Drupal\mongodb_watchdog\Logger::log' => 1,
      'Drupal\Core\Logger\LoggerChannel::log' => 1,
      'Drupal\Core\Logger\LoggerChannel::alert' => 1,
      'Drupal\Core\Logger\LoggerChannel::critical' => 1,
      'Drupal\Core\Logger\LoggerChannel::debug' => 1,
      'Drupal\Core\Logger\LoggerChannel::emergency' => 1,
      'Drupal\Core\Logger\LoggerChannel::error' => 1,
      'Drupal\Core\Logger\LoggerChannel::info' => 1,
      'Drupal\Core\Logger\LoggerChannel::notice' => 1,
      'Drupal\Core\Logger\LoggerChannel::warning' => 1,
    );

    foreach ($backtrace as $bt) {
      if (isset($bt['function'])) {
        $function = empty($bt['class']) ? $bt['function'] : $bt['class'] . '::' . $bt['function'];
        if (empty($ignored[$function])) {
          $log_entry['%function'] = $function;
          /* Some part of the stack, like the line or file info, may be missing.
           * @see http://stackoverflow.com/questions/4581969/why-is-debug-backtrace-not-including-line-number-sometimes
           * No need to fetch the line using reflection: it would be redundant
           * with the name of the function.
           */
          $log_entry['%line'] = isset($bt['line']) ? $bt['line'] : NULL;
          if (empty($bt['file'])) {
            $reflected_method = new \ReflectionMethod($function);
            $bt['file'] = $reflected_method->getFileName();
          }

          $log_entry['%file'] = $bt['file'];
          break;
        }
        elseif ($bt['function'] == '_drupal_exception_handler') {
          $e = $bt['args'][0];
          $this->enhanceLogEntry($log_entry, $e->getTrace());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $template, array $context = []) {
    // Convert PSR3-style messages to SafeMarkup::format() style, so they can be
    // translated too in runtime.
    $message_placeholders = $this->parser->parseMessagePlaceholders($template, $context);

    // If code location information is all present, as for errors/exceptions,
    // then use it to build the message template id.
    $type = $context['channel'];
    $location_info = [
      '%type' => 1,
      '@message' => 1,
      '%function' => 1,
      '%file' => 1,
      '%line' => 1,
    ];
    if (!empty(array_diff_key($location_info, $message_placeholders))) {
      $this->enhanceLogEntry($message_placeholders, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10));
    }
    $file = $message_placeholders['%file'];
    $line = $message_placeholders['%line'];
    $function = $message_placeholders['%function'];
    $key = "${type}:${level}:${file}:${line}:${function}";
    $template_id = md5($key);

    $selector = [ '_id' => $template_id ];
    $update = [
      '_id' => $template_id,
      'type' => Unicode::substr($context['channel'], 0, 64),
      'message' => $template,
      'severity' => $level,
    ];
    $options = [ 'upsert' => TRUE ];
    $template_result = $this->database
      ->selectCollection(static::TEMPLATE_COLLECTION)
      ->replaceOne($selector, $update, $options);
    $template_result->getUpsertedId();

    $event_collection = $this->eventCollection($template_id);
    foreach ($message_placeholders as &$placeholder) {
      if ($placeholder instanceof MarkupInterface) {
        $placeholder = Xss::filterAdmin($placeholder);
      }
    }
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
