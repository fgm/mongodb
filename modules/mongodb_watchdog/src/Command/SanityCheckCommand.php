<?php

namespace Drupal\mongodb_watchdog\Command;

use Doctrine\Common\Util\Debug;
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

  protected $buckets;

  protected $items;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('mongodb:watchdog:sanitycheck')
      ->setDescription($this->trans('Check the sizes of the watchdog collections'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    $this->io = $io = new DrupalStyle($input, $output);

    $this->buildCollectionstats();
    $io->info(print_r($this->buckets, TRUE));
  }

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
    for ($i = 1 ; $i < $barCount ; $i++) {
      $buckets[$i * $barWidth] = 0;
    }
    ksort($buckets);
    $this->buckets = $buckets;
  }

  protected function store(int $value): void {
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

  function buildCollectionstats() {
    /** @var \Drupal\mongodb\DatabaseFactory $df */
    $df = $this->getService('mongodb.database_factory');
    $db = $df->get('default');
    $this->initBucketsList();

    $collections = $db->listCollections();
    foreach ($collections as $collectionInfo) {
      $name = $collectionInfo->getName();// . " " . $collection->count() ."\n"
      $collection = $db->selectCollection($name);
      $count = $collection->count();
      if (preg_match('/' . Logger::EVENT_COLLECTIONS_PATTERN . '/', $name)) {
        $this->store($count);
      }
    }
  }
}
