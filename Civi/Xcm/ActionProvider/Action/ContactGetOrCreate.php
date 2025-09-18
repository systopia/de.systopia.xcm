<?php
/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2020 SYSTOPIA                            |
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

namespace Civi\Xcm\ActionProvider\Action;

use CRM_Xcm_ExtensionUtil as E;
use CRM_Xcm_Form_Settings;

use \Civi\ActionProvider\Action\AbstractAction;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;
use \Civi\ActionProvider\Utils\CustomField;

class ContactGetOrCreate extends AbstractAction {

  /** @var int number of arbitrary parameters (where the target names can be defined) */
  const CUSTOM_PARAMETER_COUNT = 2;

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    $contactSubTypesApi = civicrm_api3('ContactType', 'get', ['options' => array('limit' => 0)]);
    foreach ($contactSubTypesApi['values'] as $contactSubType) {
      $contact_types[$contactSubType['name']] = E::ts($contactSubType['label']);
    }

    $profiles = \CRM_Xcm_Configuration::getProfileList();
    $configuration[] = new Specification('xcm_profile', 'String', E::ts('XCM Profile'), false, null, null, $profiles, false);
    $configuration[] = new Specification('contact_type', 'String', E::ts('Default Contact Type'), false, 'Individual', null, $contact_types, false);

    // add variable fields
    for ($number = 1; $number <= self::CUSTOM_PARAMETER_COUNT; $number++) {
      $configuration[] = new Specification("variable_target_{$number}", 'String', E::ts('Custom Parameter #%1 (key)', [1 => $number]), false);
    }

    return new SpecificationBag($configuration);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getParameterSpecification() {
    $addressOptions = \CRM_Core_BAO_Setting::valueOptions(\CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME, 'address_options');
    $isStreetAddressParsingEnabled = !empty($addressOptions['street_address_parsing']);

    // add contact specs
    $contact_specs = [];
    $contact_fields = CRM_Xcm_Form_Settings::getContactFields();
    foreach ($contact_fields as $contact_field_name => $contact_field_label) {
      $contact_specs[] = new Specification($contact_field_name, 'String', $contact_field_label, false, null, null, null, false);
    }
    $contact_specs = array_merge($contact_specs, self::getCustomFields());

    $detail_specs = [
      // special fields
      new Specification('contact_type', 'String', E::ts('Contact Type'), false, 'Individual', null, ['Individual', 'Organization', 'Household'], false),
      new Specification('id', 'Integer', E::ts('Known Contact ID'), false, null, null, null, false),
      new Specification('source', 'String', E::ts('Source'), false, null, null, null, false),

      // detail fields
      new Specification('email', 'String', E::ts('Email'), false, null, null, null, false),
      new Specification('phone', 'String', E::ts('Primary Phone'), false, null, null, null, false),
      new Specification('phone2', 'String', E::ts('Secondary Phone'), false, null, null, null, false),
      new Specification('phone3', 'String', E::ts('Tertiary Phone'), false, null, null, null, false),
      new Specification('website', 'String', E::ts('Website'), false, null, null, null, false),
      new Specification('name', 'String', E::ts('IM Handle'), false, null, null, null, false),
      new Specification('location_type_id', 'String', E::ts('Location Type ID'), false, null, null, null, false),
      new Specification('phone_type_id', 'String', E::ts('Phone Type ID'), false, null, null, null, false),
      new Specification('provider_id', 'String', E::ts('IM Provider ID'), false, null, null, null, false),
      new Specification('website_type_id', 'String', E::ts('Website Type ID'), false, null, null, null, false),

      // address fields
      new Specification('supplemental_address_1', 'String', E::ts('Supplemental Address 1'), false, null, null, null, false),
      new Specification('supplemental_address_2', 'String', E::ts('Supplemental Address 2'), false, null, null, null, false),
      new Specification('supplemental_address_3', 'String', E::ts('Supplemental Address 3'), false, null, null, null, false),
      new Specification('street_address', 'String', E::ts('Street Address'), false, null, null, null, false),
    ];
    if ($isStreetAddressParsingEnabled) {
      $detail_specs[] =  new Specification('street_name', 'String', E::ts('Street name'), false, null, null, null, false);
      $detail_specs[] =  new Specification('street_number', 'String', E::ts('Street number'), false, null, null, null, false);
      $detail_specs[] =  new Specification('street_unit', 'String', E::ts('Street unit'), false, null, null, null, false);
    }

    $detail_specs[] =  new Specification('city', 'String', E::ts('City'), false, null, null, null, false);
    $detail_specs[] =  new Specification('postal_code', 'String', E::ts('Postal Code'), false, null, null, null, false);
    $detail_specs[] =  new Specification('state_province_id', 'String', E::ts('State/Province'), false, null, null, null, false);
    $detail_specs[] =  new Specification('county_id', 'String', E::ts('County'), false, null, null, null, false);
    $detail_specs[] =  new Specification('country_id', 'String', E::ts('Country'), false, null, null, null, false);
    $detail_specs[] =  new Specification('is_billing', 'Integer', E::ts('Billing?'), false, null, null, null, false);

    // add variable fields
    for ($number = 1; $number <= self::CUSTOM_PARAMETER_COUNT; $number++) {
      $detail_specs[] = new Specification("variable_value_{$number}", 'String', E::ts('Custom Parameter #%1', [1 => $number]), false, null, null, null, false);
    }

    return new SpecificationBag(array_merge($contact_specs, $detail_specs));
  }

