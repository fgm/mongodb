<?php

declare(strict_types = 1);

namespace Drupal\mongodb_files\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mongodb\DatabaseFactory;
use Drupal\mongodb\MongoDb;
use Drupal\mongodb_files\Files;
use MongoDB\Database;
use MongoDB\GridFS\Bucket;

// phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
// phpcs:disable Squiz.PHP.NonExecutableCode.ReturnNotRequired

/**
 * Stream wrapper class for interacting with MongoDB GridFS.
 *
 * @package Drupal\mongodb_files
 */
class GridFs implements StreamWrapperInterface {

  use StringTranslationTrait;

  /**
   * The database in which the stores are created.
   *
   * @var \MongoDB\Database
   */
  protected Database $database;

  /**
   * The GridFS bucket in the files database.
   *
   * @var \MongoDB\GridFS\Bucket
   */
  protected Bucket $bucket;

  /**
   * The filename, without the scheme.
   *
   * @var string
   */
  protected string $filename;

  /**
   * The resource for an ongoing upload file operation.
   *
   * @var resource
   */
  protected $uploadRes;

  /**
   * The resource for an ongoing download file operation.
   *
   * @var resource
   */
  protected $downloadRes;

  /**
   * Constructor.
   *
   * Because stream wrappers are loaded by PHP without visibility by Drupal,
   * they can not support Drupal/Symfony standard dependency injection.
   */
  public function __construct(?DatabaseFactory $factory = NULL) {
    // This will happen in Kernel tests, when no one is instantiating the
    // service but PHP.
    if (empty($factory)) {
      // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      $dic = \Drupal::getContainer();
      $factory = $dic->get(MongoDb::SERVICE_DB_FACTORY);
    }
    assert($factory instanceof DatabaseFactory);

    $db = $factory->get(Files::DB_FILES);
    assert($db instanceof Database);
    $this->database = $db;

    $this->bucket = $db->selectGridFSBucket();
  }

  /**
   * {@inheritdoc}
   */
  public function dir_closedir() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_opendir($path, $options) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_readdir() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_rewinddir() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function mkdir($path, $mode, $options) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($path_from, $path_to) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function rmdir($path, $options) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_cast($cast_as) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_close() {
    if (is_resource($this->uploadRes)) {
      fclose($this->uploadRes);
    }
    if (is_resource($this->downloadRes)) {
      fclose($this->downloadRes);
    }
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_eof() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_flush() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_lock($operation) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_metadata($path, $option, $value) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_open($path, $mode, $options, &$opened_path): bool {
    // Shortcut: cannot open '' for whatever purpose.
    if (empty($path)) {
      return FALSE;
    }
    $url = parse_url($path);
    if (!is_array($url) || $url['scheme'] !== Files::SCHEME) {
      return FALSE;
    }
    // parse_url interprets the first path component of a URL as a host.
    $filename = implode('', [$url['host'] ?? '', $url['path'] ?? '']);

    // No need to check existence when writing to files.
    if (is_int(mb_strpos($mode, "w"))) {
      $this->filename = $filename;
      return TRUE;
    }
    // But we need to check it when reading them.
    $found = $this->bucket->findOne([
      'filename' => $filename,
    ]);
    if (!is_array($found)) {
      return FALSE;
    }
    $this->filename = $filename;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_read($count) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_seek($offset, $whence = SEEK_SET) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_set_option($option, $arg1, $arg2) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_stat() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_tell() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_truncate($new_size) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_write($data): int {
    // Should not happen: it would mean someone write to an unopened file.
    if (empty($this->filename)) {
      return 0;
    }
    if (empty($this->uploadRes)) {
      $this->uploadRes = $this->bucket->openUploadStream($this->filename);
    }
    $n = (int) fwrite($this->uploadRes, $data);
    return $n;
  }

  /**
   * {@inheritdoc}
   */
  public function unlink($path) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function url_stat($path, $flags) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    // For now, we do not allow include/require from that wrapper, so do not
    // return StreamWrapperInterface::LOCAL_NORMAL.
    return StreamWrapperInterface::NORMAL;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t('MongoDB');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Files stored in MongoDB');
  }

  /**
   * {@inheritdoc}
   */
  public function setUri($uri) {
    assert($uri === Files::SCHEME . "://");
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return Files::SCHEME . "://";
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function realpath() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri = NULL) {
    return;
  }

}
