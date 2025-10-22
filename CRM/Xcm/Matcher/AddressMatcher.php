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

declare(strict_types = 1);

/**
 *
 * Matches on address data (street_address, postal_code, city) + extra parameters
 *
 */
// phpcs:disable Generic.NamingConventions.AbstractClassNamePrefix.Missing
abstract class CRM_Xcm_Matcher_AddressMatcher extends CRM_Xcm_MatchingRule {
// phpcs:enable

  protected $required_fields = ['street_address', 'postal_code', 'city'];
  protected $additional_fields = NULL;

  protected bool $isStreetAddressParsingEnabled;

  protected function __construct($additional_fields) {
    $this->additional_fields = $additional_fields;
    $addressOptions = \CRM_Core_BAO_Setting::valueOptions(
      \CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
      'address_options'
    );
    $this->isStreetAddressParsingEnabled = !empty($addressOptions['street_address_parsing']);
  }

  /**
   * Add more parameters to the final contact query. The filters for
   *  the address are already in there
   *
   * @param $contact_query array the current query
   * @param $contact_data  array the submitted contact data
   */
  abstract public function refineContactQuery(&$contact_query, $contact_data);

  /**
   * do the following:
   * 1) find all addresses matching city, postal_code, street_address
   * 2) find contacts with matching first and last names
   */
  // phpcs:disable: Generic.Metrics.CyclomaticComplexity.TooHigh
  public function matchContact(&$contact_data, $params = NULL) {
  // phpcs:enable
    foreach ($this->additional_fields as $field_name) {
      if (empty($contact_data[$field_name])) {
        return $this->createResultUnmatched();
      }
    }
    foreach ($this->required_fields as $field_name) {
      if (empty($contact_data[$field_name])) {
        return $this->createResultUnmatched();
      }
    }

    // find addresses
    $address_query = [
      'return'         => 'contact_id',
      'option.limit'   => 0,
      'street_address' => $contact_data['street_address'],
      'postal_code'    => $contact_data['postal_code'],
      'city'           => $contact_data['city'],
    ];
    if ($this->isStreetAddressParsingEnabled) {
      if (empty($address_query['street_address'])) {
        unset($address_query['street_address']);
      }
      if (array_key_exists('street_name', $contact_data)) {
        $address_query['street_name'] = $contact_data['street_name'];
      }
      if (array_key_exists('street_number', $contact_data)) {
        $address_query['street_number'] = $contact_data['street_number'];
        if (array_key_exists('street_number_suffix', $contact_data)) {
          $address_query['street_number_suffix'] = $contact_data['street_number_suffix'];
        }
      }
      if (array_key_exists('street_unit', $contact_data)) {
        $address_query['street_unit'] = $contact_data['street_unit'];
      }
    }

    $addresses = civicrm_api3('Address', 'get', $address_query);
    $potential_contact_ids = [];
    foreach ($addresses['values'] as $address) {
      $potential_contact_ids[] = $address['contact_id'];
    }

    // now: find contacts
    if (!empty($potential_contact_ids)) {
      $contact_query = [
        'id'           => ['IN' => $potential_contact_ids],
        'is_deleted'   => 0,
        'option.limit' => 0,
        'first_name'   => $contact_data['first_name'],
        'last_name'    => $contact_data['last_name'],
        'return'       => 'id',
      ];
      $this->refineContactQuery($contact_query, $contact_data);
      $contacts = civicrm_api3('Contact', 'get', $contact_query);
      $contact_matches = [];
      foreach ($contacts['values'] as $contact) {
        $contact_matches[] = $contact['id'];
      }

      // process results
      switch (count($contact_matches)) {
        case 0:
          return $this->createResultUnmatched();

        case 1:
          return $this->createResultMatched(reset($contact_matches));

        default:
          $contact_id = $this->pickContact($contact_matches);
          if ($contact_id) {
            return $this->createResultMatched($contact_id);
          }
          else {
            return $this->createResultUnmatched();
          }
      }
    }

    return $this->createResultUnmatched();
  }

}
