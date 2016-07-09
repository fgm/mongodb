<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\mongodb_watchdog\Event;
use Drupal\mongodb_watchdog\EventTemplate;
use Drupal\mongodb_watchdog\Form\OverviewFilterForm;
use Drupal\mongodb_watchdog\Logger;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OverviewController provides the main MongoDB Watchdog report page.
 */
class OverviewController extends ControllerBase {
  const EVENT_TYPE_MAP = [
    'typeMap' => [
      'array' => 'array',
      'document' => 'array',
      'root' => 'Drupal\mongodb_watchdog\Event',
    ],
  ];
  const SEVERITY_PREFIX = 'mongodb_watchdog__severity_';
  const SEVERITY_CLASSES = [
    RfcLogLevel::DEBUG => self::SEVERITY_PREFIX . LogLevel::DEBUG,
    RfcLogLevel::INFO => self::SEVERITY_PREFIX . LogLevel::INFO,
    RfcLogLevel::NOTICE => self::SEVERITY_PREFIX . LogLevel::NOTICE,
    RfcLogLevel::WARNING => self::SEVERITY_PREFIX . LogLevel::WARNING,
    RfcLogLevel::ERROR => self::SEVERITY_PREFIX . LogLevel::ERROR,
    RfcLogLevel::CRITICAL => self::SEVERITY_PREFIX . LogLevel::CRITICAL,
    RfcLogLevel::ALERT => self::SEVERITY_PREFIX . LogLevel::ALERT,
    RfcLogLevel::EMERGENCY => self::SEVERITY_PREFIX . LogLevel::EMERGENCY,
  ];

  /**
   * The core date.formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The length of the disk path to DRUPAL_ROOT.
   *
   * @var int
   *
   * @see \Drupal\mongodb_watchdog\Controller\OverviewController::getEventSource()
   */
  protected $rootLength;

  /**
   * Controller constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service, to log intervening events.
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger, to load stored events.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   A module handler.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The core date_formatter service.
   */
  public function __construct(
    LoggerInterface $logger,
    Logger $watchdog,
    ImmutableConfig $config,
    ModuleHandlerInterface $module_handler,
    FormBuilderInterface $form_builder,
    DateFormatterInterface $date_formatter) {
    parent::__construct($logger, $watchdog, $config);

    $this->dateFormatter = $date_formatter;
    $this->formBuilder = $form_builder;
    $this->moduleHandler = $module_handler;

    // Add terminal "/".
    $this->rootLength = Unicode::strlen(DRUPAL_ROOT);

  }

  /**
   * Controller.
   *
   * @return array
   *   A render array.
   */
  public function build(Request $request) {
    $count = $this->watchdog->templatesCount();
    $page = $this->setupPager($request, $count);

    $ret = [
      'filter_form' => $this->formBuilder->getForm('Drupal\mongodb_watchdog\Form\OverviewFilterForm'),
      'rows' => $this->buildRows($page),
      'pager' => [
        '#type' => 'pager',
      ],
      '#attached' => [
        'library' => ['mongodb_watchdog/styling'],
      ],
    ];

    return $ret;
  }

  /**
   * Build a table from the event rows.
   *
   * @param int $page
   *   The number of the page to display.
   *
   * @return array<string,array|string>
   *   A render array.
   */
  public function buildRows($page) {
    $header = [
      t('#'),
      t('Latest'),
      t('Severity'),
      t('Type'),
      t('Message'),
      t('Source'),
    ];
    $rows = [];
    $levels = RfcLogLevel::getLevels();
    $filters = $_SESSION[OverviewFilterForm::SESSION_KEY] ?? NULL;
    $skip = $page * $this->itemsPerPage;
    $limit = $this->itemsPerPage;
    $cursor = $this->watchdog->templates($filters['type'] ?? [], $filters['severity'] ?? [], $skip, $limit);

    /** @var \Drupal\mongodb_watchdog\EventTemplate $template */
    foreach ($cursor as $template) {
      $row = [];
      $row[] = $template->count;
      $row[] = $this->dateFormatter->format($template->changed, 'short');
      $row[] = [
        'class' => static::SEVERITY_CLASSES[$template->severity],
        'data' => $levels[$template->severity],
      ];
      $row[] = $template->type;
      $row[] = $this->getEventLink($template);
      $row[] = [
        'data' => $this->getEventSource($template, $row),
      ];

      $rows[] = $row;
    }

    $ret = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.mongodb_watchdog');

    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get('mongodb.logger');

    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $container->get('config.factory')->get('mongodb_watchdog.settings');

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = $container->get('date.formatter');

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $container->get('form_builder');

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $container->get('module_handler');

    return new static($logger, $watchdog, $config, $module_handler, $form_builder, $date_formatter);
  }

  /**
   * Build the link to the event or top report for the event template.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The event template for which to buildl the link.
   *
   * @return string
   *   An internal link in string form.
   */
  protected function getEventLink(EventTemplate $template) {
    switch ($template->type) {
      case 'page not found':
        $cell = Link::createFromRoute(t('( Top 404 )'), 'mongodb_watchdog.reports.top404');
        break;

      case 'access denied':
        $cell = Link::createFromRoute(t('( Top 403 )'), 'mongodb_watchdog.reports.top403');
        break;

      // Limited-length message.
      default:
        $message = Unicode::truncate(strip_tags(SafeMarkup::format($template->message, [])), 56, TRUE, TRUE);
        $cell = Link::createFromRoute($message, 'mongodb_watchdog.reports.detail', [
          'event_template' => $template->_id,
        ]);
        break;
    }

    return $cell;
  }

  /**
   * Get the location in source code where the event was logged.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The template for which to find a source location.
   *
   * @return array
   *   A render array for the source location, possibly empty or wrong.
   */
  protected function getEventSource(EventTemplate $template) {
    $cell = ['#markup' => ''];

    if (in_array($template->type, TopController::TYPES)) {
      return $cell;
    }

    $event_collection = $this->watchdog->eventCollection($template->_id);
    $event = $event_collection->findOne([], static::EVENT_TYPE_MAP);
    if (!($event instanceof Event)) {
      return $cell;
    }

    $file = $event->variables['%file'] ?? '';
    if ($file && strncmp($file, DRUPAL_ROOT, $this->rootLength) === 0) {
      $hover = Unicode::substr($file, $this->rootLength + 1);
      $file = Unicode::truncate(basename($file), 30);
    }
    else {
      $hover = NULL;
    }

    $line = $event->variables['%line'] ?? NULL;
    $cell = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => "${file}#${line}",
    ];

    if ($hover) {
      $cell['#attributes'] = [
        'class' => 'mongodb_watchdog__code_path',
        'title' => $hover,
      ];
    }

    return $cell;
  }

}
