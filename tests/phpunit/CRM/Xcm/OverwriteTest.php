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
