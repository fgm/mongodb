<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\RenderableInterface;
use Drupal\mongodb_watchdog\Event;
use Drupal\mongodb_watchdog\EventTemplate;
use Drupal\mongodb_watchdog\Form\OverviewFilterForm;
use Drupal\mongodb_watchdog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The controller for the logger overview page.
 *
 * D8 has no session API, so use of $_SESSION is required, so ignore warnings.
 *
 * @SuppressWarnings("PHPMD.Superglobals")
 */
class OverviewController extends ControllerBase {
  const EVENT_TYPE_MAP = [
    'typeMap' => [
      'array' => 'array',
      'document' => 'array',
      'root' => 'Drupal\mongodb_watchdog\Event',
    ],
  ];
  const SEVERITY_PREFIX = 'mongodb-watchdog__severity--';
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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   A module handler.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The core date_formatter service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pagerManager
   *   The core pager.manager service.
   */
  public function __construct(
    LoggerInterface $logger,
    Logger $watchdog,
    ImmutableConfig $config,
    ModuleHandlerInterface $moduleHandler,
    FormBuilderInterface $formBuilder,
    DateFormatterInterface $dateFormatter,
    PagerManagerInterface $pagerManager) {
    parent::__construct($logger, $watchdog, $pagerManager, $config);

    $this->dateFormatter = $dateFormatter;
    $this->formBuilder = $formBuilder;
    $this->moduleHandler = $moduleHandler;

    // Add terminal "/".
    $this->rootLength = mb_strlen(DRUPAL_ROOT);
  }

  /**
   * Controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array<string,mixed>
   *   A render array.
   *
   * @throws \ReflectionException
   */
  public function build(Request $request): array {
    $top = $this->getTop();

    $rows = $this->getRowData($request);
    $main = empty($rows)
      ? $this->buildEmpty($this->t('No event found in logger.'))
      : $this->buildMainTable($rows);

    $ret = $this->buildDefaults($main, $top);
    return $ret;
  }

  /**
   * Build the main table.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate[] $rows
   *   The template data.
   *
   * @return array<string,mixed>
   *   A render array for the main table.
   */
  protected function buildMainTable(array $rows): array {
    $ret = [
      '#header' => $this->buildMainTableHeader(),
      '#rows' => $this->buildMainTableRows($rows),
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
      $this->t('#'),
      $this->t('Latest'),
      $this->t('Severity'),
      $this->t('Type'),
      $this->t('Message'),
      $this->t('Source'),
    ];

    return $header;
  }

  /**
   * Build the main table rows.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate[] $templates
   *   The event template data.
   *
   * @return array<int,mixed[]>
   *   A render array for the rows of a table.
   */
  protected function buildMainTableRows(array $templates): array {
    $rows = [];
    $levels = RfcLogLevel::getLevels();

    foreach ($templates as $template) {
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
        'data' => $this->getEventSource($template),
      ];

      $rows[] = $row;
    }

    return $rows;
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

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter */
    $dateFormatter = $container->get('date.formatter');

    /** @var \Drupal\Core\Form\FormBuilderInterface $formBuilder */
    $formBuilder = $container->get('form_builder');

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler */
    $moduleHandler = $container->get('module_handler');

    /** @var \Drupal\Core\Pager\PagerManagerInterface $pagerManager */
    $pagerManager = $container->get('pager.manager');

    return new static($logger, $watchdog, $config, $moduleHandler, $formBuilder, $dateFormatter, $pagerManager);
  }

  /**
   * Build the link to the event or top report for the event template.
   *
   * @param \Drupal\mongodb_watchdog\EventTemplate $template
   *   The event template for which to buildl the link.
   *
   * @return \Drupal\Core\Render\RenderableInterface
   *   An internal link in renderable form.
   */
  protected function getEventLink(EventTemplate $template): RenderableInterface {
    switch ($template->type) {
      case 'page not found':
        $cell = Link::createFromRoute($this->t('( Top 404 )'), 'mongodb_watchdog.reports.top404');
        break;

      case 'access denied':
        $cell = Link::createFromRoute($this->t('( Top 403 )'), 'mongodb_watchdog.reports.top403');
        break;

      // Limited-length message.
      default:
        $markup = new FormattableMarkup($template->message, []);
        $message = Unicode::truncate(strip_tags($markup->__toString()),
          56, TRUE, TRUE);
        $cell = Link::createFromRoute($message, 'mongodb_watchdog.reports.detail', [
          'eventTemplate' => $template->_id,
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
   * @return array<string,mixed>
   *   A render array for the source location, possibly empty or wrong.
   */
  protected function getEventSource(EventTemplate $template): array {
    $cell = ['#markup' => ''];

    if (in_array($template->type, TopController::TYPES)) {
      return $cell;
    }

    $eventCollection = $this->watchdog->eventCollection($template->_id);
    $event = $eventCollection->findOne([], static::EVENT_TYPE_MAP);
    if (!($event instanceof Event)) {
      return $cell;
    }

    $file = $event->variables['%file'] ?? '';
    if ($file && strncmp($file, DRUPAL_ROOT, $this->rootLength) === 0) {
      $hover = mb_substr($file, $this->rootLength + 1);
      $file = Unicode::truncate(basename($file), 30);
    }
    else {
      $hover = '';
    }

    $line = $event->variables['%line'] ?? '';
    $cell = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => implode("#", [$file, $line]),
    ];

    if ($hover !== '') {
      $cell['#attributes'] = [
        'class' => 'mongodb-watchdog__code-path',
        'title' => $hover,
      ];
    }

    return $cell;
  }

  /**
   * Obtain the data from the logger.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request. Needed for paging.
   *
   * @return array<scalar,mixed>
   *   The data array.
   *
   * @throws \ReflectionException
   */
  protected function getRowData(Request $request): array {
    $count = $this->watchdog->templatesCount();
    $page = $this->setupPager($request, $count);
    $skip = $page * $this->itemsPerPage;
    $limit = $this->itemsPerPage;

    $filters = $_SESSION[OverviewFilterForm::SESSION_KEY] ?? NULL;

    $rows = $this->watchdog
      ->templates($filters['type'] ?? [], $filters['severity'] ?? [], $skip, $limit)
      ->toArray();

    return $rows;
  }

  /**
   * Return the top element.
   *
   * @return array<string,mixed>
   *   A render array for the top filter form.
   */
  protected function getTop(): array {
    $top = $this->formBuilder->getForm('Drupal\mongodb_watchdog\Form\OverviewFilterForm');
    return $top;
  }

}
