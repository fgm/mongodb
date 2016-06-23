<?php
/**
 * @file
 * Contains \Drupal\mongodb\ClientFactory.
 */

namespace Drupal\mongodb;

use Drupal\Core\Site\Settings;
use MongoDB\Client;

/**
 * Class ClientFactory.
 *
 * @package Drupal\mongodb
 */
class ClientFactory {

  /**
   * The 'mongodb' client settings.
   *
   * @var string[][]
   */
  protected $settings;

  /**
   * A hash of connections per alias.
   *
   * @var \MongoDB\Client[]
   */
  protected $clients;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The system settings.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings->get('mongodb')['clients'];
  }

  public function get($alias) {
    if (!isset($this->clients[$alias]) || !$this->clients[$alias] instanceof \MongoDB\Client) {
      $info = isset($this->settings[$alias]) ? $this->settings[$alias] : [];
      $info += [
        'uri' => 'mongodb://localhost:27017',
        'uriOptions' => [],
        'driverOptions' => [],
      ];

      // Don't use ...$info: keys can be out of order.
      $this->clients[$alias] = new Client($info['uri'], $info['uriOptions'], $info['driverOptions']);
    }

    return $this->clients[$alias];
  }
}
