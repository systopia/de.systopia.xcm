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
    // add general options
    $this->addElement('select',
                      'picker',
                      ts('Of multiple matches, pick:', array('domain' => 'de.systopia.xcm')),
                      $this->getPickers(),
                      array('class' => 'crm-select2'));

    // diff activity options
    $activites = $this->getActivities();
    $activites[0] = ts("Don't generate", array('domain' => 'de.systopia.xcm'));
    $this->addElement('select',
                      'diff_activity',
                      ts('Generate Diff Activity', array('domain' => 'de.systopia.xcm')),
                      $activites,
                      array('class' => 'crm-select2'));

    $this->addElement('text',
                      "diff_activity_subject",
                      ts('Subject', array('domain' => 'de.systopia.xcm')));

    $this->addElement('select',
                      'diff_processing',
                      ts('Diff Processing Helper', array('domain' => 'de.systopia.xcm')),
                      array(0 => ts('No', array('domain' => 'de.systopia.xcm')),
                            1 => ts('Yes (beta)',  array('domain' => 'de.systopia.xcm'))),
                      array('class' => 'crm-select2'));


    $locationTypes = $this->getLocationTypes();
    $this->addElement('select',
                      'diff_current_location_type',
                      ts('Location Type', array('domain' => 'de.systopia.xcm')),
                      $locationTypes,
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'diff_old_location_type',
                      ts('Bump existing address to', array('domain' => 'de.systopia.xcm')),
                      array('0' => ts("Don't do that", array('domain' => 'de.systopia.xcm'))) + $locationTypes,
                      array('class' => 'crm-select2'));

    $this->addElement('select',
                      'fill_fields',
                      ts('Fill Fields', array('domain' => 'de.systopia.xcm')),
                      $this->getContactFields() + $this->getCustomFields(),
                      array(// 'style'    => 'width:450px; height:100%;',
                            'multiple' => 'multiple',
                            'class'    => 'crm-select2'));


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
      'picker'                     => CRM_Utils_Array::value('picker', $values),
      'diff_activity'              => CRM_Utils_Array::value('diff_activity', $values),
      'diff_activity_subject'      => CRM_Utils_Array::value('diff_activity_subject', $values),
      'diff_processing'            => CRM_Utils_Array::value('diff_processing', $values),
      'diff_current_location_type' => CRM_Utils_Array::value('diff_current_location_type', $values),
      'diff_old_location_type'     => CRM_Utils_Array::value('diff_old_location_type', $values),
      'fill_fields'                => CRM_Utils_Array::value('fill_fields', $values),
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

  protected function getActivities() {
    $activity_list = array(0 => ts('None, thank you', array('domain' => 'de.systopia.xcm')));

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
   * get a list of custom fields eligible for submission.
   * those are all custom fields that belong to a contact in general
   */
  protected function getCustomFields() {
    $custom_fields = array();

    $custom_group_query = civicrm_api3('CustomGroup', 'get', array(
      'extends'      => 'Contact',
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
      'display_name'                   => ts("Display Name", array('domain' => 'de.systopia.xcm')),
      'household_name'                 => ts("Household Name", array('domain' => 'de.systopia.xcm')),
      'organization_name'              => ts("Organization Name", array('domain' => 'de.systopia.xcm')),
      'first_name'                     => ts("First Name", array('domain' => 'de.systopia.xcm')),
      'last_name'                      => ts("Last Name", array('domain' => 'de.systopia.xcm')),
      'middle_name'                    => ts("Middle Name", array('domain' => 'de.systopia.xcm')),
      'display_name'                   => ts("Display Name", array('domain' => 'de.systopia.xcm')),
      'nick_name'                      => ts("Nick Name", array('domain' => 'de.systopia.xcm')),
      'legal_name'                     => ts("Legal Name", array('domain' => 'de.systopia.xcm')),
      'prefix_id'                      => ts("Prefix", array('domain' => 'de.systopia.xcm')),
      'suffix_id'                      => ts("Suffix", array('domain' => 'de.systopia.xcm')),
      'birth_date'                     => ts("Birth Date", array('domain' => 'de.systopia.xcm')),
      'gender_id'                      => ts("Gender", array('domain' => 'de.systopia.xcm')),
      'formal_title'                   => ts("Formal Title", array('domain' => 'de.systopia.xcm')),
      'job_title'                      => ts("Job Title", array('domain' => 'de.systopia.xcm')),
      'do_not_email'                   => ts("Do not Email", array('domain' => 'de.systopia.xcm')),
      'do_not_phone'                   => ts("Do not Phone", array('domain' => 'de.systopia.xcm')),
      'do_not_sms'                     => ts("Do not SMS", array('domain' => 'de.systopia.xcm')),
      'do_not_trade'                   => ts("Do not Trade", array('domain' => 'de.systopia.xcm')),
      'is_opt_out'                     => ts("Opt-Out", array('domain' => 'de.systopia.xcm')),
      'preferred_language'             => ts("Preferred Language", array('domain' => 'de.systopia.xcm')),
      'preferred_communication_method' => ts("Preferred Communication Method", array('domain' => 'de.systopia.xcm')),
      'legal_identifier'               => ts("Legal Identifier", array('domain' => 'de.systopia.xcm')),
      'external_identifier'            => ts("External Identifier", array('domain' => 'de.systopia.xcm')),
    );
  }
}
