<?php

namespace Drupal\mongodb_watchdog\Controller;

use MongoDB\BSON\Unserializable;

/**
 * Class TopResult is a value object holding the Top aggregation results.
 */
final class TopResult implements Unserializable {

  /**
   * The number of hits on the grouped URL.
   *
   * @var int
   */
  public $count;

  /**
   * The URL on which the count of hits is grouped.
   *
   * @var string
   */
  public $uri;

  /**
   * {@inheritDoc}
   */
  public function bsonUnserialize(array $data) {
    $this->uri = $data['_id'];
    $this->count = $data['count'];
  }

}
