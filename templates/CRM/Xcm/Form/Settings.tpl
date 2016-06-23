{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

<div>
  <h3>{ts domain="de.systopia.xcm"}General Options{/ts}</h3>
  <div class="crm-section">
    <div class="label">{$form.picker.label}</div>
    <div class="content">{$form.picker.html}</div>
    <div class="clear"></div>
  </div>
</div>  

<div>
  <h3>{ts domain="de.systopia.xcm"}Matching Rules{/ts}</h3>
  {foreach from=$rule_idxs item=rule_idx}
    {capture assign=dropdown_name}rule_{$rule_idx}{/capture}
    <div class="crm-section">
      <div class="label">{$form.$dropdown_name.label}</div>
      <div class="content">{$form.$dropdown_name.html}</div>
      <div class="clear"></div>
    </div>
  {/foreach}
</div>


<div>
  <h3>{ts domain="de.systopia.xcm"}Matched Contacts{/ts}</h3>
  <div class="crm-section">
    <div class="label">{$form.matched_add_group.label}</div>
    <div class="content">{$form.matched_add_group.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.matched_add_tag.label}</div>
    <div class="content">{$form.matched_add_tag.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.matched_add_activity.label}</div>
    <div class="content">{$form.matched_add_activity.html}</div>
    <div class="clear"></div>
  </div>
</div>

<div>
  <h3>{ts domain="de.systopia.xcm"}Created Contacts{/ts}</h3>
  <div class="crm-section">
    <div class="label">{$form.created_add_group.label}</div>
    <div class="content">{$form.created_add_group.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.created_add_tag.label}</div>
    <div class="content">{$form.created_add_tag.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.created_add_activity.label}</div>
    <div class="content">{$form.created_add_activity.html}</div>
    <div class="clear"></div>
  </div>
</div>
{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
