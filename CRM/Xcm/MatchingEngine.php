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
 * This will execute a matching process based on the configuration,
 * employing various matching rules
 */
class CRM_Xcm_MatchingEngine {

  /** singleton instance of the engine */
  protected static $_singleton = NULL;

  /**
   * get the singleton instance of the engine
   */
  public static function getSingleton() {
    if (self::$_singleton===NULL) {
      self::$_singleton = new CRM_Xcm_MatchingEngine();
    }
    return self::$_singleton;
  }

  /**
   * Try to find/match the contact with the given data.
   * If that fails, a new contact will be created with that data
   *
   * @throws exception  if anything goes wrong during matching/contact creation
   */
  public function getOrCreateContact(&$contact_data) {
    $result = $this->matchContact($contact_data);
    if (empty($result['contact_id'])) {
      // the matching failed
      $new_contact = $this->createContact($contact_data);
      $result['contact_id'] = $new_contact['id'];
      // TODO: add more data? how?

      // do the post-processing
      $this->postProcessNewContact($new_contact, $contact_data);
    
    } else {
      // the matching was successful
      $this->postProcessContactMatch($result, $contact_data);
    }
    
    return $result;
  }


  /**
   * @todo document
   */
  public function matchContact(&$contact_data) {
    $rules = $this->getMatchingRules();
    foreach ($rules as $rule) {
      $result = $rule->matchContact($contact_data);
      if (!empty($result['contact_id'])) {
        return $result;
      }
    }

    // if we get here, there was no match
    return array();
  }

  /**
   * @todo document
   */
  protected function createContact(&$contact_data) {
    // TODO: handle extra data?
    $contact_data['contact_type'] = CRM_Xcm_MatchingRule::getContactType($contact_data);
    $new_contact  = civicrm_api3('Contact', 'create', $contact_data);
    return $new_contact;
  }


  /**
   * @todo document
   */
  protected function getMatchingRules() {
    $rules = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'rules');
    $rule_instances = array();

    foreach ($rules as $rule_name) {
      if (empty($rule_name)) {
        continue;

      } elseif ('DEDUPE_' == substr($rule_name, 0, 7)) {
        // this is a dedupe rule
        $rule_instances[] = new CRM_Xcm_Matcher_DedupeRule(substr($rule_name, 7));

      } else {
        // this should be a class name
        // TODO: error handling
        $rule_instances[] = new $rule_name();
      }
    }

    return $rule_instances;
  }

  /**
   * @todo document
   */
  protected function postProcessNewContact(&$new_contact, &$contact_data) {
    $postprocessing = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'postprocessing');

    if (!empty($postprocessing['created_add_group'])) {
      $this->addContactToGroup($new_contact['id'], $postprocessing['created_add_group']);
    }

    if (!empty($postprocessing['created_add_tag'])) {
      $this->addContactToTag($new_contact['id'], $postprocessing['created_add_tag']);
    }

    if (!empty($postprocessing['created_add_activity'])) {
      $this->addActivityToContact($new_contact['id'], $postprocessing['created_add_activity'], $contact_data);
    }
  }

  /**
   * @todo document
   */
  protected function postProcessContactMatch(&$result, &$contact_data) {
    $postprocessing = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'postprocessing');
    $options        = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'xcm_options');

    if (!empty($postprocessing['matched_add_group'])) {
      $this->addContactToGroup($result['contact_id'], $postprocessing['matched_add_group']);
    }

    if (!empty($postprocessing['matched_add_tag'])) {
      $this->addContactToTag($result['contact_id'], $postprocessing['matched_add_tag']);
    }

    if (!empty($postprocessing['matched_add_activity'])) {
      $this->addActivityToContact($result['contact_id'], $postprocessing['matched_add_activity'], $contact_data);
    }

    if (!empty($options['diff_activity'])) {
      $this->createDiffActivity($result['contact_id'], $options['diff_activity'], $contact_data);
    }
  }


  protected function addContactToGroup($contact_id, $group_id) {
    // TODO: error handling
    civicrm_api3('GroupContact', 'create', array('contact_id' => $contact_id, 'group_id' => $group_id));
  }

  protected function addContactToTag($contact_id, $tag_id) {
    // TODO: error handling
    civicrm_api3('EntityTag', 'create', array('entity_id' => $contact_id, 'tag_id' => $tag_id, 'entity_table' => 'civicrm_contact'));
  }

  protected function addActivityToContact($contact_id, $activity_type_id, &$contact_data) {
    // TODO: implement    
  }

  protected function createDiffActivity($contact_id, $activity_type_id, &$contact_data) {
    // TODO: implement
  }
}
