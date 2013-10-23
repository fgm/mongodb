<?php

/**
 * @file
 * Definition of Drupal\mongodb\Tests\QueueTest.
 */

namespace Drupal\mongodb\Tests;

use Drupal\mongodb\Queue\Queue;

/**
 * Tests queue functions.
 */
class QueueTest extends \Drupal\system\Tests\Queue\QueueTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('mongodb');

  public static function getInfo() {
    return array(
      'name' => 'Queue',
      'description' => 'Tests the mongo queue functions.',
      'group' => 'MongoDB',
    );
  }

  /**
   * Tests MongoDB queue.
   */
  public function testMongoDBQueue() {
    // Create two queues.
    $queue1 = new Queue(\Drupal::service('mongo'), $this->randomName());
    $queue1->createQueue();
    $queue2 = new Queue(\Drupal::service('mongo'), $this->randomName());
    $queue2->createQueue();

    $this->queueTest($queue1, $queue2);
  }

  /**
   * Overrides \Drupal\system\Tests\Queue\QueueTestQueueTest::testSystemQueue().
   *
   * We override tests from core class we exted to prevent them from running.
   */
  public function testSystemQueue() {}

  /**
   * Overrides \Drupal\system\Tests\Queue\QueueTestQueueTest::testMemoryQueue().
   *
   * We override tests from core class we exted to prevent them from running.
   */
  public function testMemoryQueue() {}
}

