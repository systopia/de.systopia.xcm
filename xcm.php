<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2016-2019 SYSTOPIA                       |
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

declare(strict_types = 1);

// phpcs:disable PSR1.Files.SideEffects
require_once 'xcm.civix.php';
// phpcs:enable

use CRM_Xcm_ExtensionUtil as E;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Implements hook_civicrm_container().
 */
function xcm_civicrm_container(ContainerBuilder $container): void {
  if (class_exists('Civi\Xcm\ContainerSpecs')) {
    $container->addCompilerPass(new Civi\Xcm\ContainerSpecs());
  }
}

/**
 * Implements hook_civicrm_config().
 */
function xcm_civicrm_config(\CRM_Core_Config &$config): void {
  _xcm_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function xcm_civicrm_install(): void {
  _xcm_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function xcm_civicrm_enable(): void {
  _xcm_civix_civicrm_enable();

  require_once 'CRM/Xcm/CustomData.php';
  $customData = new CRM_Xcm_CustomData('de.systopia.xcm');
  $customData->syncOptionGroup(__DIR__ . '/resources/rules_option_group.json');
}

/**
 * Implements hook_civicrm_buildForm().
 */
function xcm_civicrm_buildForm(string $formName, \CRM_Core_Form &$form): void {
  // Inject modification tpl snippets, where required
  if ('CRM_Activity_Form_Activity' === $formName && CRM_Core_Permission::check('edit all contacts')) {
    /** @var \CRM_Activity_Form_Activity $form */
    // not required if we are deleting!
    if (CRM_Core_Action::DELETE !== $form->_action) {
      // check if status_id field exists. $form->getElement triggers an error
      // otherwise (doesn't throw an exception!)
      if (!$form->elementExists('status_id')) {
        return;
      }
      // look up activity type id and status_id
      $elem_status_id = $form->getElement('status_id');
      /** @phpstan-var array<int, mixed> $current_status_value */
      $current_status_value = $elem_status_id->getValue();
      $current_status_id = $current_status_value[0];
      $current_activity_type_id = $form->_activityTypeId;

      \CRM_Xcm_Configuration::injectDiffHelper($form, $current_activity_type_id, $current_status_id);
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @phpstan-param array<string, array<string, mixed>> $menu
 */
function xcm_civicrm_navigationMenu(array &$menu): void {
  // ADD 'Import Contacts' item (if it doesn't exist elsewhere)
  $menu_items = [];
  $parentMenuItem = \Civi\Api4\Navigation::get(FALSE)
    ->addSelect('weight')
    ->addWhere('name', '=', 'Import Contacts')
    ->execute()
    ->single();
  _xcm_civix_insert_navigation_menu($menu, 'Contacts', [
    'label' => E::ts('Import contacts (XCM)'),
    'name' => 'Import contacts (XCM)',
    'url' => 'civicrm/import/contact/xcm',
    'permission' => 'import contacts',
    'operator' => 'OR',
    'separator' => 0,
    // See https://github.com/civicrm/civicrm-core/pull/11772 for weight.
    'weight' => $parentMenuItem['weight'] + 1,
  ]);

  // Note: "Configure XCM" in "Automation" sub-menu is being taken care of by
  // managed entities, see /managed/Navigation__configure_xcm.mgd.php.
  _xcm_civix_navigationMenu($menu);
}

/**
 * Checks whether a navigation menu item exists.
 *  (copied from form processor, code by Jaap)
 *
 * @phpstan-param array<string, array<string, mixed>> $menu
 * @param string $path - path to parent of this item, e.g. 'my_extension/submenu'
 *    'Mailing', or 'Administer/System Settings'
 * @return bool
 */
function _xcm_menu_exists(array &$menu, string $path): bool {
  // Find an recurse into the next level down
  $found = FALSE;
  $path = explode('/', $path);
  $first = array_shift($path);
  foreach ($menu as $key => &$entry) {
    if ($entry['attributes']['name'] === $first) {
      if ([] === $path) {
        return TRUE;
      }
      $found = _xcm_menu_exists($entry['child'], implode('/', $path));
      if ($found) {
        return TRUE;
      }
    }
  }
  return $found;
}
