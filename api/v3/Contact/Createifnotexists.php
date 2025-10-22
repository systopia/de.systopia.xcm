<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2016 SYSTOPIA                            |
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

use CRM_Xcm_ExtensionUtil as E;

/**
 * API3 action specs
 */
function _civicrm_api3_contact_Createifnotexists_spec(&$spec) {
  $spec['contact_type'] = [
    'name'         => 'contact_type',
    'api.default'  => 'Individual',
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Contact Type',
  ];
  $spec['xcm_profile'] = [
    'name'         => 'xcm_profile',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Which profile should be used for matching?',
  ];
  $spec['match_only'] = [
    'type'        => CRM_Utils_Type::T_BOOLEAN,
    'title'       => 'Match only',
    'api.default' => 0,
    'description' => 'Either return the matched contact, or nothing; do not create a contact.',
  ];
}

/**
 * Get or create a contact for the given data
 *
 * This offers an alternative contract to getOrCreateContact in that this
 * supports the optional match_only parameter to suppress contact creation,
 * and returns whether a contact was created as well as the contact_id.
 *
 * @param $params any kind of contact related information: base data, address data, phone data, email, etc.
 *        Nb. One of first_name, last_name, email, display_name is mandatory.
 *
 * @return array api3 reply with keys:
 *   - contact_id  int|null
 *   - was_created TRUE|FALSE whether the contact had to be created
 *
 * @throws Exception
 */
function civicrm_api3_contact_Createifnotexists($params) {
  $profile = $params['xcm_profile'] ?? NULL;
  $engine = CRM_Xcm_MatchingEngine::getEngine($profile);
  $result = $engine->createIfNotExists($params);

  $reply = [
    'contact_id' => $result['contact_id'],
    'was_created' => $result['was_created'],
  ];
  $null = NULL;
  return civicrm_api3_create_success(
    [$result['contact_id'] => $reply],
    $params,
    'Contact',
    'createifnotexists',
    $null,
    $reply
  );
}
