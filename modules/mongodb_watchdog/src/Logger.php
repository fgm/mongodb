<?php

namespace Drupal\mongodb_watchdog;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLogLevel;
use MongoDB\Database;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Driver\Exception\RuntimeException;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class Logger is a PSR/3 Logger using a MongoDB data store.
 *
 * @package Drupal\mongodb_watchdog
 */
class Logger extends AbstractLogger {
  const CONFIG_NAME = 'mongodb_watchdog.settings';

  const TRACKER_COLLECTION = 'watchdog_tracker';
  const TEMPLATE_COLLECTION = 'watchdog';
  const EVENT_COLLECTION_PREFIX = 'watchdog_event_';
  const EVENT_COLLECTIONS_PATTERN = '^watchdog_event_[[:xdigit:]]{32}$';

  const LEGACY_TYPE_MAP = [
    'typeMap' => [
      'array' => 'array',
      'document' => 'array',
      'root' => 'array',
    ],
  ];

  /**
   * The logger storage.
   *
   * @var \MongoDB\Database
   */
  protected $database;

  /**
   * The limit for the capped event collections.
   *
   * @var int
   */
  protected $items;

  /**
   * The minimum logging level.
   *
   * @var int
   */
  protected $limit;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * The "requests" setting.
   *
   * @var int
   */
  protected $requests;

  /**
   * An array of templates already used in this request.
   *
   * Used only with request tracking enabled.
   *
   * @var string[]
   */
  protected $templates = [];

  /**
   * A sequence number for log events during a request.
   *
   * @var int
   */
  protected $sequence = 0;

  /**
   * Logger constructor.
   *
   * @param \MongoDB\Database $database
   *   The database object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The core config_factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $stack
   *   The core request_stack service.
   */
  public function __construct(Database $database, LogMessageParserInterface $parser, ConfigFactoryInterface $config_factory, RequestStack $stack) {
    $this->database = $database;
    $this->parser = $parser;
    $this->requestStack = $stack;

    $config = $config_factory->get(static::CONFIG_NAME);
    $this->limit = $config->get('limit');
    $this->items = $config->get('items');
    $this->requests = $config->get('requests');
    $this->requestTracking = $config->get('request_tracking');
  }

