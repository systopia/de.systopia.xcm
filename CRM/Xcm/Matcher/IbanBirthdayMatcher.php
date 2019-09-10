<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2017-2019 SYSTOPIA                       |
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
 * Matches on IBAN (CiviBanking or CiviSEPA) + birth day
 */
class CRM_Xcm_Matcher_IbanBirthdayMatcher extends CRM_Xcm_Matcher_IbanMatcher {

  public function __construct() {
    parent::__construct(['birth_date']);
  }

  /**
   * Add more parameters to the final contact query. The filters for
   *  the iban are already in there
   *
   * @param $contact_query array the current query
   * @param $contact_data  array the submitted contact data
   */
  public function refineContactQuery(&$contact_query, $contact_data) {
    $contact_query['birth_date'] = date('Y-m-d', strtotime($contact_data['birth_date']));
  }

}
