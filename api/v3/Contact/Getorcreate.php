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
 *        Nb. One of first_name, last_name, email, display_name is mandatory.
 * @return array api3 reply with contact_id  or  ERROR
 * @throws Exception
 */
function civicrm_api3_contact_getorcreate($params) {
  $profile = CRM_Utils_Array::value('xcm_profile', $params, NULL);
  $engine = CRM_Xcm_MatchingEngine::getEngine($profile);
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
 */
function _civicrm_api3_contact_getorcreate_spec(&$params) {
  $params['contact_type'] = array(
      'name'         => 'contact_type',
      'api.default'  => 'Individual',
      'type'         => CRM_Utils_Type::T_STRING,
      'title'        => 'Contact Type',
  );
  $params['xcm_profile'] = array(
      'name'         => 'xcm_profile',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_STRING,
      'title'        => 'Which profile should be used for matching?',
  );
}

