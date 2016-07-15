<?php

namespace Drupal\mongodb_storage\Model;

use Doctrine\Common\Util\Debug;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Schema\DynamicallyFieldableEntityStorageSchemaInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

class ContentEntityStorageSchema implements DynamicallyFieldableEntityStorageSchemaInterface {
  public function __construct() {
    echo __METHOD__ . "\n";
    Debug::dump(func_get_args());
  }

  /**
   * Checks if the changes to the storage definition requires schema changes.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition
   *   The updated field storage definition.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $original
   *   The original field storage definition.
   *
   * @return bool
   *   TRUE if storage schema changes are required, FALSE otherwise.
   */
  public function requiresFieldStorageSchemaChanges(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    // TODO: Implement requiresFieldStorageSchemaChanges() method.
  }

  /**
   * Checks if existing data would be lost if the schema changes were applied.
   *
   * If there are no schema changes needed, then no data needs to be migrated,
   * but it is not the responsibility of this function to recheck what
   * requiresFieldStorageSchemaChanges() checks. Rather, the meaning of what
   * this function returns when requiresFieldStorageSchemaChanges() returns
   * FALSE is undefined. Callers are expected to only call this function when
   * requiresFieldStorageSchemaChanges() is TRUE.
   *
   * This function can return FALSE if any of these conditions apply:
   * - There are no existing entities for the entity type to which this field
   *   is attached.
   * - There are existing entities, but none with existing values for this
   *   field.
   * - There are existing field values, but the schema changes can be applied
   *   without losing them (e.g., if the schema changes can be performed by
   *   altering tables rather than dropping and recreating them).
   * - The only field values that would be lost are ones that are not valid for
   *   the new definition (e.g., if changing a field from revisionable to
   *   non-revisionable, then it's okay to drop data for the non-default
   *   revision).
   *
   * When this function returns FALSE, site administrators will be unable to
   * perform an automated update, and will instead need to perform a site
   * migration or invoke some custom update process.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition
   *   The updated field storage definition.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $original
   *   The original field storage definition.
   *
   * @return bool
   *   TRUE if data migration is required, FALSE otherwise.
   *
   * @see self::requiresFieldStorageSchemaChanges()
   */
  public function requiresFieldDataMigration(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    // TODO: Implement requiresFieldDataMigration() method.
  }

  /**
   * Performs final cleanup after all data of a field has been purged.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition
   *   The field being purged.
   */
  public function finalizePurge(FieldStorageDefinitionInterface $storage_definition) {
    // TODO: Implement finalizePurge() method.
  }

  /**
   * Checks if the changes to the entity type requires storage schema changes.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The updated entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeInterface $original
   *   The original entity type definition.
   *
   * @return bool
   *   TRUE if storage schema changes are required, FALSE otherwise.
   */
  public function requiresEntityStorageSchemaChanges(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    // TODO: Implement requiresEntityStorageSchemaChanges() method.
  }

  /**
   * Checks if existing data would be lost if the schema changes were applied.
   *
   * If there are no schema changes needed, then no data needs to be migrated,
   * but it is not the responsibility of this function to recheck what
   * requiresEntityStorageSchemaChanges() checks. Rather, the meaning of what
   * this function returns when requiresEntityStorageSchemaChanges() returns
   * FALSE is undefined. Callers are expected to only call this function when
   * requiresEntityStorageSchemaChanges() is TRUE.
   *
   * This function can return FALSE if any of these conditions apply:
   * - There are no existing entities for the entity type.
   * - There are existing entities, but the schema changes can be applied
   *   without losing their data (e.g., if the schema changes can be performed
   *   by altering tables rather than dropping and recreating them).
   * - The only entity data that would be lost are ones that are not valid for
   *   the new definition (e.g., if changing an entity type from revisionable
   *   to non-revisionable, then it's okay to drop data for the non-default
   *   revision).
   *
   * When this function returns FALSE, site administrators will be unable to
   * perform an automated update, and will instead need to perform a site
   * migration or invoke some custom update process.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The updated entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeInterface $original
   *   The original entity type definition.
   *
   * @return bool
   *   TRUE if data migration is required, FALSE otherwise.
   *
   * @see self::requiresEntityStorageSchemaChanges()
   */
  public function requiresEntityDataMigration(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    // TODO: Implement requiresEntityDataMigration() method.
  }

  /**
   * Reacts to the creation of the entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type being created.
   */
  public function onEntityTypeCreate(EntityTypeInterface $entity_type) {
    // TODO: Implement onEntityTypeCreate() method.
  }

  /**
   * Reacts to the update of the entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The updated entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeInterface $original
   *   The original entity type definition.
   */
  public function onEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    // TODO: Implement onEntityTypeUpdate() method.
  }

  /**
   * Reacts to the deletion of the entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type being deleted.
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type) {
    // TODO: Implement onEntityTypeDelete() method.
  }

  /**
   * Reacts to the creation of a field storage definition.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition
   *   The definition being created.
   */
  public function onFieldStorageDefinitionCreate(FieldStorageDefinitionInterface $storage_definition) {
    // TODO: Implement onFieldStorageDefinitionCreate() method.
  }

  /**
   * Reacts to the update of a field storage definition.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition
   *   The field being updated.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $original
   *   The original storage definition; i.e., the definition before the update.
   *
   * @throws \Drupal\Core\Entity\Exception\FieldStorageDefinitionUpdateForbiddenException
   *   Thrown when the update to the field is forbidden.
   */
  public function onFieldStorageDefinitionUpdate(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    // TODO: Implement onFieldStorageDefinitionUpdate() method.
  }

  /**
   * Reacts to the deletion of a field storage definition.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition
   *   The field being deleted.
   */
  public function onFieldStorageDefinitionDelete(FieldStorageDefinitionInterface $storage_definition) {
    // TODO: Implement onFieldStorageDefinitionDelete() method.
  }
}
