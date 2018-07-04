<?php

namespace Drupal\mongodb_watchdog\Command;

// @codingStandardsIgnoreLine
use Drupal\Console\Annotations\DrupalCommand;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\mongodb\MongoDb;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SanityCheckCommand.
 *
 * @DrupalCommand (
 *     extension="mongodb_watchdog",
 *     extensionType="module"
 * )
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
      ->setDescription($this->trans('commands.mongodb.watchdog.sanitycheck.description'))
      ->setHelp($this->trans('commands.mongodb.watchdog.sanitycheck.help'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->buildCollectionstats();
    /** @var \Drupal\Core\Serialization\Yaml $serializer */
    $serializer = $this->get('serialization.yaml');
    $this->getIo()->writeln($serializer->encode($this->buckets));
  }

  /**
   * Prepare a table of bucket to hold the statistics.
   */
  protected function initBucketsList() {
    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->get('config.factory')->get(Logger::CONFIG_NAME);
    $this->items = $items = $config->get(Logger::CONFIG_ITEMS);
    unset($config);

    $barCount = 10;
    $barWidth = $items / $barCount;
    $buckets = [
      0 => 0,
      1 => 0,
      $items - 1 => 0,
      $items => 0,
    ];

    // Values 0, 1 and the value of $items are reserved.
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
    /** @var \Drupal\mongodb\DatabaseFactory $databaseFactory */
    $databaseFactory = $this->get(MongoDb::SERVICE_DB_FACTORY);
    $database = $databaseFactory->get(Logger::DB_LOGGER);
    $this->initBucketsList();

    $collections = $database->listCollections();
    foreach ($collections as $collectionInfo) {
      $name = $collectionInfo->getName();
      $collection = $database->selectCollection($name);
      $count = $collection->count();
      if (preg_match('/' . Logger::EVENT_COLLECTIONS_PATTERN . '/', $name)) {
        $this->store($count);
      }
    }
  }

}
