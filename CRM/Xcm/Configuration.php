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

use CRM_Xcm_ExtensionUtil as E;

/*
 * Configuration wrapper
 */
class CRM_Xcm_Configuration {

  /** stores the whole settings blob */
  protected static $_all_profiles = NULL;

  /** stores the whole settings blob */
  protected $profile_name = NULL;

  /**
   * Get the configuration object with the given name
   *  If name is omitted, returns the default configuration
   *
   * @param null $profile_name
   * @return CRM_Xcm_Configuration
   *
   * @throws Exception
   */
  public static function getConfigProfile($profile_name = NULL) {
    return new CRM_Xcm_Configuration($profile_name);
  }

  /**
   * Get a simple list of all current profiles
   * @return array profile_name => profile_label
   */
  public static function getProfileList() {
    $profile_list = array();
    $all_profiles = self::getAllProfiles();
    foreach ($all_profiles as $profile_name => $profile_data) {
      $profile = CRM_Xcm_Configuration::getConfigProfile($profile_name);
      $profile_list[$profile_name] = $profile->getLabel();
    }
    return $profile_list;
  }

  /**
   * Constructor. Every object merely consists of the name of
   * the profile, the whole data blob is stored in a shared,
   * static way.
   *
   * CRM_Xcm_Configuration constructor.
   * @param null $profile_name
   *
   * @throws Exception
   */
  protected function __construct($profile_name = NULL) {
    $all_profiles = &self::getAllProfiles();

    // find the profile
    if (empty($profile_name)) {
      // empty parameter means 'default'
      foreach ($all_profiles as $name => $profile) {
        if (!empty($profile['is_default'])) {
          $profile_name = $name;
        }
      }

      // if no profile found, take the first one
      if (empty($profile_name)) {
        $all_profile_name = array_keys($all_profiles);
        $profile_name = reset($all_profile_name);
        $this->setDefaultProfile($profile_name);
      }
    }
    $this->profile_name = $profile_name;
  }

  /**
   * Get the complete configuration data
   */
  protected function &getConfiguration() {
    $all_profiles = &self::getAllProfiles();
    if (isset($all_profiles[$this->profile_name])) {
      return $all_profiles[$this->profile_name];
    } else {
      throw new Exception("Profile '{$this->profile_name}' unknown!");
    }
  }

  /**
   * Get the whole config blob
   *
   * @return array profile_name => profile data
   */
  protected static function &getAllProfiles() {
    if (self::$_all_profiles === NULL) {
      self::$_all_profiles = Civi::settings()->get('xcm_config_profiles');
      if (!is_array(self::$_all_profiles) || empty(self::$_all_profiles)) {
        self::$_all_profiles = array('default' => array());
      }
    }
    return self::$_all_profiles;
  }

  /**
   * Writes the all config profiles to the DB
   */
  public static function storeAllProfiles() {
    if (is_array(self::$_all_profiles)) {
      Civi::settings()->set('xcm_config_profiles', self::$_all_profiles);
    }
  }

  /**
   * Flush the local profile cache.
   * Caution! This might lose current changes
   */
  public static function flushProfileCache() {
    self::$_all_profiles = null;
  }

  /**
   * Mark the default profile
   *
   * @param $default_profile_name string name of the profile to be set default
   * @throws Exception if the profile given doesn't exist
   */
  public static function setDefaultProfile($default_profile_name) {
    $all_profiles = &self::getAllProfiles();
    if (isset($all_profiles[$default_profile_name])) {
      foreach ($all_profiles as $profile_name => &$profile) {
        if ($profile_name == $default_profile_name) {
          $profile['is_default'] = 1;
        } else {
          $profile['is_default'] = 0;
        }
      }
    } else {
      throw new Exception("Profile '{$default_profile_name}' unknown!");
    }
    self::storeAllProfiles();
  }

  /**
   * Clone this profile
   *
   * @param $default_profile_name string name of the profile to be set default
   * @throws Exception if the profile given doesn't exist
   */
  public function cloneProfile($new_profile_name) {
    $all_profiles = &self::getAllProfiles();
    if (isset($all_profiles[$new_profile_name])) {
      throw new Exception("Profile '{$new_profile_name}' already exists!");
    }

    // simply store a copy of the data
    $new_configuration = (array) $this->getConfiguration();
    $new_configuration['is_default'] = 0;
    $all_profiles[$new_profile_name] = $new_configuration;
  }

  /**
   * Delete this profile.
   * Warning: do not use this object after deletion
   */
  public function deleteProfile() {
    $all_profiles = &self::getAllProfiles();
    unset($all_profiles[$this->profile_name]);
  }

  /**
   * Get one of the main setting groups: options, rules, postprocessors
   *
   * @param $setting_name string name of the setting group
   *
   * @throws Exception
   *
   * @return array config group
   */
  protected function getConfigGroup($setting_name) {
    $config = $this->getConfiguration();
    return CRM_Utils_Array::value($setting_name, $config, array());
  }

