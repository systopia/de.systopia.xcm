<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2017-2019 SYSTOPIA                       |
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

/**
 *
 * Matches on IBAN (CiviBanking or CiviSEPA)
 *
 */
// phpcs:disable Generic.NamingConventions.AbstractClassNamePrefix.Missing
abstract class CRM_Xcm_Matcher_IbanMatcher extends CRM_Xcm_MatchingRule {
// phpcs:enable

  /**
   * @var array attributes that are checked and therefore have to be present
   */
  protected $attributes = [];

  /**
   * Initialises an CRM_Xcm_Matcher_IbanMatcher constructor.
   *
   * @param $attributes array list of attributes
   */
  protected function __construct($attributes) {
    $this->attributes = $attributes;
  }

  /**
   * Add more parameters to the final contact query. The filters for
   *  the iban are already in there
   *
   * @param $contact_query array the current query
   * @param $contact_data  array the submitted contact data
   */
  abstract protected function refineContactQuery(&$contact_query, $contact_data);

  /**
   * do the following:
   * 1) collect (SDD) mandate contacts (CiviSEPA)
   * 2) collect bank account contacts (CiviBanking)
   * 3) find contacts with the same birth date
   */
  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
  public function matchContact(&$contact_data, $params = NULL) {
  // phpcs:enable
    $potential_contact_ids = [];
    foreach ($this->attributes as $attribute) {
      if (empty($contact_data[$attribute])) {
        return $this->createResultUnmatched();
      }
    }
    if (empty($contact_data['iban'])) {
      return $this->createResultUnmatched();
    }

    // clean up IBAN
    $contact_data['iban'] = strtoupper(preg_replace('/\s+/', '', $contact_data['iban']));

    // find SEPA mandates
    try {
      $mandates = civicrm_api3('SepaMandate', 'get', [
        'iban'         => $contact_data['iban'],
        'return'       => 'contact_id',
        'option.limit' => 0,
      ]);
      foreach ($mandates['values'] as $mandate) {
        $potential_contact_ids[] = $mandate['contact_id'];
      }
    }
    catch (Exception $e) {
      // probably means CiviSEPA is not installed
    }

    // find bank accounts
    try {
      // find reference type
      // look up reference type option value ID(!)
      $reference_type_value = civicrm_api3('OptionValue', 'getsingle', [
        'value'           => 'IBAN',
        'option_group_id' => 'civicrm_banking.reference_types',
      ]);

      // find references
      $account_references = civicrm_api3('BankingAccountReference', 'get', [
        'reference'         => $contact_data['iban'],
        'reference_type_id' => $reference_type_value['id'],
        'return'            => 'ba_id',
        'option.limit'      => 0,
      ]);
      if ($account_references['count']) {
        // load the accounts
        $account_ids = [];
        foreach ($account_references['values'] as $account_reference) {
          $account_ids[] = $account_reference['ba_id'];
        }
      }

      // find bank accounts
      if (!empty($account_ids)) {
        $accounts = civicrm_api3('BankingAccount', 'get', [
          'id'           => ['IN' => $account_ids],
          'return'       => 'contact_id',
          'option.limit' => 0,
        ]);
        foreach ($accounts['values'] as $account) {
          $potential_contact_ids[] = $account['contact_id'];
        }
      }
    }
    catch (Exception $e) {
      // probably means CiviBanking is not installed
    }

    // now: find contacts
    if (!empty($potential_contact_ids)) {
      $contact_query = [
        'id'           => ['IN' => $potential_contact_ids],
        'is_deleted'   => 0,
        'option.limit' => 0,
        'return'       => 'id',
      ];
      $this->refineContactQuery($contact_query, $contact_data);
      $contacts = civicrm_api3('Contact', 'get', $contact_query);
      $contact_matches = [];
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
          if ($contact_id) {
            return $this->createResultMatched($contact_id);
          }
          else {
            return $this->createResultUnmatched();
          }
      }
    }

    return $this->createResultUnmatched();
  }

}
