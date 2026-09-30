<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\Queue;

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
   * For items from other producers which omit it, see Queue::claimItem().
   */
  public int $created;

  /**
   * The data as published to the queue by createItem.
   */
  public mixed $data;

  /**
   * The timestamp at which the claim which returned this item will expire.
   *
   * At that point, the item will be released automatically for other claims.
   */
  public int $expires;

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
   * The _id as stored, since other producers may use any type for it.
   */
  protected mixed $mongoId;

  /**
   * Constructs a new instance from a document returned by claimItem().
   *
   * The claim sets "created" and "expires" when they are missing, so they are
   * read as they are: only "data" may be absent.
   *
   * @param \MongoDB\Model\BSONDocument $doc
   *   The input document.
   *
   * @return static
   *   A new instance.
   *
   * @throws \InvalidArgumentException
   *   If the document lacks a field every claimed document has.
   *
   * @internal
   *   Public only for Queue::claimItem() to call it.
   */
  public static function fromDoc(BSONDocument $doc): static {
    if (!isset($doc['_id'], $doc['created'], $doc['expires'])) {
      throw new \InvalidArgumentException('Item::fromDoc() needs a claimed document, with "_id", "created" and "expires".');
    }
    $that = new static();
    $that->created = $doc['created'];
    // If someone has managed to put malicious content into our database,
    // then it is probably already too late to defend against an attack.
    // @codingStandardsIgnoreStart
    $that->data = unserialize($doc['data'] ?? 'N;');
    // @codingStandardsIgnoreEnd
    $that->expires = $doc['expires'];
    $that->item_id = (string) $doc['_id'];
    $that->mongoId = $doc['_id'];
    return $that;
  }

  /**
   * The item _id, in string form.
   *
   * @return string
   *   The ID as a string, which is not always an ObjectId.
   */
  public function id(): string {
    return $this->item_id;
  }

  /**
   * The item _id as stored, ready to be used in queries.
   *
   * @return mixed
   *   Usually an ObjectId, but any type from other producers.
   */
  public function mongoId(): mixed {
    return $this->mongoId;
  }

}
