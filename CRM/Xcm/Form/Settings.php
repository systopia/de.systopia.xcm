<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2016-2018 SYSTOPIA                       |
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

require_once 'CRM/Core/Form.php';

use CRM_Xcm_ExtensionUtil as E;

define('XCM_MAX_RULE_COUNT', 9);

/**
 * XCM Settings form controller
 */
class CRM_Xcm_Form_Settings extends CRM_Core_Form {

  /** profile name */
  protected $config = NULL;

  /**
   * build form
   *
   * @throws Exception
   */
  public function buildQuickForm() {
    // load Profile stuff
    $clone        = CRM_Utils_Request::retrieve('clone', 'String');
    $profile_name = CRM_Utils_Request::retrieve('pid', 'String');
    if ($clone) {
      $this->config = CRM_Xcm_Configuration::getConfigProfile($clone);
      $this->addElement('hidden', 'clone', $clone);
    } else {
      $this->config = CRM_Xcm_Configuration::getConfigProfile($profile_name);
      $this->addElement('hidden', 'clone', '');
    }

    $this->addElement('text',
        'pid',
        E::ts('Profile ID'));
    if ($clone) {
      $this->setDefaults(['pid' => E::ts('new_profile_id')]);
    } else {
      $this->setDefaults(['pid' => $profile_name]);
      $this->freeze(['pid']);
    }

    $this->addElement('text',
        'profile_label',
        E::ts('Profile Name'),
        ['class' => 'huge'],
        FALSE);
    if ($clone) {
      $this->setDefaults(['profile_label' => E::ts("Copy of ") . $this->config->getLabel()]);
    } else {
      $this->setDefaults(['profile_label' => $this->config->getLabel()]);
    }


    $locationTypes = $this->getLocationTypes();
    $phoneTypes = $this->getPhoneTypes();

    // add general options
    $this->addElement('select',
                      'picker',
                      E::ts('Of multiple matches, pick:'),
                      $this->getPickers(),
                      array('class' => 'crm-select2 huge'));

    $this->addElement('select',
                      'default_location_type',
                      E::ts('Default Location Type'),
                      $locationTypes,
                      array('class' => 'crm-select2 huge'));
    $this->add('select',
                      'primary_phone_type',
                      E::ts('Primary Phone Type'),
                      $phoneTypes,
                      true,
                      array('class' => 'crm-select2 huge'));
    $this->add('select',
                      'secondary_phone_type',
                      E::ts('Secondary Phone Type'),
                      $phoneTypes,
                      false,
                      array('class' => 'crm-select2 huge', 'placeholder' => E::ts('Secondary phone not used')));
    $this->addElement('select',
                      'fill_fields',
                      E::ts('Fill Fields'),
                      self::getContactFields() + self::getCustomFields(),
                      array(// 'style'    => 'width:450px; height:100%;',
                            'multiple' => 'multiple',
                            'class'    => 'crm-select2 huge'));

    $this->addElement('select',
        'override_fields',
        E::ts('Override Fields'),
        self::getContactFields() + self::getCustomFields(),
        array(// 'style'    => 'width:450px; height:100%;',
              'multiple' => 'multiple',
              'class'    => 'crm-select2 huge'));

    $this->addElement('select',
        'override_details',
        E::ts('Override Details'),
        array(
            'email'   => E::ts('Email'),
            'phone'   => E::ts('Phone'),
            'website' => E::ts('Website'),
            'im'      => E::ts('IM'),
            'address' => E::ts('Address')),
        array(// 'style'    => 'width:450px; height:100%;',
              'multiple' => 'multiple',
              'class'    => 'crm-select2 huge'));

    $this->addElement('checkbox',
        'override_details_primary',
        E::ts('Change Primary Detail?'));


    $this->addElement('checkbox',
      'fill_fields_multivalue',
      E::ts('Fill multi-value field values'));

    $this->addElement('select',
                      'fill_details',
                      E::ts('Fill Details'),
                      array(
                        'email'   => E::ts('Email'),
                        'phone'   => E::ts('Phone'),
                        'website' => E::ts('Website')),
                      array(// 'style'    => 'width:450px; height:100%;',
                            'multiple' => 'multiple',
                            'class'    => 'crm-select2 huge'));

    $this->addElement('checkbox',
                      'fill_details_primary',
                      E::ts('Make New Detail Primary'));

    $this->addElement('select',
                      'fill_address',
                      E::ts('Fill Address'),
                      array(0 => E::ts('Never'),
                            1 => E::ts('If contact has no address'),
                            2 => E::ts('If contact has no address of that type')),
                      array('class' => 'crm-select2 huge'));


    // diff activity options
    $this->addElement('select',
                      'diff_handler',
                      E::ts('Process Differences'),
                      $this->getDiffHandlers(),
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'diff_activity',
                      E::ts('Activity Type'),
                      $this->getActivities(FALSE),
                      array('class' => 'crm-select2'));
    $this->addElement('select',
      'diff_activity_status',
      E::ts('Activity Status'),
      $this->getActivityStatuses(),
      array('class' => 'crm-select2'));

    $this->addElement('text',
                      "diff_activity_subject",
                      E::ts('Subject'));

    $this->addElement('select',
                      'case_insensitive',
                      E::ts('Attribute Comparison'),
                      array(0 => E::ts('case-sensitive',     array('domain' => 'de.systopia.xcm')),
                            1 => E::ts('not case-sensitive')),
                      array('class' => 'crm-select2 huge'));

    $this->addElement('select',
                      'diff_processing',
                      E::ts('Diff Processing Helper'),
                      array(0 => E::ts('No'),
                            1 => E::ts('Yes (beta)',  array('domain' => 'de.systopia.xcm'))),
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'diff_current_location_type',
                      E::ts('Location Type'),
                      $locationTypes,
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'diff_old_location_type',
                      E::ts('Bump old address to type'),
                      array('0' => E::ts("Don't do that")) + $locationTypes,
                      array('class' => 'crm-select2'));


    $this->addElement(
      'checkbox',
      'match_contact_id',
      ts('Match contacts by contact ID', array('domain' => 'de.systopia.xcm'))
    );

    // add the rule selectors
    for ($i=1; $i <= XCM_MAX_RULE_COUNT; $i++) {
      $this->addElement('select',
                        "rule_$i",
                        E::ts('Matching Rule #%1', array(1 => $i, 'domain' => 'de.systopia.xcm')),
                        $this->getRuleOptions($i),
                        array('class' => 'crm-select2', 'style' => 'width: 400px'));
    }

    // add stuff for matched/created postprocessing
    foreach (array('matched', 'created') as $mode) {
      $this->addElement('select',
                        "{$mode}_add_group",
                        E::ts('Add to group'),
                        $this->getGroups(),
                        array('class' => 'crm-select2'));

      $this->addElement('select',
                        "{$mode}_add_tag",
                        E::ts('Add to tag'),
                        $this->getTags(),
                        array('class' => 'crm-select2'));

      $this->addElement('select',
                        "{$mode}_add_activity",
                        E::ts('Add activity'),
                        $this->getActivities(),
                        array('class' => 'crm-select2'));

      $this->addElement('text',
                        "{$mode}_add_activity_subject",
                        E::ts('Subject'));

      $this->addElement('select',
                        "{$mode}_add_activity_status",
                        E::ts('Status'),
                        $this->getActivityStatuses(),
                        array('class' => 'crm-select2'));

      $this->addElement('select',
                        "{$mode}_add_activity_template",
                        E::ts('Template'),
                        $this->getTemplates(),
                        array('class' => 'crm-select2'));

      $this->addElement('select',
                        "{$mode}_add_activity_campaign",
                        E::ts('Campaign'),
                        $this->getCampaigns(),
                        array('class' => 'crm-select2'));
    }

    // generate duplicate activity
    $this->addElement('select',
                      'duplicates_activity',
                      E::ts('Generate Duplicates Activity'),
                      $this->getActivities(),
                      array('class' => 'crm-select2'));

    $this->addElement('text',
                      'duplicates_subject',
                      E::ts('Activity Subject'));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    // pass params to smarty
    $this->assign('rule_idxs', range(1, XCM_MAX_RULE_COUNT));

    parent::buildQuickForm();
  }

