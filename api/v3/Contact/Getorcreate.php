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

/**
 * Get or create a contact for the given data
 *
 * @param $params any kind of contact related information: base data, address data, phone data, email, etc.
 * @return api3 reply with contact_id  or  ERROR
 */
function civicrm_api3_contact_getorcreate($params) {
  $engine = CRM_Xcm_MatchingEngine::getSingleton();
  $result = $engine->getOrCreateContact($params);

  if (empty($result['contact_id'])) {
    // this shouldn't happen
    return civicrm_api3_create_error("Unknown matching error.");
  } else {
    $reply = array('contact_id' => $result['contact_id']);
    return civicrm_api3_create_success(array($result['contact_id'] => $reply));
  }
}

/**
 * API3 action specs
 *
 * @todo implement properly
 */
function _civicrm_api3_contact_getorcreate_spec(&$params) {
  // $params['contact_type']['api.required'] = 1;
}

