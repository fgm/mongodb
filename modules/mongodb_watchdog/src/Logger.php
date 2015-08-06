<?php

/**
 * @file
 * Contains MongoDB Logger.
 */

namespace Drupal\mongodb_watchdog;


use Drupal\Component\Utility\Unicode;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * Class Logger is a PSR/3 Logger using a MongoDB data store.
 *
 * @package Drupal\mongodb_watchdog
 */
class Logger implements LoggerInterface {

  use RfcLoggerTrait;

  const COLLECTION = 'watchdog';

  /**
   * The logger storage.
   *
   * @var \MongoDB
   */
  protected $database;

  /**
   * Constructs a DbLog object.
   *
   * @param \MongoDB $database
   *   The database object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(\MongoDB $database, LogMessageParserInterface $parser) {
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
    $message_placeholders = $this->parser->parseMessagePlaceholders($message,
      $context);

    $this->database
      ->selectCollection(static::COLLECTION)
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
}
