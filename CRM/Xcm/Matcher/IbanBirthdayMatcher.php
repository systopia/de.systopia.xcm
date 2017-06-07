<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2017 SYSTOPIA                            |
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

/*
 * Matches on IBAN (CiviBanking or CiviSEPA)
 */
class CRM_Xcm_Matcher_IbanBirthdayMatcher extends CRM_Xcm_MatchingRule {

  /**
   * do the following:
   * 1) collect (SDD) mandate contacts (CiviSEPA)
   * 2) collect bank account contacts (CiviBanking)
   * 3) find contacts with the same birth date
   */
  public function matchContact($contact_data, $params = NULL) {
    $potential_contact_ids = array();
    if (empty($contact_data['birth_date'])) {
      return $this->createResultUnmatched();
    }
    if (empty($contact_data['iban'])) {
      return $this->createResultUnmatched();
    }

    // find SEPA mandates
    try {
      $mandates = civicrm_api3('SepaMandate', 'get', array(
        'iban'         => $contact_data['iban'],
        'return'       => 'contact_id',
        'option.limit' => 0));
      foreach ($mandates['values'] as $mandate) {
        $potential_contact_ids[] = $mandate['contact_id'];
      }
    } catch (Exception $e) {
      // probably means CiviSEPA is not istalled
    }

    // find bank accounts
    try {
      // find reference type
      // look up reference type option value ID(!)
      $reference_type_value = civicrm_api3('OptionValue', 'getsingle', array(
        'value'           => 'IBAN',
        'option_group_id' => 'civicrm_banking.reference_types'));

      // find references
      $account_references = civicrm_api3('BankingAccountReference', 'get', array(
        'reference'         => $contact_data['iban'],
        'reference_type_id' => $reference_type_value['id'],
        'return'            => 'ba_id',
        'option.limit'      => 0));
      if ($account_references['count']) {
        // load the accounts
        $account_ids = array();
        foreach ($account_references['values'] as $account_reference) {
          $account_ids[] = $account_reference['ba_id'];
        }
      }

      // find bank accounts
      if (!empty($account_ids)) {
        $accounts = civicrm_api3('BankingAccount', 'get', array(
          'id'           => array('IN' => $account_ids),
          'return'       => 'contact_id',
          'option.limit' => 0));
        foreach ($accounts['values'] as $account) {
          $potential_contact_ids[] = $account['contact_id'];
        }
      }
    } catch (Exception $e) {
      // probably means CiviBanking is not istalled
    }

    // now: find contacts
    if (!empty($potential_contact_ids)) {
      $contacts = civicrm_api3('Contact', 'get', array(
        'id'           => array('IN' => $potential_contact_ids),
        'is_deleted'   => 0,
        'option.limit' => 0,
        'birth_date'   => date('Y-m-d', strtotime($contact_data['birth_date'])),
        'return'       => 'id'));
      $contact_matches = array();
      foreach ($contacts['values'] as $contact) {
        $contact_matches[] = $contact['id'];
      }

      // process results
      switch (count($contact_matches)) {
        case 0:
          return $this->createResultUnmatched();

        case 1:
          return $this->createResultMatched(reset($contact_matches));

        default:
          $contact_id = $this->pickContact($contact_matches);
          return $this->createResultMatched($contact_id);
      }
    }

    return $this->createResultUnmatched();
  }
}
