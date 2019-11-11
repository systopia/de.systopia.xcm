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

/*
 * This matcher will match on email,
 * BUT only match only the email was submitted
 */
class CRM_Xcm_Matcher_EmailOnlyMatcher extends CRM_Xcm_Matcher_SingleAttributeMatcher {

  public function __construct() {
    parent::__construct('email', 'Email');
  }

}
