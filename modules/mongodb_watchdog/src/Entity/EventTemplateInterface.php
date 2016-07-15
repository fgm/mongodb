<?php

namespace Drupal\mongodb_watchdog\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Event template entities.
 *
 * @ingroup mongodb_watchdog
 */
interface EventTemplateInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Event template name.
   *
   * @return string
   *   Name of the Event template.
   */
  public function getName();

  /**
   * Sets the Event template name.
   *
   * @param string $name
   *   The Event template name.
   *
   * @return \Drupal\mongodb_watchdog\Entity\EventTemplateInterface
   *   The called Event template entity.
   */
  public function setName($name);

  /**
   * Gets the Event template creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Event template.
   */
  public function getCreatedTime();

  /**
   * Sets the Event template creation timestamp.
   *
   * @param int $timestamp
   *   The Event template creation timestamp.
   *
   * @return \Drupal\mongodb_watchdog\Entity\EventTemplateInterface
   *   The called Event template entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Event template published status indicator.
   *
   * Unpublished Event template are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Event template is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Event template.
   *
   * @param bool $published
   *   TRUE to set this Event template to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\mongodb_watchdog\Entity\EventTemplateInterface
   *   The called Event template entity.
   */
  public function setPublished($published);

}
