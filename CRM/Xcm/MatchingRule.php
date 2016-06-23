<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2016 SYSTOPIA                            |
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
 * This as the base class of all XCM matching rules
 */
abstract class CRM_Xcm_MatchingRule {

  /**
   * This is the core matching function
   * 
   * @param $contact_data  an array of all information we have on the contact, e.g. first_name, street_address, etc...
   * @param $params        additional parameters
   * @return array result: mandatory entries:
   *                         contact_id   -  matched contact ID or NULL if not matched
   *                       recommended entries:
   *                         confidence   - float [0..1] defining the likelihood of the match
   *                       other entries:
   *                         ...fee free to return whatever you think might be interesting
   */ 
  abstract public function matchContact($contact_data, $params = NULL);


  /**
   * try to return/guess the contact_type.
   * Default/Fallback is 'Individual'
   */
  protected function getContactType(&$contact_data) {
    // contact_type set -> all is well
    if (!empty($contact_data['contact_type'])) {
      return $contact_data['contact_type'];
    }

    // if not, start guessing
    if (!empty($contact_data['organization_name'])) {
      return 'Organization';
    } elseif (!empty($contact_data['household_name'])) {
      return 'Household';
    } else {
      return 'Individual';
    }
  }

  /**
   * try to return/guess the contact_type.
   * Default/Fallback is 'Individual'
   */
  protected function isContactType($contact_type, &$contact_data) {
    $data_type = $this->getContactType($contact_data);
    return ($data_type == $contact_type);
  }

  /**
   * will pick one of the contact_id candidates
   * according to the policy
   *
   * @param $contact_ids  array of contact IDs
   * @return contact_id or NULL
   */
  protected function pickContact($contact_ids) {
    // TODO: setting
    return min($contact_ids);
  }

  /**
   * generate a valid reply with the given contact ID and confidence
   */
  protected function createResultMatched($contact_id, $confidence = 1.0) {
    if (empty($contact_id)) {
      return $this->createResultUnmatched();
    } else {
      return array(
        'contact_id' => $contact_id,
        'confidence' => $confidence
        );      
    }
  }

  /**
   * generate a valid negative reply
   */
  protected function createResultUnmatched($message = 'not matched') {
    return array(
      'message' => $message,
      );
  }
}
