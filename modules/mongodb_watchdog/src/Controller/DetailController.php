<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Template\Attribute;
use Drupal\mongodb_watchdog\EventController;
use Drupal\mongodb_watchdog\EventTemplate;
use Drupal\mongodb_watchdog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The controller for the event detail page.
 */
class DetailController extends ControllerBase {

  /**
   * The mongodb.watchdog_event_controller service.
   *
   * @var \Drupal\mongodb_watchdog\EventController
   */
  protected $eventController;

  /**
   * Controller constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service, to log intervening events.
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger, to load stored events.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   * @param \Drupal\mongodb_watchdog\EventController $eventController
   *   The event controller service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pagerManager
   *   The core pager.manager service.
   */
  public function __construct(
    LoggerInterface $logger,
    Logger $watchdog,
    ImmutableConfig $config,
    EventController $eventController,
    PagerManagerInterface $pagerManager) {
    parent::__construct($logger, $watchdog, $pagerManager, $config);

    $this->eventController = $eventController;
  }

  /**
   * Controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\mongodb_watchdog\EventTemplate $eventTemplate
   *   The event template.
   *
   * @return array<string,mixed>
   *   A render array.
   */
  public function build(Request $request, EventTemplate $eventTemplate): array {
    $top = $this->getTop($eventTemplate);

    $rows = $this->getRowData($request, $eventTemplate);
    $main = empty($rows)
      ? $this->buildEmpty($this->t('No occurrence of this event found in logger.'))
      : $this->buildMainTable($rows, $eventTemplate);

    $ret = $this->buildDefaults($main, $top);
    return $ret;
  }

  /**
   * Build the main table.
   *
   * @param \Drupal\mongodb_watchdog\Event[] $events
   *   The event data.
   * @param \Drupal\mongodb_watchdog\EventTemplate $eventTemplate
   *   The template for which to built the detail lines.
   *
   * @return array<string,mixed>
   *   A render array for the main table.
   */
  protected function buildMainTable(array $events, EventTemplate $eventTemplate): array {
    $ret = [
      '#attributes' => new Attribute(['class' => 'mongodb_watchdog__detail']),
      '#caption' => $this->t('Event occurrences'),
      '#header' => $this->buildMainTableHeader(),
      '#rows' => $this->buildMainTableRows($events, $eventTemplate),
      '#type' => 'table',
    ];

    return $ret;
  }

  /**
   * Build the main table header.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   A table header array.
   */
  protected function buildMainTableHeader(): array {
    $header = [
      $this->t('Date'),
      $this->t('User'),
      $this->t('Message'),
      $this->t('Location'),
      $this->t('Referrer'),
      $this->t('Hostname'),
      $this->t('Operations'),
    ];

    return $header;
  }

  /**
   * Build the main table rows.
   *
   * @param \Drupal\mongodb_watchdog\Event[] $events
   *   The event row data.
   * @param \Drupal\mongodb_watchdog\EventTemplate $eventTemplate
   *   The template for these events.
   *
   * @return array<int,\Drupal\Core\Link[]|\Drupal\Core\StringTranslation\TranslatableMarkup[]|null[]|string[]>
   *   A render array for a table.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function buildMainTableRows(array $events, EventTemplate $eventTemplate): array {
    $rows = [];

    foreach ($events as $event) {
      // @todo bring this back from "model": it is a display method.
      $rows[] = $this->eventController->asTableRow($eventTemplate, $event);
    }

    return $rows;
  }

  /**
   * Title callback for mongodb_watchdog.detail.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $eventTemplate
   *   The event template for which the title is built.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The page title.
   */
  public function buildTitle(EventTemplate $eventTemplate): MarkupInterface {
    return $this->t('MongoDB events: "@template"', ['@template' => $eventTemplate->message]);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.mongodb_watchdog');

    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get(Logger::SERVICE_LOGGER);

    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $container->get('config.factory')->get('mongodb_watchdog.settings');

    /** @var \Drupal\mongodb_watchdog\EventController $eventController */
    $eventController = $container->get('mongodb.watchdog_event_controller');

    /** @var \Drupal\Core\Pager\PagerManagerInterface $pagerManager */
    $pagerManager = $container->get('pager.manager');

    return new static($logger, $watchdog, $config, $eventController, $pagerManager);
  }

  /**
   * Obtain the data from the logger.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request. Needed for paging.
   * @param \Drupal\mongodb_watchdog\EventTemplate $eventTemplate
   *   The template for which to build the detail page.
   *
   * @return \Drupal\mongodb_watchdog\Event[]
   *   The data array.
   */
  protected function getRowData(Request $request, EventTemplate $eventTemplate): array {
    $count = $this->watchdog->eventCount($eventTemplate);
    $page = $this->setupPager($request, $count);
    $skip = $page * $this->itemsPerPage;
    $limit = $this->itemsPerPage;

    $rows = $this->eventController
      ->find($eventTemplate, $skip, $limit)
      ->toArray();

    return $rows;
  }

  /**
   * Build the heading rows on the per-template event occurrences page.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate|null $eventTemplate
   *   The template for which to provide details. Not actually expected to be
   *   NULL, but this is needed to remain compatible with parent class.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   A render array for a table.
   */
  protected function getTop(EventTemplate $eventTemplate = NULL): array {
    $rows = [];
    foreach ($eventTemplate->keys() as $key => $info) {
      $value = $eventTemplate->{$key};
      $row = [];
      $row[] = [
        'header' => TRUE,
        'data' => $info['label'],
      ];
      $row[] = isset($info['display_callback']) ? $info['display_callback']($value) : $value;
      $rows[] = $row;
    }

    $ret = [
      '#caption' => $this->t('Event template'),
      '#rows' => $rows,
      '#type' => 'table',
    ];

    return $ret;
  }

}
