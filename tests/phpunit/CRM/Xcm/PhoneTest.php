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
 * Test phone-related matching and handling
 *
 * @group headless
 */
class CRM_Xcm_PhoneTest extends CRM_Xcm_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function testPhoneNumeric() {
    // set up our test scenario
    $this->setXCMOption('fill_details', ['phone']);
    $test_contact = $this->createContactWithRandomEmail([
      'first_name' => 'John',
      'last_name'  => 'Doe',
    ]);
    $this->setXCMOption(
      'default_location_type',
      $this->assertAPI3(
        'LocationType',
        'get',
        ['is_default' => 1]
      )
    );
    $this->assertAPI3('Phone', 'create', [
      'contact_id' => $test_contact['id'],
      'phone'      => '+43 680 1337199',
    ]);

    $this->setXCMRules(['CRM_Xcm_Matcher_PhoneLastnameMatcher']);
    // lookup using a slightly different format
    $this->assertXCMLookup([
      'first_name'       => 'John',
      'last_name'        => 'Doe',
      'phone'            => '+43 680 133 71 99',
    ], $test_contact['id']);

    // repeat using phone-only matcher
    $this->setXCMRules(['CRM_Xcm_Matcher_PhoneOnlyMatcher']);
    $this->assertXCMLookup([
      'phone'            => '+43 680 1 337 19 9',
    ], $test_contact['id']);
  }

}
