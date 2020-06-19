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
  protected static $_singletons = array();

  /** Configuration for this engine */
  protected $config = NULL;

  /**
   * get the singleton instance of the engine
   * @param $profile
   * @return CRM_Xcm_MatchingEngine
   */
  public static function getEngine($profile = NULL) {
    if (!isset(self::$_singletons[$profile])) {
      self::$_singletons[$profile] = new CRM_Xcm_MatchingEngine($profile);
    }
    return self::$_singletons[$profile];
  }

  /**
   * CRM_Xcm_MatchingEngine constructor, fetches the configuration
   *
   * @param $profile string profile name or NULL
   * @throws Exception
   */
  protected function __construct($profile) {
    $this->config = CRM_Xcm_Configuration::getConfigProfile($profile);
  }

  /**
   * Try to find/match the contact with the given data.
   * If that fails, a new contact will be created with that data
   *
   * @throws exception  if anything goes wrong during matching/contact creation
   */
  public function getOrCreateContact(&$contact_data) {
    // first: resolve custom fields to custom_xx notation
    CRM_Xcm_Tools::resolveCustomFields($contact_data);

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
    // Check for "match_contact_id" setting and try to match by contact ID.
    if (isset($contact_data['id'])) {
      if (!empty($contact_data['id'])) {
        $options = $this->config->getOptions();
        if (!empty($options['match_contact_id'])) {
          // The setting is "on", try to match by contact ID.
          try {
            $contact = civicrm_api3('Contact', 'getsingle', array(
                'id'         => $contact_data['id'],
                'return'     => 'id,is_deleted'
            ));
            if (empty($contact['is_deleted'])) {
              // ID refers to a real contact, that has not been deleted
              return self::createResultMatched($contact_data['id']);
            }
          } catch (Exception $ex) {
            // not found? no problem... let's move on...
          }
        }
      }

      // Rename the "id" parameter to avoid any quirks later on.
      $contact_data['xcm_submitted_contact_id'] = $contact_data['id'];
      unset($contact_data['id']);
    }

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
    $new_contact  = civicrm_api3('Contact', 'create', CRM_Xcm_Tools::stripAddressAndDetailData($contact_data));

    // create address separately (see https://github.com/systopia/de.systopia.xcm/issues/6)
    $address_data = CRM_Xcm_Tools::extractAddressData($contact_data);
    if (!empty($address_data)) {
      $address_data['contact_id'] = $new_contact['id'];
      if (empty($address_data['location_type_id'])) {
        $address_data['location_type_id'] = $this->config->defaultLocationType();
      }
      civicrm_api3('Address', 'create', $address_data);
    }

    // create phone number (that used to work...)
    $this->addDetailToContact($new_contact['id'], 'email',   $contact_data);
    $this->addPhoneToContact($new_contact['id'], $contact_data, 'phone', $this->config->primaryPhoneType());
    if ($this->config->secondaryPhoneType()) {
      $this->addPhoneToContact($new_contact['id'], $contact_data, 'phone2', $this->config->secondaryPhoneType());
    }
    $this->addDetailToContact($new_contact['id'], 'website', $contact_data);

    return $new_contact;
  }


  /**
   * @todo document
   */
  protected function getMatchingRules() {
    $rules = $this->config->getRules();
    $rule_instances = array();

    foreach ($rules as $rule_name) {
      if (empty($rule_name)) {
        continue;

      } elseif ('DEDUPE_' == substr($rule_name, 0, 7)) {
        // this is a dedupe rule
        $new_rule = new CRM_Xcm_Matcher_DedupeRule(substr($rule_name, 7));
        $new_rule->setConfig($this->config);
        $rule_instances[] = $new_rule;

      } else {
        // this should be a class name
        // TODO: error handling
        $new_rule = new $rule_name();
        $new_rule->setConfig($this->config);
        $rule_instances[] = $new_rule;
      }
    }
    return $rule_instances;
  }

  /**
   * @todo document
   */
  protected function postProcessNewContact(&$new_contact, &$contact_data) {
    $postprocessing = $this->config->getPostprocessing();

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
                                  $postprocessing['created_add_activity_status'],
                                  $postprocessing['created_add_activity_campaign'],
                                  $postprocessing['created_add_activity_template'],
                                  $contact_data);
    }
  }

  /**
   * Perform all the post processing the configuration imposes
   */
  protected function postProcessContactMatch(&$result, &$submitted_contact_data) {
    $postprocessing = $this->config->getPostprocessing();
    $options        = $this->config->getOptions();

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
                                  $postprocessing['matched_add_activity_status'],
                                  $postprocessing['matched_add_activity_campaign'],
                                  $postprocessing['matched_add_activity_template'],
                                  $submitted_contact_data);
    }

    // FILL/DIFF ACTIONS (require the current contact data):
    $diff_handler = $this->config->diffHandler();
    if (   ($diff_handler != 'none')
        || !empty($options['override_fields'])
        || !empty($options['override_details'])
        || !empty($options['fill_fields'])
        || !empty($options['fill_address'])
        || !empty($options['fill_details'])) {

      // sort out location type
      if (empty($submitted_contact_data['location_type_id'])) {
        $location_type_id = $this->config->defaultLocationType();
      } else {
        $location_type_id = $submitted_contact_data['location_type_id'];
      }

      // load contact
      $current_contact_data = $this->loadCurrentContactData($result['contact_id'], $submitted_contact_data);
      CRM_Xcm_DataNormaliser::normaliseData($current_contact_data);
      $original_contact_data = $current_contact_data;

      // OVERRIDE CURRENT CONTACT DATA
      if (!empty($options['override_fields'])) {
        //  caution: will set the overwritten fields in $current_contact_data
        $this->overrideContactData($current_contact_data, $submitted_contact_data, $options['override_fields']);
      }

      // OVERRIDE CONTACT DETAILS
      if (!empty($options['override_details']) && is_array($options['override_details'])) {
        //  caution: will override detail data
        foreach ($options['override_details'] as $entity_type) {
          if ($entity_type == 'phone') {
            $this->overrideContactPhone($current_contact_data, $submitted_contact_data, 'phone', $this->config->primaryPhoneType());
            if ($this->config->secondaryPhoneType()) {
              $this->overrideContactPhone($current_contact_data, $submitted_contact_data, 'phone2', $this->config->secondaryPhoneType());
            }
          } else {
            $this->overrideContactDetail($entity_type, $current_contact_data, $submitted_contact_data);
          }
        }
      }

      // FILL CURRENT CONTACT DATA
      if (!empty($options['fill_fields'])) {
        //  caution: will set the overwritten fields in $current_contact_data
        $this->fillContactData($current_contact_data, $submitted_contact_data, $options['fill_fields'], $options['fill_fields_multivalue']);
      }

      // FILL CURRENT CONTACT DETAILS
      if (!empty($options['fill_details']) && is_array($options['fill_details'])) {
        foreach ($options['fill_details'] as $entity) {
          if ($entity == 'phone') {
            $this->addPhoneToContact($result['contact_id'], $submitted_contact_data, 'phone', $this->config->primaryPhoneType(), !empty($options['fill_details_primary']), $current_contact_data);
            if ($this->config->secondaryPhoneType()) {
              $this->addPhoneToContact($result['contact_id'], $submitted_contact_data, 'phone2', $this->config->secondaryPhoneType(), FALSE, $current_contact_data);
            }
          } else {
            $this->addDetailToContact($result['contact_id'], $entity, $submitted_contact_data, !empty($options['fill_details_primary']), $current_contact_data);
          }
        }
      }

      // FILL CURRENT CONTACT ADDRESS
      if (!empty($options['fill_address'])) {
        $address_data = CRM_Xcm_Tools::extractAddressData($submitted_contact_data);
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
            $existing_address_data = CRM_Xcm_Tools::extractAddressData($existing_address, FALSE);
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
        case 'updated_diff':
          $this->createDiffActivity($original_contact_data, $options, $options['diff_activity_subject'], $submitted_contact_data, $location_type_id);
          break;

        case 'i3val':
          $this->createI3ValActivity($current_contact_data, $submitted_contact_data);

        default:
          break;
      }
    }
  }

  protected function addContactToGroup($contact_id, $group_id) {
    $contact_id = (int) $contact_id;
    $group_id = (int) $group_id;
    if ($contact_id && $group_id) {
      try {
        $is_group_member = civicrm_api3('GroupContact', 'getcount', ['contact_id' => $contact_id, 'group_id' => $group_id, 'status' => 'Added']);
        if (!$is_group_member) {
          civicrm_api3('GroupContact', 'create', ['contact_id' => $contact_id, 'group_id' => $group_id, 'status' => 'Added']);
        }
      } catch (Exception $ex) {
        // this shouldn't happen
        error_log("Error when adding contact to group: " . $ex->getMessage());
      }
    }
  }

  /**
   * Tag the contact with the given tag
   *
   * @param $contact_id integer contact ID
   * @param $tag_id     integer tag ID
   */
  protected function addContactToTag($contact_id, $tag_id) {
    $contact_id = (int) $contact_id;
    $tag_id = (int) $tag_id;
    if ($contact_id && $tag_id) {
      try {
        $is_tagged = civicrm_api3('EntityTag', 'getcount', ['entity_id' => $contact_id, 'tag_id' => $tag_id, 'entity_table' => 'civicrm_contact']);
        if (!$is_tagged) {
          civicrm_api3('EntityTag', 'create', ['entity_id' => $contact_id, 'tag_id' => $tag_id, 'entity_table' => 'civicrm_contact']);
        }
      } catch (Exception $ex) {
        // tag probably already exists with the contact, or contact doesn't exist
        //  no need to worry.
      }
    }
  }

  /**
   * Create a marker activity with the given contact
   *
   * @param $contact_id         int contact ID
   * @param $activity_type_id   int activity type id
   * @param $subject            string subject
   * @param $status_id          int activity status id
   * @param $campaign           string campaign: empty string (no campaign), 'input' (take from input), campaign_id otherwise
   * @param $template_id        int template ID
   * @param $contact_data       array contact data
   */
  protected function addActivityToContact($contact_id, $activity_type_id, $subject, $status_id, $campaign, $template_id, &$contact_data) {
    if (empty($status_id)) {
      $status_id = $this->config->defaultActivityStatus();
    }
    if ($campaign == 'input') {
      $campaign = CRM_Utils_Array::value('campaign_id', $contact_data);
    }

    $activity_data = array(
        'activity_type_id'   => $activity_type_id,
        'subject'            => $subject,
        'status_id'          => $status_id,
        'activity_date_time' => date("YmdHis"),
        'target_contact_id'  => (int) $contact_id,
        'source_contact_id'  => (int) $contact_id,
        'campaign_id'        => $campaign,
    );

    try {
      if ($template_id) {
        $template = civicrm_api3('MessageTemplate', 'getsingle', array('id' => $template_id));
        $activity_data['details'] = $this->renderTemplate('string:' . $template['msg_text'], $contact_data);
      }

      $activity = CRM_Activity_BAO_Activity::create($activity_data);
    } catch (Exception $ex) {
      CRM_Core_Error::debug_log_message("XCM: failed to create activity: " . $ex->getMessage());
    }
  }

  /**
   * Add a certain entity detail (phone,email,website)
   */
  protected function addDetailToContact($contact_id, $entity, &$data, $as_primary = FALSE, &$data_update = NULL) {
    if (!empty($data[$entity])) {
      // sort out location type
      if (empty($data['location_type_id'])) {
        $location_type_id = $this->config->defaultLocationType();
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

        // create the detail
        civicrm_api3($entity, 'create', $create_detail_call);

        // mark in update_data
        if ($data_update && is_array($data_update)) {
          $data_update[$attribute] = $data[$entity];

          // if we're dealing with a phone number, update phone_numeric as well
          // to avoid unnecessary diff activities
          if ($entity == 'phone') {
            $data_update['phone_numeric'] = $data['phone_numeric'];
          }
        }
      } else {
        // there already is a detail withe same value...
        if ($as_primary) {
          // ...and config says it should be primary -> make it sure it's primary:
          $this->makeExistingDetailPrimary($contact_id, $entity, $attribute, $data[$entity]);
        }
        // also make sure, it doesn't end up in diff:
        unset($data[$entity]);
        // if we're dealing with phone, also do so for phone_numeric
        if ($entity == 'phone') {
          unset($data['phone_numeric']);
        }
      }
    }
  }

  /**
   * Add a phone number to the contact
   *
   * @param int $contact_id
   * @param array $data
   *   Submitted data.
   * @param $attribute
   *  Either 'phone' or 'phone2'. This the atttribute in the $submitted data which holds the phone number
   * @param $phone_type_id
   * @param $as_primary
   *  Mark the phone as primary
   * @param $data_update
   *  The current contact data
   * @throws \CiviCRM_API3_Exception
   */
  protected function addPhoneToContact($contact_id, &$data, $attribute='phone', $phone_type_id=null, $as_primary = FALSE, &$data_update = NULL) {
    if (!empty($data[$attribute])) {
      // sort out location type
      if (empty($data['location_type_id'])) {
        $location_type_id = $this->config->defaultLocationType();
      } else {
        $location_type_id = $data['location_type_id'];
      }

      $api_query = [
        'phone'     => $data[$attribute],
        'contact_id'   => $contact_id,
        'options' => [
          'sort'  => 'is_primary desc',
          'limit' => 1
        ]
      ];
      if ($phone_type_id) {
        $api_query['phone_type_id'] = $phone_type_id;
      }

      // some value was submitted -> check if there is already an existing one
      $existing_entity = civicrm_api3('Phone', 'get', $api_query);
      if (empty($existing_entity['count'])) {
        // there is none -> create
        $create_detail_call = array(
          'phone'         => $data[$attribute],
          'contact_id'       => $contact_id,
          'location_type_id' => $location_type_id);

        // mark as primary if requested
        if ($as_primary) {
          $create_detail_call['is_primary'] = 1;
        }
        if ($phone_type_id) {
          $create_detail_call['phone_type_id'] = $phone_type_id;
        }

        // create the detail
        civicrm_api3('Phone', 'create', $create_detail_call);

        // mark in update_data
        if ($data_update && is_array($data_update)) {
          $data_update[$attribute] = $data[$attribute];

          // if we're dealing with a phone number, update phone_numeric as well
          // to avoid unnecessary diff activities
          $data_update[$attribute.'_numeric'] = $data['phone_numeric'];
        }
      } else {
        // there already is a detail withe same value...
        if ($as_primary) {
          // ...and config says it should be primary -> make it sure it's primary:
          $this->makeExistingDetailPrimary($contact_id, 'Phone', 'phone', $data[$attribute]);
        }
        // also make sure, it doesn't end up in diff:
        unset($data[$attribute]);
        // if we're dealing with phone, also do so for phone_numeric
        unset($data[$attribute.'_numeric']);
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

    // Load second phone
    if ($this->config->secondaryPhoneType()) {
      try {
        $phone = civicrm_api3('Phone', 'getvalue', [
          'contact_id' => $contact_id,
          'phone_type_id' => $this->config->secondaryPhoneType(),
          'return' => 'phone'
        ]);
        $contact['phone2'] = $phone;
      } catch (CiviCRM_API3_Exception $e) {
        // Do nothing
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
        elseif (!empty($fill_multivalue) && self::fieldIsMultivalue($key)) {
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
   * Will override the given fields in the database
   *  and update the $current_contact_data accordingly
   */
  protected function overrideContactData(&$current_contact_data, $submitted_contact_data, $fields) {
    $update_query = array();
    foreach ($fields as $key) {
      if (isset($submitted_contact_data[$key])) {
        $current_value   = CRM_Utils_Array::value($key, $current_contact_data);
        if ($current_value != $submitted_contact_data[$key]) {
          $update_query[$key]         = $submitted_contact_data[$key];
          $current_contact_data[$key] = $submitted_contact_data[$key];
        }
      }
    }

    // run update should it be required
    if (!empty($update_query)) {
      $update_query['id'] = $current_contact_data['id'];
      civicrm_api3('Contact', 'create', $update_query);
    }
  }

  /**
   * Will override the given detail entity in the database,
   *  and update the $current_contact_data accordingly
   *
   * It will only overwrite entities with the same location type,
   *  and not overwrite primary entries, unless $override_details_primary is TRUE
   */
  protected function overrideContactDetail($entity_type, &$current_contact_data, $submitted_contact_data) {
    switch (strtolower($entity_type)) {
      case 'email':
        $has_primary = TRUE;
        $data_attributes = ['email'];
        $identifying_attributes = ['location_type_id'];
        break;

      case 'phone':
        $has_primary = TRUE;
        $data_attributes = ['phone'];
        $identifying_attributes = ['location_type_id', 'phone_type_id'];
        break;

      case 'im':
        $has_primary = TRUE;
        $data_attributes = ['name'];
        $identifying_attributes = ['location_type_id', 'provider_id'];
        break;

      case 'website':
        $has_primary = FALSE;
        $data_attributes = ['url'];
        $identifying_attributes = ['website_type_id'];
        break;

      case 'address':
        $has_primary = TRUE;
        $data_attributes = ['street_address', 'postal_code', 'city', 'supplemental_address_1', 'supplemental_address_2', 'supplemental_address_3', 'county_id', 'country_id', 'state_province_id'];
        $identifying_attributes = ['location_type_id'];
        break;

      default:
        # unknown type
        return;
    }

    // if all main attributes are empty, there's nothing to to
    $data_present = FALSE;
    foreach ($data_attributes as $data_attribute) {
      if (!empty($submitted_contact_data[$data_attribute])) {
        $data_present = TRUE;
        break;
      }
    }
    if (!$data_present) {
      return;
    }

    // find current entries and replace the first match
    try {
      $options = $this->config->getOptions();
      $case_insensitive         = CRM_Utils_Array::value('case_insensitive', $options);
      $override_details_primary = CRM_Utils_Array::value('override_details_primary', $options);
      if (empty($submitted_contact_data['location_type_id'])) {
        $submitted_contact_data['location_type_id'] = $this->config->defaultLocationType();
      }

      // query existing entities
      $entity_query_params = [
          'contact_id'       => $current_contact_data['id'],
          'option.limit'     => 0];
      // add identifying attributes
      foreach ($identifying_attributes as $identifying_attribute) {
        if (!empty($submitted_contact_data[$identifying_attribute]))
        $entity_query_params[$identifying_attribute] = $submitted_contact_data[$identifying_attribute];
      }
      $entity_query = civicrm_api3($entity_type, 'get', $entity_query_params);

      // find the first matching one, and overwrite
      foreach ($entity_query['values'] as $entity_data) {
        if ($this->attributesDiffer($data_attributes, $entity_data, $submitted_contact_data, $case_insensitive)) {
          if (empty($entity_data['is_primary']) || $override_details_primary || !$has_primary) {
            // this is the one that will be overwritten - i.e. deleted an newly created, so...
            // FIRST: delete existing one
            civicrm_api3($entity_type, 'delete', ['id' => $entity_data['id']]);

            // THEN: compile a new one
            $new_entity = ['contact_id' => $entity_data['contact_id']];
            foreach ($identifying_attributes as $attribute) {
              if (isset($submitted_contact_data[$attribute])) {
                $new_entity[$attribute] = $submitted_contact_data[$attribute];
                $current_contact_data[$attribute] = $submitted_contact_data[$attribute];
              }
            }
            foreach ($data_attributes as $attribute) {
              if (isset($submitted_contact_data[$attribute])) {
                $new_entity[$attribute] = $submitted_contact_data[$attribute];
                $current_contact_data[$attribute] = $submitted_contact_data[$attribute];
              }
            }
            $result = civicrm_api3($entity_type, 'create', $new_entity);
            break;
          }
        }
      }
    } catch (Exception $ex) {
      // something went wrong
      error_log("de.systopia.xcm: error when trying to override {$entity_type}: " . $ex->getMessage());
    }
  }

  /**
   * Will override the given detail entity in the database,
   *  and update the $current_contact_data accordingly
   *
   * It will only overwrite entities with the same location type,
   *  and not overwrite primary entries, unless $override_details_primary is TRUE
   */
  protected function overrideContactPhone(&$current_contact_data, $submitted_contact_data, $attribute, $phone_type_id) {
    $has_primary = TRUE;

    $data_present = FALSE;
    if (!empty($submitted_contact_data[$attribute])) {
      $data_present = TRUE;
    }
    if (!$data_present) {
      return;
    }

    // find current entries and replace the first match
    try {
      $options = $this->config->getOptions();
      $case_insensitive         = CRM_Utils_Array::value('case_insensitive', $options);
      $override_details_primary = CRM_Utils_Array::value('override_details_primary', $options);
      if (empty($submitted_contact_data['location_type_id'])) {
        $submitted_contact_data['location_type_id'] = $this->config->defaultLocationType();
      }

      // query existing entities
      $entity_query_params = [
        'contact_id'       => $current_contact_data['id'],
        'location_type_id' => $submitted_contact_data['location_type_id'],
        'phone_type_id'    => $phone_type_id,
        'option.limit'     => 0];
      $entity_query = civicrm_api3('Phone', 'get', $entity_query_params);

      // find the first matching one, and overwrite
      foreach ($entity_query['values'] as $entity_data) {
        $entity_data[$attribute] = $entity_data['phone'];
        if ($this->attributesDiffer([$attribute], $entity_data, $submitted_contact_data, $case_insensitive)) {
          if (empty($entity_data['is_primary']) || $override_details_primary || !$has_primary) {
            // this is the one that will be overwritten - i.e. deleted an newly created, so...
            // FIRST: delete existing one
            civicrm_api3('Phone', 'delete', ['id' => $entity_data['id']]);

            // THEN: compile a new one
            $new_entity = [
              'contact_id' => $entity_data['contact_id'],
              'location_type_id' => $submitted_contact_data['location_type_id'],
              'phone_type_id'    => $phone_type_id,
              'phone' => $submitted_contact_data[$attribute],
            ];
            $result = civicrm_api3('Phone', 'create', $new_entity);
            break;
          }
        }
      }
    } catch (Exception $ex) {
      // something went wrong
      error_log("de.systopia.xcm: error when trying to override Phone with type ".$phone_type_id.": " . $ex->getMessage());
    }
  }

  /**
   * Will make an existing detail primary, if it isn't already
   *
   * @param $contact_id
   * @param $entity
   * @param $attribute
   * @param $attribute_value
   */
  protected function makeExistingDetailPrimary($contact_id, $entity, $attribute, $attribute_value) {
    // find the detail
    $detail = civicrm_api3($entity, 'getsingle', [
        'contact_id'   => $contact_id,
        $attribute     => $attribute_value,
        'option.sort'  => "$attribute desc",
        'option.limit' => 1,
    ]);
    if (empty($detail['is_primary'])) {
      // detail not yet primary -> set it
      $detail['is_primary'] = 1;
      civicrm_api3($entity, 'create', $detail);
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
  public static function fieldIsMultivalue($key) {
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
    $options = $this->config->getOptions();

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
    $options = $this->config->getOptions();
    $case_insensitive = CRM_Utils_Array::value('case_insensitive', $options);

    // look up some id fields
    CRM_Xcm_DataNormaliser::labelData($contact);
    CRM_Xcm_DataNormaliser::labelData($contact_data);

    // create diff
    $differing_attributes = array();
    $all_attributes = array_keys($contact) + array_keys($contact_data);
    foreach ($all_attributes as $attribute) {
      if (isset($contact[$attribute]) && isset($contact_data[$attribute])) {
        if ($this->attributesDiffer([$attribute], $contact, $contact_data, $case_insensitive)) {
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
      $location_fields = CRM_Xcm_Tools::getAddressFields() + array('phone', 'email');
      foreach ($location_fields as $fieldname) {
        $location_types[$fieldname] = $location_type_name;
      }

      // create activity
      $data = array(
        'differing_attributes' => $differing_attributes,
        'fieldlabels'          => CRM_Xcm_Tools::getFieldLabels($differing_attributes, $this->config),
        'existing_contact'     => $contact,
        'location_types'       => $location_types,
        'submitted_data'       => $contact_data
        );

      $activity_data = array(
          'activity_type_id'   => $options['diff_activity'],
          'subject'            => $subject,
          'status_id'          => !empty($options['diff_activity_status']) ? $options['diff_activity_status'] : $this->config->defaultActivityStatus(),
          'activity_date_time' => date("YmdHis"),
          'target_contact_id'  => (int) $contact['id'],
          'source_contact_id'  => $this->config->getCurrentUserID($contact['id']),
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
  protected function attributesDiffer($data_attributes, $original_values, $submitted_values, $case_insensitive) {
    // TODO: collapse double spaces?

    foreach ($data_attributes as $data_attribute) {
      $original_value  = CRM_Utils_Array::value($data_attribute, $original_values, '');
      $submitted_value = CRM_Utils_Array::value($data_attribute, $submitted_values, '');

      // trim values first
      if (is_string($original_value)) {
        $original_value  = trim($original_value);
      }
      if (is_string($submitted_value)) {
        $submitted_value = trim($submitted_value);
      }

      // compare
      if ($case_insensitive && is_string($original_value) && is_string($submitted_value)) {
        if (strtolower($original_value) != strtolower($submitted_value)) {
          return TRUE;
        }
      } else {
        if ($original_value != $submitted_value) {
          return TRUE;
        }
      }
    }

    // all are equal? good
    return FALSE;
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

  /**
   * generate a valid reply with the given contact ID and confidence
   */
  public static function createResultMatched($contact_id, $confidence = 1.0) {
    if (empty($contact_id)) {
      return self::createResultUnmatched();
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
  public static function createResultUnmatched($message = 'not matched') {
    return array(
        'message' => $message,
    );
  }
}
