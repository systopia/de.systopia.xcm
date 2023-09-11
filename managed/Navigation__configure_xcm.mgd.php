<?php
/*
 * Copyright (C) 2023 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use CRM_Xcm_ExtensionUtil as E;

return [
  [
    'name' => 'Navigation__automation',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'domain_id' => 'current_domain',
        'label' => E::ts('Automation'),
        'name' => 'automation',
        'url' => NULL,
        'icon' => NULL,
        'permission' => [
          'administer CiviCRM',
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'Administer',
        'is_active' => TRUE,
        'has_separator' => 0,
      ],
      'match' => ['name', 'parent_id'],
    ],
  ],
  [
    'name' => 'Navigation__configure_xcm',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'domain_id' => 'current_domain',
        'label' => E::ts('Extended Contact Matcher (XCM)'),
        'name' => 'configure_xcm',
        'url' => 'civicrm/admin/setting/xcm',
        'icon' => NULL,
        'permission' => [
          'administer CiviCRM',
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'automation',
        'is_active' => TRUE,
        'has_separator' => 0,
      ],
      'match' => ['name', 'parent_id'],
    ],
  ],
];