  /**
   * Set one of the main setting groups: options, rules, postprocessors
   *
   * @param $setting_name string name of the setting group
   * @param $settings     array  group data
   *
   * @throws Exception
   */
  protected function setConfigGroup($setting_name, $settings) {
    $config = &$this->getConfiguration();
    if (is_array($settings)) {
      $config[$setting_name] = $settings;
    } else {
      throw new Exception("ConfigGroup has to be an array.");
    }
  }

  /**
   * See if this is the default profile
   *
   * @return int 1 if is default
   * @throws Exception
   */
  public function isDefault() {
    $profile_data = $this->getConfiguration();
    return CRM_Utils_Array::value('is_default', $profile_data, '0');return $value;
  }


  /**
   * Get the label/name of this profile
   *
   * @return string label
   * @throws Exception
   */
  public function getLabel() {
    $profile_data = $this->getConfiguration();
    if (!empty($profile_data['label'])) {
      return $profile_data['label'];
    } else {
      return E::ts("Profile '%1'", [1 => $this->profile_name]);
    }
  }

  /**
   * Set this config profile's label
   *
   * @param $label string the new label
   * @throws Exception
   */
  public function setLabel($label) {
    $profile_data = &$this->getConfiguration();
    $profile_data['label'] = $label;
  }

  /**
   * Get the options
   *
   * @return array options
   * @throws Exception
   */
  public function getOptions() {
    return $this->getConfigGroup('options');
  }

  /**
   * Set the options
   *
   * @param $data array the settings/config data
   * @throws Exception
   */
  public function setOptions($data) {
    $this->setConfigGroup('options', $data);
  }

  /**
   * Get the rules
   *
   * @return array data
   * @throws Exception
   */
  public function getRules() {
    return $this->getConfigGroup('rules');
  }

  /**
   * Set the rules
   *
   * @param $data array the settings/config data
   * @throws Exception
   */
  public function setRules($data) {
    $this->setConfigGroup('rules', $data);
  }

  /**
   * Get the postprocessing options
   *
   * @return array data
   * @throws Exception
   */
  public function getPostprocessing() {
    return $this->getConfigGroup('postprocessing');
  }

  /**
   * Set the postprocessing options
   *
   * @param $data array the settings/config data
   * @throws Exception
   */
  public function setPostprocessing($data) {
    $this->setConfigGroup('postprocessing', $data);
  }


  /**
   * Save all changes in the configuration to the DB
   */
  public function store() {
    self::storeAllProfiles();
  }


  /**
   * Get created activity status
   */
  public function defaultActivityStatus() {
    return CRM_Core_PseudoConstant::getKey(
      'CRM_Activity_BAO_Activity',
      'activity_status_id',
      'Scheduled'
    );
  }

  /**
   * Get the activity handler type
   *
   * @return string 'i3val' (see be.aivl.i3val), 'diff' (simple activity) or 'none'
   * @throws Exception
   */
  public function diffHandler() {
    $options = $this->getOptions();
    $handler = CRM_Utils_Array::value('diff_handler', $options);
    if ($handler == 'i3val' && function_exists('i3val_civicrm_install')) {
      return 'i3val';
    } elseif ($handler == 'diff') {
      return 'diff';
    } else {
      return 'none';
    }
  }

  /**
   * Get default location type
   */
  public function defaultLocationType() {
    $options = $this->getOptions();
    return (int) CRM_Utils_Array::value('default_location_type', $options);
  }

  /**
   * Get primary phone type
   */
  public function primaryPhoneType() {
    $options = $this->getOptions();
    return (int) CRM_Utils_Array::value('primary_phone_type', $options);
  }

  /**
   * Get secondary phone type
   */
  public function secondaryPhoneType() {
    $options = $this->getOptions();
    return (int) CRM_Utils_Array::value('secondary_phone_type', $options);
  }


  /**
   * Get location type to be used for new addresses
   */
  public function currentLocationType() {
    $options = $this->getOptions();
    return (int) CRM_Utils_Array::value('diff_current_location_type', $options);
  }

  /**
   * Get location type to be used for addresses that are being
   * replaced by new ones
   */
  public function oldLocationType() {
    $options = $this->getOptions();
    return (int) CRM_Utils_Array::value('diff_old_location_type', $options);
  }

  /**
   * Get generic (landline) phone type
   */
  public function phoneType() {
    $options = $this->getOptions();
    if (!empty($options['diff_phone_type'])) {
      return (int) $options['diff_phone_type'];
    } else {
      return CRM_Core_PseudoConstant::getKey(
        'CRM_Core_BAO_Phone',
        'phone_type_id',
        'Phone'
      );
    }
  }

  /**
   * Get mobile phone type
   */
  public function mobileType() {
    $options = $this->getOptions();
    if (!empty($options['diff_mobile_type'])) {
      return (int) $options['diff_mobile_type'];
    } else {
      return CRM_Core_PseudoConstant::getKey(
        'CRM_Core_BAO_Phone',
        'phone_type_id',
        'Mobile'
      );
    }
  }