  /**
   * Method to check if the version of the Action Provider is 1.18 or later
   *
   * @return bool
   */
  private function isActionProvider181() {
    $fileName = \Civi::paths()->getPath("[civicrm.files]/ext") . "/action-provider/info.xml";
    if (file_exists($fileName)) {
      $infoXml = simplexml_load_file($fileName);
      if ($infoXml) {
        if ($infoXml->version >= 1.81) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Returns the custom fields as a Specification array.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function getCustomFields() {
    $specs = array();
    $custom_group_query = civicrm_api3('CustomGroup', 'get', array(
      'extends'      => array('IN' => array('Contact', 'Individual', 'Organization', 'Household')),
      'is_active'    => 1,
      'option.limit' => 0,
      'is_multiple'  => 0,
      'is_reserved'  => 0));
    $custom_group_ids   = array();
    $custom_groups = array();
    foreach ($custom_group_query['values'] as $custom_group) {
      $custom_group_ids[] = (int) $custom_group['id'];
      $custom_groups[$custom_group['id']] = $custom_group;
    }

    if (!empty($custom_group_ids)) {
      $custom_field_query = civicrm_api3('CustomField', 'get', array(
        'custom_group_id'  => array('IN' => $custom_group_ids),
        'is_active'        => 1,
        'option.limit'     => 0));
      foreach ($custom_field_query['values'] as $custom_field) {
        $spec = CustomField::getSpecFromCustomField($custom_field, $custom_groups[$custom_field['custom_group_id']]['title'].': ', false);
        if ($spec) {
          $specs[] = $spec;
        }
      }
    }
    return $specs;
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overriden by child classes.
   *
   * @return SpecificationBag specs
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('contact_id', 'Integer', E::ts('Contact ID'), false, null, null, null, false),
    ]);
  }

  /**
   * Run the action
   *
   * @param ParameterBagInterface $parameters
   *   The parameters to this action.
   * @param ParameterBagInterface $output
   * 	 The parameters this action can send back
   * @return void
   */
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $apiParams = array();
    foreach($this->getParameterSpecification() as $spec) {
      if ($parameters->doesParameterExists($spec->getName())) {
        if ($spec->getApiFieldName()) {
          $apiParams[$spec->getApiFieldName()] = $parameters->getParameter($spec->getName());
        } else {
          $apiParams[$spec->getName()] = $parameters->getParameter($spec->getName());
        }
      } elseif ($spec->getApiFieldName() && $parameters->doesParameterExists($spec->getApiFieldName())) {
        // Use above statement so that custom_1 still works.
        $apiParams[$spec->getApiFieldName()] = $parameters->getParameter($spec->getApiFieldName());
      }
    }

    // override if necessary
    foreach (['xcm_profile', 'contact_type'] as $field_name) {
      if (empty($apiParams[$field_name])) {
        $apiParams[$field_name] = $this->configuration->getParameter($field_name);
      }
    }

    // add additional (custom) parameters
    for ($number = 1; $number <= self::CUSTOM_PARAMETER_COUNT; $number++) {
      $parameter_name = $this->configuration->getParameter("variable_target_{$number}");
      $parameter_value = $parameters->getParameter("variable_value_{$number}");
      if (!empty($parameter_name)) {
        $apiParams[$parameter_name] = $parameter_value;
      }
      unset($apiParams["variable_value_{$number}"]);
    }

    // Handle Contact Subtypes
    if (!in_array($apiParams['contact_type'], ['Individual', 'Organization', 'Household'])) {
      $apiParams['contact_sub_type'] = $apiParams['contact_type'];
      $apiParams['contact_type'] = civicrm_api3('ContactType', 'getvalue', [
        'return' => 'parent_id.name',
        'name' => $apiParams['contact_sub_type'],
      ]);
    }
    // Split street number in a numeric value and in street number suffix
    // For example street_number 162A becomes Street number 162 and street number suffix A
    if (isset($apiParams['street_number'])) {
      $matches = [];
      if (preg_match('/^(\d+)(.*)$/', $apiParams['street_number'], $matches)) {
        $apiParams['street_number'] = $matches[1];
        $apiParams['street_number_suffix'] = '';
        if (isset($matches[2])) {
          $apiParams['street_number_suffix'] = $matches[2];
        }
      }
    }

    // execute
    $result = \civicrm_api3('Contact', 'getorcreate', $apiParams);
    $output->setParameter('contact_id', $result['id']);
  }
}
