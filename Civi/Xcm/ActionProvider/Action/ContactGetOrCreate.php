<?php
/*-------------------------------------------------------+
| SYSTOPIA CUSTOM DATA HELPER                            |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
| Source: https://github.com/systopia/Custom-Data-Helper |
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

use \Civi\ActionProvider\Action\AbstractAction;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContactGetOrCreate extends AbstractAction implements CompilerPassInterface {

  /**
   * Register this one action: XcmGetOrCreate
   */
  public function process(ContainerBuilder $container) {
    if (!$container->hasDefinition('action_provider')) {
      return;
    }
    $typeFactoryDefinition = $container->getDefinition('action_provider');
    $typeFactoryDefinition->addMethodCall('addAction', ['XcmGetOrCreate', 'Civi\Xcm\ActionProvider\Action\ContactGetOrCreate', E::ts('Extended Contact Matcher (XCM)'), [
        AbstractAction::SINGLE_CONTACT_ACTION_TAG,
        AbstractAction::DATA_RETRIEVAL_TAG
    ]]);
  }

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
    return new SpecificationBag([
        new Specification('contact_type', 'String', E::ts('Contact Type'), false, 'Individual', null, ['Individual', 'Organization', 'Household'], false),
        new Specification('first_name', 'String', E::ts('First Name'), false, null, null, null, false),
        new Specification('last_name', 'String', E::ts('Last Name'), false, null, null, null, false),
        new Specification('organization_name', 'String', E::ts('Organisation Name'), false, null, null, null, false),
    ]);
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
    $params = $parameters->toArray();
    // override if necessary
    foreach (['xcm_profile', 'contact_type'] as $field_name) {
      if (empty($params[$field_name])) {
        $params[$field_name] = $this->configuration->getParameter($field_name);
      }
    }

    // execute
    $result = \civicrm_api3('Contact', 'getorcreate', $params);
    $output->setParameter('contact_id', $result['id']);
  }
}