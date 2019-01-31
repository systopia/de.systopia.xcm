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
 * Tests the fill details feature
 *
 * @see https://github.com/systopia/de.systopia.xcm/issues/32
 *
 * @group headless
 */
class CRM_Xcm_FillDetailTest extends CRM_Xcm_TestBase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * Test enhancement#36: submitted details should be made primary if already exists
   */
  public function testEnhancement37() {
    // set up our test scenario
    $test_contact = $this->createContactWithRandomEmail([
        'first_name' => 'Aaron',
        'last_name'  => 'Aaronson'
    ]);
    $phone_1 = str_replace('.', '', microtime(TRUE));
    $phone_2 = $phone_1 . '-1';
    $this->assertAPI3('Phone', 'create', [
        'contact_id' => $test_contact['id'],
        'is_primary' => 1,
        'phone'      => $phone_1]);
    $this->assertAPI3('Phone', 'create', [
        'contact_id' => $test_contact['id'],
        'is_primary' => 0,
        'phone'      => $phone_2]);


    // match and submit existing, non_primary phone
    $this->setXCMRules(['CRM_Xcm_Matcher_EmailMatcher']);
    $this->setXCMOption('fill_details', ['phone']);
    $this->setXCMOption('fill_details_primary', 1);
    $this->assertXCMLookup([
        'email'      => $test_contact['email'],
        'first_name' => 'Aaron',
        'last_name'  => 'Aaronson',
        'phone'      => $phone_2,
    ], $test_contact['id']);

    // now, let's see if the phone has the right location type
    $phone = $this->assertAPI3('Phone', 'getsingle', [
        'contact_id' => $test_contact['id'],
        'phone'      => $phone_2]);
    $this->assertEquals('1', $phone['is_primary'], "Submitted phone was not made primary.");
  }
}
