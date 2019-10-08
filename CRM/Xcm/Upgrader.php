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

use CRM_Xcm_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Xcm_Upgrader extends CRM_Xcm_Upgrader_Base {

  /**
   * Add new phone rules
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0142() {
    $this->ctx->log->info('Adding phone rules.');
    $customData = new CRM_Xcm_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('/resources/rules_option_group.json'));
    return TRUE;
  }

  /**
   * Add new phone rules
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0151() {
    $this->ctx->log->info('Introducing configuration profiles.');
    $profiles = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'xcm_config_profiles');
    if ($profiles === NULL) {
      // this seems to be the first time: convert
      $options        = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'xcm_options');
      $rules          = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'rules');
      $postprocessing = CRM_Core_BAO_Setting::getItem('de.systopia.xcm', 'postprocessing');
      $profiles = array(
          'default' => array(
              'is_default'     => 1,
              'options'        => ($options === NULL ? array() : $options),
              'rules'          => ($rules === NULL ? array() : $rules),
              'postprocessing' => ($postprocessing === NULL ? array() : $postprocessing),
          )
      );

      // save and reset the others
      CRM_Core_BAO_Setting::setItem($profiles, 'de.systopia.xcm', 'xcm_config_profiles');
      CRM_Core_BAO_Setting::setItem(NULL, 'de.systopia.xcm', 'xcm_options');
      CRM_Core_BAO_Setting::setItem(NULL, 'de.systopia.xcm', 'rules');
      CRM_Core_BAO_Setting::setItem(NULL, 'de.systopia.xcm', 'postprocessing');
    }

    // also: rebuild menu
    CRM_Core_Invoke::rebuildMenuAndCaches();

    return TRUE;
  }

  /**
   * Add new iban/address rules
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0160() {
    $this->ctx->log->info('Adding new iban/address rules.');
    $customData = new CRM_Xcm_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('/resources/rules_option_group.json'));
    return TRUE;
  }

}
