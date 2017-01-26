<?php

namespace Drupal\mongodb_watchdog\Command;

use Drupal\Console\Command\ContainerAwareCommand;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Style\DrupalStyle;

/**
 * Class SanityCheckCommand.
 *
 * @package Drupal\mongodb_watchdog
 */
class SanityCheckCommand extends ContainerAwareCommand {

  /**
   * The per-collection-size statistics buckets.
   *
   * @var array
   */
  protected $buckets;

  /**
   * The bucket size values.
   *
   * @var array
   */
  protected $items;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('mongodb:watchdog:sanitycheck')
      ->setDescription($this->trans('Check the sizes of the watchdog collections'))
      ->setHelp($this->trans(<<<HELP
This command produces a list of the sizes of the watchdog capped collections,
grouped by "bucket". The bucket sizes are 0 (empty collection), 1 (single document), one bucket for each fraction of the size of the capping limit
(which should be the typical case), one for capping limit - 1, and one for the
capping limit itself, showing events occurring too often for the configured
limit.

For example: with a typical capping limit of 10000, the list will be made of
the following buckers: 0, 1, 2-1000, 1001-2000, 2001-3000, ... 9000-9998,
9999, and 10000.
HELP
      ));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    $this->io = $io = new DrupalStyle($input, $output);

    $this->buildCollectionstats();
    $io->info(print_r($this->buckets, TRUE));
  }

  /**
   * Prepare a table of bucket to hold the statistics.
   */
  protected function initBucketsList() {

    $config = $this->getConfigFactory()->get(Logger::CONFIG_NAME);
    $this->items = $items = $config->get('items');
    unset($config);

    $barCount = 10;
    $barWidth = $items / $barCount;
    $buckets = [
      0 => 0,
      1 => 0,
      $items - 1 => 0,
      $items => 0,
    ];

    // 0, 1 and $items are reserved.
    for ($i = 1; $i < $barCount; $i++) {
      $buckets[$i * $barWidth] = 0;
    }
    ksort($buckets);
    $this->buckets = $buckets;
  }

  /**
   * Store a collection document count in its statistics bucket.
   *
   * @param int $value
   *   The value to store.
   */
  protected function store(int $value) {
    if ($value <= 1 || $value >= $this->items - 1) {
      $this->buckets[$value]++;
      return;
    }
    $keys = array_slice(array_keys($this->buckets), 1, -1);

    foreach ($keys as $key) {
      if ($value < $key) {
        $this->buckets[$key]++;
        return;
      }
    }
  }

  /**
   * Build a list of the number of entries per collection in the default DB.
   */
  public function buildCollectionstats() {
    /** @var \Drupal\mongodb\DatabaseFactory $df */
    $df = $this->getService('mongodb.database_factory');
    $db = $df->get('default');
    $this->initBucketsList();

    $collections = $db->listCollections();
    foreach ($collections as $collectionInfo) {
      $name = $collectionInfo->getName();
      $collection = $db->selectCollection($name);
      $count = $collection->count();
      if (preg_match('/' . Logger::EVENT_COLLECTIONS_PATTERN . '/', $name)) {
        $this->store($count);
      }
    }
  }

}
