<?php
/**
 * @file
 * Contains AdminController.
 */

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\mongodb\Connection;
use Drupal\mongodb_watchdog\Logger;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdminController.
 *
 * @package Drupal\mongodb_watchdog
 */
class AdminController implements ContainerInjectionInterface {

  /**
   * @var \MongoDB
   */
  protected $database;

  /**
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $logger;

  /**
   * Constructor.
   *
   * @param \MongoDB $database
   *   The watchdog database.
   * @param \Drupal\mongodb_watchdog\Logger $logger
   */
  public function __construct(Database $database, LoggerInterface $logger) {
    $this->database = $database;
    $this->logger = $logger;
  }

  /**
   * Controller for mongodb_watchdog.detail.
   *
   * @return array
   *   A render array.
   */
  public function detail() {
    return [];
  }

  /**
   * Controller for mongodb_watchdog.overview.
   *
   * @return array
   *   A render array.
   */
  public function overview() {
    $icons = array(
      LogLevel::DEBUG     => '',
      LogLevel::INFO      => '',
      LogLevel::NOTICE    => '',
      LogLevel::WARNING   => ['#theme' => 'image', 'path' => 'misc/watchdog-warning.png', 'alt' => t('warning'), 'title' => t('warning')],
      LogLevel::ERROR     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('error'), 'title' => t('error')],
      LogLevel::CRITICAL  => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('critical'), 'title' => t('critical')],
      LogLevel::ALERT     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('alert'), 'title' => t('alert')],
      LogLevel::EMERGENCY => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('emergency'), 'title' => t('emergency')],
    );

    $collection = $this->database->selectCollection(Logger::TEMPLATE_COLLECTION);
    $cursor = $collection->find();

    $header = array(
      // Icon column.
      '',
      t('#'),
      array('data' => t('Type')),
      array('data' => t('Date')),
      t('Source'),
      t('Message'),
    );

    $rows = array();
    $this->logger->warning("Some @info", ['@info' => 'Is info']);
    foreach ($cursor as $id => $value) {
      // dsm($value, $id);
//      if ($value['type'] == 'php' && $value['message'] == '%type: %message in %function (line %line of %file).') {
//        $collection = $this->logger->eventCollection($value['_id']);
//        $result = $collection->find()
//                             ->sort(array('$natural' => -1))
//                             ->limit(1)
//                             ->getNext();
//        if ($value) {
//          $value['file'] = basename($result['variables']['%file']);
//          $value['line'] = $result['variables']['%line'];
//          $value['message'] = '%type in %function';
//          $value['variables'] = $result['variables'];
//        }
//      }
      $message = ''; //Unicode::truncate(strip_tags(SafeMarkup::format($value)), 56, TRUE, TRUE);
      //$value['count'] = $this->logger->eventCollection($value['_id'])->count();
      $rows[$id] = array(
        //$icons[$value['severity']],
        isset($value['count']) && $value['count'] > 1 ? intval($value['count']) : 0,
        t($value['type']),
        empty($value['timestamp']) ? '' : format_date($value['timestamp'], 'short'),
        empty($value['file']) ? '' : Unicode::truncate(basename($value['file']), 30) . (empty($value['line']) ? '' : ('+' . $value['line'])),
        \Drupal::l($message, Url::fromRoute('mongodb_watchdog.reports.detail', ['id' => $id])),
      );
    }

    $build['mongodb_watchdog_table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['id' => 'admin-mongodb_watchdog'],
    );


    return $build;
  }

  /**
   * The controller factory.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The DIC.
   *
   * @return static
   *   The database instance.
   */
  public static function create(ContainerInterface $container) {
    /** @var \MongoDB $database */
    $database = $container->get('mongodb.watchdog_storage');
    /** @var \Drupal\mongodb_watchdog\Logger $logger */
    $logger = $container->get('logger.factory')->get('mongodb_watchdog');

    return new static($database, $logger);
  }
}
