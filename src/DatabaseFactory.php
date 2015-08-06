<?php
/**
 * @file
 * Contains DatabaseFactory.
 */

namespace Drupal\mongodb;

/**
 * Class DatabaseFactory.
 *
 * @package Drupal\mongodb
 */
class DatabaseFactory {

  /**
   * The connection factory service.
   *
   * @var \Drupal\mongodb\ConnectionFactory
   */
  protected $connectionFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\mongodb\ConnectionFactory $connection_factory
   *   The connection factory service.
   */
  public function __construct(ConnectionFactory $connection_factory) {
    $this->connectionFactory = $connection_factory;
  }

  /**
   * Return the MongoDB database matching an alias.
   *
   * @param string $alias
   *   The alias string, like "default".
   *
   * @return \MongoDB|null
   *   The selected database, or NULL if an error occurred.
   */
  public function get($alias) {
    try {
      $connection = $this->connectionFactory->create($alias);
      $result = $connection->isAvailable()
        ? $connection->client()->selectDB($alias)
        : NULL;
    }
    catch (\InvalidArgumentException $e) {
      $result = NULL;
    }
    catch (\MongoConnectionException $e) {
      $result = NULL;
    }

    return $result;
  }
}
