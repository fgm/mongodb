<?php

namespace Drupal\mongodb_watchdog\Model;

use Doctrine\Common\Util\Debug;
use Drupal\mongodb_storage\Model\ContentEntityStorageSchema;

class EventTemplateStorageSchema extends ContentEntityStorageSchema {

  public function __construct() {
    echo __METHOD__ . "\n";
    Debug::dump(func_get_args());
    parent::__construct(...func_get_args());
  }
}
