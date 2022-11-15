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

use CRM_Xcm_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests the overwrite feature
 *
 * @see https://github.com/systopia/de.systopia.xcm/issues/32
 *
 * @group headless
 */
class CRM_Xcm_OverwriteTest extends CRM_Xcm_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * Example: Test that a version is returned.
   */
  public function testSimpleOverwrite() {
    // set up our test scenario
    $test_contact = $this->createContactWithRandomEmail([
        'first_name' => 'Aaron',
        'last_name'  => 'Aaronson'
    ]);
    $this->setXCMRules(['CRM_Xcm_Matcher_EmailMatcher']);

    // run XCM and make sure we're matched
    $this->assertXCMLookup([
        'email'      => $test_contact['email'],
        'first_name' => 'Bert',
        'last_name'  => 'Bertson'
    ], $test_contact['id']);

    // after this, there should NOT be any changes (because we haven't enabled the overwrite
    $test_contact_v2 = civicrm_api3('Contact', 'getsingle', ['id' => $test_contact['id']]);
    $this->assertEntityEqual($test_contact, $test_contact_v2, ['first_name', 'last_name']);

    // NOW: activate overwrite.
    $this->setXCMOption('override_fields', ['first_name']);
    $this->assertXCMLookup([
        'email'      => $test_contact['email'],
        'first_name' => 'Bert',
        'last_name'  => 'Bertson'
    ], $test_contact['id']);

    // this time, the first_name should have changed
    $test_contact_v3 = civicrm_api3('Contact', 'getsingle', ['id' => $test_contact['id']]);
    $this->assertEquals('Bert', $test_contact_v3['first_name'], 'override not working!');

    // and the last_name shouldn't have
    $this->assertEntityEqual($test_contact, $test_contact_v3, ['last_name']);
  }

  /**
   * Test if the entity overwrite works
   */
  public function testDetailOverwrite() {
    $details_to_test = ['phone', 'im', 'website', 'address']; // TODO: test email, but needs different identification
    $this->setXCMRules(['CRM_Xcm_Matcher_EmailMatcher']);
    $this->setXCMOption('fill_details', []);
    $this->setXCMOption('override_details', $details_to_test);
    $location_type_ids = array_keys($this->getLocationTypeIDs());
    $additional_create_attributes = [];

    foreach ($details_to_test as $entity) {
      // create test contact
      $test_contact = $this->createContactWithRandomEmail([
          'first_name' => 'Carl',
          'last_name'  => 'Carlson'
      ]);

      $non_primary = rand(10000000,99999999);
      $primary     = $non_primary . '9';

      switch (strtolower($entity)) {
        case 'email':
          $has_primary = TRUE;
          $attribute = 'email';
          $identifying_attributes = ['location_type_id'];
          break;

        case 'phone':
          $has_primary = TRUE;
          $non_primary = "+1 {$non_primary}";
          $primary     = "+2 {$primary}";
          $attribute   = 'phone';
          $identifying_attributes = ['location_type_id', 'phone_type_id'];
          $additional_create_attributes = ['phone_type_id' => 1];
          $this->setXCMOption('primary_phone_type', '1');
          break;

        case 'im':
          $has_primary = TRUE;
          $attribute   = 'name';
          $identifying_attributes = ['location_type_id', 'provider_id'];
          break;

        case 'website':
          $has_primary = FALSE;
          $non_primary = "http://{$non_primary}.com";
          $primary     = "http://{$primary}.net";
          $attribute   = 'url';
          $identifying_attributes = ['website_type_id'];
          break;

        case 'address':
          $has_primary = TRUE;
          $non_primary = "{$non_primary} Str. 1";
          $primary     = "{$primary}-Weg 23";
          $attribute   = 'street_address';
          $identifying_attributes = ['location_type_id'];
          break;

        default:
          # unknown type
          $has_primary = FALSE;
          $identifying_attributes = [];
          $this->throwException(new Exception("Unknown type {$entity}!"));
      }


      // create details with contact
      $primary_detail = $this->assertAPI3($entity, 'create', [
          $attribute         => $primary,
          'contact_id'       => $test_contact['id'],
          'location_type_id' => $location_type_ids[1],
          'is_primary'       => 1] + $additional_create_attributes);
      $primary_detail = $this->assertAPI3($entity, 'getsingle', ['id' => $primary_detail['id']]);

      if ($has_primary) {
        $non_primary_detail = $this->assertAPI3($entity, 'create', [
            $attribute         => $non_primary,
            'contact_id'       => $test_contact['id'],
            'location_type_id' => $location_type_ids[0],
            'is_primary'       => 0] + $additional_create_attributes);
        $non_primary_detail = $this->assertAPI3($entity, 'getsingle', ['id' => $non_primary_detail['id']]);
      } else {
        $non_primary_detail = $primary_detail;
      }

      // check if primary was set (if applicable)
      if ($has_primary) {
        $this->assertEquals(1, $primary_detail['is_primary']);
        $this->assertEquals(0, $non_primary_detail['is_primary']);
      }

      // test with override primary off and on
      $this->setXCMOption('override_details_primary', 0);
      $this->assertDetailOverride($entity, $test_contact, $attribute, !$has_primary, $primary_detail, $identifying_attributes, '1');
      $this->assertDetailOverride($entity, $test_contact, $attribute, TRUE, $non_primary_detail, $identifying_attributes, '2');

      $this->setXCMOption('override_details_primary', 1);
      $this->assertDetailOverride($entity, $test_contact, $attribute, TRUE, $primary_detail, $identifying_attributes, '3');
      $this->assertDetailOverride($entity, $test_contact, $attribute, TRUE, $non_primary_detail, $identifying_attributes, '4');
    }
  }

  /**
   * Helper function for testDetailOverwrite
   * Simply try to override the detail
   */
  protected function assertDetailOverride($entity, $test_contact, $attribute, $expects_success, $detail, $identifying_attributes, $suffix = 's') {
    // compile lookup query
    $lookup_query = [
        'email'            => $test_contact['email'],
        'first_name'       => 'Carl',
        'last_name'        => 'Carlson',
        $attribute         => $detail[$attribute] . $suffix,
    ];
    foreach ($identifying_attributes as $identifying_attribute) {
      if (!empty($detail[$identifying_attribute])) {
        $lookup_query[$identifying_attribute] = $detail[$identifying_attribute];
      }
    }

    $old_detail_count = $this->assertAPI3($entity, 'getcount', [
        'contact_id' => $test_contact['id'],
        $attribute   => $detail[$attribute] . $suffix]);

    $this->assertXCMLookup($lookup_query, $test_contact['id']);

    $new_detail_count = $this->assertAPI3($entity, 'getcount', [
        'contact_id' => $test_contact['id'],
        $attribute   => $detail[$attribute] . $suffix]);

    $old_detail_still_exists = (bool) $this->assertAPI3($entity, 'getcount', ['id' => $detail['id']]);

    // evaluate
    if ($expects_success) {
      $this->assertGreaterThan($old_detail_count, $new_detail_count, "Detail '{$detail[$attribute]}{$suffix}' not created");
      $this->assertFalse($old_detail_still_exists, "Detail '{$detail[$attribute]}' still exists.");
    } else {
      $this->assertLessThanOrEqual($old_detail_count, $new_detail_count, "Detail '{$detail[$attribute]}{$suffix}' created despite primary protection.");
      $this->assertTrue($old_detail_still_exists, "Detail '{$detail[$attribute]}' deleted despite primary protection.");
    }
  }

  /**
   * Test the the overwrite function doesn't trigger the creation of a change activity
   */
  public function testSimpleOverwriteNoChangeActivity() {
    // set up our test scenario
    $test_contact = $this->createContactWithRandomEmail([
        'first_name' => 'Ceron',
        'last_name'  => 'Ceronson'
    ]);
    $this->setXCMRules(['CRM_Xcm_Matcher_EmailMatcher']);

    // run XCM and make sure we're matched
    $this->enableDiffActivity("testSimpleOverwriteNoChangeActivity");
    $this->assertXCMLookup([
        'email'      => $test_contact['email'],
        'first_name' => 'Ceron',
        'last_name'  => 'Ceronson'
    ], $test_contact['id']);

    // the names are the same, so there shouldn't be any diff activity
    $activity_count = $this->getDiffActivityCount($test_contact['id'], "testSimpleOverwriteNoChangeActivity");
    $this->assertEquals(0, $activity_count, "Unexpected diff activity");


    $this->setXCMOption('override_fields', ['first_name','last_name']);
    $this->assertXCMLookup([
        'email'      => $test_contact['email'],
        'first_name' => 'Derron',
        'last_name'  => 'Derpson'
    ], $test_contact['id']);

    // both overwritten, so there shouldn't be any diff activity either
    $activity_count = $this->getDiffActivityCount($test_contact['id'], "testSimpleOverwriteNoChangeActivity");
    $this->assertEquals(0, $activity_count, "Unexpected diff activity");



    $this->setXCMOption('override_fields', ['first_name']);
    $this->assertXCMLookup([
        'email'      => $test_contact['email'],
        'first_name' => 'Ethan',
        'last_name'  => 'Earmark'
    ], $test_contact['id']);

    // one overwritten, so there SHOULD be any diff activity
    $activity_count = $this->getDiffActivityCount($test_contact['id'], "testSimpleOverwriteNoChangeActivity");
    $this->assertEquals(1, $activity_count, "Missing diff activity");
  }

}
