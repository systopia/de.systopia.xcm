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
      $this->addActivityToContact($result['contact_id'],
                                  $postprocessing['created_add_activity'],
                                  $postprocessing['created_add_activity_subject'],
                                  $postprocessing['created_add_activity_template'],
                                  $contact_data);
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
      $this->addActivityToContact($result['contact_id'],
                                  $postprocessing['matched_add_activity'],
                                  $postprocessing['matched_add_activity_subject'],
                                  $postprocessing['matched_add_activity_template'],
                                  $contact_data);
    }

    if (!empty($options['diff_activity'])) {
      $this->createDiffActivity($result['contact_id'], $options, $options['diff_activity_subject'], $contact_data);
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

  protected function addActivityToContact($contact_id, $activity_type_id, $subject, $template_id, &$contact_data) {
    $activity_data = array(
        'activity_type_id'   => $activity_type_id,
        'subject'            => $subject,
        'status_id'          => 1, // pending
        'activity_date_time' => date("YmdHis"),
        'target_contact_id'  => (int) $contact_id,
        'source_contact_id'  => (int) $contact_id,
        'campaign_id'        => CRM_Utils_Array::value('campaign_id', $contact_data),
    );

    if ($template_id) {
      $template = civicrm_api3('MessageTemplate', 'getsingle', array('id' => $template_id));
      $activity_data['details'] = $this->renderTemplate('string:' . $template['msg_text'], $contact_data);
    }

    $activity = CRM_Activity_BAO_Activity::create($activity_data);
  }



  protected function createDiffActivity($contact_id, $options, $subject, &$contact_data) {
    // remove all non-whitelisted custom fields
    $key_set = array_keys($contact_data);
    foreach ($key_set as $key) {
      if (preg_match('/^custom_\d+$/', $key)) {
        // this is a custom field...
        $custom_field_id = substr($key, 8);
        if ($options['custom_fields'] == NULL || !in_array($custom_field_id, $options['custom_fields'])) {
          unset($contact_data[$key]);
        }
      }
    }

    // load the contact
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));

    // look up some fields (e.g. prefix, ...)
    // TODO

    // create diff
    $differing_attributes = array();
    $all_attributes = array_keys($contact) + array_keys($contact_data);
    foreach ($all_attributes as $attribute) {
      if (isset($contact[$attribute]) && isset($contact_data[$attribute])) {
        if ($contact[$attribute] != $contact_data[$attribute]) {
          $differing_attributes[] = $attribute;
        }        
      }
    }

    // if there is one address attribute, add all (so the user can later compile a full address)
    $address_parameters = array('street_address', 'country', 'postal_code', 'city', 'supplemental_address_1', 'supplemental_address_2');
    if (array_intersect($address_parameters, $differing_attributes)) {
      foreach ($address_parameters as $attribute) {
        if (!in_array($attribute, $differing_attributes) && isset($contact[$attribute]) && isset($contact_data[$attribute])) {
          $differing_attributes[] = $attribute;
        }
      }
    }

    // special case for phones
    
    // filter attributes
    // TODO:

    if (!empty($differing_attributes)) {
      // create activity
      $data = array(
        'differing_attributes' => $differing_attributes,
        'existing_contact'     => $contact,
        'submitted_data'       => $contact_data
        );

      $activity_data = array(
          'activity_type_id'   => $options['activity_type_id'],
          'subject'            => $subject,
          'status_id'          => 1, // pending
          'activity_date_time' => date("YmdHis"),
          'target_contact_id'  => (int) $contact_id,
          'source_contact_id'  => (int) $contact_id,
          'campaign_id'        => CRM_Utils_Array::value('campaign_id', $contact_data),
          'details'            => $this->renderTemplate('activity/diff.tpl', $data),
      );
      $activity = CRM_Activity_BAO_Activity::create($activity_data);      
    }
  }




  /**
   * uses SMARTY to render a template
   *
   * @return string 
   */
  protected function renderTemplate($template_path, $vars) {
    $smarty = CRM_Core_Smarty::singleton();

    // first backup original variables, since smarty instance is a singleton
    $oldVars = $smarty->get_template_vars();
    $backupFrame = array();
    foreach ($vars as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $backupFrame[$key] = isset($oldVars[$key]) ? $oldVars[$key] : NULL;
    }

    // then assign new variables
    foreach ($vars as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $smarty->assign($key, $value);
    }

    // create result
    $result =  $smarty->fetch($template_path);

    // reset smarty variables
    foreach ($backupFrame as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $smarty->assign($key, $value);
    }

    return $result;
  }
}
