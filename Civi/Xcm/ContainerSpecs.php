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

declare(strict_types = 1);

namespace Civi\Xcm;

use CRM_Xcm_ExtensionUtil as E;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContainerSpecs implements CompilerPassInterface {

  /**
   * Register this one action: XcmGetOrCreate
   */
  public function process(ContainerBuilder $container): void {
    if (!$container->hasDefinition('action_provider')) {
      return;
    }
    $typeFactoryDefinition = $container->getDefinition('action_provider');
    $typeFactoryDefinition->addMethodCall(
      'addAction',
      [
        'XcmGetOrCreate',
        'Civi\Xcm\ActionProvider\Action\ContactGetOrCreate',
        E::ts('Extended Contact Matcher (XCM)'),
        [
          \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
          \Civi\ActionProvider\Action\AbstractAction::DATA_RETRIEVAL_TAG,
        ],
      ]
    );
  }

}
