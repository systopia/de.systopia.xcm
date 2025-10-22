<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2017 SYSTOPIA                            |
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
 * Matches on (any) email and first_name and last name... BUT only when they're reversed!
 *
 */
class CRM_Xcm_Matcher_EmailFullNameReversedMatcher extends CRM_Xcm_Matcher_EmailMatcher {

  public function __construct() {
    parent::__construct(['first_name' => 'last_name', 'last_name' => 'first_name', 'contact_type' => 'contact_type']);
  }

}
