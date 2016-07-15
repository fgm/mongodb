<?php

namespace Drupal\mongodb_watchdog\Model;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\mongodb_storage\Model\ContentEntityStorageBase;

class EventTemplateStorage extends ContentEntityStorageBase {
  public function __construct(ContentEntityTypeInterface $entityType,
    EntityManagerInterface $entityManager,
    CacheBackendInterface $cacheBackend) {
    echo __METHOD__ . "\n";
    parent::__construct($entityType, $entityManager, $cacheBackend);
  }

}
