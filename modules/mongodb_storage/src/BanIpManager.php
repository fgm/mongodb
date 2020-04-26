<?php

/**
 * @file
 * Definition of Drupal\mongodb\BanIpManager.
 */

namespace Drupal\mongodb_storage;

/**
 * Ban IP manager.
 */
class BanIpManager {

  /**
   * The mongodb factory registered as a service.
   *
   * @var \Drupal\mongodb\MongoCollectionFactory
   */
  protected $mongo;

  /**
   * Construct the BanSubscriber.
   *
   * @param \Drupal\mongodb\MongoCollectionFactory $mongo
   *   The database connection which will be used to check the IP against.
   */
  public function __construct(MongoCollectionFactory $mongo) {
    $this->mongo = $mongo;
  }

  /**
   * Returns if this IP address is banned.
   *
   * @param string $ip
   *   The IP address to check.
   *
   * @return bool
   *   TRUE if the IP address is banned, FALSE otherwise.
   */
  public function isBanned($ip) {
    // TODO: it seems isBanned is redundant (check https://drupal.org/node/2225417).
    return (bool) $this->mongo->get('ban_ip')
      ->count(array('ip' => $ip));
  }

  /**
   * Finds all banned IP addresses.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   The result of the database query.
   */
  public function findAll() {
    $all = $this->mongo->get('ban_ip')->find();
    array_walk($all, function($value) {
      $value['iid'] = $value['_id'];
    });
    return $all;
  }

  /**
   * Bans an IP address.
   *
   * @param string $ip
   *   The IP address to ban.
   */
  public function banIp($ip) {
    $this->mongo->get('ban_ip')
      ->insert(array('ip' => $ip));
  }

  /**
   * Unbans an IP address.
   *
   * @param string $ip
   *   The IP address to unban.
   */
  public function unbanIp($ip) {
    $this->mongo->get('ban_ip')
      ->remove(array('ip' => $ip));
  }

  /**
   * Finds a banned IP address by its ID.
   *
   * @param int $ban_id
   *   The ID for a banned IP address.
   *
   * @return string|false
   *   Either the banned IP address or FALSE if none exist with that ID.
   */
  public function findById($ban_id) {
    $result = $this->mongo->get('ban_ip')
      ->findOne(array('_id' => $ban_id), array('ip'));
    return $result ? $result['ip'] : FALSE;
  }

}
