<?php

namespace Drupal\mongodb_watchdog\Controller;

/**
 * Class TopController implements the Top403/Top404 controllers.
 */
class TopController {
  public function top403() {
    return ['#markup' => "403"];
  }

  public function top404() {
    return ['#markup' => "404"];
  }
}
