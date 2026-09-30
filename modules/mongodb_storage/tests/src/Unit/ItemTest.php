<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_storage\Unit;

use Drupal\mongodb_storage\Queue\Item;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests building queue items from claimed documents.
 *
 * The annotations serve PHPUnit 9 on Drupal 10, the attributes later versions.
 *
 * @covers \Drupal\mongodb_storage\Queue\Item
 *
 * @group MongoDB
 */
#[CoversClass(Item::class)]
#[Group('MongoDB')]
class ItemTest extends TestCase {

  const FOREIGN_ID = 'foreign-id';

  /**
   * A claimed document sets each property from its field.
   */
  public function testFromDoc(): void {
    $id = new ObjectId();
    $item = Item::fromDoc(new BSONDocument([
      '_id' => $id,
      'created' => 1234,
      'data' => serialize(['key' => 'value']),
      'expires' => 5678,
    ]));
    $this->assertSame(1234, $item->created);
    $this->assertSame(['key' => 'value'], $item->data);
    $this->assertSame(5678, $item->expires);
    $this->assertSame((string) $id, $item->id());
    $this->assertSame($id, $item->mongoId());
  }

  /**
   * Items from other producers keep their _id type and may omit their data.
   */
  public function testFromForeignDoc(): void {
    $item = Item::fromDoc(new BSONDocument([
      '_id' => self::FOREIGN_ID,
      'created' => 1234,
      'expires' => 5678,
    ]));
    $this->assertNull($item->data);
    $this->assertSame(self::FOREIGN_ID, $item->id());
    $this->assertSame(self::FOREIGN_ID, $item->mongoId());
  }

  /**
   * A document which was not returned by a claim is rejected.
   */
  public function testFromUnclaimedDoc(): void {
    $this->expectException(\InvalidArgumentException::class);
    Item::fromDoc(new BSONDocument([
      '_id' => self::FOREIGN_ID,
      'data' => serialize('value'),
    ]));
  }

}
