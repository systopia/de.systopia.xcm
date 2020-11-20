<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2020 SYSTOPIA                            |
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
 * This class will provide data sanitation
 *
 *  based on https://github.com/greenpeace-cee/at.greenpeace.multibite by @pfigel
 */
class CRM_Xcm_DataSanitiser {

  const SANITISER_NONE                 = '';
  const SANITISER_UTF8MB4_STRIP        = 'utf8mb4';
  const SANITISER_UTF8MB4_QUESTIONMARK = 'utf8mb4_?';
  const SANITISER_UTF8MB4_QUESTIONCHAR = 'utf8mb4_c';

  const MULTIBYTE_REGEX = '/[\x{10000}-\x{10FFFF}]/u';
  const REPLACEMENT_CHARACTER = "\xEF\xBF\xBD";

  /**
   * Sanitise the given data
   * @param mixed $data
   *   the data to be sanitised in-place
   * @param array $options
   *   list of sanitation options
   * @param int $recursion
   *   recursion depth. default is 0
   */
  public static function sanitise(&$data, $options, $recursion = 1)
  {
    if (is_string($data)) {
      // sanitise strings
      self::sanitiseString($data, $options);

    } elseif (is_array($data)) {
      if ($recursion > 0) {
        // sanitise values of an array
        $recursion = $recursion - 1;
        foreach ($data as $key => &$value) {
          self::sanitise($value, $options, $recursion);
        }
      }

    // other data types don't need to be sanitised
    }
  }

  /**
   * Sanitise the given string
   * @param string $string
   *   the data to be sanitised
   * @param array $options
   *   list of sanitation options
   *
   * @return string
   *   sanitised version of the string
   */
  public static function sanitiseString(&$string, $options)
  {
    foreach ($options as $sanitation) {
      switch ($sanitation) {
        case self::SANITISER_UTF8MB4_STRIP:
          $string = preg_replace(self::MULTIBYTE_REGEX, '', $string);
          break;

        case self::SANITISER_UTF8MB4_QUESTIONMARK:
          $string = preg_replace(self::MULTIBYTE_REGEX, '?', $string);
          break;

        case self::SANITISER_UTF8MB4_QUESTIONCHAR:
          $string = preg_replace(self::MULTIBYTE_REGEX, self::REPLACEMENT_CHARACTER, $string);
          break;

        default:
          // don't do anything
      }
    }
  }

  /**
   * Get the list of sanitation options
   */
  public static function getDataSanitationOptions()
  {
    return [
        self::SANITISER_NONE    => E::ts("None"),
        self::SANITISER_UTF8MB4_STRIP        => E::ts("Strip UTF8 4 Byte Characters"),
        self::SANITISER_UTF8MB4_QUESTIONMARK => E::ts("Replace UTF8 4 Byte Characters with '?'"),
        self::SANITISER_UTF8MB4_QUESTIONCHAR => E::ts("Replace UTF8 4 Byte Characters with '�'"),
    ];
  }

  /**
   * Get the current configuration
   *
   * @param array $config
   *  profile options
   *
   * @return array of options
   */
  public static function getSetting($config) {
    $sanitiser_setting = CRM_Utils_Array::value('input_sanitation', $config, '');
    if (!is_array($sanitiser_setting)) {
      $sanitiser_setting = [$sanitiser_setting];
    }
    return $sanitiser_setting;
  }
}
