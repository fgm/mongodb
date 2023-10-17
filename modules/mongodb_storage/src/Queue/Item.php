<?php

namespace Drupal\mongodb_storage\Queue;

use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;

/**
 * Class Item is the type returned by claimItem on MongoDB queues.
 *
 * Its properties are public and snake-case because they are documented as part
 * of Drupal\Core\Queue\QueueInterface::claimItem().
 *
 * @see \Drupal\Core\Queue\QueueInterface::claimItem()
 */
class Item {

  /**
   * The timestamp at which the item was stored in the DB.
   *
   * @var int
   */
  public int $created;

  /**
   * The timestamp at which the claim which returned this item will expire.
   *
   * At that point, the item will be released automatically for other claims.
   *
   * @var int
   */
  public int $expires;

  /**
   * The data as published to the queue by createItem.
   *
   * @var mixed
   */
  public mixed $data;

  /**
   * A string representation of the _id key.
   *
   * Its name is required by QueueInterface::claimItem().
   *
   * @var string
   */
  // phpcs:ignore
  public string $item_id;

  /**
   * Constructs a new instance from a BSONDocument returned by a find call.
   *
   * @param \MongoDB\Model\BSONDocument $doc
   *   The input document.
   *
   * @return static
   *   A new instance.
   */
  public static function fromDoc(BSONDocument $doc): static {
    $that = new static();
    $that->created = $doc['created'] ?? time();
    // If someone has managed to put malicious content into our database,
    // then it is probably already too late to defend against an attack.
    // @codingStandardsIgnoreStart
    $that->data = unserialize($doc['data'] ?? 'N;');
    // @codingStandardsIgnoreEnd
    $that->expires = (int) ($doc['expires'] ?? 0);
    $that->item_id = (string) ($doc['_id'] ?? new ObjectId());
    return $that;
  }

  /**
   * The item _id, ready to be used in queries.
   *
   * @return string
   *   The ID in ObjectId form.
   */
  public function id(): string {
    return $this->item_id;
  }

}
