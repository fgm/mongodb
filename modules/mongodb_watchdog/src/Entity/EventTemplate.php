<?php

namespace Drupal\mongodb_watchdog\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Event template entity.
 *
 * @ingroup mongodb_watchdog
 *
 * @ContentEntityType(
 *   id = "mongodb_watchdog_event_template",
 *   label = @Translation("Event template"),
 *   handlers = {
 *     "storage" = "Drupal\mongodb_watchdog\Model\EventTemplateStorage",
 *     "storage_schema" = "Drupal\mongodb_watchdog\Model\EventTemplateStorageSchema",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\mongodb_watchdog\EventTemplateListBuilder",
 *     "Zviews_data" = "Drupal\mongodb_watchdog\Entity\EventTemplateViewsData",
 *     "translation" = "Drupal\mongodb_watchdog\EventTemplateTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\mongodb_watchdog\Form\EventTemplateForm",
 *       "add" = "Drupal\mongodb_watchdog\Form\EventTemplateForm",
 *       "edit" = "Drupal\mongodb_watchdog\Form\EventTemplateForm",
 *       "delete" = "Drupal\mongodb_watchdog\Form\EventTemplateDeleteForm",
 *     },
 *     "access" = "Drupal\mongodb_watchdog\EventTemplateAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\mongodb_watchdog\EventTemplateHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "mongodb_watchdog_event_template",
 *   data_table = "mongodb_watchdog_event_template_field_data",
 *   translatable = TRUE,
  *   admin_permission = "administer event template entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/reports/watchdog/mongodb_watchdog_event_template/{mongodb_watchdog_event_template}",
 *     "add-form" = "/admin/reports/watchdog/mongodb_watchdog_event_template/add",
 *     "edit-form" = "/admin/reports/watchdog/mongodb_watchdog_event_template/{mongodb_watchdog_event_template}/edit",
 *     "delete-form" = "/admin/reports/watchdog/mongodb_watchdog_event_template/{mongodb_watchdog_event_template}/delete",
 *     "collection" = "/admin/reports/watchdog/mongodb_watchdog_event_template",
 *   },
 *   field_ui_base_route = "mongodb_watchdog_event_template.settings"
 * )
 */
class EventTemplate extends ContentEntityBase implements EventTemplateInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'user_id' => \Drupal::currentUser()->id(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? NODE_PUBLISHED : NODE_NOT_PUBLISHED);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Event template entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ),
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Event template entity.'))
      ->setSettings(array(
        'max_length' => 50,
        'text_processing' => 0,
      ))
      ->setDefaultValue('')
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -4,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Event template is published.'))
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
