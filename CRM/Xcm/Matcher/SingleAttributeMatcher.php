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
 * This matcher will only match if only one attribute
 * of a set is given, otherwise it will not do anything
 */
class CRM_Xcm_Matcher_SingleAttributeMatcher extends CRM_Xcm_MatchingRule {

  /** the attribute that this matcher is using */
  protected $attribute = NULL;

  /** the entity this attribute belongs to */
  protected $entity = NULL;

  /** the set of fields to be monitored */
  protected $fields = NULL;


  function __construct($attribute, $entity, $fields = NULL) {
    $this->attribute = $attribute;
    $this->entity = $entity;
    if (is_array($fields)) {
      $this->fields = $fields;
    } else {
      // default fields to monitor
      $this->fields = array(
        'first_name',
        'last_name',
        'phone',
        'email',
        'street_address',
        'postal_code',
        'city',
        'birth_date');
    }
  }

  /**
   * simply
   * 1) check if attribute is set
   * 2) check if none of the other attributes are set
   * 3) find all contacts based on our attribute
   */
  public function matchContact(&$contact_data, $params = NULL) {
    // 1) check if attribute is set
    if (empty($contact_data[$this->attribute])) {
      return $this->createResultUnmatched();
    }

    // 2) check if none of the other attributes are set
    foreach ($this->fields as $field) {
      if ($field == $this->attribute) {
        continue;
      } elseif (!empty($contact_data[$field])) {
        return $this->createResultUnmatched();
      }
    }

    // find contact ids
    $entity_query = $this->restrictions;
    $entity_query[$this->attribute] = $contact_data[$this->attribute];
    $entity_query['return'] = 'contact_id';
    $entity_query['option.limit'] = 0;
    $entities_found = civicrm_api3($this->entity, 'get', $entity_query);
    $entity_contact_ids = array();
    foreach ($entities_found['values'] as $entity) {
      $entity_contact_ids[] = $entity['contact_id'];
    }

    // make sure not to query w/o contact ids
    if (empty($entity_contact_ids)) {
      return $this->createResultUnmatchedFixed($contact_data);
    }

    // now: find contacts
    $contact_search = array(
      'id'           => array('IN' => $entity_contact_ids),
      'is_deleted'   => 0,
      'option.limit' => 0,
      'return'       => 'id');
    $contacts = civicrm_api3('Contact', 'get', $contact_search);
    $contact_matches = array();
    foreach ($contacts['values'] as $contact) {
      $contact_matches[] = $contact['id'];
    }

    // process results
    switch (count($contact_matches)) {
      case 0:
        return $this->createResultUnmatchedFixed($contact_data);

      case 1:
        return $this->createResultMatched(reset($contact_matches));

      default:
        $contact_id = $this->pickContact($contact_matches);
        if ($contact_id) {
          return $this->createResultMatched($contact_id);
        } else {
          return $this->createResultUnmatchedFixed($contact_data);
        }
    }
  }

  /**
   * overrides createResultUnmatched() because in this case
   * there are no other vital attributes for the creation
   * of a contact present. We want to at least make sure
   * the display_name is set.
   */
  protected function createResultUnmatchedFixed(&$contact_data) {
    // set display_name so a contact _can_ be created
    if (empty($contact_data['display_name'])) {
      $contact_data['display_name'] = $contact_data[$this->attribute];
    }

    return $this->createResultUnmatched();
  }
}
