<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\Queue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\QueueInterface;
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
   */
  protected string $collectionName;

  /**
   * The collection holding the queue items.
   */
  protected Collection $mongoDbCollection;

  /**
   * The datetime.time service.
   */
  public TimeInterface $time;

  /**
   * Queue constructor.
   *
   * @param \MongoDB\Collection $collection
   *   The collection holding the queue items.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(Collection $collection, TimeInterface $time) {
    $this->collectionName = $collection->getCollectionName();
    $this->mongoDbCollection = $collection;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $data
   *   The enqueued data. It is stored with serialize(), so it must be
   *   serializable: not a closure, a resource, or an object refusing
   *   serialization, nor contain one.
   *
   * @throws \Exception
   */
  public function createItem($data): string {
    $item = [
      'created' => $this->time->getCurrentTime(),
      // Prevent BSON transform.
      'data' => serialize($data),
      'expires' => 0,
    ];

    $result = $this->mongoDbCollection->insertOne($item);

    return (string) $result->getInsertedId();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function numberOfItems(): int {
    return $this->mongoDbCollection->countDocuments();
  }

  /**
   * {@inheritdoc}
   *
   * @param int $lease_time
   *   The time after which the job will be considered as stuck.
   */
  public function claimItem($lease_time = 30): Item|false {
    $now = $this->time->getCurrentTime();
    /** @var \MongoDB\Model\BSONDocument|null $libRes */
    $libRes = $this->mongoDbCollection->findOneAndUpdate(
      [
        '$or' => [
          ['expires' => ['$lte' => $now]],
          // Items from other producers may omit it.
          ['expires' => ['$exists' => FALSE]],
        ],
      ],
      // A pipeline rather than an operator, to compute "created" from the
      // document itself.
      [
        [
          '$set' => [
            'created' => static::createdExpression($now),
            'expires' => $now + $lease_time,
          ],
        ],
      ],
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
   * Builds the expression setting "created" on a claimed item.
   *
   * Items from other producers may omit "created". An ObjectId _id embeds its
   * creation time, so it is used when available, and the claim time otherwise.
   * Either way the value is stored, so that releasing and claiming the item
   * again does not change it.
   *
   * @param int $now
   *   The claim time.
   *
   * @return array<string,mixed>
   *   An aggregation expression.
   */
  protected static function createdExpression(int $now): array {
    return [
      '$ifNull' => [
        '$created',
        [
          '$cond' => [
            ['$eq' => [['$type' => '$_id'], 'objectId']],
            ['$toLong' => ['$divide' => [['$toLong' => ['$toDate' => '$_id']], 1000]]],
            $now,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Returns TRUE only when the item was claimed and is now released: an
   * unclaimed, deleted or unknown item gives FALSE, like core's Memory queue.
   * Core's DatabaseQueue returns TRUE for an unclaimed item, and other queue
   * backends vary, so callers should not rely on the value across backends.
   *
   * @param \Drupal\mongodb_storage\Queue\Item $item
   *   An item obtained from claimItem().
   *
   * @see https://www.drupal.org/project/mongodb/issues/3626967
   */
  public function releaseItem($item): bool {
    $res = $this->mongoDbCollection
      ->updateOne(
        ['_id' => $item->mongoId()],
        ['$set' => ['expires' => 0]],
      );
    return $res->isAcknowledged()
      && $res->getMatchedCount() == 1
      && $res->getModifiedCount() == 1
      && $res->getUpsertedCount() == 0;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\mongodb_storage\Queue\Item $item
   *   An item obtained from claimItem().
   */
  public function deleteItem($item): void {
    $this->mongoDbCollection->deleteOne(['_id' => $item->mongoId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue(): void {
    // Create the index.
    $this->mongoDbCollection->createIndex([
      'expires' => 1,
      'created' => 1,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue(): void {
    $this->mongoDbCollection->drop();
  }

}
