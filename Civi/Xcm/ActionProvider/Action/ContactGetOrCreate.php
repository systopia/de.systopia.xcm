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

class ContactGetOrCreate extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    $contact_types = [
        'Individual'   => E::ts('Individual'),
        'Organization' => E::ts('Organization'),
        'Household'    => E::ts('Household'),
    ];
    $profiles = \CRM_Xcm_Configuration::getProfileList();
    return new SpecificationBag([
      new Specification('xcm_profile', 'String', E::ts('XCM Profile'), false, null, null, $profiles, false),
      new Specification('contact_type', 'String', E::ts('Default Contact Type'), false, 'Individual', null, $contact_types, false),
    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getParameterSpecification() {
    // add contact specs
    $contact_specs = array_merge(CRM_Xcm_Form_Settings::getContactFields(), CRM_Xcm_Form_Settings::getCustomFields());
    return new SpecificationBag(array_merge($contact_specs, [
        // special fields
        new Specification('contact_type', 'String', E::ts('Contact Type'), false, 'Individual', null, ['Individual', 'Organization', 'Household'], false),
        new Specification('xcm_submitted_contact_id', 'Integer', E::ts('Known Contact ID'), false, null, null, null, false),

        // detail fields
        new Specification('email', 'String', E::ts('Email'), false, null, null, null, false),
        new Specification('phone', 'String', E::ts('Primary Phone'), false, null, null, null, false),
        new Specification('phone2', 'String', E::ts('Secondary Phone'), false, null, null, null, false),
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
        new Specification('city', 'String', E::ts('City'), false, null, null, null, false),
        new Specification('postal_code', 'String', E::ts('Postal Code'), false, null, null, null, false),
        new Specification('state_province_id', 'String', E::ts('State/Province'), false, null, null, null, false),
        new Specification('country_id', 'String', E::ts('Country'), false, null, null, null, false),
        new Specification('is_billing', 'Integer', E::ts('Billing?'), false, null, null, null, false),
   ]));
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

    // execute
    $result = \civicrm_api3('Contact', 'getorcreate', $apiParams);
    $output->setParameter('contact_id', $result['id']);
  }
}
