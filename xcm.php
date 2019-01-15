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
use CRM_Xcm_ExtensionUtil as E;

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
        && CRM_Core_Permission::check('edit all contacts')) {

        // not required if we are deleting!
    if ($form->_action != CRM_Core_Action::DELETE) {
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

      CRM_Xcm_Configuration::injectDiffHelper($form, $current_activity_type_id, $current_status_id);
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function xcm_civicrm_navigationMenu(&$menu) {
  $menu_item_search = array(
    'name' => 'Import Contacts',
  );
  $menu_items = array();
  CRM_Core_BAO_Navigation::retrieve($menu_item_search, $menu_items);
  _xcm_civix_insert_navigation_menu($menu, 'Contacts', array(
    'label' => E::ts('Import contacts (XCM)', array('domain' => 'de.systopia.xcm')),
    'name' => 'Import contacts (XCM)',
    'url' => 'civicrm/import/contact/xcm',
    'permission' => 'import contacts',
    'operator' => 'OR',
    'separator' => 0,
    // See https://github.com/civicrm/civicrm-core/pull/11772 for weight.
    'weight' => $menu_items['weight'],
  ));
  _xcm_civix_navigationMenu($menu);
}
