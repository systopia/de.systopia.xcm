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
 * Configuration wrapper
 */
class CRM_Xcm_Configuration {


  /**
   * returns a list of tag names to warn on if processing diffs
   *
   * @return array
   */
  public static function getOptions() {
    return CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'xcm_options');
  }

  /**
   * Get created activity status
   */
  public static function defaultActivityStatus() {
    return (int) CRM_Core_OptionGroup::getValue('activity_status', 'Scheduled', 'name');
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
   * return all address fields
   */
  public static function getAddressFields() {
    return array(
      'supplemental_address_1', 'supplemental_address_2', 'supplemental_address_3',
      'street_address', 'city', 'country_id', 'state_province_id', 'postal_code',
      'is_billing', 'geo_code_1', 'geo_code_2');
  }

  /**
   * Generate a list of field labels for the given diff
   */
  public static function getFieldLabels($differing_attributes) {
    $field_labels = array();
    $all_labels = self::getKnownFieldLabels();
    foreach ($differing_attributes as $field_name) {
      if (isset($all_labels[$field_name])) {
        $field_labels[$field_name] = $all_labels[$field_name];
      } else {
        $field_labels[$field_name] = $field_name;
      }
    }
    return $field_labels;
  }

  /**
   * return all field labels
   */
  public static function getKnownFieldLabels() {
    return array(
      'first_name'             => ts('First Name'),
      'last_name'              => ts('Last Name'),
      'middle_name'            => ts('Middle Name'),
      'prefix_id'              => ts('Prefix'),
      'suffix_id'              => ts('Suffix'),
      'phone'                  => ts('Phone'),
      'email'                  => ts('Email'),
      'street_address'         => ts('Street Address'),
      'city'                   => ts('City'),
      'country_id'             => ts('Country'),
      'state_province_id'      => ts('State/Province'),
      'postal_code'            => ts('Postal Code'),
      'supplemental_address_1' => ts('Supplemental Address 1'),
      'supplemental_address_2' => ts('Supplemental Address 2'),
      'supplemental_address_3' => ts('Supplemental Address 3')
    );
  }

  /**
   * resolve some identifiers in the values
   */
  public static function resolveFieldValues(&$data) {
    // country
    if (isset($data['country_id']) && is_numeric($data['country_id'])) {
      $countries = CRM_Core_PseudoConstant::country(FALSE, FALSE);
      if (isset($countries[$data['country_id']])) {
        $data['country_id'] = $countries[$data['country_id']];
      }
    }

    // prefix
    if (isset($data['prefix_id']) && is_numeric($data['prefix_id'])) {
      try {
        $data['prefix_id'] = civicrm_api3('OptionValue', 'getvalue', array(
          'return'          => 'label',
          'value'           => $data['prefix_id'],
          'option_group_id' => 'individual_prefix'
        ));
      } catch (Exception $e) {
        // if not found, there's not much we can do
      }
    }

    // suffix
    if (isset($data['suffix_id']) && is_numeric($data['suffix_id'])) {
      try {
        $data['suffix_id'] = civicrm_api3('OptionValue', 'getvalue', array(
          'return'          => 'label',
          'value'           => $data['suffix_id'],
          'option_group_id' => 'individual_suffix'
        ));
      } catch (Exception $e) {
        // if not found, there's not much we can do
      }
    }
  }

  /**
   * extract and return only the address data
   */
  public static function extractAddressData($data) {
    $fields = self::getAddressFields();
    $address_data = array();
    foreach ($fields as $field_name) {
      if (isset($data[$field_name])) {
        $address_data[$field_name] = $data[$field_name];
      }
    }
    return $address_data;
  }

  /**
   * extract and return everything but the address data
   */
  public static function stripAddressData($data) {
    $fields = self::getAddressFields();
    $remaining_data = array();
    foreach ($data as $field_name => $value) {
      if (!in_array($field_name, $fields)) {
        $remaining_data[$field_name] = $value;
      }
    }
    return $remaining_data;
  }

  /**
   * Get the activity type ID used for the diff activity
   * If NULL|0|'' the generation is not enabled
   */
  public static function diffActivity() {
    $options = self::getOptions();
    return (int) CRM_Utils_Array::value('diff_activity', $options);
  }

  /**
   * Get the activity handler type
   *
   * @return 'i3val' (see be.aivl.i3val), 'diff' (simple activity) or 'none'
   */
  public static function diffHandler() {
    $options = self::getOptions();
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
   * See if the enhances (JS) diff processing is enabled
   */
  public static function diffProcessing() {
    $options = self::getOptions();
    return (int) CRM_Utils_Array::value('diff_processing', $options);
  }

  /**
   * Get default location type
   */
  public static function defaultLocationType() {
    $options = self::getOptions();
    return (int) CRM_Utils_Array::value('default_location_type', $options);
  }


  /**
   * Get location type to be used for new addresses
   */
  public static function currentLocationType() {
    $options = self::getOptions();
    return (int) CRM_Utils_Array::value('diff_current_location_type', $options);
  }

  /**
   * Get location type to be used for addresses that are being
   * replaced by new ones
   */
  public static function oldLocationType() {
    $options = self::getOptions();
    return (int) CRM_Utils_Array::value('diff_old_location_type', $options);
  }

  /**
   * Get generic (landline) phone type
   */
  public static function phoneType() {
    $options = self::getOptions();
    if (!empty($options['diff_phone_type'])) {
      return (int) $options['diff_phone_type'];
    } else {
      return (int) CRM_Core_OptionGroup::getValue('phone_type', 'Phone', 'name');
    }
  }

  /**
   * Get mobile phone type
   */
  public static function mobileType() {
    $options = self::getOptions();
    if (!empty($options['diff_mobile_type'])) {
      return (int) $options['diff_mobile_type'];
    } else {
      return (int) CRM_Core_OptionGroup::getValue('phone_type', 'Mobile', 'name');
    }
  }

  /*********************************************************
   **               Custom Field Logic                    **
   *********************************************************

  /** caches custom field data, indexed by group name */
  protected static $custom_group_cache = array();

  /**
   * internal function to replace "<custom_group_name>.<custom_field_name>"
   * in the data array with the custom_XX notation.
   */
  public static function resolveCustomFields(&$data) {
    // first: find out which ones to cache
    $customgroups_used = array();
    foreach ($data as $key => $value) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match)) {
        $customgroups_used[$match['group_name']] = 1;
      }
    }

    // cache the groups used
    self::cacheCustomGroups(array_keys($customgroups_used));

    // now: replace stuff
    foreach (array_keys($data) as $key) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match)) {
        if (isset(self::$custom_group_cache[$match['group_name']][$match['field_name']])) {
          $custom_field = self::$custom_group_cache[$match['group_name']][$match['field_name']];
          $custom_key = 'custom_' . $custom_field['id'];
          $data[$custom_key] = $data[$key];
          unset($data[$key]);
        } else {
          // TODO: unknown data field $match['group_name'] . $match['field_name']
        }
      }
    }
  }

  /**
  * Get CustomField entity (cached)
  */
  public static function getCustomField($custom_group_name, $custom_field_name) {
    self::cacheCustomGroups(array($custom_group_name));

    if (isset(self::$custom_group_cache[$custom_group_name][$custom_field_name])) {
      return self::$custom_group_cache[$custom_group_name][$custom_field_name];
    } else {
      return NULL;
    }
  }

  /**
  * Get CustomField entity (cached)
  */
  public static function cacheCustomGroups($custom_group_names) {
    foreach ($custom_group_names as $custom_group_name) {
      if (!isset(self::$custom_group_cache[$custom_group_name])) {
        // set to empty array to indicate our intentions
        self::$custom_group_cache[$custom_group_name] = array();
        $fields = civicrm_api3('CustomField', 'get', array(
          'custom_group_id' => $custom_group_name,
          'option.limit'    => 0));
        foreach ($fields['values'] as $field) {
          self::$custom_group_cache[$custom_group_name][$field['name']] = $field;
        }
      }
    }
  }

  /**
   * determine the current user ID
   * @see https://github.com/CiviCooP/org.civicoop.apiuidfix
   */
  public static function getCurrentUserID($fallback_id = 2) {
    // try the session first
    $session = CRM_Core_Session::singleton();
    $userId = $session->get('userID');
    if (!empty($userId)) {
      return $userId;
    }

    // check via API key, i.e. when coming through REST-API
    $api_key = CRM_Utils_Request::retrieve('api_key', 'String', $store, FALSE, NULL, 'REQUEST');
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
}