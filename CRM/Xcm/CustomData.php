<?php
/*-------------------------------------------------------+
| SYSTOPIA CUSTOM DATA HELPER                            |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
| Source: https://github.com/systopia/Custom-Data-Helper |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

define('CUSTOM_DATA_HELPER_VERSION', '0.3.1.dev');
define('CUSTOM_DATA_HELPER_LOG_LEVEL', 3);

// log levels
define('CUSTOM_DATA_HELPER_LOG_DEBUG', 1);
define('CUSTOM_DATA_HELPER_LOG_INFO',  3);
define('CUSTOM_DATA_HELPER_LOG_ERROR', 5);

class CRM_Xcm_CustomData {

  /** caches custom field data, indexed by group name */
  protected static $custom_group_cache = array();

  protected $ts_domain = NULL;
  protected $version   = CUSTOM_DATA_HELPER_VERSION;

  public function __construct($ts_domain) {
   $this->ts_domain = $ts_domain;
  }

  /**
   * Log a message if the log level is high enough
   */
  protected function log($level, $message) {
    if ($level >= CUSTOM_DATA_HELPER_LOG_LEVEL) {
      CRM_Core_Error::debug_log_message("CustomDataHelper {$this->version} ({$this->ts_domain}): {$message}");
    }
  }

  /**
  * will take a JSON source file and synchronise the
  * generic entity data with those specs
  */
  public function syncEntities($source_file) {
    $data = json_decode(file_get_contents($source_file), TRUE);
    if (empty($data)) {
       throw new Exception("syncOptionGroup::syncOptionGroup: Invalid specs");
    }

    foreach ($data['_entities'] as $entity_data) {
       $this->translateStrings($entity_data);
       $entity = $this->identifyEntity($data['entity'], $entity_data);

       if (empty($entity)) {
          // create OptionValue
          $entity = $this->createEntity($data['entity'], $entity_data);
       } elseif ($entity == 'FAILED') {
          // Couldn't identify:
          $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update {$entity_type}: " . json_encode($entity_data));
       } else {
          // update OptionValue
          $this->updateEntity($data['entity'], $entity_data, $entity);
       }
    }
  }

  /**
  * will take a JSON source file and synchronise the
  * OptionGroup/OptionValue data in the system with
  * those specs
  */
  public function syncOptionGroup($source_file) {
    $data = json_decode(file_get_contents($source_file), TRUE);
    if (empty($data)) {
       throw new Exception("syncOptionGroup::syncOptionGroup: Invalid specs");
    }

    // first: find or create option group
    $this->translateStrings($data);
    $optionGroup = $this->identifyEntity('OptionGroup', $data);
    if (empty($optionGroup)) {
       // create OptionGroup
       $optionGroup = $this->createEntity('OptionGroup', $data);
    } elseif ($optionGroup == 'FAILED') {
       // Couldn't identify:
       $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update OptionGroup: " . json_encode($data));
       return;
    } else {
       // update OptionGroup
       $this->updateEntity('OptionGroup', $data, $optionGroup);
    }

    // now run the update for the OptionValues
    foreach ($data['_values'] as $optionValueSpec) {
       $this->translateStrings($optionValueSpec);
       $optionValueSpec['option_group_id'] = $optionGroup['id'];
       $optionValueSpec['_lookup'][] = 'option_group_id';
       $optionValue = $this->identifyEntity('OptionValue', $optionValueSpec);

       if (empty($optionValue)) {
          // create OptionValue
          $optionValue = $this->createEntity('OptionValue', $optionValueSpec);
       } elseif ($optionValue == 'FAILED') {
          // Couldn't identify:
          $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update OptionValue: " . json_encode($optionValueSpec));
       } else {
          // update OptionValue
          $this->updateEntity('OptionValue', $optionValueSpec, $optionValue);
       }
    }
  }


  /**
  * will take a JSON source file and synchronise the
  * CustomGroup/CustomField data in the system with
  * those specs
  */
  public function syncCustomGroup($source_file) {
    $data = json_decode(file_get_contents($source_file), TRUE);
    if (empty($data)) {
       throw new Exception("CRM_Utils_CustomData::syncCustomGroup: Invalid custom specs");
    }

    // first: find or create custom group
    $this->translateStrings($data);
    $customGroup = $this->identifyEntity('CustomGroup', $data);
    if (empty($customGroup)) {
       // create CustomGroup
       $customGroup = $this->createEntity('CustomGroup', $data);
    } elseif ($customGroup == 'FAILED') {
       // Couldn't identify:
       $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update CustomGroup: " . json_encode($data));
       return;
    } else {
       // update CustomGroup
       $this->updateEntity('CustomGroup', $data, $customGroup, array('extends'));
    }

    // now run the update for the CustomFields
    foreach ($data['_fields'] as $customFieldSpec) {
       $this->translateStrings($customFieldSpec);
       $customFieldSpec['custom_group_id'] = $customGroup['id'];
       $customFieldSpec['_lookup'][] = 'custom_group_id';
       if (!empty($customFieldSpec['option_group_id']) && !is_numeric($customFieldSpec['option_group_id'])) {
          // look up custom group id
          $optionGroup = $this->getEntityID('OptionGroup', array('name' => $customFieldSpec['option_group_id']));
          if ($optionGroup == 'FAILED' || $optionGroup==NULL) {
            $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update CustomField, bad option_group: {$customFieldSpec['option_group_id']}");
            return;
          }
          $customFieldSpec['option_group_id'] = $optionGroup['id'];
       }

       $customField = $this->identifyEntity('CustomField', $customFieldSpec);
       if (empty($customField)) {
          // create CustomField
          $customField = $this->createEntity('CustomField', $customFieldSpec);
       } elseif ($customField == 'FAILED') {
          // Couldn't identify:
          $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update CustomField: " . json_encode($customFieldSpec));
       } else {
          // update CustomField
          $this->updateEntity('CustomField', $customFieldSpec, $customField, array('in_selector', 'is_view', 'is_searchable'));
       }
    }
  }

  /**
  * return the ID of the given entity (if exists)
  */
  protected function getEntityID($entity_type, $selector) {
    if (empty($selector)) return NULL;
    $selector['sequential'] = 1;
    $selector['options'] = array('limit' => 2);

    $lookup_result = civicrm_api3($entity_type, 'get', $selector);
    switch ($lookup_result['count']) {
       case 1:
          // found
          return $lookup_result['values'][0];
       default:
          // more than one found
          $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Bad {$entity_type} lookup selector: " . json_encode($selector));
          return 'FAILED';
       case 0:
          // not found
          return NULL;
    }
  }

  /**
  * see if a given entity does already exist in the system
  * the $data blob should have a '_lookup' parameter listing the
  * lookup attributes
  */
  protected function identifyEntity($entity_type, $data) {
    $lookup_query = array(
       'sequential' => 1,
       'options'    => array('limit' => 2));

    foreach ($data['_lookup'] as $lookup_key) {
       $lookup_query[$lookup_key] = $data[$lookup_key];
    }

    $this->log(CUSTOM_DATA_HELPER_LOG_DEBUG, "LOOKUP {$entity_type}: " . json_encode($lookup_query));
    $lookup_result = civicrm_api3($entity_type, 'get', $lookup_query);
    switch ($lookup_result['count']) {
       case 0:
          // not found
          return NULL;

       case 1:
          // found
          return $lookup_result['values'][0];

       default:
          // bad lookup selector
          $this->log(CUSTOM_DATA_HELPER_LOG_ERROR, "Bad {$entity_type} lookup selector: " . json_encode($selector));
          return 'FAILED';
    }
  }

  /**
  * create a new entity
  */
  protected function createEntity($entity_type, $data) {
    // first: strip fields starting with '_'
    foreach (array_keys($data) as $field) {
       if (substr($field, 0, 1) == '_') {
          unset($data[$field]);
       }
    }

    // then run query
    CRM_Core_Error::debug_log_message("CustomDataHelper ({$this->ts_domain}): CREATE {$entity_type}: " . json_encode($data));
    return civicrm_api3($entity_type, 'create', $data);
  }

  /**
  * create a new entity
  */
  protected function updateEntity($entity_type, $requested_data, $current_data, $required_fields = array()) {
    $update_query = array();

    // first: identify fields that need to be updated
    foreach ($requested_data as $field => $value) {
       // fields starting with '_' are ignored
       if (substr($field, 0, 1) == '_') {
          continue;
       }

       if (isset($current_data[$field]) && $value != $current_data[$field]) {
          $update_query[$field] = $value;
       }
    }

    // run update if required
    if (!empty($update_query)) {
       $update_query['id'] = $current_data['id'];

       // add required fields
       foreach ($required_fields as $required_field) {
          if (isset($requested_data[$required_field])) {
            $update_query[$required_field] = $requested_data[$required_field];
          } else {
            $update_query[$required_field] = $current_data[$required_field];
          }
       }

       $this->log(CUSTOM_DATA_HELPER_LOG_INFO, "UPDATE {$entity_type}: " . json_encode($update_query));
       return civicrm_api3($entity_type, 'create', $update_query);
    } else {
       return NULL;
    }
  }

  /**
  * translate all fields that are listed in the _translate list
  */
  protected function translateStrings(&$data) {
    if (empty($data['_translate'])) return;
    foreach ($data['_translate'] as $translate_key) {
       $value = $data[$translate_key];
       if (is_string($value)) {
          $data[$translate_key] = ts($value, array('domain' => $this->ts_domain));
       }
    }
  }


  /**
   * internal function to replace "<custom_group_name>.<custom_field_name>"
   * in the data array with the custom_XX notation.
   *
   * @param $data          array  key=>value data, keys will be changed
   * @param $customgroups  array  if given, restrict to those groups
   *
   */
  public static function resolveCustomFields(&$data, $customgroups = NULL) {
    // first: find out which ones to cache
    $customgroups_used = array();
    foreach ($data as $key => $value) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match)) {
        if (empty($customgroups) || in_array($match['group_name'], $customgroups)) {
          $customgroups_used[$match['group_name']] = 1;
        }
      }
    }

    // cache the groups used
    self::cacheCustomGroups(array_keys($customgroups_used));

    // now: replace stuff
    foreach (array_keys($data) as $key) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match)) {
        if (empty($customgroups) || in_array($match['group_name'], $customgroups)) {
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
