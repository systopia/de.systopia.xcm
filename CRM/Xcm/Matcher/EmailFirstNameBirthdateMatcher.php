<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2023 SYSTOPIA                            |
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
 * Matches on (any) email, birthdate, and first name
 */
class CRM_Xcm_Matcher_EmailFirstNameBirthdateMatcher extends CRM_Xcm_Matcher_EmailMatcher {
  public function __construct() {
    parent::__construct(['first_name' => 'first_name', 'birth_date' => 'birth_date', 'contact_type' => 'contact_type']);
  }
}
