<?php

/**
 * @file
 * Contains MongoDB_Path_Resolver.
 */

/**
 * Class MongoDB_Path_Resolver.
 */
class MongoDbPathResolver implements MongoDbPathResolverInterface {

  /**
   * The database to use.
   *
   * @var \MongoDB
   */
  protected $mongo;

  /**
   * Constructor.
   */
  protected function __construct(\MongoDB $mongo) {
    $this->mongo = $mongo;
  }

  /**
   * Fake a flush using a flush timestamp, à la Varnish.
   */
  public function flush() {
    variable_set(static::FLUSH_VAR, REQUEST_TIME);
  }

  /**
   * Resolver factory.
   *
   * This is the only part of the Resolver to be aware of the global mongodb().
   *
   * @return static
   *   The resolver instance.
   */
  public static function create() {
    module_load_include('module', 'mongodb');
    return new static(mongodb());
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalPath($path, $language = NULL) {
    return drupal_get_normal_path($path, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function getPathAlias($path = NULL, $path_language = NULL) {
    return drupal_get_path_alias($path, $path_language);
  }

}
