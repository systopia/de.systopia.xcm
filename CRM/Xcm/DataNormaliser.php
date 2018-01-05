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
 * This class will deal with resolving id <-> label,
 *  e.g. prefix_id vs. prefix
 */
class CRM_Xcm_DataNormaliser {

  // cached lookups
  protected static $_value2id = array();
  protected static $_id2value = array();

  /**
   * Will 'normalise' field names, e.g.
   *  'prefix' => 'prefix_id'
   */
  public static function normaliseFieldnames(&$data, $strip_wrong_fields = TRUE) {
    $field_map = self::getFieldnameMap();
    foreach ($field_map as $wrong_field_name => $right_field_name) {
      if (isset($data[$wrong_field_name])) {
        // copy to the right field (if that's not set)
        if (empty($data[$right_field_name])) {
          $data[$right_field_name] = $data[$wrong_field_name];
        }

        // remove wrong field if requested
        if ($strip_wrong_fields) {
          unset($data[$wrong_field_name]);
        }
      }
    }
  }

  /**
   * Will 'normalise' data, e.g. the birth_date
   */
  public static function normaliseData(&$data) {
    // strip whitespaces
    foreach ($data as $key => $value) {
      if (is_string($value)) {
        $data[$key] = trim($value);
      }
    }

    // format birth_date
    if (!empty($data['birth_date'])) {
      $data['birth_date'] = date('Y-m-d', strtotime($data['birth_date']));
    }
  }

  /**
   * Will make sure the that all fields with
   *  labels will be resolved to the respective IDs
   */
  public static function resolveData(&$data) {
    $fieldtypes = self::getFieldTypes();
    foreach ($fieldtypes as $fieldname => $fieldtype) {
      if (!empty($data[$fieldname])) {
        if (!is_numeric($data[$fieldname])) {
          // this needs to be resolved
          $data[$fieldname] = self::lookupID($fieldname, $data[$fieldname], $fieldtype);
        }
      }
    }
  }

  /**
   * Label data fields
   */
  public static function labelData(&$data, $strip_ids = TRUE) {
    // TODO
  }



  /**
   * a list of 'wrong' field names
   *  used by the normaliseFieldnames function above
   */
  private static function getFieldnameMap() {
    return array(
      'prefix'               => 'prefix_id',
      'individual_prefix'    => 'prefix_id',
      'individual_prefix_id' => 'prefix_id',
      'suffix'               => 'suffix_id',
      'individual_suffix'    => 'suffix_id',
      'individual_suffix_id' => 'suffix_id',
      'country'              => 'country_id',
      'gender'               => 'gender_id',
      'location_type'        => 'location_type_id',
      'phone_type'           => 'phone_type_id',
    );
  }


  /**
   * Look up the ID for the given value in the give field
   * The result will be cached
   */
  public static function lookupID($fieldname, $value, $fieldtype) {
    // check if the lookup has been cached
    if (isset(self::$_value2id[$fieldname][$value])) {
      $lookup_result = self::$_value2id[$fieldname][$value];
      if ($lookup_result == 'NOT_FOUND') {
        return NULL;
      } else {
        return $lookup_result;
      }
    }

    // ok, do the lookup
    $lookup_result = NULL;
    $query         = NULL;
    $result_field  = 'id';

    switch ($fieldtype['type']) {
      case 'option_value':
        $query = civicrm_api3('OptionValue', 'get', array(
          'option_group_id' => $fieldtype['option_group'],
          'label'           => $value,
          'option.limit'    => 1,
          'return'          => 'value,label',
          'sequential'      => 1));
        $result_field = 'value';
        break;

      case 'country':
        # resolve option value
        if (strlen($value) == 2) {
          $query = civicrm_api3('Country', 'get', array(
            'option.limit'    => 1,
            'iso_code'        => strtoupper($value),
            'return'          => 'iso_code,id,name'));
        } else {
          $query = civicrm_api3('Country', 'get', array(
            'option.limit'    => 1,
            'name'            => $value,
            'return'          => 'iso_code,id,name'));
        }
        break;

      case 'location_type':
        $query = civicrm_api3('LocationType', 'get', array(
          'name'            => $value,
          'option.limit'    => 1,
          'return'          => 'name,id'));
        break;

      default:
        # unknown type
        return $value;
    }

    if ($query['count'] > 0) {
      $result = reset($query['values']);
      $lookup_result = $result[$result_field];

      // cache result
      self::$_value2id[$fieldname][$value] = $lookup_result;
      self::$_id2value[$fieldname][$lookup_result] = $value;
    } else {
      self::$_value2id[$fieldname][$value] = 'NOT_FOUND';
    }

    return $lookup_result;
  }

  /**
   * Get resolvable field types
   */
  protected static function getFieldTypes() {
    return array(
      'prefix_id'        => array('type' => 'option_value', 'option_group' => 'individual_prefix'),
      'suffix_id'        => array('type' => 'option_value', 'option_group' => 'individual_suffix'),
      'gender_id'        => array('type' => 'option_value', 'option_group' => 'gender'),
      'location_type_id' => array('type' => 'location_type'),
      'country_id'       => array('type' => 'country'),
    );
  }
}
