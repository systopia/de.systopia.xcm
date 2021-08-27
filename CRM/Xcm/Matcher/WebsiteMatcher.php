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

/**
 * This will execute a matching process based on the configuration,
 * employing various matching rules
 */
class CRM_Xcm_Matcher_WebsiteMatcher extends CRM_Xcm_MatchingRule {

  /**
   * Fields to match in addition to the website
   * @var array
   */
  protected $other_contact_fields = NULL;

  /**
   * Restrictions for search, e.g. array('is_billing' => '0')
   * @var array
   */
  protected $restrictions = [];

  public function __construct($other_contact_fields = NULL) {
    if (is_array($other_contact_fields)) {
      $this->other_contact_fields = $other_contact_fields;
    }
    else {
      $this->other_contact_fields = ['contact_type' => 'contact_type'];
    }
  }

  /**
   * simply
   * 1) find websites
   * 2) load the attached contacts
   * 3) check the other_contact_fields
   */
  public function matchContact(&$contact_data, $params = NULL) {
    if (empty($contact_data['website'])) {
      return $this->createResultUnmatched();
    }

    // make sure the other fields are there
    foreach ($this->other_contact_fields as $submitted_field_name) {
      if (!isset($contact_data[$submitted_field_name])) {
        return $this->createResultUnmatched();
      }
    }

    // find websites
    $website_query = $this->restrictions;
    $website_query['url'] = $contact_data['website'];
    $website_query['return'] = 'contact_id';
    $website_query['option.limit'] = 0;
    $websites_found = civicrm_api3('Website', 'get', $website_query);
    $website_contact_ids = [];
    foreach ($websites_found['values'] as $website) {
      $website_contact_ids[] = $website['contact_id'];
    }

    // make sure not to query w/o contact ids
    if (empty($website_contact_ids)) {
      return $this->createResultUnmatched();
    }

    // now: find contacts
    $contact_search = array(
      'id'           => array('IN' => $website_contact_ids),
      'is_deleted'   => 0,
      'option.limit' => 0,
      'return'       => 'id',
    );
    foreach ($this->other_contact_fields as $contact_field_name => $submitted_field_name) {
      $contact_search[$contact_field_name] = $contact_data[$submitted_field_name];
    }
    $contacts = civicrm_api3('Contact', 'get', $contact_search);
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

}
