<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_watchdog\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mongodb_watchdog\Controller\ArgumentResolver\FormStateValueResolver;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Test the FormStateValueResolver mechanisms.
 *
 * @coversDefaultClass \Drupal\mongodb_watchdog\Controller\ArgumentResolver\FormStateValueResolver
 *
 * @group mongodb
 */
class FormStateValueResolverTest extends UnitTestCase {

  /**
   * Test formState argument resolution.
   *
   * @covers ::supports
   */
  public function testFormStateArgumentResolver(): void {
    $resolver = new FormStateValueResolver();
    $request = new Request();
    $request->attributes->add([FormStateValueResolver::NAME_LEGACY => new FormState()]);
    $argument = new ArgumentMetadata('formState', FormStateInterface::class, FALSE, FALSE, NULL, FALSE);

    $this->assertEquals(TRUE, $resolver->supports($request, $argument));
  }

  /**
   * Test extra optional argument resolution.
   *
   * @covers ::supports
   */
  public function testOptionalExtraArgumentResolver(): void {
    $resolver = new FormStateValueResolver();
    $request = new Request();
    $request->attributes->add([FormStateValueResolver::NAME_LEGACY => new FormState()]);
    $argument = new ArgumentMetadata('extra', NULL, FALSE, TRUE, '', TRUE);

    $this->assertEquals(FALSE, $resolver->supports($request, $argument));
  }

}
