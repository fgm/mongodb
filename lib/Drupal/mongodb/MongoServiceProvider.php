<?php

/**
 * @file
 * Definition of Drupal\mongodb\MongoServiceProvider..
 */

namespace Drupal\mongodb;

use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * MongoDB service provider. Registers Mongo-related services.
 */
class MongoServiceProvider implements  ServiceProviderInterface, ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    foreach ($container->findTaggedServiceIds('mongodb.override') as $id => $attribute) {
      $container->setDefinition(substr($id, 8), $container->getDefinition($id));
    }
  }

}
