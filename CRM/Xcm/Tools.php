<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2018 SYSTOPIA                            |
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
 * Some basic tools for addresses and custom fields
 */
class CRM_Xcm_Tools {

  /** caches custom field data, indexed by group name */
  protected static $custom_group_cache = array();

  /*********************************************************
   **                 Address  Logic                      **
   *********************************************************

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
   * return all contact detail fields
   */
  public static function getDetailFields() {
    return ['email', 'url', 'phone'];
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
   * extract and return only the address data
   */
  public static function extractAddressData($data, $copy_location_type = TRUE) {
    $fields = self::getAddressFields();
    $address_data = array();
    foreach ($fields as $field_name) {
      if (isset($data[$field_name])) {
        $address_data[$field_name] = $data[$field_name];
      }
    }

    if ($copy_location_type && isset($data['location_type_id'])) {
      $address_data['location_type_id'] = $data['location_type_id'];
    }
    return $address_data;
  }

  /**
   * extract and return everything but the address data
   */
  public static function stripAddressAndDetailData($data) {
    $fields = array_merge(self::getAddressFields(), self::getDetailFields());
    $remaining_data = array();
    foreach ($data as $field_name => $value) {
      if (!in_array($field_name, $fields)) {
        $remaining_data[$field_name] = $value;
      }
    }
    return $remaining_data;
  }


  /*********************************************************
   **               Custom Field Logic                    **
   *********************************************************

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