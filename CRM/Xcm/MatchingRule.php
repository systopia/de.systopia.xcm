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
  abstract public function matchContact(&$contact_data, $params = NULL);

  /** stores the configuration instance */
  protected $config = NULL;

  /**
   * Get the configuration context this matcher runs under
   *
   * @return CRM_Xcm_Configuration
   * @throws Exception
   */
  public function getConfig() {
    if (!$this->config) {
      // not set? shouldn't happen. Anyway: use default!
      $this->config = CRM_Xcm_Configuration::getConfigProfile();
    }
    return $this->config;
  }

  /**
   * Set the configuration context this matcher runs under
   *
   * @param $config CRM_Xcm_Configuration configuration object
   * @throws Exception
   */
  public function setConfig($config) {
    $this->config = $config;
  }

  /**
   * try to return/guess the contact_type.
   * Default/Fallback is 'Individual'
   */
  public static function getContactType(&$contact_data) {
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
    $data_type = self::getContactType($contact_data);
    return ($data_type == $contact_type);
  }

  /**
   * will pick one (or none) of the contact_id candidates
   * according to the policy
   *
   * @param $contact_ids  array of contact IDs
   * @return contact_id or NULL
   * @throws Exception
   */
  protected function pickContact($contact_ids) {
    if (empty($contact_ids)) return NULL;

    $config  = $this->getConfig();
    $options = $config->getOptions();

    // create activity for duplicates if requested
    try {
      if (count($contact_ids) > 1 && !empty($options['duplicates_activity'])) {
        civicrm_api3('Activity', 'create', array(
            'activity_type_id'   => $options['duplicates_activity'],
            'subject'            => $options['duplicates_subject'],
            'status_id'          => $config->defaultActivityStatus(),
            'activity_date_time' => date("YmdHis"),
            'target_id'          => $contact_ids,
        ));
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message("de.systopia.xcm: Failed to create duplicates activity, check your settings! Error was " . $e->getMessage());
    }

    $picker  = CRM_Utils_Array::value('picker', $options, 'min');
    switch ($picker) {
      case 'none':
        return NULL;

      case 'max':
        return max($contact_ids);

      default:
      case 'min':
        return min($contact_ids);
    }
  }

  /**
   * generate a valid reply with the given contact ID and confidence
   */
  protected function createResultMatched($contact_id, $confidence = 1.0) {
    return CRM_Xcm_MatchingEngine::createResultMatched($contact_id, $confidence);
  }

  /**
   * generate a valid negative reply
   */
  protected function createResultUnmatched($message = 'not matched') {
    return CRM_Xcm_MatchingEngine::createResultUnmatched($message);
  }
}
