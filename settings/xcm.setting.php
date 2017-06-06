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
* Settings metadata file
*/
return array(
  'xcm_options' => array(
    'group_name' => 'de.systopia.xcm',
    'group' => 'de.systopia.xcm',
    'name' => 'xcm_options',
    'type' => 'Array',
    'default' => NULL,
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Generic XCM Settings'
  ),
  'postprocessing' => array(
    'group_name' => 'de.systopia.xcm',
    'group' => 'de.systopia.xcm',
    'name' => 'postprocessing',
    'type' => 'Array',
    'default' => NULL,
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'XCM Postprocessing Settings'
  )
 );