  /**
   * Fill in the log_entry function, file, and line.
   *
   * @param array $log_entry
   *   An event information to be logger.
   * @param array $backtrace
   *   A call stack.
   */
  protected function enhanceLogEntry(array &$log_entry, array $backtrace) {
    // Create list of functions to ignore in backtrace.
    static $ignored = array(
      'call_user_func_array' => 1,
      '_drupal_log_error' => 1,
      '_drupal_error_handler' => 1,
      '_drupal_error_handler_real' => 1,
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
           *
           * @see http://goo.gl/8s75df
           *
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
    if ($level > $this->limit) {
      return;
    }

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

    $selector = ['_id' => $template_id];
    $update = [
      '$inc' => ['count' => 1],
      '$set' => [
        '_id' => $template_id,
        'message' => $template,
        'severity' => $level,
        'changed' => time(),
        'type' => Unicode::substr($context['channel'], 0, 64),
      ],
    ];
    $options = ['upsert' => TRUE];
    $template_result = $this->database
      ->selectCollection(static::TEMPLATE_COLLECTION)
      ->updateOne($selector, $update, $options);

    // Only insert each template once per request.
    if ($this->requestTracking && !isset($this->templates[$template_id])) {
      $request_id = $this->requestStack
        ->getCurrentRequest()
        ->server
        ->get('UNIQUE_ID');

      $this->templates[$template_id] = 1;
      $track = [
        'request_id' => $request_id,
        'template_id' => $template_id,
      ];
      $this->trackerCollection()->insertOne($track);
    }

    $event_collection = $this->eventCollection($template_id);
    if ($template_result->getUpsertedCount()) {
      // Capped collections are actually size-based, not count-based, so "items"
      // is only a maximum, assuming event documents weigh 1kB, but the actual
      // number of items stored may be lower if items are heavier.
      // We do not use 'autoindexid' for greater speed, because:
      // - it does not work on replica sets,
      // - it is deprecated in MongoDB 3.2 and going away in 3.4.
      $options = [
        'capped' => TRUE,
        'size' => $this->items * 1024,
        'max' => $this->items,
      ];
      $this->database->createCollection($event_collection->getCollectionName(), $options);

      // Do not create this index by default, as its cost is useless if request
      // tracking is not enabled.
      if ($this->requestTracking) {
        $key = ['requestTracking_id' => 1];
        $options = ['name' => 'admin-by-request'];
        $event_collection->createIndex($key, $options);
      }
    }

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
    if ($this->requestTracking) {
      // Fetch the current request on each event to support subrequest nesting.
      $event['requestTracking_id'] = $request_id;
      $event['requestTracking_sequence'] = $this->sequence;
      $this->sequence++;
    }
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
   * Ensure a collection is capped with the proper size.
   *
   * @param string $name
   *   The collection name.
   * @param int $size
   *   The collection size cap.
   *
   * @return \MongoDB\Collection
   *   The collection, usable for additional commands like index creation.
   *
   * @TODO support sharded clusters: convertToCapped does not support them.
   *
   * @see https://docs.mongodb.com/manual/reference/command/convertToCapped
   *
   * Note that MongoDB 3.2 still misses a propert exists() command, which is the
   * reason for the weird try/catch logic.
   *
   * @see https://jira.mongodb.org/browse/SERVER-1938
   */
  public function ensureCappedCollection($name, $size) {
    try {
      $stats = $this->database->command([
        'collStats' => $name,
      ], static::LEGACY_TYPE_MAP)->toArray()[0];
    }
    catch (RuntimeException $e) {
      // 59 is expected if the collection was not found. Other values are not.
      if ($e->getCode() !== 59) {
        throw $e;
      }

      $this->database->createCollection($name);
      $stats = $this->database->command([
        'collStats' => $name,
      ], static::LEGACY_TYPE_MAP)->toArray()[0];
    }

    $collection = $this->database->selectCollection($name);
    if (!empty($stats['capped'])) {
      return $collection;
    }

    $this->database->command([
      'convertToCapped' => $name,
      'size' => $size,
    ]);
    return $collection;
  }

  /**
   * Ensure indexes are set on the collections and tracker collection is capped.
   *
   * First index is on <line, timestamp> instead of <function, line, timestamp>,
   * because we write to this collection a lot, and the smaller index on two
   * numbers should be much faster to create than one with a string included.
   */
  public function ensureSchema() {
    $trackerCollection = $this->ensureCappedCollection(static::TRACKER_COLLECTION, $this->requests * 1024);
    $indexes = [
      [
        'name' => 'tracker-request',
        'key' => ['request_id' => 1],
      ],
    ];
    $trackerCollection->createIndexes($indexes);

    $indexes = [
      // Index for adding/updating increments.
      [
        'name' => 'for-increments',
        'key' => ['line' => 1, 'changed' => -1],
      ],

      // Index for overview page without filters.
      [
        'name' => 'overview-no-filters',
        'key' => ['changed' => -1],
      ],

      // Index for overview page filtering by type.
      [
        'name' => 'overview-by-type',
        'key' => ['type' => 1, 'changed' => -1],
      ],

      // Index for overview page filtering by severity.
      [
        'name' => 'overview-by-severity',
        'key' => ['severity' => 1, 'changed' => -1],
      ],

      // Index for overview page filtering by type and severity.
      [
        'name' => 'overview-by-both',
        'key' => ['type' => 1, 'severity' => 1, 'changed' => -1],
      ],
    ];

    $this->templateCollection()->createIndexes($indexes);

  }

  /**
   * Return the events having occurred during a given request.
   *
   * @param string $unsafe_request_id
   *   The raw request_id.
   *
   * @return array<\Drupal\mongodb_watchdog\EventTemplate\Drupal\mongodb_watchdog\Event[]>
   *   An array of [template, event] arrays, ordered by occurrence order.
   */
  public function requestEvents($unsafe_request_id) {
    $templates = $this->requestTemplates($unsafe_request_id);
    $request_id = "$unsafe_request_id";
    $selector = ['requestTracking_id' => $request_id];
    $events = [];
    $options = [
      'typeMap' => [
        'array' => 'array',
        'document' => 'array',
        'root' => '\Drupal\mongodb_watchdog\Event',
      ],
    ];

    /**
     * @var string $template_id
     * @var \Drupal\mongodb_watchdog\EventTemplate $template
     */
    foreach ($templates as $template_id => $template) {
      $event_collection = $this->eventCollection($template_id);
      $cursor = $event_collection->find($selector, $options);
      /** @var \Drupal\mongodb_watchdog\Event $event */
      foreach ($cursor as $event) {
        $events[$event->requestTracking_sequence] = [
          $template,
          $event,
        ];
      }
    }

    return $events;
  }

  /**
   * Return an array of templates uses during a given request.
   *
   * @param string $unsafe_request_id
   *   A request "unique_id".
   *
   * @return array
   *   An array of EventTemplate instances.
   */
  public function requestTemplates($unsafe_request_id) {
    $request_id = "${unsafe_request_id}";
    $selector = [
      'request_id' => $request_id,
    ];

    $cursor = $this
      ->trackerCollection()
      ->find($selector, static::LEGACY_TYPE_MAP + [
        'projection' => [
          '_id' => 0,
          'template_id' => 1
        ]
      ]);
    $template_ids = [];
    foreach ($cursor as $request) {
      $template_ids[] = $request['template_id'];
    }
    if (empty($template_ids)) {
      return [];
    }

    $selector = ['_id' => ['$in' => $template_ids]];
    $options = [
      'typeMap' => [
        'array' => 'array',
        'document' => 'array',
        'root' => '\Drupal\mongodb_watchdog\EventTemplate',
      ],
    ];
    $templates = [];
    $cursor = $this->templateCollection()->find($selector, $options);
    /** @var \Drupal\mongodb_watchdog\EventTemplate $template */
    foreach ($cursor as $template) {
      $templates[$template->_id] = $template;
    }
    return $templates;
  }

  /**
   * Return the request events tracker collection.
   *
   * @return \MongoDB\Collection
   *   The collection.
   */
  public function trackerCollection() {
    return $this->database->selectCollection(static::TRACKER_COLLECTION);
  }

  /**
   * Return the event templates collection.
   *
   * @return \MongoDB\Collection
   *   The collection.
   */
  public function templateCollection() {
    return $this->database->selectCollection(static::TEMPLATE_COLLECTION);
  }

  /**
   * Return templates matching type and level criteria.
   *
   * @param string[] $types
   *   An array of EventTemplate types. May be a hash.
   * @param string[]|int[] $levels
   *   An array of severity levels.
   *
   * @return \MongoDB\Driver\Cursor
   *   A query result for the templates.
   */
  public function templates(array $types = [], array $levels = []) {
    $selector = [];
    if (!empty($types)) {
      $selector['type'] = ['$in' => array_values($types)];
    }
    if (!empty($levels) && count($levels) !== count(RfcLogLevel::getLevels())) {
      // Severity levels come back from the session as strings, not integers.
      $selector['severity'] = ['$in' => array_values(array_map('intval', $levels))];
    }
    $options = [
      'sort' => [
        'count' => -1,
        'changed' => -1,
      ],
      'typeMap' => [
        'array' => 'array',
        'document' => 'array',
        'root' => '\Drupal\mongodb_watchdog\EventTemplate',
      ],
    ];

    $cursor = $this->templateCollection()->find($selector, $options);
    return $cursor;
  }

  /**
   * Return the template types actually present in storage.
   *
   * @return string[]
   *   An array of distinct EventTemplate types.
   */
  public function templateTypes() {
    $ret = $this->templateCollection()->distinct('type');
    return $ret;
  }

}
