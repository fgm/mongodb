<?php

namespace Drupal\mongodb_watchdog\Controller\ArgumentResolver;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Yields a form_state argument for FormStateInterface $formState arguments.
 *
 * This resolver supports form methods with a FormStateInterface argument
 * regardless of its name.
 */
class FormStateValueResolver implements ArgumentValueResolverInterface {

  const NAME_LEGACY = 'form_state';

  /**
   * {@inheritdoc}
   */
  public function supports(Request $request, ArgumentMetadata $argument): bool {
    $argumentInterfaceMatches = $argument->getType() === FormStateInterface::class;
    $requestAttributeExists = $request->attributes->has(static::NAME_LEGACY);
    return $argumentInterfaceMatches && $requestAttributeExists;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Generator
   *   Returns the argument values.
   */
  public function resolve(Request $request, ArgumentMetadata $argument): iterable {
    $formState = $request->attributes->has(static::NAME_LEGACY)
      ? $request->attributes->get(static::NAME_LEGACY)
      : NULL;
    yield $formState;
  }

}
