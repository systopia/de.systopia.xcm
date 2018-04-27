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
    // first: resolve custom fields to custom_xx notation
    CRM_Xcm_Configuration::resolveCustomFields($contact_data);

    // also: do some sanitation and formatting
    CRM_Xcm_DataNormaliser::normaliseFieldnames($contact_data);
    CRM_Xcm_DataNormaliser::normaliseData($contact_data);
    CRM_Xcm_DataNormaliser::resolveData($contact_data);

    // set defaults
    if (empty($contact_data['contact_type'])) {
      $contact_data['contact_type'] = CRM_Xcm_MatchingRule::getContactType($contact_data);
    }

    // then: match
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

    // create contact
    $contact_data['contact_type'] = CRM_Xcm_MatchingRule::getContactType($contact_data);
    $new_contact  = civicrm_api3('Contact', 'create', CRM_Xcm_Configuration::stripAddressData($contact_data));

    // create address separately (see https://github.com/systopia/de.systopia.xcm/issues/6)
    $address_data = CRM_Xcm_Configuration::extractAddressData($contact_data);
    if (!empty($address_data)) {
      $address_data['contact_id'] = $new_contact['id'];
      if (empty($address_data['location_type_id'])) {
        $address_data['location_type_id'] = CRM_Xcm_Configuration::defaultLocationType();
      }
      civicrm_api3('Address', 'create', $address_data);
    }

    // create phone number (that used to work...)
    $this->addDetailToContact($new_contact['id'], 'phone',   $contact_data);
    $this->addDetailToContact($new_contact['id'], 'website', $contact_data);

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
      $this->addActivityToContact($new_contact['id'],
                                  $postprocessing['created_add_activity'],
                                  $postprocessing['created_add_activity_subject'],
                                  $postprocessing['created_add_activity_template'],
                                  $contact_data);
    }
  }

  /**
   * Perform all the post processing the configuration imposes
   */
  protected function postProcessContactMatch(&$result, &$submitted_contact_data) {
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
                                  $submitted_contact_data);
    }

    // FILL/DIFF ACTIONS (require the current contact data):
    $diff_handler = CRM_Xcm_Configuration::diffHandler();
    if (   ($diff_handler != 'none')
        || !empty($options['fill_fields'])
        || !empty($options['fill_address'])
        || !empty($options['fill_details'])) {

      // sort out location type
      if (empty($submitted_contact_data['location_type_id'])) {
        $location_type_id = CRM_Xcm_Configuration::defaultLocationType();
      } else {
        $location_type_id = $submitted_contact_data['location_type_id'];
      }

      // load contact
      $current_contact_data = $this->loadCurrentContactData($result['contact_id'], $submitted_contact_data);
      CRM_Xcm_DataNormaliser::normaliseData($current_contact_data);

      // FILL CURRENT CONTACT DATA
      if (!empty($options['fill_fields'])) {
        //  caution: will set the overwritten fields in $current_contact_data
        $this->fillContactData($current_contact_data, $submitted_contact_data, $options['fill_fields'], $options['fill_fields_multivalue']);
      }

      // FILL CURRENT CONTACT DETAILS
      if (!empty($options['fill_details']) && is_array($options['fill_details'])) {
        foreach ($options['fill_details'] as $entity) {
          $this->addDetailToContact($result['contact_id'], $entity, $submitted_contact_data, !empty($options['fill_details_primary']), $current_contact_data);
        }
      }

      // FILL CURRENT CONTACT ADDRESS
      if (!empty($options['fill_address'])) {
        $address_data = CRM_Xcm_Configuration::extractAddressData($submitted_contact_data);
        if (!empty($address_data)) {
          $address_data['location_type_id'] = $location_type_id;

          // see if contact alread has an address
          $address_query = array(
            'contact_id'   => $result['contact_id'],
            'option.sort'  => 'is_primary desc',
            'option.limit' => 1);
          if ($options['fill_address'] == 2) {
            // 2 = only if no address of the same _type_ exists
            $address_query['location_type_id'] = $address_data['location_type_id'];
          }
          $addresses = civicrm_api3('Address', 'get', $address_query);
          if (empty($addresses['count'])) {
            // there is NO address -> create one!
            $address_data['contact_id'] = $result['contact_id'];
            civicrm_api3('Address', 'create', $address_data);

            // also add to 'submitted data' to avoid diff activity
            foreach ($address_data as $key => $value) {
              $current_contact_data[$key] = $value;
            }

          } else {
            // address found -> add to current_contact_data for diff activity
            $existing_address = reset($addresses['values']);
            $existing_address_data = CRM_Xcm_Configuration::extractAddressData($existing_address, FALSE);
            foreach ($existing_address_data as $key => $value) {
              $current_contact_data[$key] = $value;
            }
          }
        }
      }

      // HANDLE DIFFERENCES
      switch ($diff_handler) {
        case 'diff':
          $this->createDiffActivity($current_contact_data, $options, $options['diff_activity_subject'], $submitted_contact_data, $location_type_id);
          break;

        case 'i3val':
          $this->createI3ValActivity($current_contact_data, $submitted_contact_data);

        default:
          break;
      }
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
        'status_id'          => CRM_Xcm_Configuration::defaultActivityStatus(),
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

  /**
   * Add a certain entity detail (phone,email,website)
   */
  protected function addDetailToContact($contact_id, $entity, $data, $as_primary = FALSE, &$data_update = NULL) {
    if (!empty($data[$entity])) {
      // sort out location type
      if (empty($data['location_type_id'])) {
        $location_type_id = CRM_Xcm_Configuration::defaultLocationType();
      } else {
        $location_type_id = $data['location_type_id'];
      }

      // get attribute
      $attribute = strtolower($entity); // for email and phone that works
      $sorting = 'is_primary desc';
      if (strtolower($entity) == 'website') {
        $attribute = 'url';
        $sorting = 'id desc';
      }



      // some value was submitted -> check if there is already an existing one
      $existing_entity = civicrm_api3($entity, 'get', array(
        $attribute     => $data[$entity],
        'contact_id'   => $contact_id,
        'option.sort'  => $sorting,
        'option.limit' => 1));
      if (empty($existing_entity['count'])) {
        // there is none -> create
        $create_detail_call = array(
          $attribute         => $data[$entity],
          'contact_id'       => $contact_id,
          'location_type_id' => $location_type_id);

        // mark as primary if requested
        if ($as_primary) {
          $create_detail_call['is_primary'] = 1;
        }

        // create the deail
        civicrm_api3($entity, 'create', $create_detail_call);

        // mark in update_data
        if ($data_update && is_array($data_update)) {
          $data_update[$attribute] = $data[$entity];
        }
      }
    }
  }

  /**
   * Load the matched contact with all data, including the
   * custom fields in the submitted data
   */
  protected function loadCurrentContactData($contact_id, $submitted_data) {
    // load the contact
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
    // load the custom fields
    $custom_value_query = array();
    foreach ($submitted_data as $key => $value) {
      if (!isset($contact[$key])) { // i.e. not loaded yet
        if (preg_match('/^custom_\d+$/', $key)) {
          // this is a custom field...
          $custom_field_id = substr($key, 7);
          $custom_value_query["return.custom_{$custom_field_id}"] = 1;
        }
      }
    }
    if (!empty($custom_value_query)) {
      // i.e. there are fields that need to be looked up separately
      $custom_value_query['entity_table'] = 'civicrm_contact';
      $custom_value_query['entity_id']    = $contact_id;
      $custom_value_query_result = civicrm_api3('CustomValue', 'get', $custom_value_query);
      foreach ($custom_value_query_result['values'] as $entry) {
        if (empty($entry['id'])) continue;
        $contact["custom_{$entry['id']}"] = $entry['latest'];
      }
    }

    return $contact;
  }

  /**
   * Will fill (e.g. set if not set yet) the given fields in the database
   *  and update the $current_contact_data accordingly
   */
  protected function fillContactData(&$current_contact_data, $submitted_contact_data, $fields, $fill_multivalue) {
    $update_query = array();
    foreach ($fields as $key) {
      if (isset($submitted_contact_data[$key])) {
        // Fill field if empty.
        if (!isset($current_contact_data[$key]) || $current_contact_data[$key]==='') {
          $update_query[$key] = $submitted_contact_data[$key];
          $current_contact_data[$key] = $submitted_contact_data[$key];
        }
        // Fill multi-value field values.
        elseif (!empty($fill_multivalue) && $this->fieldIsMultivalue($key)) {
          // Ensure current and submitted field data being an array.
          foreach (array(
                     &$current_contact_data[$key],
                     &$submitted_contact_data[$key],
                   ) as &$value) {
            if (!is_array($value)) {
              if ($value === '' || $value === NULL) {
                $value = array();
              }
              else {
                $value = array($value);
              }
            }
          }

          // Retrieve field options for correct ordering.
          static $field_options = array();
          if (empty($field_options[$key])) {
            $result = civicrm_api3('Contact', 'getfield', array(
              'name' => $key,
              'action' => 'getsingle',
              'get_options' => 'get',
            ));
            $field_options[$key] = $result['values']['options'];
          }
          $current_field_options = $field_options[$key];

          // Merge current and submitted field values.
          $current_contact_data[$key] = array_merge(
            $submitted_contact_data[$key],
            $current_contact_data[$key]
          );

          // Replace field item labels with their corresponding field values.
          $current_contact_data[$key] = array_map(function($v) use ($current_field_options) {
            if (array_key_exists($v, $current_field_options)) {
              return $v;
            }
            elseif (in_array($v, $current_field_options)) {
              return array_search($v, $current_field_options);
            }
            else {
              return NULL;
            }
          }, $current_contact_data[$key]);

          // Remove duplicate and disallowed field values.
          $current_contact_data[$key] = array_intersect(
            array_unique($current_contact_data[$key]),
            array_keys($current_field_options)
          );

          $update_query[$key] = $current_contact_data[$key];
        }
      }
    }
    if (!empty($update_query)) {
      $update_query['id'] = $current_contact_data['id'];
      civicrm_api3('Contact', 'create', $update_query);
    }
  }

  /**
   * @param $key
   *   The field name to check for being multi-value.
   *
   * @return bool
   *   Whether the given field accepts multiple values.
   *
   * @throws \CiviCRM_API3_Exception
   *   When an error occurred retrieving a custom field.
   */
  protected function fieldIsMultivalue($key) {
    // Check for multi-value core field.
    if (in_array($key, array(
      'preferred_communication_method',
      // TODO: Add mulit-value core fields here.
    ))) {
      $is_multivalue = TRUE;
    }

    // Check for multi-value custom field.
    if (strpos($key, 'custom_') === 0) {
      $custom_field_id = explode('custom_', $key)[1];
      // Check whether the field is multi-value, statically cache results.
      static $custom_field_definitions = array();
      if (empty($custom_field_definitions)) {
        $custom_field_definitions = civicrm_api3(
          'CustomField',
          'get',
          array(
            'html_type' => array('IN' => array(
              'CheckBox',
              'Multi-Select',
              'Multi-Select State/Province',
              'Multi-Select Country',
              'AdvMulti-Select',
            )),
            'return' => array('name', 'html_type', 'option_group_id')
          )
        );
        if (!empty($custom_field_definitions['values'])) {
          $custom_field_definitions = $custom_field_definitions['values'];
        }
      }
      if (array_key_exists($custom_field_id, $custom_field_definitions)) {
        $is_multivalue = TRUE;
      }
    }

    return !empty($is_multivalue);
  }

  /**
   * create I3Val diff activity
   */
  protected function createI3ValActivity($current_contact_data, $submitted_contact_data) {
    $options = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'xcm_options');

    // compile udpate request
    $submitted_contact_data['id'] = $current_contact_data['id'];
    $submitted_contact_data['activity_subject'] = $options['diff_activity_subject'];

    try {
      $result = civicrm_api3('Contact', 'request_update', $submitted_contact_data);
    } catch (Exception $e) {
      // some problem with the creation
      error_log("de.systopia.xcm: error when trying to create i3val update request: " . $e->getMessage());
    }
  }

  /**
   * Create an activity listing all differences between the matched contact
   * and the data submitted
   */
  protected function createDiffActivity($contact, $options, $subject, &$contact_data, $location_type_id) {
    $options = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'xcm_options');
    $case_insensitive = CRM_Utils_Array::value('case_insensitive', $options);

    // look up some id fields
    CRM_Xcm_DataNormaliser::labelData($contact);
    CRM_Xcm_DataNormaliser::labelData($contact_data);

    // create diff
    $differing_attributes = array();
    $all_attributes = array_keys($contact) + array_keys($contact_data);
    foreach ($all_attributes as $attribute) {
      if (isset($contact[$attribute]) && isset($contact_data[$attribute])) {
        if ($this->attributesDiffer($attribute, $contact[$attribute], $contact_data[$attribute], $case_insensitive)) {
          $differing_attributes[] = $attribute;
        }
      }
    }

    // if there is one address attribute, add all (so the user can later compile a full address)
    $address_parameters = array('street_address', 'country_id', 'postal_code', 'city', 'supplemental_address_1', 'supplemental_address_2');
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
      // There ARE changes: render the diff activity

      // add the location type for clarity
      $location_types = array();
      $location_type_name = civicrm_api3('LocationType', 'getvalue', array(
          'return' => 'display_name',
          'id'     => $location_type_id));
      $location_fields = CRM_Xcm_Configuration::getAddressFields() + array('phone', 'email');
      foreach ($location_fields as $fieldname) {
        $location_types[$fieldname] = $location_type_name;
      }

      // create activity
      $data = array(
        'differing_attributes' => $differing_attributes,
        'fieldlabels'          => CRM_Xcm_Configuration::getFieldLabels($differing_attributes),
        'existing_contact'     => $contact,
        'location_types'       => $location_types,
        'submitted_data'       => $contact_data
        );

      $activity_data = array(
          'activity_type_id'   => $options['diff_activity'],
          'subject'            => $subject,
          'status_id'          => CRM_Xcm_Configuration::defaultActivityStatus(),
          'activity_date_time' => date("YmdHis"),
          'target_contact_id'  => (int) $contact['id'],
          'source_contact_id'  => CRM_Xcm_Configuration::getCurrentUserID($contact['id']),
          'campaign_id'        => CRM_Utils_Array::value('campaign_id', $contact_data),
          'details'            => $this->renderTemplate('activity/diff.tpl', $data),
      );

      try {
        $activity = CRM_Activity_BAO_Activity::create($activity_data);
      } catch (Exception $e) {
        // some problem with the creation
        error_log("de.systopia.xcm: error when trying to create diff activity: " . $e->getMessage());
      }
    }
  }

  /**
   * compare two values of the given attribute name
   *
   * @return TRUE if atttributes differ
   */
  protected function attributesDiffer($attribute_name, $original_value, $submitted_value, $case_insensitive) {
    // TODO: collapse double spaces?

    // trim values first
    $original_value  = trim($original_value);
    $submitted_value = trim($submitted_value);

    // compare
    if ($case_insensitive) {
      return strtolower($original_value) != strtolower($submitted_value);
    } else {
      return $original_value != $submitted_value;
    }
  }


  /**
   * sanitise/format input
   */
  protected function sanitiseData(&$contact_data) {
    // strip whitespaces
    foreach ($contact_data as $key => $value) {
      if (is_string($value)) {
        $contact_data[$key] = trim($value);
      }
    }

    // format birth_date
    if (!empty($contact_data['birth_date'])) {
      $contact_data['birth_date'] = date('Y-m-d', strtotime($contact_data['birth_date']));
    }

    // TODO: more?
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
