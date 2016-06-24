<?php
/**
 * @file
 * Contains AdminForm.
 */

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\mongodb\Connection;
use Drupal\mongodb_watchdog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdminForm.
 *
 * @package Drupal\mongodb_watchdog
 */
class AdminForm extends FormBase implements ContainerInjectionInterface {

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
  public function __construct(\MongoDB\database $database, Logger $logger) {
    $this->database = $database;
    $this->logger = $logger;
  }

  /**
   * From for mongodb_watchdog.detail.
   *
   * @return array
   *   A render array.
   */
  public function detail() {
    return [];
  }

  /**
   * Form for mongodb_watchdog.overview.
   *
   * @return array
   *   A render array.
   */
  public function overview() {
    $icons = array(
      WATCHDOG_DEBUG     => '',
      WATCHDOG_INFO      => '',
      WATCHDOG_NOTICE    => '',
      WATCHDOG_WARNING   => ['#theme' => 'image', 'path' => 'misc/watchdog-warning.png', 'alt' => t('warning'), 'title' => t('warning')],
      WATCHDOG_ERROR     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('error'), 'title' => t('error')],
      WATCHDOG_CRITICAL  => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('critical'), 'title' => t('critical')],
      WATCHDOG_ALERT     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('alert'), 'title' => t('alert')],
      WATCHDOG_EMERGENCY => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('emergency'), 'title' => t('emergency')],
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
    foreach ($cursor as $id => $value) {
      dsm($value, $id);
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
      $message = Unicode::truncate(strip_tags(SafeMarkup::format($value)), 56, TRUE, TRUE);
      $value['count'] = $this->logger->eventCollection($value['_id'])->count();
      $rows[$id] = array(
        $icons[$value['severity']],
        isset($value['count']) && $value['count'] > 1 ? intval($value['count']) : 0,
        t($value['type']),
        empty($value['timestamp']) ? '' : format_date($value['timestamp'], 'short'),
        empty($value['file']) ? '' : Unicode::truncate(basename($value['file']), 30) . (empty($value['line']) ? '' : ('+' . $value['line'])),
        \Drupal::l($message, Url::fromRoute('mongodb_watchdog.detail', ['id' => $id])),
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
   * The form factory.
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
    $logger = $container->get('mongodb.logger');

    return new static($database, $logger);
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mongodb_watchdog_overview';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = array();
    $icons = array(
      WATCHDOG_DEBUG     => '',
      WATCHDOG_INFO      => '',
      WATCHDOG_NOTICE    => '',
      WATCHDOG_WARNING   => ['#theme' => 'image', 'path' => 'misc/watchdog-warning.png', 'alt' => t('warning'), 'title' => t('warning')],
      WATCHDOG_ERROR     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('error'), 'title' => t('error')],
      WATCHDOG_CRITICAL  => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('critical'), 'title' => t('critical')],
      WATCHDOG_ALERT     => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('alert'), 'title' => t('alert')],
      WATCHDOG_EMERGENCY => ['#theme' => 'image', 'path' => 'misc/watchdog-error.png', 'alt' => t('emergency'), 'title' => t('emergency')],
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
    foreach ($cursor as $id => $value) {
      dsm($value, $id);
      if ($value['type'] == 'php' && $value['message'] == '%type: %message in %function (line %line of %file).') {
        $collection = $this->logger->eventCollection($value['_id']);
        $result = $collection->find()
                             ->sort(array('$natural' => -1))
                             ->limit(1)
                             ->getNext();
        if ($value) {
          $value['file'] = basename($result['variables']['%file']);
          $value['line'] = $result['variables']['%line'];
          $value['message'] = '%type in %function';
          $value['variables'] = $result['variables'];
        }
      }
      $message = Unicode::truncate(strip_tags(SafeMarkup::format($value['message'], array())), 56, TRUE, TRUE);
      $value['count'] = $this->logger->eventCollection($value['_id'])->count();
      $rows[$id] = array(
        $icons[$value['severity']],
        isset($value['count']) && $value['count'] > 1 ? intval($value['count']) : 0,
        t($value['type']),
        empty($value['timestamp']) ? '' : format_date($value['timestamp'], 'short'),
        empty($value['file']) ? '' : Unicode::truncate(basename($value['file']), 30) . (empty($value['line']) ? '' : ('+' . $value['line'])),
        \Drupal::l($message, Url::fromRoute('mongodb_watchdog.detail', ['id' => $id])),
      );
    }

    $form['mongodb_watchdog_table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['id' => 'admin-mongodb_watchdog'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
}
