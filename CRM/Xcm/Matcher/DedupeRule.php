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

declare(strict_types = 1);

/**
 *
 * This will execute a matching process based on the configuration,
 * employing various matching rules
 *
 */
class CRM_Xcm_Matcher_DedupeRule extends CRM_Xcm_MatchingRule {

  protected int $dedupeGroupId;

  protected string $dedupeGroupContactType;

  public function __construct(int $dedupe_group_id) {
    $this->dedupeGroupId = $dedupe_group_id;
    $dedupeGroup = \Civi\Api4\DedupeRuleGroup::get(FALSE)
      ->addSelect('contact_type')
      ->addWhere('id', '=', $dedupe_group_id)
      ->execute()
      ->single();
    $this->dedupeGroupContactType = $dedupeGroup['contact_type'];
  }

  public function matchContact(&$contact_data, $params = NULL) {
    // first check, if the contact_type is right
    $contact_type = $this->dedupeGroupContactType;

    if ($this->isContactType($contact_type, $contact_data)) {
      // it's the right type, let's go:
      $dedupeParams = CRM_Dedupe_Finder::formatParams($contact_data, $contact_type);
      $dedupeParams['check_permission'] = '';
      $finderParams = [
        'rule_group_id' => $this->dedupeGroupId,
        'contact_type' => $contact_type,
        'check_permission' => TRUE,
        'excluded_contact_ids' => [],
        'match_params' => $dedupeParams,
        'rule' => 'Unsupervised',
      ];
      $dupes = CRM_Contact_BAO_Contact::findDuplicates($finderParams, ['is_legacy_usage' => TRUE]);

      $contact_id = $this->pickContact($dupes);
      if ($contact_id) {
        return $this->createResultMatched($contact_id);
      }
    }

    return $this->createResultUnmatched();
  }

  /**
   * @return array<int, string>
   *   A key => title list of existing unsupervised dedupe rules.
   */
  public static function getRuleList(): array {
    /** @phpstan-var iterable<array{id: int, contact_type: string, used: string, title: string}> $dedupeRuleGroups */
    $dedupeRuleGroups = \Civi\Api4\DedupeRuleGroup::get(FALSE)
      ->addSelect('id', 'contact_type', 'used', 'title')
      ->execute();
    $list = [];
    foreach ($dedupeRuleGroups as $dedupeRuleGroup) {
      $list[$dedupeRuleGroup['id']]
        = "[{$dedupeRuleGroup['contact_type']}|{$dedupeRuleGroup['used']}] {$dedupeRuleGroup['title']}";
    }
    return $list;
  }

}
