<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_storage\Kernel;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mongodb_storage\Queue\MongoDBQueue;
use Drupal\mongodb_storage\Storage;

/**
 * Queues and dequeues a set of items to check the basic queue functionality.
 *
 * @coversDefaultClass \Drupal\mongodb_storage\Queue\MongoDbQueue
 *
 * @group MongoDB
 */
class QueueTest extends QueueTestBase {

  use DependencySerializationTrait;

  /**
   * Tests the System queue.
   */
  public function testMongoDBQueue() {
    // Create two queues.
    $queue1 = new MongoDBQueue(
      $this->randomMachineName(),
      $this->getSettingsArray(),
      $this->container->get(Storage::SERVICE_QUEUE_STORAGE)
    );
    $queue1->createQueue();

    $queue2 = new MongoDBQueue(
      $this->randomMachineName(),
      $this->getSettingsArray(),
      $this->container->get(Storage::SERVICE_QUEUE_STORAGE)
    );
    $queue1->createQueue();

    $this->runQueueTest($queue1, $queue2);
    //    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n"); ob_flush();
  }

  /**
   * Queues and dequeues a set of items to check the basic queue functionality.
   *
   * @param \Drupal\Core\Queue\QueueInterface $queue1
   *   An instantiated queue object.
   * @param \Drupal\Core\Queue\QueueInterface $queue2
   *   An instantiated queue object.
   */
  protected function runQueueTest($queue1, $queue2) {
    // Create four items.
    $data = [];
    for ($i = 0; $i < 4; $i++) {
      $data[] = [$this->randomMachineName() => $this->randomMachineName()];
    }

    // Queue items 1 and 2 in the queue1.
    $queue1->createItem($data[0]);
    $queue1->createItem($data[1]);

    // Retrieve two items from queue1.
    $items = [];
    $new_items = [];

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    // First two dequeued items should match the first two items we queued.
    $score = $this->queueScore($data, $new_items);

    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();
    $this->assertEquals(2, $score, 'Two items matched');
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();

    // Add two more items.
    $queue1->createItem($data[2]);
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();
    $queue1->createItem($data[3]);
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();

    $this->assertSame(4, $queue1->numberOfItems(),
      'Queue 1 is not empty after adding items.');
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();
    $this->assertSame(0, $queue2->numberOfItems(),
      'Queue 2 is empty while Queue 1 has items');
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();

    $items[] = $item = $queue1->claimItem();
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();
    $new_items[] = $item->data;

    $items[] = $item = $queue1->claimItem();
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();
    $new_items[] = $item->data;

    // All dequeued items should match the items we queued exactly once,
    // therefore the score must be exactly 4.
    $this->assertEquals(4, $this->queueScore($data, $new_items),
      'Four items matched');
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();

    // There should be no duplicate items.
    $this->assertEquals(4, $this->queueScore($new_items, $new_items),
      'Four items matched');
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();

    // Delete all items from queue1.
    foreach ($items as $item) {
      $queue1->deleteItem($item);
    }
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();

    // Check that both queues are empty.
    $this->assertSame(0, $queue1->numberOfItems(), 'Queue 1 is empty');
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();
    $this->assertSame(0, $queue2->numberOfItems(), 'Queue 2 is empty');
    // fwrite(STDERR, __METHOD__ . " line " . __LINE__ . "\n");
    ob_flush();
  }

  /**
   * Returns the number of equal items in two arrays.
   */
  protected function queueScore($items, $new_items): int {
    $score = 0;
    foreach ($items as $item) {
      foreach ($new_items as $new_item) {
        if ($item === $new_item) {
          $score++;
        }
      }
    }
    return $score;
  }

}
