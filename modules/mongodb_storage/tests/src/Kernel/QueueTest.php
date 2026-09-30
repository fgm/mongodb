<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_storage\Kernel;

use Drupal\Core\Queue\QueueInterface;
use Drupal\KernelTests\Core\Queue\QueueTest as coreQueueTest;
use Drupal\mongodb\MongoDb;
use Drupal\mongodb_storage\Queue\Item;
use Drupal\mongodb_storage\Queue\QueueFactory;
use Drupal\mongodb_storage\Storage;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;

/**
 * Queues and dequeues a set of items to check the basic queue functionality.
 *
 * @coversDefaultClass \Drupal\mongodb_storage\Queue\Queue
 *
 * @group MongoDB
 */
class QueueTest extends QueueTestBase {

  /**
   * The queue.mongodb service.
   */
  protected ?QueueFactory $queueFactory;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->queueFactory = $this->container->get(Storage::SERVICE_QUEUE);
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    $this->queueFactory = NULL;
    parent::tearDown();
  }

  /**
   * Creates a queue Item from the data specified or generated.
   *
   * @param \Drupal\Core\Queue\QueueInterface $q
   *   An already created queue.
   * @param mixed $data
   *   The data to use for the item, or NULL to generate a pseudo-random item.
   *
   * @return \Drupal\mongodb_storage\Queue\Item
   *   An item based on $data after insertion.
   */
  protected function createTestItem(QueueInterface $q, mixed $data = NULL): Item {
    $c1 = $q->numberOfItems();
    if (empty($data)) {
      $data = [$this->randomMachineName() => $this->randomObject()];
    }
    // Now will always be <= the actual stored item.
    $now = $this->container->get('datetime.time')->getCurrentTime();
    /** @var string $id */
    $id = $q->createItem($data);
    $this->assertIsString($id);
    $this->assertEquals($c1 + 1, $q->numberOfItems());
    return Item::fromDoc(
      new BSONDocument([
        '_id' => $id,
        'created' => $now,
        'expires' => 0,
        'data' => serialize($data),
      ])
    );
  }

  /**
   * Create a queue from the given name or a pseudo-random one.
   *
   * @param string $name
   *   The name to use, or empty to generate a pseudo-random name.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   A queue instance.
   */
  protected function createQueue(string $name = ''): QueueInterface {
    if (empty($name)) {
      $name = $this->randomMachineName();
    }
    $q = $this->queueFactory->get($name);
    $q->createQueue();
    $this->assertEquals(0, $q->numberOfItems());
    return $q;
  }

  /**
   * Test that expired claims automatically release items.
   */
  public function testClaimTimeout(): void {
    $q = $this->createQueue();
    $id = $this->createTestItem($q)->id();

    /** @var \Drupal\mongodb_storage\Queue\Item|false $claimed */
    $claimed = $q->claimItem(0);
    $this->assertInstanceOf(Item::class, $claimed);
    $this->assertEquals($id, $claimed->id());

    // Since the claim expired immediately, the item is available again.
    $c2 = $q->claimItem();
    $this->assertInstanceOf(Item::class, $c2);
    $this->assertEquals($id, $c2->id());
  }

  /**
   * Validates collection creation and removal.
   */
  public function testCreateDeleteQueue(): void {
    $name = $this->randomMachineName();
    $expectedName = "q_$name";

    /** @var \Drupal\mongodb\DatabaseFactory $dbf */
    $dbf = $this->container->get(MongoDb::SERVICE_DB_FACTORY);
    $db = $dbf->get('queue');

    $q = $this->queueFactory->get($name);
    $q->createQueue();
    $actualNames = $db->listCollectionNames();
    $this->assertContains(
      $expectedName,
      $actualNames,
      "Creating queue $name did not create collection $expectedName",
    );

    $q->deleteQueue();
    $actualNames = $db->listCollectionNames();
    $this->assertNotContains(
      $expectedName,
      $actualNames,
      "Deleting queue $name did not remove collection $expectedName",
    );
  }

  /**
   * Items written by other producers can be claimed, released and deleted.
   *
   * Such producers may omit "created" and "expires", and use any _id type.
   */
  public function testForeignItems(): void {
    $name = $this->randomMachineName();
    $q = $this->createQueue($name);
    /** @var \Drupal\mongodb\DatabaseFactory $dbf */
    $dbf = $this->container->get(MongoDb::SERVICE_DB_FACTORY);
    $collection = $dbf->get(QueueFactory::DB_QUEUE)->selectCollection("q_$name");
    $objectId = new ObjectId();
    $collection->insertMany([
      ['_id' => $objectId, 'data' => serialize('objectId')],
      ['_id' => 'foreign-id', 'data' => serialize('string')],
    ]);

    $time = $this->container->get('datetime.time');
    $before = $time->getCurrentTime();
    $claimed = $this->claimAll($q);
    $after = $time->getCurrentTime();
    $this->assertEqualsCanonicalizing(['objectId', 'string'], array_keys($claimed));
    // An ObjectId embeds its creation time.
    $this->assertSame($objectId->getTimestamp(), $claimed['objectId']->created);
    // Other _id types get the first claim time.
    $this->assertGreaterThanOrEqual($before, $claimed['string']->created);
    $this->assertLessThanOrEqual($after, $claimed['string']->created);
    $this->assertSame('foreign-id', $claimed['string']->id());

    // Releasing and claiming again does not change the creation time.
    foreach ($claimed as $item) {
      $this->assertTrue($q->releaseItem($item));
    }
    $reclaimed = $this->claimAll($q);
    foreach ($claimed as $key => $item) {
      $this->assertSame($item->created, $reclaimed[$key]->created);
    }

    foreach ($reclaimed as $item) {
      $q->deleteItem($item);
    }
    $this->assertSame(0, $q->numberOfItems());
  }

  /**
   * Claims all available items, keyed by their data.
   *
   * @param \Drupal\Core\Queue\QueueInterface $q
   *   The queue.
   *
   * @return array<string,\Drupal\mongodb_storage\Queue\Item>
   *   The claimed items.
   */
  protected function claimAll(QueueInterface $q): array {
    $claimed = [];
    while (($item = $q->claimItem()) instanceof Item) {
      $claimed[$item->data] = $item;
    }
    return $claimed;
  }

  /**
   * Tests the Queue createItem / claimItem / deleteItem operations.
   *
   * @throws \ReflectionException
   */
  public function testMongoDbQueue(): void {
    // Create two queues.
    $q1 = $this->queueFactory->get($this->randomMachineName());
    $q1->createQueue();
    $q2 = $this->queueFactory->get($this->randomMachineName());
    $q2->createQueue();

    $this->runQueueTest($q1, $q2);
  }

  /**
   * Reuse the core QueueTest::runQueueTest to catch regressions.
   *
   * This implementation is a workaround for 3311758 in case it is now fixed.
   *
   * @throws \ReflectionException
   */
  protected function runQueueTest(
    QueueInterface $queue1,
    QueueInterface $queue2,
  ): void {
    // PHPUnit 10+ requires a test name; this instance is never run itself.
    $coreTest = new coreQueueTest('testSystemQueue');
    $rc = new \ReflectionClass($coreTest);
    $rm = $rc->getMethod('runQueueTest');
    $rm->invoke($coreTest, $queue1, $queue2);
  }

  /**
   * Test the createItem / releaseItem behaviour.
   */
  public function testReleaseItem(): void {
    $q = $this->createQueue();
    $id = $this->createTestItem($q)->id();

    /** @var \Drupal\mongodb_storage\Queue\Item|false $claimed */
    $claimed = $q->claimItem();
    $this->assertTrue($claimed instanceof Item);
    $this->assertEquals($id, $claimed->id());
    // Claiming does not consume the item.
    $this->assertEquals(1, $q->numberOfItems());

    // But it makes it unavailable.
    $c2 = $q->claimItem();
    $this->assertFalse($c2);

    // While releasing it makes it available again.
    $q->releaseItem($claimed);
    $c3 = $q->claimItem();
    $this->assertTrue($c3 instanceof Item);
  }

  /**
   * Checks the releaseItem() result for each state an item can be in.
   */
  public function testReleaseItemResult(): void {
    $q = $this->createQueue();
    $this->createTestItem($q);
    /** @var \Drupal\mongodb_storage\Queue\Item $claimed */
    $claimed = $q->claimItem();
    $this->assertInstanceOf(Item::class, $claimed);

    $this->assertTrue($q->releaseItem($claimed), 'A claimed item is released.');

    // Like core's Memory queue. Core's DatabaseQueue returns TRUE instead, but
    // only because its database counts the matched row as affected.
    $this->assertFalse($q->releaseItem($claimed), 'An unclaimed item is not released.');

    $q->deleteItem($claimed);
    $this->assertFalse($q->releaseItem($claimed), 'A deleted item is not released.');

    // Regression test for #3311675, where a wrong filter released nothing but
    // still reported success.
    $unknown = Item::fromDoc(new BSONDocument([
      '_id' => new ObjectId(),
      'created' => 0,
      'expires' => 0,
    ]));
    $this->assertFalse($q->releaseItem($unknown), 'An unknown item is not released.');
  }

  /**
   * Checks https://www.drupal.org/project/mongodb/issues/3323976.
   *
   * Also checks that different items get different claimed IDs.
   */
  public function testIds(): void {
    $q = $this->createQueue();
    $id = $this->createTestItem($q)->id();
    /** @var \Drupal\mongodb_storage\Queue\Item|false $claimed */
    $claimed = $q->claimItem();
    $this->assertInstanceOf(Item::class, $claimed);
    $this->assertSame($id, $claimed->id());

    $otherId = $this->createTestItem($q)->id();
    /** @var \Drupal\mongodb_storage\Queue\Item|false $otherClaimed */
    $otherClaimed = $q->claimItem();
    $this->assertInstanceOf(Item::class, $otherClaimed);
    $this->assertSame($otherId, $otherClaimed->id());

    // Do not just test for NotSame, but also for an actual difference.
    $this->assertNotEquals($otherClaimed->id(), $claimed->id());
  }

}
