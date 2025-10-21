<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Xcm_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * This class adds some base functions for PHP unit tests for the XCM
 */
// phpcs:disable Generic.Files.LineLength.TooLong, Generic.NamingConventions.AbstractClassNamePrefix.Missing
abstract class CRM_Xcm_TestBase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
// phpcs:enable

  protected static $counter = 0;

  protected $diff_activity_type_id = NULL;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Execute the API call and assert that it is successfull
   *
   * @param $entity string entity
   * @param $action string action
   * @param $params array parameters
   * @return array result
   */
  public function assertAPI3($entity, $action, $params) {
    try {
      return civicrm_api3($entity, $action, $params);
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->assertFalse(TRUE, 'API Exception: ' . $ex->getMessage());
      return NULL;
    }
  }

  /**
   * Get the default activity type id used for diff activities, unless otherwise specified
   *
   * @return array|null
   */
  public function getDefaultDiffActivityTypeID() {
    if ($this->diff_activity_type_id === NULL) {
      $existing_activity = $this->assertAPI3(
        'OptionValue',
        'get',
        ['option_group_id' => 'activity_type', 'name' => 'xcm_test_diff_activity']
      );
      if (!empty($existing_activity['id'])) {
        $this->diff_activity_type_id = $this->assertAPI3(
          'OptionValue',
          'getvalue',
          ['id' => $existing_activity['id'], 'return' => 'value']
        );
      }
      else {
        // create it
        $new_activity = $this->assertAPI3('OptionValue', 'create', [
          'option_group_id' => 'activity_type',
          'name'            => 'xcm_test_diff_activity',
          'label'           => 'XCM Test Suite Diff Activity',
        ]);
        $this->diff_activity_type_id = $this->assertAPI3(
          'OptionValue',
          'getvalue',
          ['id' => $new_activity['id'], 'return' => 'value']
        );
      }
    }
    return $this->diff_activity_type_id;
  }

  /**
   * Enable the generation of diff activities
   *
   * @param $activity_subject
   * @param null $profile
   * @param null $activity_type_id
   */
  public function enableDiffActivity($activity_subject = 'test diff', $profile = NULL, $activity_type_id = NULL) {
    $config = CRM_Xcm_Configuration::getConfigProfile($profile);

    // get activity type
    if (!$activity_type_id) {
      $activity_type_id = $this->getDefaultDiffActivityTypeID();
    }

    $options = $config->getOptions();
    $options['diff_handler']  = 'diff';
    $options['diff_activity'] = $activity_type_id;
    $options['diff_activity_subject'] = $activity_subject;
    $options['default_location_type'] = 1;

    $config->setOptions($options);
    $config->store();
  }

  /**
   * Get the count of the given activity
   *
   * @param $contact_id
   * @param string $subject
   */
  public function getDiffActivityCount($contact_id, $subject = 'test diff', $activity_type_id = NULL) {
    // get activity type
    if (!$activity_type_id) {
      $activity_type_id = $this->getDefaultDiffActivityTypeID();
    }

    return $this->assertAPI3('Activity', 'getcount', [
      'activity_type_id'  => $activity_type_id,
      'target_contact_id' => $contact_id,
      'subject'           => $subject,
    ]);
  }

  /**
   * Set the matcher list
   *
   * @param $matchers array  matcher keys
   * @param $profile  string profile to be used (default is default)
   */
  public function setXCMRules($matchers, $profile = NULL) {
    $config = CRM_Xcm_Configuration::getConfigProfile($profile);
    $config->setRules($matchers);
    $config->store();
  }

  /**
   * Set an XCM option
   *
   * @param $key      string key
   * @param $value    mixed  value to set
   * @param $profile  string profile to be used (default is default)
   */
  public function setXCMOption($key, $value, $profile = NULL) {
    $config = CRM_Xcm_Configuration::getConfigProfile($profile);
    $options = $config->getOptions();
    $options[$key] = $value;
    $config->setOptions($options);
    $config->store();
  }

  /**
   * Verifies that queried with the given data the submitted contact_id is matched
   *
   * @param $contact_data array data to use for the tests
   * @param $contact_id   int   contact_id that is expected to be identified
   */
  public function assertXCMLookup($contact_data, $contact_id) {
    if (empty($contact_data['contact_type'])) {
      $contact_data['contact_type'] = 'Individual';
    }

    $result = civicrm_api3('Contact', 'getorcreate', $contact_data);
    $this->assertEquals($contact_id, $result['id'], 'Unexpected contact identified');
  }

  /**
   * get a list of active location type
   * @return array lc_id => lc_name
   */
  public function getLocationTypeIDs() {
    $list = [];
    $lt_query = $this->assertAPI3('LocationType', 'get', ['option.limit' => 0, 'return' => 'id,name']);
    foreach ($lt_query['values'] as $lt) {
      $list[$lt['id']] = $lt['name'];
    }
    return $list;
  }

  /**
   * Create a new contact with a random email address. Good for simple
   *  tests via the 'CRM_Xcm_Matcher_EmailOnlyMatcher'
   *
   * @param array $contact_data
   */
  public function createContactWithRandomEmail($contact_data = []) {
    if (empty($contact_data['contact_type'])) {
      $contact_data['contact_type'] = 'Individual';
    }
    if (empty($contact_data['first_name'])) {
      $contact_data['first_name'] = 'Random';
    }
    if (empty($contact_data['last_name'])) {
      $contact_data['last_name'] = 'Bloke';
    }

    // add random email
    self::$counter++;
    $contact_data['email'] = sha1(microtime() . self::$counter) . '@nowhere.nil';

    $contact = civicrm_api3('Contact', 'create', $contact_data);
    $new_contact = civicrm_api3('Contact', 'getsingle', ['id' => $contact['id']]);
    self::assertEntityEqual($contact_data, $new_contact, ['email']);
    return $new_contact;
  }

  /**
   * Asser that the two entities (arrays) are equal in all fields
   *
   * @param $entity_1 array entity 1
   * @param $entity_2 array entity 2
   * @param $fields   array explicit list of fields to compare. othewise it's all fields of entity_1
   */
  public function assertEntityEqual($entity_1, $entity_2, $fields = NULL) {
    if ($fields === NULL) {
      $fields = array_keys($entity_1);
    }

    foreach ($fields as $field) {
      $value_1 = CRM_Utils_Array::value($field, $entity_1);
      $value_2 = CRM_Utils_Array::value($field, $entity_2);
      $this->assertEquals($value_1, $value_2, 'Entities differ!');
    }
  }

}
