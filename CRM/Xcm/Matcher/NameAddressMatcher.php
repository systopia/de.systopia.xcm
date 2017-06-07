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
class CRM_Xcm_Matcher_NameAddressMatcher extends CRM_Xcm_MatchingRule {

  protected $required_fields = array('first_name', 'last_name',
                                     'street_address', 'postal_code', 'city');
  /**
   * do the following:
   * 1) find all addresses matching city, postal_code, street_address
   * 2) find contacts with matching first and last names
   */
  public function matchContact($contact_data, $params = NULL) {
    foreach ($this->required_fields as $field_name) {
      if (empty($contact_data[$field_name])) {
        return $this->createResultUnmatched();
      }
    }

    // find addresses
    $address_query = array(
      'return'         => 'contact_id',
      'option.limit'   => 0,
      'street_address' => $contact_data['street_address'],
      'postal_code'    => $contact_data['postal_code'],
      'city'           => $contact_data['city'],
      );
    $addresses = civicrm_api3('Address', 'get', $address_query);
    $potential_contact_ids = array();
    foreach ($addresses['values'] as $address) {
      $potential_contact_ids[] = $address['contact_id'];
    }

    // now: find contacts
    if (!empty($potential_contact_ids)) {
      $contacts = civicrm_api3('Contact', 'get', array(
        'id'           => array('IN' => $potential_contact_ids),
        'is_deleted'   => 0,
        'option.limit' => 0,
        'first_name'   => $contact_data['first_name'],
        'last_name'    => $contact_data['last_name'],
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
