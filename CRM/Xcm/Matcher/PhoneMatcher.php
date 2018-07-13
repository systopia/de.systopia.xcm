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
 * This will execute a matching process based on the configuration,
 * employing various matching rules
 */
class CRM_Xcm_Matcher_PhoneMatcher extends CRM_Xcm_MatchingRule {

  /** fields to match in addition to the phone number */
  protected $other_contact_fields = NULL;

  // restrictions for search, e.g. array('is_billing' => '0')
  protected $restrictions = array();

  function __construct($other_contact_fields = NULL) {
    if (is_array($other_contact_fields)) {
      $this->other_contact_fields = $other_contact_fields;
    } else {
      $this->other_contact_fields = array('contact_type');
    }
  }

  /**
   * simply
   * 1) find phones
   * 2) load the attached contacts
   * 3) check the other_contact_fields
   */
  public function matchContact(&$contact_data, $params = NULL) {
    if (empty($contact_data['phone'])) {
      return $this->createResultUnmatched();
    }

    // make sure the other fields are there
    foreach ($this->other_contact_fields as $field_name) {
      if (!isset($contact_data[$field_name])) {
        return $this->createResultUnmatched();
      }
    }

    // use com.cividesk.normalize to normalize phone numbers prior to matching
    // this is necessary if prefixing with +[country_code] is enabled, otherwise
    // no incoming phone numbers without the prefix will match
    if (method_exists('CRM_Utils_Normalize', 'normalize_phone')) {
      // all we want is the normalized phone number, so make a copy first
      $normalized_phone = $contact_data;
      $normalized_phone['phone_type_id'] = 1; // use a dummy value
      $normalizer = new CRM_Utils_Normalize();
      $normalizer->normalize_phone($normalized_phone);
      // strip non-numeric characters
      $phone_numeric = preg_replace('#[^0-9]#', '', $normalized_phone['phone']);
    } else {
      // strip non-numeric characters
      $phone_numeric = preg_replace('#[^0-9]#', '', $contact_data['phone']);
    }

    // find phones
    $phone_query = $this->restrictions;
    $phone_query['phone_numeric'] = $phone_numeric;
    $phone_query['return'] = 'contact_id';
    $phone_query['option.limit'] = 0;
    $phones_found = civicrm_api3('Phone', 'get', $phone_query);
    $phone_contact_ids = array();
    foreach ($phones_found['values'] as $phone) {
      $phone_contact_ids[] = $phone['contact_id'];
    }

    // make sure not to query w/o contact ids
    if (empty($phone_contact_ids)) {
      return $this->createResultUnmatched();
    }

    // now: find contacts
    $contact_search = array(
      'id'           => array('IN' => $phone_contact_ids),
      'is_deleted'   => 0,
      'option.limit' => 0,
      'return'       => 'id');
    foreach ($this->other_contact_fields as $field_name) {
      $contact_search[$field_name] = $contact_data[$field_name];
    }
    $contacts = civicrm_api3('Contact', 'get', $contact_search);
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
          if ($contact_id) {
            return $this->createResultMatched($contact_id);
          } else {
            return $this->createResultUnmatched();
          }
    }
  }
}
