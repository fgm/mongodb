<?php

/**
 * @file
 * Definition of Drupal\mongodb\MongoServiceProvider..
 */

namespace Drupal\mongodb;

use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * MongoDB service provider. Registers Mongo-related services.
 */
class MongoServiceProvider implements ServiceProviderInterface  {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    global $conf;
    $conf += array('mongodb_connections' => array(), 'mongodb_collections' => array());
    $conf['mongodb_connections'] += array('default' => array());
    foreach ($conf['mongodb_connections'] as $alias => $connection) {
      $connection += array('host' => 'localhost', 'db' => 'drupal', 'connection_options' => array());
      $container
        ->register("mongo.$alias", 'Drupal\mongodb\Mongo')
        ->addArgument($connection);
    }
    $container
      ->register('mongo', 'Drupal\mongodb\MongoCollectionFactory')
      ->addArgument(new Reference('service_container'))
      ->addArgument($conf['mongodb_collections']);
//    $container
//      ->register('file.usage', 'Drupal\mongodb\FileUsage')
//      ->addArgument(new Reference('mongo'))
//      ->addArgument('file_usage');
    $container
      ->register('flood', 'Drupal\mongodb\Flood')
      ->addArgument(new Reference('mongo'))
      ->addArgument('flood');
//    $container
//      ->register('keyvalue', 'Drupal\mongodb\KeyValueFactory')
//      ->addArgument(new Reference('mongo'));*/
  }

}
