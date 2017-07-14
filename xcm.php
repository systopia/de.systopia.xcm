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

require_once 'xcm.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function xcm_civicrm_config(&$config) {
  _xcm_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function xcm_civicrm_xmlMenu(&$files) {
  _xcm_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function xcm_civicrm_install() {
  _xcm_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function xcm_civicrm_uninstall() {
  _xcm_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function xcm_civicrm_enable() {
  _xcm_civix_civicrm_enable();

  require_once 'CRM/Xcm/CustomData.php';
  $customData = new CRM_Xcm_CustomData('de.systopia.xcm');
  $customData->syncOptionGroup(__DIR__ . '/resources/rules_option_group.json');
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function xcm_civicrm_disable() {
  _xcm_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function xcm_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _xcm_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function xcm_civicrm_managed(&$entities) {
  _xcm_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function xcm_civicrm_caseTypes(&$caseTypes) {
  _xcm_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function xcm_civicrm_angularModules(&$angularModules) {
_xcm_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function xcm_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _xcm_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implementation of hook_civicrm_buildForm:
 *   Inject modification tpl snippets, where required
 */
function xcm_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Activity_Form_Activity'
        && CRM_Core_Permission::check('edit all contacts')
        && CRM_Xcm_Configuration::diffProcessing()) {

    try {
      // look up activity type id and status_id
      $elem_status_id           = $form->getElement('status_id');
      $current_status_value     = $elem_status_id->getValue();
      $current_status_id        = $current_status_value[0];
      $current_activity_type_id = $form->getVar('_activityTypeId');
    } catch (Exception $e) {
      // something went wrong there, but that probably means it's not our form...
      return;
    }

    // look up activity type id by label
    $activity_type_id = CRM_Xcm_Configuration::diffActivity();

    // look up status id for label "Scheduled"
    $activity_status_id = CRM_Xcm_Configuration::defaultActivityStatus();

    // only inject javascript if current activity is of type "Adressprüfung"
    // and its current status is "Scheduled"
    if($current_activity_type_id == $activity_type_id &&
              $current_status_id == $activity_status_id) {

      // WARN if contact is tagged with certain tags
      $warnOnTags = CRM_Xcm_Configuration::diffProcess_warnOnTags();
      if (!empty($warnOnTags)) {
        $contact_id = $form->getVar('_currentlyViewedContactId');
        if ($contact_id) {
          $tags = CRM_Core_BAO_EntityTag::getContactTags($contact_id);
          foreach ($warnOnTags as $tagName) {
            if (in_array($tagName, $tags)) {
              CRM_Core_Session::setStatus(
                ts("Warning! This contact is tagged '%1'.", array(1=>$tagName, 'domain'=>'de.systopia.xcm')),
                ts("Warning", array('domain'=>'de.systopia.xcm')), 'warning');
            }
          }
        } else {
          CRM_Core_Session::setStatus(
            ts("Warning! The tags couldn't be read.", array('domain'=>'de.systopia.xcm')),
            ts("Warning", array('domain'=>'de.systopia.xcm')), 'error');
        }
      }

      // build constants array for JS
      $constants['targetActivityId']              = $form->getVar('_activityId');
      $constants['location_type_current_address'] = CRM_Xcm_Configuration::currentLocationType();
      $constants['location_type_old_address']     = CRM_Xcm_Configuration::oldLocationType();
      $constants['phone_type_phone_value']        = CRM_Xcm_Configuration::phoneType();
      $constants['phone_type_mobile_value']       = CRM_Xcm_Configuration::mobileType();

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
          'script' => file_get_contents(__DIR__ . '/js/process_diff.js')
      ));
    }
  }
}
