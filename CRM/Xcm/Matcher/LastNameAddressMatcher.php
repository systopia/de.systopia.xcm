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
 * Matches on first name and address
 */
class CRM_Xcm_Matcher_LastNameAddressMatcher extends CRM_Xcm_Matcher_AddressMatcher {

  public function __construct() {
    parent::__construct(['last_name']);
  }

  /**
   * Add more parameters to the final contact query. The filters for
   *  the address are already in there
   *
   * @param $contact_query array the current query
   * @param $contact_data  array the submitted contact data
   */
  public function refineContactQuery(&$contact_query, $contact_data) {
    $contact_query['last_name'] = $contact_data['last_name'];
  }
}
