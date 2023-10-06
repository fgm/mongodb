<?php

namespace Drupal\mongodb_watchdog;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * MongodbWatchdogServiceProvider add $formState support to forms.
 */
class MongodbWatchdogServiceProvider implements ServiceModifierInterface {

  const KERNEL_RESOLVER = 'http_kernel.controller.argument_resolver';

  // Individual resolvers.
  const RESOLVER_DEFAULT = 'argument_resolver.default';
  const RESOLVER_FORM_STATE = 'argument_resolver.mongodb_watchdog.form_state';

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $kernelResolverDefinition = $container
      ->getDefinition(static::KERNEL_RESOLVER);
    $resolvers = $kernelResolverDefinition->getArgument(1);
    $defaultIndex = $count = count($resolvers);
    /** @var \Symfony\Component\DependencyInjection\Reference $reference */
    foreach ($resolvers as $index => $reference) {
      if ("$reference" === static::RESOLVER_DEFAULT) {
        $defaultIndex = $index;
        break;
      }
    }

    $formStateResolverReference = new Reference(static::RESOLVER_FORM_STATE);

    // If the default resolved is present, insert just before it.
    if ($defaultIndex != $count) {
      array_splice($resolvers, $defaultIndex, 0, [$formStateResolverReference]);
    }
    // Else add the formState resolver at the end of the list.
    else {
      array_push($resolvers, [$formStateResolverReference]);
    }

    $kernelResolverDefinition->setArgument(1, $resolvers);
  }

}
