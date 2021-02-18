<?php

namespace Drupal\mongodb_watchdog\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Event template entities.
 */
class EventTemplateViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['mongodb_watchdog_event_template']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('Event template'),
      'help' => $this->t('The Event template ID.'),
    );

    return $data;
  }

}
