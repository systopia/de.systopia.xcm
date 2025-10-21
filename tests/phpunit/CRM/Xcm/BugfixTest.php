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
 * Tests the overwrite feature
 *
 * @see https://github.com/systopia/de.systopia.xcm/issues/32
 *
 * @group headless
 */
class CRM_Xcm_BugfixTest extends CRM_Xcm_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * Test bug#36: the location type is ignored for new
   */
  public function testBug36PhoneNewContact() {
    $test_location_type = $this->getNonDefaultLocationType();

    // create a new contact
    $this->setXCMRules([]);
    $this->setXCMOption('fill_details', []);
    $test_contact = $this->assertAPI3('Contact', 'getorcreate', [
      'contact_type'  => 'Individual',
      'first_name'    => 'Some',
      'last_name'     => 'Guy',
      'location_type' => $test_location_type,
      'email'         => sha1(microtime() . 'b36') . '@nowhere.nil',
    ]);
    $test_contact = $this->assertAPI3('Contact', 'getsingle', ['id' => $test_contact['id']]);

    // check email
    $email = $this->assertAPI3('Email', 'getsingle', ['contact_id' => $test_contact['id']]);
    $this->assertEquals($test_location_type, $email['location_type_id'], 'Bug#36 still active for new contacts!');
  }

  /**
   * Test bug#36: the location type is ignored for new
   */
  public function testBug36Phone() {
    // get a non-default location type
    $test_location_type = $this->getNonDefaultLocationType();

    // set up our test scenario
    $test_contact = $this->createContactWithRandomEmail([
      'first_name' => 'Aaron',
      'last_name'  => 'Aaronson',
    ]);
    $this->setXCMRules(['CRM_Xcm_Matcher_EmailMatcher']);

    // run XCM, adding a phone number
    $this->setXCMOption('fill_details', ['phone']);
    $this->assertXCMLookup([
      'email'            => $test_contact['email'],
      'first_name'       => 'Bert',
      'last_name'        => 'Bertson',
      'phone'            => '123456789',
      'location_type_id' => $test_location_type,
    ], $test_contact['id']);

    // now, let's see if the phone has the right location type
    $phone = $this->assertAPI3('Phone', 'getsingle', [
      'contact_id' => $test_contact['id'],
      'phone'      => '123456789',
    ]);

    $this->assertEquals($test_location_type, $phone['location_type_id'], 'Bug#36 still active!');
  }

  /**
   * Test bug#36: the location type is ignored for new
   */
  public function testBug36Email() {
    // get a non-default location type
    $test_location_type = $this->getNonDefaultLocationType();

    // set up our test scenario
    $this->setXCMOption('fill_details', ['phone']);
    $phone = str_replace('.', '', microtime(1));
    $test_contact = $this->createContactWithRandomEmail([
      'first_name' => 'Aaron',
      'last_name'  => 'Aaronson',
    ]);
    $this->assertAPI3('Phone', 'create', [
      'contact_id' => $test_contact['id'],
      'phone'      => $phone,
    ]);

    // run XCM, adding an email
    $this->setXCMOption('fill_details', ['email']);
    $this->setXCMRules(['CRM_Xcm_Matcher_PhoneLastnameMatcher']);
    $this->assertXCMLookup([
      'email'            => 'test36@email.test',
      'first_name'       => 'Aaron',
      'last_name'        => 'Aaronson',
      'phone'            => $phone,
      'location_type_id' => $test_location_type,
    ], $test_contact['id']);

    // now, let's see if the phone has the right location type
    $phone = $this->assertAPI3('Email', 'getsingle', [
      'contact_id' => $test_contact['id'],
      'email'      => 'test36@email.test',
    ]);

    $this->assertEquals($test_location_type, $phone['location_type_id'], 'Bug#36 still active!');
  }

  protected function getNonDefaultLocationType() {
    // get a non-default location type
    $default_location_type = $this->assertAPI3('LocationType', 'get', ['is_default' => 1]);
    $this->assertNotEmpty($default_location_type['id'], "Couldn't identify default location type.");
    $test_location_type = NULL;
    $location_types = $this->getLocationTypeIDs();
    foreach ($location_types as $location_type_id => $location_type_name) {
      if ($location_type_id != $default_location_type['id']) {
        $test_location_type = $location_type_id;
        break;
      }
    }
    $this->assertNotEmpty($test_location_type, "Couldn't find non-default location type");
    return $test_location_type;
  }

}
