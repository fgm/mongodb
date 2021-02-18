<?php

namespace Drupal\mongodb_storage\Model;

class Query {

  public function __construct(...$args) {
    echo __METHOD__ . "\n";
    Debug::dump($args);
  }
}