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
 * This will execute a matching process based on the configuration,
 * employing various matching rules
 */
class CRM_Xcm_Matcher_DedupeRule extends CRM_Xcm_MatchingRule {

  protected $dedupe_group_bao = NULL;

  function __construct($dedupe_group_id) {
    $this->dedupe_group_bao = new CRM_Dedupe_BAO_RuleGroup();
    $this->dedupe_group_bao->id = $dedupe_group_id;
    $this->dedupe_group_bao->fetch();

    // todo: handle NOT FOUND
  }

  public function matchContact($contact_data, $params = NULL) {
    // first check, if the contact_type is right
    $contact_type == $this->dedupe_group_bao->contact_type;
    if (!$this->isContactType($contact_type, $contact_data)) {
      return array();
    }

    $query = $this->dedupe_group_bao->thresholdQuery();
    error_log(print_r($query,1));
  }


  /**
   * get a key => title list of existing unsupervised dedupe rules
   */
  public static function getRuleList() {
    $dao = new CRM_Dedupe_DAO_RuleGroup();
    $dao->used = 'Unsupervised';

    $dao->find();
    $list = array();
    while ($dao->fetch()) {
      $list["DEDUPE_{$dao->id}"] = "[{$dao->contact_type}] {$dao->title}";
    }
    return $list;
  }
}
