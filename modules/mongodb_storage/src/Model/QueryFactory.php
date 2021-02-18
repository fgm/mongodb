<?php

namespace Drupal\mongodb_storage\Model;

use Doctrine\Common\Util\Debug;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Site\Settings;

/**
 * Factory class Creating entity query objects.
 *
 * Any implementation of this service must call getQuery()/getAggregateQuery()
 * of the corresponding entity storage.
 *
 * @see \Drupal\Core\Entity\EntityStorageBase::getQuery()
 *
 * @todo https://www.drupal.org/node/2389335 remove entity.query service and
 *   replace with using the entity storage's getQuery() method.
 */
class QueryFactory {

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager used by the query.
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   */
  public function __construct(EntityManagerInterface $entity_manager,
    Settings $settings, ...$args) {
    echo __METHOD__ . "\n";
    Debug::dump($args);
    $this->entityManager = $entity_manager;
  }

  /**
   * Returns a query object for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object that can query the given entity type.
   */
  public function get(EntityTypeInterface $entity_type, $conjunction = 'AND') {
    $query = "GET " . $entity_type->id() . " $conjunction";
    echo __METHOD__ . " $query\n";
    $query = $this->entityManager->getStorage($entity_type->id())->getQuery($conjunction);
    return $query;
  }

  /**
   * Returns an aggregated query object for a given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type_id
   *   The entity type.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\Core\Entity\Query\QueryAggregateInterface
   *   The aggregated query object that can query the given entity type.
   */
  public function getAggregate(EntityTypeInterface $entity_type_id, $conjunction = 'AND') {
    $query = "GETAGGREGATE " . $entity_type_id->id() . " $conjunction";
    echo __METHOD__ . " $query\n";
    $query = $this->entityManager->getStorage($entity_type_id)->getAggregateQuery($conjunction);
    Debug::dump($query);
    return $query;
  }

}