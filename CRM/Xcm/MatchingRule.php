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
 * This as the base class of all XCM matching rules
 */
abstract class CRM_Xcm_MatchingRule {

  /**
   * This is the core matching function
   * 
   * @param $contact_data  an array of all information we have on the contact, e.g. first_name, street_address, etc...
   * @return array result: mandatory entries:
   *                         contact_id   -  matched contact ID or NULL if not matched
   *                       recommended entries:
   *                         confidence   - float [0..1] defining the likelihood of the match
   *                       other entries:
   *                         ...fee free to return whatever you think might be interesting
   */ 
  abstract public function matchContact($contact_data);

}
