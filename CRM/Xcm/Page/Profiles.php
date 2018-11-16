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

class CRM_Xcm_Page_Profiles extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Extended Contact Matcher (XCM) Profiles'));

    // execute actions
    $action = CRM_Utils_Request::retrieve('xaction', 'String');
    $pid    = CRM_Utils_Request::retrieve('pid', 'String');
    if ($action && $pid) {
      switch ($action) {
        case 'delete':
          $profile = CRM_Xcm_Configuration::getConfigProfile($pid);
          $profile->deleteProfile();
          $profile->store();
          break;
        case 'setdefault':
          CRM_Xcm_Configuration::setDefaultProfile($pid);
          break;
        default:
          CRM_Core_Session::setStatus(E::ts("Unkown action submitted."), E::ts("Warning"), 'warning');
      }
    }

    // compile profile data
    $profile_data = [];
    $all_profiles = CRM_Xcm_Configuration::getProfileList();
    foreach ($all_profiles as $profile_name => $profile_label) {
      $profile = CRM_Xcm_Configuration::getConfigProfile($profile_name);
      $profile_data[] = [
          'pid'        => $profile_name,
          'label'      => $profile->getLabel(),
          'is_default' => $profile->isDefault(),
      ];
    }

    $this->assign('profiles', $profile_data);
    parent::run();
  }
}
