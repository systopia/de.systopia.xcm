<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2021 SYSTOPIA                            |
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

use CRM_XCM_ExtensionUtil as E;

/**
 * This analyser will use the XCM's facilities to get or create a contact based on the contact
 *   data in the transaction - typically
 */
class CRM_Banking_PluginImpl_Matcher_GetOrCreateContactAnalyser extends CRM_Banking_PluginModel_Analyser {

  const FIRST_NAME_CACHE_KEY = 'banking_xcm_analyser_db_first_name_list';
  const FIRST_NAME_CACHE_TTL = 60 * 60 * 24 * 7; // one week

  /**
   * Contact Get-Or-Create Analyser. Configuration options:
   *   'xcm_profile':
   *       the name of the xcm_profile to use. leave empty for the default profile
   *
   *   'name_mode'
   *       how should the first name and last name be separated from the 'name' field. options are:
   *       'first': first part of the name (separated by blanks) is the first name, the rest is last name (default)
   *       'last':  last part of the name (separated by blanks) is the last name, the rest is first name
   *       'off':   no name extraction is done, you would then have to use a mapping to get the fields used for your
     *                xc_ profile
   *       'db':    if the first part is already in the database as a first_name, use that as first name,
   *                  otherwise use same as 'last'
   *
   *   'contact_type':
   *       contact type to be passed to XCM. Default is 'Individual'. Can be overridden by the mapping
   *
   *   'mapping':
   *       array mapping (propagation) values to the values passed to the XCM
   *
   *   'output_field':
   *       field to which the resulting contact ID is written. Default is 'contact_id'
   *
   *   'ucwords_fields':
   *       array (or comma separated string) of fields, that should be normalised before being passed
   *       to the XCM as follows: all lower case with the first letter capitalised (strtolower + ucwords)
   *
   *   'first_name_blacklist':
   *       array of values that should be excluded from being a first name
   *
   *   'name_blacklist':
   *       array of values that should be excluded from being considered to be part of the name at all,
   *       e.g. 'Mr' or 'Mrs'
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->xcm_profile))           $config->xcm_profile          = null; // i.e. default
    if (!isset($config->threshold))             $config->threshold            = 1.0; // 100% matches
    if (!isset($config->name_mode))             $config->name_mode            = 'first';
    if (!isset($config->contact_type))          $config->contact_type         = 'Individual';
    if (!isset($config->mapping))               $config->mapping              = [];
    if (!isset($config->output_field))          $config->output_field         = 'contact_id';
    if (!isset($config->ucwords_fields))        $config->ucwords_fields       = [];
    if (!isset($config->name_blacklist))        $config->name_blacklist       = [];
    if (!isset($config->first_name_blacklist))  $config->first_name_blacklist = [];
  }

  /**
   * Run the analyser
   */
  public function analyse(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;

    // You can control/restrict the execution with the required values - just like a matcher
    if (!$this->requiredValuesPresent($btx)) return null;

    // make sure we don't re-create a contact that's already been identified
    if ($this->contactAlreadyIdentified($btx, $context)) {
      $this->logMessage("Contact already identified, not running XCM.", 'debug');
      return null;
    }

    // start compiling the values
    $xcm_values = [
        'xcm_profile'  => $config->xcm_profile,
        'contact_type' => $config->contact_type,
    ];

    // step 1: get the first/last name
    $this->applyNameExtraction($btx, $xcm_values, $config->name_mode);

    // step 2: apply mapping
    $this->applyMapping($btx, $xcm_values, $config->mapping);

    // step 3: apply value formatting
    $this->applyNormalisation($btx, $xcm_values);

    // step 3: run XCM
    $contact_id = $this->runXCM($btx, $xcm_values);
    $this->logMessage("Contact identified by XCM: " . $contact_id, 'debug');

    // step 4: apply contact ID
    if ($contact_id) {
      $data_parsed = $btx->getDataParsed();
      if (!isset($data_parsed[$config->output_field]) || $data_parsed[$config->output_field] != $contact_id) {
        $data_parsed[$config->output_field] = $contact_id;
        $btx->setDataParsed($data_parsed);
        $this->logMessage("Update field {$config->output_field} with: " . $contact_id, 'debug');
      }
    }
  }

  /**
   * Apply the current mapping of btx parameters to the xcm values
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *     the current transaction
   * @param string $xcm_profile
   *     the xcm profile to use
   * @param array $xcm_values
   *     values to be passed to the xcm
   *
   * @return integer
   *     contact ID
   */
  protected function runXCM($btx, $xcm_values)
  {
    // first add some config values
    $config = $this->_plugin_config;
    if (!isset($xcm_values['xcm_profile'])) {
      $xcm_values['xcm_profile'] = $config->xcm_profile;
    }
    if (!isset($xcm_values['contact_type'])) {
      $xcm_values['contact_type'] = $config->contact_type;
    }

    try {
      $this->logMessage('Calling XCM with parameters: ' . json_encode($xcm_values), 'debug');
      $xcm_result = civicrm_api3('Contact', 'getorcreate', $xcm_values);
      return $xcm_result['id'];
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logMessage('XCM call failed with ' . $ex->getMessage() . ' Parameters were: ' . json_encode($xcm_values), 'error');
      return null;
    }
  }

