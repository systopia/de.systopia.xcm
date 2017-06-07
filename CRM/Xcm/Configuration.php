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
   * Get the activity type ID used for the diff activity
   * If NULL|0|'' the generation is not enabled
   */
  public static function diffActivity() {
    $options = self::getOptions();
    return (int) CRM_Utils_Array::value('diff_activity', $options);
  }

  /**
   * See if the enhances (JS) diff processing is enabled
   */
  public static function diffProcessing() {
    $options = self::getOptions();
    return (int) CRM_Utils_Array::value('diff_processing', $options);
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
}