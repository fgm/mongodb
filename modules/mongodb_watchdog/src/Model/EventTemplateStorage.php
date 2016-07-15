<?php

namespace Drupal\mongodb_watchdog\Model;

use Doctrine\Common\Util\Debug;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\mongodb_storage\Model\ContentEntityStorageBase;

class EventTemplateStorage extends ContentEntityStorageBase {
  public function __construct(ContentEntityTypeInterface $entityType,
    EntityManagerInterface $entityManager,
    CacheBackendInterface $cacheBackend) {
    echo __METHOD__ . "\n";
    $databaseFactory = \Drupal::service('mongodb.database_factory');
    parent::__construct($entityType, $entityManager, $cacheBackend, $databaseFactory);
  }

  public function __call($name, array $args) {
    Debug::dump($name, $args);
    return call_user_func_array(parent::$name, $args);
  }
}
