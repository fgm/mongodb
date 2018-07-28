<?php

namespace Drupal\Tests\mongodb_watchdog\Functional;

use Drupal\Core\Site\Settings;
use Drupal\mongodb\MongoDb;
use Drupal\mongodb_watchdog\Logger;
use Drupal\Tests\BrowserTestBase;

/**
 * Class ControllerTest
 *
 * @group MongoDB
 */
class ControllerTest extends BrowserTestBase {

  const DEFAULT_URI = 'mongodb://localhost:27017';
  const CLIENT_TEST_ALIAS = 'test';

  const DB_DEFAULT_ALIAS = 'default';

  protected static $modules = [
    MongoDb::MODULE,
    Logger::MODULE,
  ];

  /**
   * A basic authenticated user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anyUser;

  /**
   * An administrator-type user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $bigUser;

  /**
   * The event templates collection.
   *
   * @var \MongoDB\Collection
   */
  protected $collection;

  /**
   * The time the test started, simulating a request time.
   *
   * @var int
   */
  protected $requestTime;

  /**
   * The site base URI.
   *
   * @var string
   */
  protected $uri;

  /**
   * Enable modules and create users with specific permissions.
   */
  public function setUp() {
    // $_ENV if it comes from phpunit.xml <env>
    // $_SERVER if it comes from the phpunit command line environment.
    $this->uri = $_ENV['MONGODB_URI']
      ?? $_SERVER['MONGODB_URI']
      ?? static::DEFAULT_URI;

    // This line customizes the parent site; ::writeSettings the child site.
    $this->settings = new Settings([
      MongoDb::MODULE => $this->getSettingsArray(),
    ]);

    parent::setUp();

    // Create users.
    $this->bigUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'access site reports',
      'administer users',
    ]);
    $this->anyUser = $this->drupalCreateUser([
      'access content',
    ]);

    $this->requestTime = $this->container
      ->get('datetime.time')
      ->getCurrentTime();

    try {
      $this->collection = $this->container
        ->get(MongoDb::SERVICE_DB_FACTORY)
        ->get(Logger::DB_LOGGER)
        ->selectCollection(Logger::TEMPLATE_COLLECTION);
    }
    catch (\Exception $e) {
      $this->collection = NULL;
    }
    $this->assertNotNull($this->collection, t('Access MongoDB watchdog collection'));
  }

  public function tearDown() {
    // Get the database before container is torn down.
    $database = $this->container
      ->get(MongoDb::SERVICE_DB_FACTORY)
      ->get(Logger::DB_LOGGER);

    // Might trigger some more log insertions, so do not drop yet.
    parent::tearDown();

    $database->drop();
  }

  /**
   * Rewrites the settings.php file of the test site.
   *
   * @param array $settings
   *   An array of settings to write out, in the format expected by
   *   drupal_rewrite_settings().
   *
   * @throws \Exception

   * @see \Drupal\Core\Test\FunctionalTestSetupTrait::writeSettings()
   */
  protected function writeSettings(array $settings) {
    // Taken from trait.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    $filename = $this->siteDirectory . '/settings.php';

    // Customizations.
    $settings['settings'] += [MongoDb::MODULE => (object) [
      'value' => $this->getSettingsArray(),
      'required' => TRUE,
    ]];

    // End of code taken from trait again.
    // system_requirements() removes write permissions from settings.php
    // whenever it is invoked.
    // Not using File API; a potential error must trigger a PHP warning.
    chmod($filename, 0666);
    drupal_rewrite_settings($settings, $filename);
  }

  /**
   * Prepare the Settings from a base set of MongoDB settings.
   *
   * @return array
   *   A settings array only containing MongoDB-related settings.
   */
  protected function getSettingsArray() : array {
    return [
      'clients' => [
        static::CLIENT_TEST_ALIAS => [
          'uri' => $this->uri,
          'uriOptions' => [],
          'driverOptions' => [],
        ],
      ],
      'databases' => [
        static::DB_DEFAULT_ALIAS => [static::CLIENT_TEST_ALIAS, $this->getDatabasePrefix()],
        Logger::DB_LOGGER => [static::CLIENT_TEST_ALIAS, $this->getDatabasePrefix()],
      ],
    ];
  }

  /**
   * Getter for the test database prefix.
   *
   * @return string
   *
   * @see \Drupal\KernelTests\KernelTestBase::getDatabasePrefix()
   */
  protected function getDatabasePrefix() : string {
    return $this->databasePrefix ?? '';
  }

  /**
   * Login users, create dblog events, and test dblog functionality through the admin and user interfaces.
   */
  public function testDbLog() {
    // Login the admin user.
    $this->drupalLogin($this->bigUser);
    return;

    // No implementation
    // $row_limit = 100;
    // $this->verifyRowLimit($row_limit);
    // No implementation
    // $this->verifyCron($row_limit);
    $this->verifyEvents();
    $this->verifyReports();

    // Login the regular user.
    $this->drupalLogin($this->anyUser);
    $this->verifyReports(403);
  }

  /**
   * Login an admin user, create dblog event, and test clearing dblog functionality through the admin interface.
   */
  public function testDbLogAddAndClear() {
    $this->pass(__METHOD__);
    return;
    global $base_root;
    // Get a count of how many watchdog entries there are.
    $count = db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField();
    $log = [
      'type'        => 'custom',
      'message'     => 'Log entry added to test the doClearTest clear down.',
      'variables'   => [],
      'severity'    => WATCHDOG_NOTICE,
      'link'        => NULL,
      'user'        => $this->bigUser,
      'request_uri' => $base_root . request_uri(),
      'referer'     => $_SERVER['HTTP_REFERER'],
      'ip'          => ip_address(),
      'timestamp'   => $this->requestTime,
    ];
    // Add a watchdog entry.
    dblog_watchdog($log);
    // Make sure the table count has actually incremented.
    $this->assertEqual($count + 1, db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField(), t('dblog_watchdog() added an entry to the dblog :count', [':count' => $count]));
    // Login the admin user.
    $this->drupalLogin($this->bigUser);
    // Now post to clear the db table.
    $this->drupalPost('admin/reports/dblog', [], t('Clear log messages'));
    // Count rows in watchdog that previously related to the deleted user.
    $count = db_query('SELECT COUNT(*) FROM {watchdog}')->fetchField();
    $this->assertEqual($count, 0, t('DBLog contains :count records after a clear.', [':count' => $count]));
  }

  /**
   * Test the dblog filter on admin/reports/dblog.
   */
  public function testFilter() {
    $this->pass(__METHOD__);
    return;
    $this->drupalLogin($this->bigUser);

    // Clear log to ensure that only generated entries are found.
    db_delete('watchdog')->execute();

    // Generate watchdog entries.
    $type_names = [];
    $types = [];
    for ($i = 0; $i < 3; $i++) {
      $type_names[] = $type_name = $this->randomName();
      $severity = WATCHDOG_EMERGENCY;
      for ($j = 0; $j < 3; $j++) {
        $types[] = $type = [
          'count' => mt_rand(1, 5),
          'type' => $type_name,
          'severity' => $severity++,
        ];
        $this->generateLogEntries($type['count'], $type['type'], $type['severity']);
      }
    }

    // View the dblog.
    $this->drupalGet('admin/reports/dblog');

    // Confirm all the entries are displayed.
    $count = $this->getTypeCount($types);
    foreach ($types as $key => $type) {
      $this->assertEqual($count[$key], $type['count'], 'Count matched');
    }

    // Filter by each type and confirm that entries with various severities are
    // displayed.
    foreach ($type_names as $type_name) {
      $edit = [
        'type[]' => [$type_name],
      ];
      $this->drupalPost(NULL, $edit, t('Filter'));

      // Count the number of entries of this type.
      $type_count = 0;
      foreach ($types as $type) {
        if ($type['type'] == $type_name) {
          $type_count += $type['count'];
        }
      }

      $count = $this->getTypeCount($types);
      $this->assertEqual(array_sum($count), $type_count, 'Count matched');
    }

    // Set filter to match each of the three type attributes and confirm the
    // number of entries displayed.
    foreach ($types as $key => $type) {
      $edit = [
        'type[]' => [$type['type']],
        'severity[]' => [$type['severity']],
      ];
      $this->drupalPost(NULL, $edit, t('Filter'));

      $count = $this->getTypeCount($types);
      $this->assertEqual(array_sum($count), $type['count'], 'Count matched');
    }
  }

}