  /**
   * Getter for $_defaultValues.
   *
   * @return array
   */
  public function setDefaultValues() {
    $options        = $this->config->getOptions();
    $rules          = $this->config->getRules();
    $postprocessing = $this->config->getPostprocessing();

    if ($options==NULL)        $options = array();
    if ($rules==NULL)          $rules = array();
    if ($postprocessing==NULL) $postprocessing = array();

    return $options + $rules + $postprocessing;
  }

  /**
   * Field validation
   *
   * @return bool
   */
  public function validate() {
    $values = $this->exportValues();

    if (empty($values['pid']) || !preg_match("#^[0-9a-zA-Z_-]+$#", $values['pid'])) {
      $this->_errors['pid'] = E::ts("A profile ID cannot contain whitespaces or special characters");
    }

    if (!empty($values['clone'])) {
      // make sure the pid doesn't exist
      $list = CRM_Xcm_Configuration::getProfileList();
      if (isset($list[$values['pid']])) {
        $this->_errors['pid'] = E::ts("This profile ID is already in use");
      }
    }

    parent::validate();
    return (0 == count($this->_errors));
  }

  public function postProcess() {
    $values = $this->exportValues();

    // check if this is to be stored as a copy!
    if (!empty($values['clone'])) {
      $new_profile_id = $values['pid'];
      $this->config->cloneProfile($new_profile_id);
      $this->config = CRM_Xcm_Configuration::getConfigProfile($new_profile_id);
    }

    // store label
    $this->config->setLabel($values['profile_label']);

    // store options
    $options = array(
      'fill_address'               => CRM_Utils_Array::value('fill_address', $values),
      'fill_fields_multivalue'     => CRM_Utils_Array::value('fill_fields_multivalue', $values),
      'fill_details'               => CRM_Utils_Array::value('fill_details', $values),
      'fill_details_primary'       => CRM_Utils_Array::value('fill_details_primary', $values),
      'override_details'           => CRM_Utils_Array::value('override_details', $values),
      'override_details_primary'   => CRM_Utils_Array::value('override_details_primary', $values),
      'default_location_type'      => CRM_Utils_Array::value('default_location_type', $values),
      'primary_phone_type'         => CRM_Utils_Array::value('primary_phone_type', $values),
      'secondary_phone_type'       => CRM_Utils_Array::value('secondary_phone_type', $values),
      'picker'                     => CRM_Utils_Array::value('picker', $values),
      'duplicates_activity'        => CRM_Utils_Array::value('duplicates_activity', $values),
      'duplicates_subject'         => CRM_Utils_Array::value('duplicates_subject', $values),
      'diff_handler'               => CRM_Utils_Array::value('diff_handler', $values),
      'diff_activity'              => CRM_Utils_Array::value('diff_activity', $values),
      'diff_activity_status'       => CRM_Utils_Array::value('diff_activity_status', $values),
      'diff_activity_subject'      => CRM_Utils_Array::value('diff_activity_subject', $values),
      'diff_processing'            => CRM_Utils_Array::value('diff_processing', $values),
      'diff_current_location_type' => CRM_Utils_Array::value('diff_current_location_type', $values),
      'diff_old_location_type'     => CRM_Utils_Array::value('diff_old_location_type', $values),
      'fill_fields'                => CRM_Utils_Array::value('fill_fields', $values),
      'override_fields'            => CRM_Utils_Array::value('override_fields', $values),
      'case_insensitive'           => CRM_Utils_Array::value('case_insensitive', $values),
      'match_contact_id'           => CRM_Utils_Array::value('match_contact_id', $values),
      );
    $this->config->setOptions($options);

    // store the rules
    $rules = array();
    for ($i=1; isset($values["rule_$i"]); $i++) {
      $rules["rule_$i"] = $values["rule_$i"];
    }
    $this->config->setRules($rules);

    // store the postprocessing
    $postprocessing = array();
    foreach (array('matched', 'created') as $mode) {
      foreach (array('group', 'tag', 'activity', 'activity_subject', 'activity_template', 'activity_status', 'activity_campaign') as $type) {
        $key = "{$mode}_add_{$type}";
        $postprocessing[$key] = CRM_Utils_Array::value($key, $values);
      }
    }
    $this->config->setPostprocessing($postprocessing);

    // save options
    $this->config->store();

    parent::postProcess();
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/setting/xcm'));
  }







