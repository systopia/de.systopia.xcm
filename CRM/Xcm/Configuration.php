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
}