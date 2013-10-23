<?php

/**
 * Contains \Drupal\mongodb\Config\ConfigStorageFactory
 */

namespace Drupal\mongodb\Config;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\mongodb\MongoCollectionFactory;

/**
 * Provides a factory for creating MongoDB storage objects.
 */
class ConfigStorageFactory {

  /**
   * Returns a ConfigStorage object working with the active config environment
   * (i.e. "directory").
   *
   * @return \Drupal\mongodb\Config\ConfigStorage
   */
  static function getActive(MongoCollectionFactory $mongo, TranslationInterface $translation) {
    return new ConfigStorage($mongo, $translation);
  }

  /**
   * Returns a ConfigStorage object working with the stage config environment
   * (i.e. "directory").
   *
   * @return \Drupal\mongodb\Config\ConfigStorage
   */
  static function getStaging(MongoCollectionFactory $mongo, TranslationInterface $translation) {
    return new ConfigStorage($mongo, $translation, CONFIG_STAGING_DIRECTORY);
  }

}
