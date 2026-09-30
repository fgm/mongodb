<?php

declare(strict_types=1);

namespace Drupal\mongodb\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mongodb\Install\Severity;

/**
 * Requirements hook implementations for mongodb.
 *
 * The checks are static so that every phase shares them: the install phase
 * runs before the module services exist, and on Drupal 10 before its classes
 * are autoloadable.
 */
class MongodbRequirements {

  /**
   * The name of the PHP extension, of the settings key and of the requirement.
   *
   * MongoDb::MODULE is not used: this class must load without the autoloader.
   */
  const NAME = 'mongodb';

  /**
   * Implements hook_runtime_requirements().
   *
   * @return array<string,array<string,mixed>>
   *   The requirements.
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    return static::check();
  }

  /**
   * Implements hook_update_requirements().
   *
   * @return array<string,array<string,mixed>>
   *   The requirements.
   */
  #[Hook('update_requirements')]
  public function update(): array {
    return static::check();
  }

  /**
   * Checks the requirements, identically in every phase.
   *
   * @return array<string,array<string,mixed>>
   *   The requirements.
   */
  public static function check(): array {
    $ret = [];
    $ret[self::NAME] = [
      'title' => new TranslatableMarkup('MongoDB'),
      'severity' => Severity::ok(),
    ];
    $description = [];

    if (!self::checkExtension($ret)) {
      return $ret;
    }

    if (!self::checkExtensionVersion($ret, $description)) {
      return $ret;
    }

    /** @var array{clients: array<string,array<string,mixed>>, databases: array<string,array{0:string,1:string}>} $settings */
    $settings = Settings::get(self::NAME) ?? [];
    $databases = $settings['databases'] ?? [];

    if (!self::checkAliases($ret, $description, $databases)) {
      return $ret;
    }

    if (!self::checkDatabases($settings, $databases, $description)) {
      $ret[self::NAME] = [
        'value' => new TranslatableMarkup('Inconsistent database/client settings.'),
        'severity' => Severity::error(),
        'description' => $description,
      ] + $ret[self::NAME];
      return $ret;
    }

    $ret[self::NAME] += [
      'value' => new TranslatableMarkup('Valid configuration'),
      'description' => $description,
    ];
    return $ret;
  }

  /**
   * Requirements check: existence of the client aliases.
   *
   * @param array<string,array<string,mixed>> $ret
   *   The running requirements array.
   * @param array<int,mixed> $description
   *   The running description array.
   * @param array<string,array{0:string,1:string}> $databases
   *   The databases array, sanitized from settings.
   *
   * @return bool
   *   Did requirements check succeed ?
   */
  protected static function checkAliases(array &$ret, array &$description, array $databases): bool {
    $success = !empty($databases);
    if (!$success) {
      $ret[self::NAME] = [
        'severity' => Severity::warning(),
        'value' => new TranslatableMarkup('No database aliases found in settings. Did you actually configure your settings ?'),
        'description' => [
          '#theme' => 'item_list',
          '#items' => $description,
        ],
      ] + $ret[self::NAME];
    }

    return $success;
  }

  /**
   * Requirements check: database vs clients consistency.
   *
   * @param array{clients: array<string,array<string,mixed>>, databases: array<string,array{0:string,1:string}>} $settings
   *   The mongodb settings.
   * @param array<string,array{0:string,1:string}> $databases
   *   The databases, sanitized from settings.
   * @param array<scalar,mixed> $description
   *   The running description array.
   *
   * @return bool
   *   Did requirements check succeed ?
   */
  protected static function checkDatabases(array $settings, array $databases, array &$description): bool {
    // Should be an array, but a PHPdoc @param is not an actual type constraint.
    $aliases = $settings['clients'] ?? [];
    $warnings = [];
    $success = TRUE;
    foreach ($databases as $database => $list) {
      [$client] = $list;
      if (empty($aliases[$client])) {
        $success = FALSE;
        $warnings[] = new TranslatableMarkup('Database "@db" references undefined client "@client".', [
          '@db' => $database,
          '@client' => $client,
        ]);
      }
    }

    if ($success) {
      $warnings = [new TranslatableMarkup('Databases and clients are consistent.')];
    }

    $description = [
      '#theme' => 'item_list',
      '#items' => array_merge($description, $warnings),
    ];

    return $success;
  }

  /**
   * Requirements check: MongoDB extension.
   *
   * @param array<string,array<string,mixed>> $ret
   *   The running requirements array.
   *
   * @return bool
   *   Did requirements check succeed ?
   */
  protected static function checkExtension(array &$ret): bool {
    $success = extension_loaded(self::NAME);
    if (!$success) {
      $ret[self::NAME] = [
        'value' => new TranslatableMarkup('Extension not loaded'),
        'description' => new TranslatableMarkup('Mongodb requires the non-legacy PHP MongoDB extension (@name) to be installed.', [
          '@name' => self::NAME,
        ]),
        'severity' => Severity::error(),
      ] + $ret[self::NAME];
    }
    return $success;
  }

  /**
   * Requirements check: extension version.
   *
   * @param array<string,array<string,mixed>> $ret
   *   The running requirements array.
   * @param array<int,\Drupal\Core\StringTranslation\TranslatableMarkup> $description
   *   The running description array.
   *
   * @return bool
   *   Did requirements check succeed ?
   */
  protected static function checkExtensionVersion(array &$ret, array &$description): bool {
    $minimumVersion = '1.1.7';
    $extensionVersion = (string) phpversion(self::NAME);
    $versionStatus = version_compare($extensionVersion, $minimumVersion);
    $success = $versionStatus >= 0;
    $description[] = $success
      ? new TranslatableMarkup('Extension version @version found.', ['@version' => $extensionVersion])
      : new TranslatableMarkup('Module needs extension @name @minimum_version or later, found @version.', [
        '@name' => self::NAME,
        '@minimum_version' => $minimumVersion,
        '@version' => $extensionVersion,
      ]);

    if (!$success) {
      $ret[self::NAME]['severity'] = Severity::error();
    }

    return $success;
  }

}