  protected function getRuleOptions($i) {
    // compile list
    if ($i > 1) {
      $none_option = array(0 => E::ts('None, thank you'));
    } else {
      $none_option = array();
    }

    // TOOD: add option group rules
    $rules = array();
    $option_values = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'xcm_matching_rules', 'is_active' => 1));
    foreach ($option_values['values'] as $option_value) {
      $rules[$option_value['value']] = $option_value['label'];
    }


    // get the dedupe rules
    $dedupe_rules = CRM_Xcm_Matcher_DedupeRule::getRuleList();

    return $none_option + $rules + $dedupe_rules;
  }

  protected function getLocationTypes() {
    $types = array();
    $result = civicrm_api3('LocationType', 'get', array('is_active' => 1));
    foreach ($result['values'] as $type) {
      $types[$type['id']] = $type['display_name'];
    }
    return $types;
  }

  protected function getPhoneTypes() {
    $types = array();
    $result = civicrm_api3('OptionValue', 'get', array('is_active' => 1, 'option_group_id' => 'phone_type', 'option.limit' => 0));
    foreach ($result['values'] as $type) {
      $types[$type['value']] = $type['label'];
    }
    return $types;
  }

  protected function getActivities($none_option = TRUE) {
    $activity_list = array();
    if ($none_option) {
      $activity_list[0] = E::ts('None, thank you');
    }

    $activities = civicrm_api3('OptionValue', 'get', array('is_active' => 1, 'option_group_id' => 'activity_type', 'option.limit' => 0));
    foreach ($activities['values'] as $activity) {
      $activity_list[$activity['value']] = $activity['label'];
    }

    return $activity_list;
  }

  protected function getActivityStatuses() {
    $activity_status_list = array();
    $statuses = civicrm_api3('OptionValue', 'get', array('is_active' => 1, 'option_group_id' => 'activity_status', 'option.limit' => 0));
    foreach ($statuses['values'] as $status) {
      $activity_status_list[$status['value']] = $status['label'];
    }

    return $activity_status_list;
  }

  protected function getCampaigns() {
    $campaign_list = array(
        ''      => E::ts("No Campaign"),
        'input' => E::ts("From Input (campaign_id)"),
    );
    $campaigns = civicrm_api3('Campaign', 'get', array('is_active' => 1, 'option.limit' => 0));
    foreach ($campaigns['values'] as $campaign) {
      $campaign_list[$campaign['id']] = $campaign['name'];
    }

    return $campaign_list;
  }

  protected function getTags() {
    $tag_list = array(0 => E::ts('None, thank you'));

    $tags = civicrm_api3('Tag', 'get', array('is_active' => 1, 'option.limit' => 0));
    foreach ($tags['values'] as $tag) {
      $tag_list[$tag['id']] = $tag['name'];
    }
    return $tag_list;
  }

  protected function getTemplates() {
    $template_list = array(0 => E::ts('No content'));

    $templates = civicrm_api3('MessageTemplate', 'get', array('is_active' => 1, 'is_reserved' => 0, 'option.limit' => 0));
    foreach ($templates['values'] as $template) {
      $template_list[$template['id']] = $template['msg_title'];
    }
    return $template_list;
  }

  protected function getGroups() {
    $group_list = array(0 => E::ts('None, thank you'));

    $groups = civicrm_api3('Group', 'get', array('is_active' => 1, 'option.limit' => 0));
    foreach ($groups['values'] as $group) {
      $group_list[$group['id']] = $group['title'];
    }

    return $group_list;
  }

  protected function getPickers() {
    return array(
      'min'  => E::ts('the oldest contact'),
      'max'  => E::ts('the newest contact'),
      'none' => E::ts('none (create new contact)'),
      );
  }

  /**
   * Get the list of options for diff handlers
   */
  protected function getDiffHandlers() {
    $diff_handlers = array();
    $diff_handlers['none'] = E::ts("Don't do anything");
    $diff_handlers['diff'] = E::ts("Only changes requiring review (Diff Activity)");
    $diff_handlers['updated_diff'] = E::ts("All changes (Difference and Update Activity)");

    if (function_exists('i3val_civicrm_install')) {
      $diff_handlers['i3val'] = E::ts("I3Val Handler");
    }

    return $diff_handlers;
  }

  /**
   * get a list of custom fields eligible for submission.
   * those are all custom fields that belong to a contact in general
   */
  public static function getCustomFields() {
    $custom_fields = array();

    $custom_group_query = civicrm_api3('CustomGroup', 'get', array(
      'extends'      => array('IN' => array('Contact', 'Individual', 'Organization', 'Household')),
      'is_active'    => 1,
      'option.limit' => 0,
      'is_multiple'  => 0,
      'is_reserved'  => 0,
      'return'       => 'id'));
    $custom_group_ids   = array();
    foreach ($custom_group_query['values'] as $custom_group) {
      $custom_group_ids[] = (int) $custom_group['id'];
    }

    if (!empty($custom_group_ids)) {
      $custom_field_query = civicrm_api3('CustomField', 'get', array(
        'custom_group_id'  => array('IN' => $custom_group_ids),
        'is_active'        => 1,
        'option.limit'     => 0,
        'return'           => 'id,label'));
      foreach ($custom_field_query['values'] as $custom_field) {
        $custom_fields["custom_{$custom_field['id']}"] = "{$custom_field['label']} [{$custom_field['id']}]";
      }
    }

    return $custom_fields;
  }

  /**
   * get a list of custom fields eligible for submission.
   * those are all custom fields that belong to a contact in general
   */
  public static function getContactFields() {
    return array(
      'display_name'                   => E::ts("Display Name"),
      'household_name'                 => E::ts("Household Name"),
      'organization_name'              => E::ts("Organization Name"),
      'first_name'                     => E::ts("First Name"),
      'last_name'                      => E::ts("Last Name"),
      'middle_name'                    => E::ts("Middle Name"),
      'nick_name'                      => E::ts("Nick Name"),
      'legal_name'                     => E::ts("Legal Name"),
      'prefix_id'                      => E::ts("Prefix"),
      'suffix_id'                      => E::ts("Suffix"),
      'birth_date'                     => E::ts("Birth Date"),
      'gender_id'                      => E::ts("Gender"),
      'formal_title'                   => E::ts("Formal Title"),
      'job_title'                      => E::ts("Job Title"),
      'do_not_email'                   => E::ts("Do not Email"),
      'do_not_phone'                   => E::ts("Do not Phone"),
      'do_not_sms'                     => E::ts("Do not SMS"),
      'do_not_trade'                   => E::ts("Do not Trade"),
      'is_opt_out'                     => E::ts("Opt-Out"),
      'preferred_language'             => E::ts("Preferred Language"),
      'preferred_communication_method' => E::ts("Preferred Communication Method"),
      'legal_identifier'               => E::ts("Legal Identifier"),
      'external_identifier'            => E::ts("External Identifier"),
    );
  }
}
