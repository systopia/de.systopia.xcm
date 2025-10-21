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

declare(strict_types = 1);

/**
 *
 * Matches on (any) email and first_name / last_name
 *
 */
class CRM_Xcm_Matcher_PhoneFullNameMatcher extends CRM_Xcm_Matcher_PhoneMatcher {

  public function __construct() {
    parent::__construct(['first_name', 'last_name', 'contact_type']);
  }

}
