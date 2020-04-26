<?php

namespace Drupal\mongodb_path\Storage;

/**
 * Class Dbtng contains the DBTNG path alias storage.
 *
 * @package Drupal\mongodb_path
 */
class Dbtng implements StorageInterface {
  /**
   * Pseudo-typing: defined recognized keys for aliases.
   *
   * @var array
   */
  protected $aliasKeys = [
    'alias' => 1,
    'language' => 1,
    'pid' => 1,
    'source' => 1,
  ];

  /**
   * A connection to the default relational database.
   *
   * @var \DatabaseConnection
   */
  protected $connection;

  /**
   * Storage constructor.
   *
   * @param \DatabaseConnection $connection
   *   A MongoDB database in which to access the alias storage collection.
   */
  public function __construct(\DatabaseConnection $connection) {
    _mongodb_path_trace();
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    _mongodb_path_trace();
    $this->connection->truncate(static::COLLECTION_NAME)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $criteria) {
    _mongodb_path_trace();
    $criteria = array_intersect_key($criteria, $this->aliasKeys);
    $query = $this->connection->delete(static::COLLECTION_NAME);
    foreach ($criteria as $field => $value) {
      $query->condition($field, $value);
    }
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $criteria) {
    _mongodb_path_trace();

    $criteria = array_intersect_key($criteria, $this->aliasKeys);
    /** @var \SelectQuery $select */
    $select = $this->connection->select('url_alias');
    foreach ($criteria as $field => $value) {
      $select->condition($field, $value);
    }
    $result = $select
      ->fields(static::COLLECTION_NAME)
      ->execute()
      ->fetchAssoc();

    if (empty($result)) {
      $result = NULL;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getTraversable($minId = -1) {
    $sql = <<<SQL
SELECT ua.pid, ua.source, ua.alias, ua.language
FROM url_alias ua
WHERE pid > :minId
ORDER BY ua.pid
SQL;

    $class = '\Drupal\mongodb_path\UrlAlias';

    /** @var \PDOStatement $ret */
    $ret = db_query($sql, [':minId' => intval($minId)]);
    $ret->setFetchMode(\PDO::FETCH_CLASS, $class);
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function getWhitelist() {
    _mongodb_path_trace();

    // For each alias in the database, get the top level component of the system
    // path it corresponds to. This is the portion of the path before the first
    // '/', if present, otherwise the whole path itself.
    $whitelist = array();
    $table_name = static::COLLECTION_NAME;
    $sql = <<<EOT
SELECT DISTINCT
  SUBSTRING_INDEX(source, '/', 1) AS path
FROM {$table_name}
EOT;

    $result = $this->connection->query($sql);
    foreach ($result as $row) {
      $whitelist[$row->path] = TRUE;
    }
    variable_set('path_alias_whitelist', $whitelist);
    return $whitelist;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupAliases(array $paths, $language, $first_pass = FALSE) {
    throw new \BadMethodCallException('Alias lookup is not implemented in the DBTNG storage.');
  }

  /**
   * {@inheritdoc}
   */
  public function lookupSource($path, $language) {
    throw new \BadMethodCallException('Source lookup is not implemented in the DBTNG storage.');
  }

  /**
   * {@inheritdoc}
   */
  public function save(array &$path) {
    _mongodb_path_trace();

    $path = array_intersect_key($path, $this->aliasKeys);

    // Matched not set or set to NULL.
    if (empty($path['pid'])) {
      $query = $this->connection->insert(static::COLLECTION_NAME);
    }
    else {
      $query = $this->connection->update(static::COLLECTION_NAME)
        ->condition('pid', $path['pid']);
    }

    $query->fields($path);
    $query->execute();

    if (empty($path['pid'])) {
      $path['pid'] = $this->connection->lastInsertId();
    }
  }

}