  /**
   * Apply the current mapping of btx parameters to the xcm values
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *     the current transaction
   * @param array $xcm_values
   *     the current list of values to be passed to XCM to be extended
   * @param array $mapping
   *     one of the name modes, see above
   */
  protected function applyMapping($btx, &$xcm_values, $mapping)
  {
    foreach ($mapping as $from_field => $to_field) {
      $value = $this->getPropagationValue($btx, NULL, $from_field);
      if (isset($value)) {
        $xcm_values[$to_field] = $value;
      }
    }
  }


  /**
   * Apply the configured normalisation to the xcm paramters
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *     the current transaction
   * @param array $xcm_values
   *     the current list of values to be passed to XCM to be extended
   */
  protected function applyNormalisation($btx, &$xcm_values)
  {
    $config = $this->_plugin_config;

    // extract ucwords normalisation
    if (!empty($config->ucwords_fields)) {
      if (!is_array($config->ucwords_fields)) {
        $config->ucwords_fields = explode(',', $config->ucwords_fields);
      }
      $config->ucwords_fields = array_map('trim', $config->ucwords_fields);
      foreach ($config->ucwords_fields as $field_name) {
        if (isset($xcm_values[$field_name])) {
          $xcm_values[$field_name] = ucwords(strtolower($xcm_values[$field_name]), " \t\r\n\f\v-");
        }
      }
    }
  }

  /**
   * Apply the selected name extraction mode to get first_name, last_name
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   *     the current transaction
   * @param array $xcm_values
   *     the current list of values to be passed to XCM to be extended
   * @param $name_mode
   *     one of the name modes, see above
   */
  protected function applyNameExtraction($btx, &$xcm_values, $name_mode)
  {
    $config = $this->_plugin_config;
    $btx_name = $btx->getDataParsed()['name'] ?? '';
    if (!$btx_name) return;

    $name_bits = preg_split('/ +/', $btx_name);
    $this->logMessage("Extracting names from '{$btx_name}', mode is '{$name_mode}'", 'debug');

    switch ($name_mode) {
      case 'first':
        $first_name = null;
        $last_names = [];
        foreach ($name_bits as $name_bit) {
          if (!isset($first_name)) {
            // still looking for the first name
            if (!$this->isNameBlacklisted($name_bit, $config->name_blacklist, $config->first_name_blacklist)) {
              $first_name = $name_bit;
            }
          } else {
            // adding last names
            if (!$this->isNameBlacklisted($name_bit, $config->name_blacklist)) {
              $last_names[] = $name_bit;
            }
          }
        }
        $xcm_values['first_name'] = $first_name ? $first_name : '';
        $xcm_values['last_name'] = implode(' ', $last_names);
        break;

      case 'last':
        $last_name = null;
        $first_names = [];
        $name_bits = array_reverse($name_bits); // will go from the back to the front
        foreach ($name_bits as $name_bit) {
          if (!isset($last_name)) {
            // still looking for the last name
            if (!$this->isNameBlacklisted($name_bit, $config->name_blacklist)) {
              $last_name = $name_bit;
            }
          } else {
            // adding first names
            if (!$this->isNameBlacklisted($name_bit, $config->name_blacklist, $config->first_name_blacklist)) {
              $first_names[] = $name_bit;
            }
          }
        }
        $xcm_values['last_name'] = $last_name ? $last_name : '';
        $xcm_values['first_name'] = implode(' ', array_reverse($first_names));
        break;

      case 'db':
        $first_names = [];
        $last_names = [];
        foreach ($name_bits as $name_bit) {
          if (!$this->isNameBlacklisted($name_bit, $config->name_blacklist)) {
            if ((!$this->isNameBlacklisted($name_bit, $config->first_name_blacklist))
                && $this->isDBFirstName($name_bit)) {
              $first_names[] = $name_bit;
            } else {
              $last_names[] = $name_bit;
            }
          }
        }
        $this->logMessage("Identified (by DB) first names of '{$btx_name}' are: " . implode(',', $first_names), 'debug');
        $xcm_values['first_name'] = implode(' ', $first_names);
        $xcm_values['last_name'] = implode(' ', $last_names);
        break;

      // See PR #112
      case 'db2':

        $first_names = [];
        $last_names = [];

        // If the name contains a comma, we assume that the name is in the format "Lastname, Firstname"
        if (in_array(',', $name_bits)) {
            $last_names += array_slice($name_bits, 0, array_search(',', $name_bits));
            $first_names += array_slice($name_bits, array_search(',', $name_bits) + 1);
        }
        // Otherwise, we assume that the name is in the format "Firstname Lastname"
        else {
          foreach ($name_bits as $name_bit) {
            if (!$this->isNameBlacklisted($name_bit, $config->name_blacklist)) {
              if ((!$this->isNameBlacklisted($name_bit, $config->first_name_blacklist))
                && $this->isDBFirstName($name_bit)
                && empty($last_names)) {
                $first_names[] = $name_bit;
              }
              else {
                $last_names[] = $name_bit;
              }
            }
          }

          // If we didn't find any last names, but we found more than one first name,
          // then we assume that the last one is the last name of the contact
          if (empty($last_names) && count($first_names) > 1) {
            $last_names[] = array_pop($first_names);
          }
        }

        $this->logMessage("Identified (by DB) first names of '{$btx_name}' are: " . implode(',', $first_names), 'debug');
        $xcm_values['first_name'] = implode(' ', $first_names);
        $xcm_values['last_name'] = implode(' ', $last_names);
        break;

      default:
      case 'off':
        break;
    }
  }


