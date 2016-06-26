<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\mongodb_watchdog\Logger;
use MongoDB\Database;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;


/**
 * Class OverviewController provides the main MongoDB Watchdog report page.
 */
class OverviewController extends ControllerBase {

  /**
   * The MongoDB database for the logger alias.
   *
   * @var \MongoDB
   */
  protected $database;

  /**
   * The core logger channel, to log intervening events.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The MongoDB logger, to load events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructor.
   *
   * @param \MongoDB $database
   *   The watchdog database.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service, to log intervening events.
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger, to load stored events.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   A module handler.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(Database $database, LoggerInterface $logger, Logger $watchdog, ModuleHandlerInterface $module_handler, FormBuilderInterface $form_builder) {
    $this->database = $database;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->formBuilder = $form_builder;
    $this->watchdog = $watchdog;
  }

  /**
   * Controller for mongodb_watchdog.overview.
   *
   * @return array
   *   A render array.
   */
  public function overview() {
    $icons = array(
      RfcLogLevel::DEBUG     => '',
      RfcLogLevel::INFO      => '',
      RfcLogLevel::NOTICE    => '',
      RfcLogLevel::WARNING   => ['#theme' => 'image', 'path' => 'misc/watchdog-warning.png', 'alt' => t('warning'), 'title' => t('warning')],
      RfcLogLevel::ERROR     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('error'), 'title' => t('error')],
      RfcLogLevel::CRITICAL  => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('critical'), 'title' => t('critical')],
      RfcLogLevel::ALERT     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('alert'), 'title' => t('alert')],
      RfcLogLevel::EMERGENCY => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('emergency'), 'title' => t('emergency')],
    );

    $collection = $this->watchdog->templateCollection();
    $templates = $collection->find([], Logger::LEGACY_TYPE_MAP)->toArray();
ksm($templates);
    $this->moduleHandler->loadInclude('mongodb_watchdog', 'admin.inc');

    $build['dblog_filter_form'] = $this->formBuilder->getForm('Drupal\mongodb_watchdog\Form\MongodbWatchdogFilterForm');

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
    foreach ($templates as $id => $value) {
      if ($id < 5) {
//        if ($value['type'] == 'php' && $value['message'] == '%type: %message in %function (line %line of %file).') {
//          $collection = $this->logger->eventCollection($value['_id']);
//          $result = $collection->find()
//            ->sort(['$natural' => -1])
//            ->limit(1)
//            ->getNext();
//          if ($value) {
//            $value['file'] = basename($result['variables']['%file']);
//            $value['line'] = $result['variables']['%line'];
//            $value['message'] = '%type in %function';
//            $value['variables'] = $result['variables'];
//          }
//        }
        $message = Unicode::truncate(strip_tags(SafeMarkup::format($value['message'], [])), 56, TRUE, TRUE);
        $value['count'] = $this->watchdog->eventCollection($value['_id'])->count();
        $rows[$id] = [
          $icons[$value['severity']],
          isset($value['count']) && $value['count'] > 1 ? intval($value['count']) : 0,
          t($value['type']),
          empty($value['timestamp']) ? '' : format_date($value['timestamp'], 'short'),
          empty($value['file']) ? '' : Unicode::truncate(basename($value['file']), 30) . (empty($value['line']) ? '' : ('+' . $value['line'])),
          \Drupal::l($message, Url::fromRoute('mongodb_watchdog.reports.detail', ['event_template' => $id])),
        ];
      }

    }
kint($rows);
    $build['mongodb_watchdog_table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['id' => 'admin-mongodb_watchdog'],
      '#attached' => array(
        'library' => array('mongodb_watchdog/drupal.mongodb_watchdog'),
      ),
    );

    $build['mongodb_watchdog_pager'] = array('#type' => 'pager');

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

    $form_builder = $container->get('form_builder');

    $module_handler = $container->get('module_handler');

    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.mongodb_watchdog');

    /** @var \Drupal\mongodb_watchdog\Logger $logger */
    $watchdog = $container->get('mongodb.logger');

    return new static($database, $logger, $watchdog, $module_handler, $form_builder);
  }
}