  /**
   * determine the current user ID
   * @see https://github.com/CiviCooP/org.civicoop.apiuidfix
   */
  public function getCurrentUserID($fallback_id = 2) {
    // try the session first
    $session = CRM_Core_Session::singleton();
    $userId = $session->get('userID');
    if (!empty($userId)) {
      return $userId;
    }

    // check via API key, i.e. when coming through REST-API
    $null = null;
    $api_key = CRM_Utils_Request::retrieve('api_key', 'String', $null, FALSE, NULL, 'REQUEST');
    if (!$api_key || strtolower($api_key) == 'null') {
      return $fallback_id; // nothing we can do
    }

    // load user via API KEU
    $valid_user = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $api_key, 'id', 'api_key');

    // If we didn't find a valid user, die
    if (!empty($valid_user)) {
      //now set the UID into the session
      return $valid_user;
    }

    return $fallback_id;
  }


  /*******************************************************
   **               DIFF HELPER                         **
   ******************************************************/

  /**
   * Identify and return the profile that feels responsible
   * for this diff activity
   *
   * @param $activity_type_id
   * @param $status_id
   *
   * @return CRM_Xcm_Configuration|null
   * @throws Exception
   */
  protected static function getProfileForDiffActivityHelper($activity_type_id, $status_id) {
    $all_profiles = self::getProfileList();
    // see if there is a matching config
    foreach ($all_profiles as $profile_name => $profile_label) {
      $config = CRM_Xcm_Configuration::getConfigProfile($profile_name);

      $diff_enabled     = $config->diffProcessing();
      $diff_activity_id = $config->diffActivity();
      $diff_status_id   = $config->defaultActivityStatus();

      if (   $diff_enabled
          && $diff_activity_id == $activity_type_id
          && $diff_status_id   == $status_id) {
        // yes, this configuration matches!
        return $config;
      }
    }

    // if we get here, none of the configurations matched.
    return NULL;
  }

  /**
   *
   * @param $form
   * @param $activity_type_id
   * @param $status_id
   * @throws Exception
   */
  public static function injectDiffHelper(&$form, $activity_type_id, $status_id) {
    try {
      $profile = self::getProfileForDiffActivityHelper($activity_type_id, $status_id);
      if (!$profile) return;

      // WARN if contact is tagged with certain tags
      $warnOnTags = CRM_Xcm_Configuration::diffProcess_warnOnTags();
      if (!empty($warnOnTags)) {
        $contact_id = $form->getVar('_currentlyViewedContactId');
        if ($contact_id) {
          $tags = CRM_Core_BAO_EntityTag::getContactTags($contact_id);
          foreach ($warnOnTags as $tagName) {
            if (in_array($tagName, $tags)) {
              CRM_Core_Session::setStatus(
                  E::ts("Warning! This contact is tagged '%1'.", array(1=>$tagName)),
                  E::ts("Warning"), 'warning');
            }
          }
        } else {
          CRM_Core_Session::setStatus(
              E::ts("Warning! The tags couldn't be read."),
              E::ts("Warning"), 'error');
        }
      }

      // build constants array for JS
      $constants['targetActivityId']              = $form->getVar('_activityId');
      $constants['location_type_current_address'] = $profile->currentLocationType();
      $constants['location_type_old_address']     = $profile->oldLocationType();
      $constants['phone_type_phone_value']        = $profile->phoneType();
      $constants['phone_type_mobile_value']       = $profile->mobileType();

      // add prefix_ids
      $constants['prefix_ids']   = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'prefix_id');
      $constants['prefix_names'] = array_flip($constants['prefix_ids']);

      // add gender_ids
      $constants['gender_ids']   = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'gender_id');
      $constants['gender_names'] = array_flip($constants['gender_ids']);

      // add countries
      $constants['country_ids']   = CRM_Core_PseudoConstant::country(FALSE, FALSE);
      $constants['country_names'] = array_flip($constants['country_ids']);

      CRM_Core_Resources::singleton()->addVars('de.systopia.xcm', $constants);

      CRM_Core_Region::instance('form-body')->add(array(
          'script' => file_get_contents(__DIR__ . '/../../js/process_diff.js')
      ));
    } catch (Exception $ex) {
      CRM_Core_Error::debug_log_message("DiffHelper injection failed: " . $ex->getMessage());
    }
  }

  /**
   * See if the enhances (JS) diff processing is enabled
   */
  public function diffProcessing() {
    $options = $this->getOptions();
    return (int) CRM_Utils_Array::value('diff_processing', $options);
  }

  /**
   * returns a list of tag names to warn on if processing diffs
   *
   * @return array
   */
  public static function diffProcess_warnOnTags() {
    return array();
  }

  /**
   * Get the activity type ID used for the diff activity
   * If NULL|0|'' the generation is not enabled
   */
  public function diffActivity() {
    $options = $this->getOptions();
    return (int) CRM_Utils_Array::value('diff_activity', $options);
  }
}
