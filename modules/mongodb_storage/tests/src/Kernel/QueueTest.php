<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_storage\Kernel;

use Drupal\Core\Queue\QueueInterface;
use Drupal\KernelTests\Core\Queue\QueueTest as coreQueueTest;
use Drupal\mongodb\MongoDb;
use Drupal\mongodb_storage\Queue\Item;
use Drupal\mongodb_storage\Queue\Queue;
use Drupal\mongodb_storage\Queue\QueueFactory;
use Drupal\mongodb_storage\Storage;
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
   *
   * @var \Drupal\mongodb_storage\Queue\QueueFactory|null
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
    unset($this->queueFactory);
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
    $now = ($q instanceof Queue) ? $q->time->getCurrentTime() : time();
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

    /** @var \Drupal\mongodb_storage\Queue\Item|bool $claimed */
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
    QueueInterface $queue2
  ): void {
    $coreTest = new coreQueueTest();
    $rc = new \ReflectionClass($coreTest);
    $rm = $rc->getMethod('runQueueTest');
    $rm->setAccessible(TRUE);
    $rm->invoke($coreTest, $queue1, $queue2);
  }

  /**
   * Test the createItem / releaseItem behaviour.
   */
  public function testReleaseItem(): void {
    $q = $this->createQueue();
    $id = $this->createTestItem($q)->id();

    /** @var \Drupal\mongodb_storage\Queue\Item|bool $claimed */
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
   * Checks https://www.drupal.org/project/mongodb/issues/3323976.
   *
   * Also checks that different items get different claimed IDs.
   */
  public function testIds(): void {
    $q = $this->createQueue();
    $id = $this->createTestItem($q)->id();
    /** @var \Drupal\mongodb_storage\Queue\Item|bool $claimed */
    $claimed = $q->claimItem();
    $this->assertInstanceOf(Item::class, $claimed);
    $this->assertSame($id, $claimed->id());

    $otherId = $this->createTestItem($q)->id();
    /** @var \Drupal\mongodb_storage\Queue\Item|bool $otherClaimed */
    $otherClaimed = $q->claimItem();
    $this->assertInstanceOf(Item::class, $otherClaimed);
    $this->assertSame($otherId, $otherClaimed->id());

    // Do not just test for NotSame, but also for an actual difference.
    $this->assertNotEquals($otherClaimed->id(), $claimed->id());
  }

}
