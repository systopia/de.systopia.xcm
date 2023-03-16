<?php

use CRM_Xcm_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Some tests to help document how this works, and maybe, even, test it.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_XCMTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public $contacts = [];

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {

    // Start with the test-default config.
    $this->configureXcm();

    parent::setUp();
  }
  /**
   * Create a new Individual. Convenience method.
   *
   * Stores new ID on $contacts so it can be cleaned up later.
   *
   * @param Array $params
   * @return Array $contact record.
   */
  public function addContact($params) {
    // Create a contact.
    $params += [
      'contact_type' => 'Individual',
    ];
    $contact = civicrm_api3('Contact', 'create', $params);
    $contact_id = $contact['id'];
    $this->contacts[$contact_id] = $contact['values'][$contact_id];
    return $this->contacts[$contact_id];
  }

  /**
   * I am this lazy.
   */
  public function getOrCreate($params) {
    $contact = civicrm_api3('Contact', 'getorcreate', $params + ['debug' => 1]);
    $contact = civicrm_api3('Contact', 'get', ['sequential' => 1, 'id' => $contact['id']]);
    $this->contacts[$contact['id']] = $contact['values'][0];
    return $contact['values'][0];
  }
  public function tearDown() {

    // Delete our contacts.
    foreach ($this->contacts as $contact_id=>$contact) {
      civicrm_api3('Contact', 'delete', [
        'id' => $contact_id,
        'skip_undelete' => TRUE,
      ]);
    }
    parent::tearDown();
  }
  /**
   * Configure defaults plus given params.
   *
   * @param Array $params
   */
  public function configureXcm($params=[]) {
    // Configure XCM.
    $toSet = $params + [
      'fill_address'               => NULL,
      'fill_fields_multivalue'     => NULL,
      'fill_details'               => NULL,
      'fill_details_primary'       => NULL,
      'default_location_type'      => 1, // Home
      'picker'                     => 'min', // Oldest contact.
      'duplicates_activity'        => 0,
      'duplicates_subject'         => NULL,
      'diff_handler'               => 'none',
      'diff_activity'              => 1,
      'diff_activity_subject'      => '',
      'diff_processing'            => 0,
      'diff_current_location_type' => 1,
      'diff_old_location_type'     => 0,
      'fill_fields'                => ['first_name', 'last_name'],
      'case_insensitive'           => 1,
    ];
    $matchers = [
      "rule_1" => "CRM_Xcm_Matcher_EmailFullNameMatcher",
      "rule_2" => "CRM_Xcm_Matcher_EmailMatcher",
      "rule_3" => "0",
      "rule_4" => "0",
      "rule_5" => "0",
      "rule_6" => "0",
      "rule_7" => "0",
      "rule_8" => "0",
      "rule_9" => "0"
    ];

    $config = CRM_Xcm_Configuration::getConfigProfile();
    $options = array_merge($config->getOptions(), $toSet);
    $config->setOptions($options);
    $config->setRules($matchers);
    $config->store();
  }


  /**
   * Test that a new contact is created.
   */
  public function testNewContactCreatedNotRecreated() {
    $n = (int) civicrm_api3('Contact', 'getcount', []);
    $result = $this->getOrCreate(['first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com']);
    $this->assertContactCountIs($n+1);
    $result = $this->getOrCreate(['first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com']);
    $this->assertContactCountIs($n+1);
  }

  /**
   * Test that a new contact is created with address.
   */
  public function testNewContactCreatedWithAddress() {
    $n = (int) civicrm_api3('Contact', 'getcount', []);
    $address = [
      'street_address' => '1 The Cave',
      'supplemental_address_1' => 'Somewhere',
      'city' => 'Caveton',
    ];
    $result = $this->getOrCreate([
      'first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com',
    ] + $address);
    $this->assertContactCountIs($n+1);
    foreach ($address as $k=>$v) {
      $this->assertEquals($v, $result[$k]);
    }
  }

  /**
   * Test that an existing contact's address is not overwritten
   */
  public function testExistingAddressNotOverwritten() {
    $this->testNewContactCreatedWithAddress();

    $n = (int) civicrm_api3('Contact', 'getcount', []);
    $address = [
      'street_address' => '2 The Cave',
      'supplemental_address_1' => 'Nowhere',
      'city' => 'Rockton',
    ];
    $result = $this->getOrCreate([
      'first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com',
    ] + $address);
    $this->assertContactCountIs($n);
    $original_address = [
      'street_address' => '1 The Cave',
      'supplemental_address_1' => 'Somewhere',
      'city' => 'Caveton',
    ];
    foreach ($original_address as $k=>$v) {
      $this->assertEquals($v, $result[$k]);
    }
  }

  /**
   * Test that an address is added if not there.
   */
  public function testAddressAddedToExistingContact() {
    $this->configureXcm(['fill_address' => 1]);
    // Create without address.
    $n = (int) civicrm_api3('Contact', 'getcount', []);
    $result = $this->getOrCreate([ 'first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com' ]);
    $this->assertContactCountIs($n+1);

    // Same but with address.
    $address = [ 'street_address' => '1 The Cave', 'supplemental_address_1' => 'Somewhere', 'city' => 'Caveton', ];
    $result = $this->getOrCreate([ 'first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com' ]+$address);
    $this->assertContactCountIs($n+1);

    foreach ($address as $k=>$v) {
      $this->assertEquals($v, $result[$k]);
    }
  }

  /**
   * Important that a partial address does not fill into a different address.
   */
  public function testAddressFillDoesNotMangleExisting() {
    $this->configureXcm(['fill_address' => 1]);
    // Create without address.
    $n = (int) civicrm_api3('Contact', 'getcount', []);
    $original_address = [ 'street_address' => '1 The Cave', 'city' => 'Caveton', ];
    $result = $this->getOrCreate([ 'first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com' ]+$original_address);
    $this->assertContactCountIs($n+1);

    // Different address
    $address = [ 'street_address' => 'xxxx', 'supplemental_address_1' => 'yyy', 'city' => 'zzz' ];
    $result = $this->getOrCreate([ 'first_name' => 'Wilma', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com' ]+$address);
    $this->assertContactCountIs($n+1);

    foreach ($original_address as $k=>$v) {
      $this->assertEquals($v, $result[$k]);
    }
  }

  /**
   * Important that a partial address does not fill into a different address.
   */
  public function testNameFill() {
    // Create without last name
    $n = (int) civicrm_api3('Contact', 'getcount', []);
    $result = $this->getOrCreate([ 'first_name' => 'Wilma', 'email' => 'wilma@example.com' ]);
    $this->assertContactCountIs($n+1);

    // Same but with name changes/additions
    $result = $this->getOrCreate([ 'first_name' => 'Betty', 'last_name' => 'Flintstone', 'email' => 'wilma@example.com' ]);
    $this->assertContactCountIs($n+1);

    // First name should not be overwritten, but last name should as it does not exist.
    $this->assertEquals('Wilma', $result['first_name']);
    $this->assertEquals('Flintstone', $result['last_name']);
  }

  /**
   * count contacts
   *
   * @param int $expected;
   */
  public function assertContactCountIs($expected) {
    $result = (int) civicrm_api3('Contact', 'getcount', []);
    $this->assertEquals($expected, $result);
  }

}
