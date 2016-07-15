<?php

namespace Drupal\mongodb_watchdog;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Event template entity.
 *
 * @see \Drupal\mongodb_watchdog\Entity\EventTemplate.
 */
class EventTemplateAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\mongodb_watchdog\Entity\EventTemplateInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished event template entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published event template entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit event template entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete event template entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add event template entities');
  }

}
