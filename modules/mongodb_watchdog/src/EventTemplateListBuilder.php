<?php

namespace Drupal\mongodb_watchdog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Event template entities.
 *
 * @ingroup mongodb_watchdog
 */
class EventTemplateListBuilder extends EntityListBuilder {

  use LinkGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Event template ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\mongodb_watchdog\Entity\EventTemplate */
    $row['id'] = $entity->id();
    $row['name'] = $this->l(
      $entity->label(),
      new Url(
        'entity.mongodb_watchdog_event_template.edit_form', array(
          'mongodb_watchdog_event_template' => $entity->id(),
        )
      )
    );
    return $row + parent::buildRow($entity);
  }

}
