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

require_once 'CRM/Core/Form.php';


define('XCM_MAX_RULE_COUNT', 9);

/**
 * XCM Settings form controller
 */
class CRM_Xcm_Form_Settings extends CRM_Core_Form {

  /**
   * build form
   */
  public function buildQuickForm() {
    $locationTypes = $this->getLocationTypes();

    // add general options
    $this->addElement('select',
                      'picker',
                      ts('Of multiple matches, pick:', array('domain' => 'de.systopia.xcm')),
                      $this->getPickers(),
                      array('class' => 'crm-select2 huge'));

    $this->addElement('select',
                      'default_location_type',
                      ts('Default Location Type', array('domain' => 'de.systopia.xcm')),
                      $locationTypes,
                      array('class' => 'crm-select2 huge'));

    $this->addElement('select',
                      'fill_fields',
                      ts('Fill Fields', array('domain' => 'de.systopia.xcm')),
                      $this->getContactFields() + $this->getCustomFields(),
                      array(// 'style'    => 'width:450px; height:100%;',
                            'multiple' => 'multiple',
                            'class'    => 'crm-select2 huge'));

    $this->addElement('select',
                      'fill_details',
                      ts('Fill Details', array('domain' => 'de.systopia.xcm')),
                      array(
                        'email'   => ts('Email', array('domain' => 'de.systopia.xcm')),
                        'phone'   => ts('Phone', array('domain' => 'de.systopia.xcm')),
                        'website' => ts('Website', array('domain' => 'de.systopia.xcm'))),
                      array(// 'style'    => 'width:450px; height:100%;',
                            'multiple' => 'multiple',
                            'class'    => 'crm-select2 huge'));

    $this->addElement('checkbox',
                      'fill_details_primary',
                      ts('Make New Detail Primary', array('domain' => 'de.systopia.xcm')));

    $this->addElement('select',
                      'fill_address',
                      ts('Fill Address', array('domain' => 'de.systopia.xcm')),
                      array(0 => ts('Never', array('domain' => 'de.systopia.xcm')),
                            1 => ts('If contact has no address', array('domain' => 'de.systopia.xcm')),
                            2 => ts('If contact has no address of that type', array('domain' => 'de.systopia.xcm'))),
                      array('class' => 'crm-select2 huge'));


    // diff activity options
    $this->addElement('select',
                      'diff_handler',
                      ts('Process Differences', array('domain' => 'de.systopia.xcm')),
                      $this->getDiffHandlers(),
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'diff_activity',
                      ts('Activity Type', array('domain' => 'de.systopia.xcm')),
                      $this->getActivities(FALSE),
                      array('class' => 'crm-select2'));

    $this->addElement('text',
                      "diff_activity_subject",
                      ts('Subject', array('domain' => 'de.systopia.xcm')));

    $this->addElement('select',
                      'case_insensitive',
                      ts('Attribute Comparison', array('domain' => 'de.systopia.xcm')),
                      array(0 => ts('case-sensitive',     array('domain' => 'de.systopia.xcm')),
                            1 => ts('not case-sensitive', array('domain' => 'de.systopia.xcm'))),
                      array('class' => 'crm-select2 huge'));

    $this->addElement('select',
                      'diff_processing',
                      ts('Diff Processing Helper', array('domain' => 'de.systopia.xcm')),
                      array(0 => ts('No', array('domain' => 'de.systopia.xcm')),
                            1 => ts('Yes (beta)',  array('domain' => 'de.systopia.xcm'))),
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'diff_current_location_type',
                      ts('Location Type', array('domain' => 'de.systopia.xcm')),
                      $locationTypes,
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'diff_old_location_type',
                      ts('Bump old address to type', array('domain' => 'de.systopia.xcm')),
                      array('0' => ts("Don't do that", array('domain' => 'de.systopia.xcm'))) + $locationTypes,
                      array('class' => 'crm-select2'));


    // add the rule selectors
    for ($i=1; $i <= XCM_MAX_RULE_COUNT; $i++) {
      $this->addElement('select',
                        "rule_$i",
                        ts('Matching Rule #%1', array(1 => $i, 'domain' => 'de.systopia.xcm')),
                        $this->getRuleOptions($i),
                        array('class' => 'crm-select2', 'style' => 'width: 400px'));
    }

    // add stuff for matched/created postprocessing
    foreach (array('matched', 'created') as $mode) {
      $this->addElement('select',
                        "{$mode}_add_group",
                        ts('Add to group', array('domain' => 'de.systopia.xcm')),
                        $this->getGroups(),
                        array('class' => 'crm-select2'));

      $this->addElement('select',
                        "{$mode}_add_tag",
                        ts('Add to tag', array('domain' => 'de.systopia.xcm')),
                        $this->getTags(),
                        array('class' => 'crm-select2'));

      $this->addElement('select',
                        "{$mode}_add_activity",
                        ts('Add activity', array('domain' => 'de.systopia.xcm')),
                        $this->getActivities(),
                        array('class' => 'crm-select2'));

      $this->addElement('text',
                        "{$mode}_add_activity_subject",
                        ts('Subject', array('domain' => 'de.systopia.xcm')));

      $this->addElement('select',
                        "{$mode}_add_activity_template",
                        ts('Template', array('domain' => 'de.systopia.xcm')),
                        $this->getTemplates(),
                        array('class' => 'crm-select2'));
    }

    // generate duplicate activity
    $this->addElement('select',
                      'duplicates_activity',
                      ts('Generate Duplicates Activity', array('domain' => 'de.systopia.xcm')),
                      $this->getActivities(),
                      array('class' => 'crm-select2'));

    $this->addElement('text',
                      'duplicates_subject',
                      ts('Activity Subject', array('domain' => 'de.systopia.xcm')));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save', array('domain' => 'de.systopia.xcm')),
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
    $options        = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'xcm_options');
    $rules          = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'rules');
    $postprocessing = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'postprocessing');

    if ($options==NULL)        $options = array();
    if ($rules==NULL)          $rules = array();
    if ($postprocessing==NULL) $postprocessing = array();

    return $options + $rules + $postprocessing;
  }


  public function postProcess() {
    $values = $this->exportValues();

    // store options
    $options = array(
      'fill_address'               => CRM_Utils_Array::value('fill_address', $values),
      'fill_details'               => CRM_Utils_Array::value('fill_details', $values),
      'fill_details_primary'       => CRM_Utils_Array::value('fill_details_primary', $values),
      'default_location_type'      => CRM_Utils_Array::value('default_location_type', $values),
      'picker'                     => CRM_Utils_Array::value('picker', $values),
      'duplicates_activity'        => CRM_Utils_Array::value('duplicates_activity', $values),
      'duplicates_subject'         => CRM_Utils_Array::value('duplicates_subject', $values),
      'diff_handler'               => CRM_Utils_Array::value('diff_handler', $values),
      'diff_activity'              => CRM_Utils_Array::value('diff_activity', $values),
      'diff_activity_subject'      => CRM_Utils_Array::value('diff_activity_subject', $values),
      'diff_processing'            => CRM_Utils_Array::value('diff_processing', $values),
      'diff_current_location_type' => CRM_Utils_Array::value('diff_current_location_type', $values),
      'diff_old_location_type'     => CRM_Utils_Array::value('diff_old_location_type', $values),
      'fill_fields'                => CRM_Utils_Array::value('fill_fields', $values),
      'case_insensitive'           => CRM_Utils_Array::value('case_insensitive', $values),
      );
    CRM_Core_BAO_Setting::setItem($options, 'de.systopia.xcm', 'xcm_options');

    // store the rules
    $rules = array();
    for ($i=1; isset($values["rule_$i"]); $i++) {
      $rules["rule_$i"] = $values["rule_$i"];
    }
    CRM_Core_BAO_Setting::setItem($rules, 'de.systopia.xcm', 'rules');

    // store the postprocessing
    $postprocessing = array();
    foreach (array('matched', 'created') as $mode) {
      foreach (array('group', 'tag', 'activity', 'activity_subject', 'activity_template') as $type) {
        $key = "{$mode}_add_{$type}";
        $postprocessing[$key] = CRM_Utils_Array::value($key, $values);
      }
    }
    CRM_Core_BAO_Setting::setItem($postprocessing, 'de.systopia.xcm', 'postprocessing');

    parent::postProcess();
  }







  protected function getRuleOptions($i) {
    // compile list
    if ($i > 1) {
      $none_option = array(0 => ts('None, thank you', array('domain' => 'de.systopia.xcm')));
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

  protected function getActivities($none_option = TRUE) {
    $activity_list = array();
    if ($none_option) {
      $activity_list[0] = ts('None, thank you', array('domain' => 'de.systopia.xcm'));
    }

    $activities = civicrm_api3('OptionValue', 'get', array('is_active' => 1, 'option_group_id' => 'activity_type', 'option.limit' => 9999));
    foreach ($activities['values'] as $activity) {
      $activity_list[$activity['value']] = $activity['label'];
    }

    return $activity_list;
  }

  protected function getTags() {
    $tag_list = array(0 => ts('None, thank you', array('domain' => 'de.systopia.xcm')));

    $tags = civicrm_api3('Tag', 'get', array('is_active' => 1, 'option.limit' => 9999));
    foreach ($tags['values'] as $tag) {
      $tag_list[$tag['id']] = $tag['name'];
    }
    return $tag_list;
  }

  protected function getTemplates() {
    $template_list = array(0 => ts('No content', array('domain' => 'de.systopia.xcm')));

    $templates = civicrm_api3('MessageTemplate', 'get', array('is_active' => 1, 'is_reserved' => 0, 'option.limit' => 9999));
    foreach ($templates['values'] as $template) {
      $template_list[$template['id']] = $template['msg_title'];
    }
    return $template_list;
  }

  protected function getGroups() {
    $group_list = array(0 => ts('None, thank you', array('domain' => 'de.systopia.xcm')));

    $groups = civicrm_api3('Group', 'get', array('is_active' => 1, 'option.limit' => 9999));
    foreach ($groups['values'] as $group) {
      $group_list[$group['id']] = $group['title'];
    }

    return $group_list;
  }

  protected function getPickers() {
    return array(
      'min'  => ts('the oldest contact', array('domain' => 'de.systopia.xcm')),
      'max'  => ts('the newest contact', array('domain' => 'de.systopia.xcm')),
      'none' => ts('none (create new contact)', array('domain' => 'de.systopia.xcm')),
      );
  }

  /**
   * Get the list of options for diff handlers
   */
  protected function getDiffHandlers() {
    $diff_handlers = array();
    $diff_handlers['none'] = ts("Don't do anything", array('domain' => 'de.systopia.xcm'));
    $diff_handlers['diff'] = ts("Diff Activity", array('domain' => 'de.systopia.xcm'));

    if (function_exists('i3val_civicrm_install')) {
      $diff_handlers['i3val'] = ts("I3Val Handler", array('domain' => 'de.systopia.xcm'));
    }

    return $diff_handlers;
  }

  /**
   * get a list of custom fields eligible for submission.
   * those are all custom fields that belong to a contact in general
   */
  protected function getCustomFields() {
    $custom_fields = array();

    $custom_group_query = civicrm_api3('CustomGroup', 'get', array(
      'extends'      => array('IN' => array('Contact', 'Individual', 'Organization', 'Household')),
      'is_active'    => 1,
      'option.limit' => 9999,
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
        'option.limit'     => 9999,
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
  protected function getContactFields() {
    return array(
      'display_name'                   => ts("Display Name"),
      'household_name'                 => ts("Household Name"),
      'organization_name'              => ts("Organization Name"),
      'first_name'                     => ts("First Name"),
      'last_name'                      => ts("Last Name"),
      'middle_name'                    => ts("Middle Name"),
      'nick_name'                      => ts("Nick Name"),
      'legal_name'                     => ts("Legal Name"),
      'prefix_id'                      => ts("Prefix"),
      'suffix_id'                      => ts("Suffix"),
      'birth_date'                     => ts("Birth Date"),
      'gender_id'                      => ts("Gender"),
      'formal_title'                   => ts("Formal Title"),
      'job_title'                      => ts("Job Title"),
      'do_not_email'                   => ts("Do not Email"),
      'do_not_phone'                   => ts("Do not Phone"),
      'do_not_sms'                     => ts("Do not SMS"),
      'do_not_trade'                   => ts("Do not Trade"),
      'is_opt_out'                     => ts("Opt-Out"),
      'preferred_language'             => ts("Preferred Language"),
      'preferred_communication_method' => ts("Preferred Communication Method"),
      'legal_identifier'               => ts("Legal Identifier"),
      'external_identifier'            => ts("External Identifier"),
    );
  }
}
