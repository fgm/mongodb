<?php

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Event template edit forms.
 *
 * @ingroup mongodb_watchdog
 */
class EventTemplateForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\mongodb_watchdog\Entity\EventTemplate */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Event template.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Event template.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.mongodb_watchdog_event_template.canonical', ['mongodb_watchdog_event_template' => $entity->id()]);
  }

}
