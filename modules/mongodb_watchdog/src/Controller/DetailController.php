<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Template\Attribute;
use Drupal\mongodb_watchdog\EventController;
use Drupal\mongodb_watchdog\EventTemplate;
use Drupal\mongodb_watchdog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DetailController implements the controller for the event detail page.
 */
class DetailController implements ContainerInjectionInterface {

  /**
   * The mongodb.watchdog_event_controller service.
   *
   * @var \Drupal\mongodb_watchdog\EventController
   */
  protected $eventController;

  /**
   * The items_per_page configuration value.
   *
   * @var int
   */
  protected $itemsPerPage;

  /**
   * The logger.mongodb_watchdog logger channel, to log events.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The MongoDB logger service, to load events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service, to log intervening events.
   * @param \Drupal\mongodb_watchdog\EventController $event_controller
   *   The event controller service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   */
  public function __construct(Logger $watchdog, LoggerInterface $logger, EventController $event_controller, ImmutableConfig $config) {
    $this->config = $config;
    $this->eventController = $event_controller;
    $this->logger = $logger;
    $this->watchdog = $watchdog;

    $this->itemsPerPage = $config->get('items_per_page');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.mongodb_watchdog');

    /** @var \Drupal\mongodb_watchdog\EventController $eventController */
    $eventController = $container->get('mongodb.watchdog_event_controller');

    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get('mongodb.logger');

    /** @var array $config */
    $config = $container->get('config.factory')->get('mongodb_watchdog.settings');

    return new static($watchdog, $logger, $eventController, $config);
  }

  /**
   * Controller for mongodb_watchdog.detail.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $event_template
   *   The event template.
   *
   * @return array
   *   A render array.
   */
  public function detail(EventTemplate $event_template, Request $request) {
    $page = $this->setupPager($event_template, $request);
    $template_rows = $this->detailHeader($event_template);
    $event_rows = $this->detailRows($event_template, $page);

    $base = [
      '#attributes' => new Attribute(['class' => 'mongodb_watchdog-detail']),
      '#type' => 'table',
    ];

    $event_header = [
      t('Date'),
      t('User'),
      t('Message'),
      t('Location'),
      t('Referrer'),
      t('Hostname'),
      t('Operations'),
    ];

    $ret = [
      "#attached" => [
        'library' => 'mongodb_watchdog/styling',
      ],
      'template' => $base + [
        '#caption' => t('Event template'),
        '#rows' => $template_rows,
      ],
      'events' => $base + [
        '#caption' => t('Event occurrences'),
        '#header' => $event_header,
        '#rows' => $event_rows,
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];

    return $ret;
  }

  /**
   * Build the heading rows on the event occurrences page.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The event template.
   *
   * @return array
   *   A table render array.
   */
  protected function detailHeader(EventTemplate $template) {
    $rows = [];
    foreach (EventTemplate::keys() as $key => $info) {
      $value = $template->{$key};
      $row = [
        [
          'header' => TRUE,
          'data' => $info['label'],
        ],
        isset($info['display_callback']) ? $info['display_callback']($value) : $value,
      ];
      $rows[] = $row;
    }
    return $rows;
  }

  /**
   * Build the occurrence rows on the event occurrences page.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The event template.
   * @param int $page
   *   The page number, starting at 0.
   *
   * @return array
   *   A table render array.
   */
  protected function detailRows(EventTemplate $template, $page) {
    $rows = [];
    $skip = $page * $this->itemsPerPage;
    $limit = $this->itemsPerPage;
    $events = $this->eventController->find($template, $skip, $limit);

    /** @var \Drupal\mongodb_watchdog\Event $event */
    foreach ($events as $event) {
      $rows[] = $this->eventController->asTableRow($template, $event);
    }
    return $rows;
  }

  /**
   * Title callback for mongodb_watchdog.detail.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $event_template
   *   The event template for which the title is built.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function detailTitle(EventTemplate $event_template) {
    return t('MongoDB events: "@template"', ['@template' => $event_template->message]);
  }

  /**
   * Set up the events pager.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return int
   *   The number of the page to display, starting at 0.
   */
  public function setupPager(EventTemplate $template, Request $request) {
    $count = $this->watchdog->eventCount($template);
    $height = $this->itemsPerPage;
    pager_default_initialize($count, $height);

    $page = intval($request->query->get('page'));
    if ($page < 0) {
      $page = 0;
    }
    else {
      $page_max = intval(min(ceil($count / $height), PHP_INT_MAX) - 1);
      if ($page > $page_max) {
        $page = $page_max;
      }
    }

    return $page;
  }

}
