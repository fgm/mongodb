<?php

namespace Drupal\mongodb_storage\Model;

use Doctrine\Common\Util\Debug;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageBase as CoreContentEntityStorageBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\mongodb\DatabaseFactory;
use MongoDB\BSON\ObjectID;

class ContentEntityStorageBase extends CoreContentEntityStorageBase {

  /**
   * @var \Drupal\mongodb\DatabaseFactory
   */
  protected $databaseFactory;

  public function __construct(ContentEntityTypeInterface $entityType,
    EntityManagerInterface $entityManager,
    CacheBackendInterface $cacheBackend,
    DatabaseFactory $databaseFactory) {
    echo __METHOD__ . "\n";
    parent::__construct($entityType, $entityManager, $cacheBackend);
    $this->databaseFactory = $databaseFactory;
  }

  /**
   * Reads values to be purged for a single field.
   *
   * This method is called during field data purge, on fields for which
   * onFieldDefinitionDelete() has previously run.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param $batch_size
   *   The maximum number of field data records to purge before returning.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   An array of field item lists, keyed by entity revision id.
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    ksm(__METHOD__, func_get_args());
  }

  /**
   * Removes field items from storage per entity during purge.
   *
   * @param ContentEntityInterface $entity
   *   The entity revision, whose values are being purged.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field whose values are bing purged.
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
    ksm(__METHOD__, func_get_args());
  }

  /**
   * Actually loads revision field item values from the storage.
   *
   * @param int|string $revision_id
   *   The revision identifier.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The specified entity revision or NULL if not found.
   */
  protected function doLoadRevisionFieldItems($revision_id) {
    ksm(__METHOD__, func_get_args());
  }

  /**
   * Writes entity field values to the storage.
   *
   * This method is responsible for allocating entity and revision identifiers
   * and updating the entity object with their values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   * @param string[] $names
   *   (optional) The name of the fields to be written to the storage. If an
   *   empty value is passed all field values are saved.
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    ksm(__METHOD__, $entity, $names);
    $id = $entity->id();
    $full_save = empty($names);
    $update = !$full_save || !$entity->isNew();
    $serializer = \Drupal::service('serializer');
    $doc = $serializer->normalize($entity, 'json');
    $doc['_id'] = $id ?: new ObjectID();
    ksm(__METHOD__, $names, $doc, (string) $doc['_id']);
    $database = $this->databaseFactory->get('default');
    $collection = $database->selectCollection($entity->bundle());
    $result = $collection->replaceOne(['_id' => $doc['_id']], $doc, ['upsert' => TRUE]);
    ksm($doc, $collection,
      "matched " . $result->getMatchedCount(),
      "modified " . $result->getModifiedCount(),
      "upserted " . $result->getUpsertedCount(),
      "new id " . $result->getUpsertedId(),
      "ack" . ($result->isAcknowledged() ? "Y" : "N")
    );
  }

  /**
   * Deletes entity field values from the storage.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   An array of entity objects to be deleted.
   */
  protected function doDeleteFieldItems($entities) {
    ksm(__METHOD__, func_get_args());
  }

  /**
   * Deletes field values of an entity revision from the storage.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   An entity revision object to be deleted.
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
    ksm(__METHOD__, func_get_args());
  }

  /**
   * Performs storage-specific loading of entities.
   *
   * Override this method to add custom functionality directly after loading.
   * This is always called, while self::postLoad() is only called when there are
   * actual results.
   *
   * @param array|null $ids
   *   (optional) An array of entity IDs, or NULL to load all entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Associative array of entities, keyed on the entity ID.
   */
  protected function doLoadMultiple(array $ids = NULL) {
    ksm(__METHOD__, func_get_args());
  }

  /**
   * Determines if this entity already exists in storage.
   *
   * @param int|string $id
   *   The original entity ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   *
   * @return bool
   */
  protected function has($id, EntityInterface $entity) {
    if (!isset($id)) {
      drupal_set_message(__METHOD__ . "(no id)");
      return FALSE;
    }

    $bundle = $entity->bundle();
    $db = $this->databaseFactory->get('default');
    $collection = $db->selectCollection($bundle);
    $selector = [
      '_id' => "$id",
    ];
    $doc = $collection->findOne($selector);
    $has = isset($doc);
    ksm(__METHOD__, "($id, " . $entity->label() . ")", $doc ?? FALSE);
    return $has;
  }

  /**
   * Gets the name of the service for the query for this entity storage.
   *
   * @return string
   *   The name of the service for the query for this entity storage.
   */
  protected function getQueryServiceName() {
    return 'entity.query.null';
  }

  /**
   * Determines the number of entities with values for a given field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition
   *   The field for which to count data records.
   * @param bool $as_bool
   *   (Optional) Optimises the query for checking whether there are any records
   *   or not. Defaults to FALSE.
   *
   * @return bool|int
   *   The number of entities. If $as_bool parameter is TRUE then the
   *   value will either be TRUE or FALSE.
   *
   * @see \Drupal\Core\Entity\FieldableEntityStorageInterface::purgeFieldData()
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    ksm(__METHOD__, func_get_args());
    if ($as_bool) {
      return FALSE;
    }
  }

}