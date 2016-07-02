<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Template\Attribute;
use Drupal\mongodb_watchdog\EventController;
use Drupal\mongodb_watchdog\EventTemplate;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service, to log intervening events.
   * @param \Drupal\mongodb_watchdog\EventController $event_controller
   *   The event controller service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   */
  public function __construct(LoggerInterface $logger, EventController $event_controller, ImmutableConfig $config) {
    $this->config = $config;
    $this->eventController = $event_controller;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.mongodb_watchdog');

    /** @var \Drupal\mongodb_watchdog\EventController $eventController */
    $eventController = $container->get('mongodb.watchdog_event_controller');

    /** @var array $config */
    $config = $container->get('config.factory')->get('mongodb_watchdog.settings');

    return new static($logger, $eventController, $config);
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
  public function detail(EventTemplate $event_template) {
    $template_rows = $this->detailHeader($event_template);
    $event_rows = $this->detailRows($event_template);

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
    ];

    if ($footer = $this->detailFooter($event_template, $event_rows)) {
      $ret['extra'] = [
        '#markup' => $footer,
      ];
    }

    return $ret;
  }

  /**
   * Report on the extra event occurrences.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $event_template
   *   The event template.
   * @param array $event_rows
   *   The event rows array.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   A message about the remaining events not displayed on the page, or NULL
   *   if there are no additional messages.
   *
   * @XXX Maybe replace by a pager later ?
   */
  protected function detailFooter(EventTemplate $event_template, array $event_rows) {
    $display_count = count($event_rows);
    $ret = NULL;
    if ($display_count >= $this->config->get('items_per_page')) {
      $count = $this->eventController->count($event_template);
      if ($count > $display_count) {
        $ret = t('There are also @count more older occurrences of this event', [
          '@count' => $count - $display_count,
        ]);
      }
    }
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
      $rows[] = [
        [
          'header' => TRUE,
          'data' => $info['label'],
        ],
        isset($info['display_callback']) ? $info['display_callback']($value) : $value,
      ];
    }
    return $rows;
  }

  /**
   * Build the occurrence rows on the event occurrences page.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The event template.
   *
   * @return array
   *   A table render array.
   */
  protected function detailRows(EventTemplate $template) {
    $rows = [];

    $events = $this->eventController->find($template, NULL, $this->config->get('items_per_page'));
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

}
