<?php

namespace Drupal\mongodb_watchdog\Controller\ArgumentResolver;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Yields a form_state argument for FormStateInterface $formState arguments.
 *
 * This resolver supports form methods with a FormStateInterface argument
 * regardless of its name.
 */
class FormStateValueResolver implements ValueResolverInterface {

  const NAME_LEGACY = 'form_state';

  /**
   * Whether this resolver can provide a value for the argument.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata $argument
   *   The controller argument to resolve.
   *
   * @return bool
   *   TRUE if the argument is a FormStateInterface and the request carries one.
   */
  public function supports(Request $request, ArgumentMetadata $argument): bool {
    $argumentInterfaceMatches = $argument->getType() === FormStateInterface::class;
    $requestAttributeExists = $request->attributes->has(static::NAME_LEGACY);
    return $argumentInterfaceMatches && $requestAttributeExists;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int,mixed>
   *   The form state, or an empty array if this resolver does not apply.
   */
  public function resolve(Request $request, ArgumentMetadata $argument): iterable {
    if (!$this->supports($request, $argument)) {
      return [];
    }
    return [$request->attributes->get(static::NAME_LEGACY)];
  }

}