  /**
   * Check if the given string appears in the first_name column in the database
   *
   * @param $name string
   *  the name sample
   */
  public function isDBFirstName($name)
  {
    static $all_first_names = null;
    if ($all_first_names === null) {
      $all_first_names = Civi::cache('long')->get(self::FIRST_NAME_CACHE_KEY);
      if ($all_first_names === null) {
        // load all first names from the database
        $all_first_names = [];
        $this->logger->setTimer('load_first_names');
        $data = CRM_Core_DAO::executeQuery("SELECT DISTINCT(LOWER(first_name)) AS name FROM civicrm_contact WHERE is_deleted = 0;");
        while ($data->fetch()) {
          $all_first_names[$data->name] = 1;
        }
        $this->logTime("Loading all first names", 'load_first_names');
        Civi::cache('long')->set(self::FIRST_NAME_CACHE_KEY, $all_first_names, self::FIRST_NAME_CACHE_TTL);
      }
    }

    // now simply
    return isset($all_first_names[strtolower($name)]);
  }


  /**
   * @param string $name
   *    name to be checked against the blacklist
   * @param array $blacklist1
   *    list strings not to be considered names
   * @param array $blacklist2
   *    list strings not to be considered names
   */
  public function isNameBlacklisted($name, $blacklist1 = [], $blacklist2 = [])
  {
    $name = strtolower($name);
    foreach ($blacklist1 as $blacklisted_name) {
      if ($name == strtolower($blacklisted_name)) {
        return true;
      }
    }

    foreach ($blacklist2 as $blacklisted_name) {
      if ($name == strtolower($blacklisted_name)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Has the contact already been identified (i.e. confidence >= threshold)?
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param CRM_Banking_Matcher_Context $context
   */
  public function contactAlreadyIdentified(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context)
  {
    $config = $this->_plugin_config;
    if (isset($config->threshold)) {
      // check if the contact is already identified with sufficient confidence
      $data_parsed    = $btx->getDataParsed();
      $contacts_found = $context->findContacts($config->threshold, $data_parsed['name'] ?? null);
      if (!empty($contacts_found)) {
        // for consistency: set this contact ID to the output field, too
        $contact_field_value = implode(',', array_keys($contacts_found));
        if (!isset($data_parsed[$config->output_field]) || $data_parsed[$config->output_field] != $contact_field_value) {
          $data_parsed[$config->output_field] = $contact_field_value;
          $btx->setDataParsed($data_parsed);
          $this->logMessage("Copy already identified contact {$contact_field_value} to output field {$config->output_field}.", 'debug');
        }
        return true;
      }
    }
    return false;
  }

  /**
   * Register this module IF CiviBanking is installed and detected
   */
  public static function registerModule()
  {
    if (function_exists('banking_civicrm_install_options')) {
      // extension is enabled, let's see if our module is there
      $exists = civicrm_api3('OptionValue', 'getcount', [
          'option_group_id' => 'civicrm_banking.plugin_types',
          'value' => 'CRM_Banking_PluginImpl_Matcher_GetOrCreateContactAnalyser'
      ]);
      if (!$exists) {
        // register new item
        civicrm_api3('OptionValue', 'create', [
            'option_group_id' => 'civicrm_banking.plugin_types',
            'value' => 'CRM_Banking_PluginImpl_Matcher_GetOrCreateContactAnalyser',
            'label' => E::ts('Create Contact Analyser (XCM)'),
            'name' => 'analyser_xcm',
            'description' => E::ts("Uses XCM to create a potentially missing contact before reconciliation."),
        ]);
        CRM_Core_Session::setStatus(
            E::ts("Registered new XCM CiviBanking module 'Create Contact Analyser'"),
            E::ts("Registered CiviBanking Module!"),
            'info');
      }
    }
  }
}
