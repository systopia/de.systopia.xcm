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

}
