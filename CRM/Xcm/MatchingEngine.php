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
class CRM_Xcm_MatchingEngine {

  /** singleton instance of the engine */
  protected static $_singleton = NULL;

  /**
   * get the singleton instance of the engine
   */
  public static function getSingleton() {
    if (self::$_singleton===NULL) {
      self::$_singleton = new CRM_Xcm_MatchingEngine();
    }
    return self::$_singleton;
  }

  /**
   * Try to find/match the contact with the given data.
   * If that fails, a new contact will be created with that data
   *
   * @throws exception  if anything goes wrong during matching/contact creation
   */
  public function getOrCreateContact(&$contact_data) {
    $result = $this->matchContact($contact_data);
    if (empty($result['contact_id'])) {
      // the matching failed
      $new_contact = $this->createContact($contact_data);
      $result['contact_id'] = $new_contact['id'];
      // TODO: add more data? how?

      // do the post-processing
      $this->postProcessNewContact($new_contact, $contact_data);
    
    } else {
      // the matching was successful
      $this->postProcessContactMatch($result, $contact_data);
    }
    
    return $result;
  }


  /**
   * @todo document
   */
  public function matchContact(&$contact_data) {
    // TODO: implement

  }

  /**
   * @todo document
   */
  protected function createContact($contact_data) {
    // TODO: implement

  }


  /**
   * @todo document
   */
  protected function getMatchingRules() {
    // TODO: implement

  }

  /**
   * @todo document
   */
  protected function postProcessNewContact($new_contact, $contact_data) {
    // TODO: implement

  }

  /**
   * @todo document
   */
  protected function postProcessContactMatch($result, $contact_data) {
    // TODO: implement
    
  }
}
