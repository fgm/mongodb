<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\Queue;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Queue\QueueInterface;
use Drupal\mongodb\MongoDb;
use MongoDB\Collection;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * Class Queue provides a ReliableQueue as a MongoDB collection.
 *
 * @ingroup queue
 */
class Queue implements QueueInterface {

  /**
   * The MongoDB collection name, like "q_foo" for queue "foo".
   *
   * @var string
   */
  protected string $collectionName;

  /**
   * The collection holding the queue items.
   *
   * @var \MongoDB\Collection
   */
  protected Collection $mongoDbCollection;

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected Time $time;

  /**
   * Queue constructor.
   *
   * @param \MongoDB\Collection $collection
   *   The collection holding the queue items.
   * @param \Drupal\Component\Datetime\Time $time
   *   The datetime.time service.
   */
  public function __construct(Collection $collection, Time $time) {
    $this->collectionName = $collection->getCollectionName();
    $this->mongoDbCollection = $collection;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    return [
      'collectionName',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * The __wakeup() method cannot use the container, because its constructor is
   * never invoked, and the container itself must not be serialized.
   */
  public function __wakeup() {
    /** @var \Drupal\mongodb\DatabaseFactory $databaseFactory */
    $dbFactory = \Drupal::service(MongoDb::SERVICE_DB_FACTORY);

    /** @var \MongoDB\Database $database */
    $database = $dbFactory->get(QueueFactory::DB_QUEUE);
    $this->mongoDbCollection = $database->selectCollection($this->mongoDbCollectionName);
    $this->time = \Drupal::service("datetime.time");
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function createItem($data) {
    $item = [
      'created' => $this->time->getCurrentTime(),
      // Prevent BSON transform.
      'data' => serialize($data),
      'expires' => 0,
    ];

    $result = $this->mongoDbCollection->insertOne($item);

    return $result->getInsertedId();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function numberOfItems(): int {
    return MongoDb::countCollection($this->mongoDbCollection);
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30): Item|bool {
    $newobj = [
      'expires' => time() + $lease_time,
    ];
    /** @var \MongoDB\Model\BSONDocument|null $libRes */
    $libRes = $this->mongoDbCollection->findOneAndUpdate(
      ['expires' => 0],
      ['$set' => $newobj],
      [
        'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
        'sort' => ['created' => 1],
      ],
    );
    if (is_null($libRes)) {
      return FALSE;
    }
    return Item::fromDoc($libRes);
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item): bool {
    $res = $this->mongoDbCollection
      ->updateOne(
        ['_id' => $item->_id],
        [
          '$set' =>
            [
              'expires' => 0,
            ],
        ]
      );
    return $res->isAcknowledged();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) {
    $this->mongoDbCollection->deleteOne(['_id' => $item->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() {
    // Create the index.
    $this->mongoDbCollection->createIndex([
      'expires' => 1,
      'created' => 1,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {
    $this->mongoDbCollection->drop();
  }

}
