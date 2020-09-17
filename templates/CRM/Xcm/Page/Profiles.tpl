{*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2018 SYSTOPIA                            |
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

{capture assign=token_url}{crmURL p="civicrm/import/contact/xcm"}{/capture}
<div>{ts domain="de.systopia.xcm"}The "Extended Contact Matcher" allows you to map incoming contact data an existing contact, and even update some of the attributes right away. If the contact doesn't exist, a new contact will be created instead.{/ts}</div>
<div>{ts domain="de.systopia.xcm" 1=$token_url}The functionality can be accessed via the <code>Contact.getorcreate</code> API command, with the ContactGetOrCreate action provided, or via the <a href="%1">Contact Importer</a>.{/ts}</div>
<br/>

<div>{ts domain="de.systopia.xcm"}This is a list of the different XCM configurations. You might be perfectly fine with just the default configuration, but if you're using XCM in multiple contexts, different configuration profiles might come in handy.{/ts}</div>
<br/>

<table>
    <thead>
        <tr>
            <th>{ts domain="de.systopia.xcm"}Name{/ts}</th>
            <th>{ts domain="de.systopia.xcm"}ID{/ts}</th>
            <th>{ts domain="de.systopia.xcm"}Default?{/ts}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
{foreach from=$profiles item=profile}
        <tr>
            {capture assign=pid}{$profile.pid}{/capture}
            <td>{$profile.label}</td>
            <td><code>{$profile.pid}</code></td>
            <td>{if $profile.is_default}{ts domain="de.systopia.xcm"}YES{/ts}{else}{ts domain="de.systopia.xcm"}NO{/ts}{/if}</td>
            <td>
                <a class="action-item crm-hover-button no-popup" href="{crmURL p="civicrm/admin/setting/xcm_profile" q="reset=1&pid=$pid"}">{ts domain="de.systopia.xcm"}edit{/ts}</a>
                <a class="action-item crm-hover-button no-popup" href="{crmURL p="civicrm/admin/setting/xcm_profile" q="reset=1&clone=$pid"}">{ts domain="de.systopia.xcm"}copy{/ts}</a>
                {if !$profile.is_default}
                    <a class="action-item crm-hover-button no-popup" href="{crmURL p="civicrm/admin/setting/xcm" q="xaction=delete&pid=$pid"}">{ts domain="de.systopia.xcm"}delete{/ts}</a>
                    <a class="action-item crm-hover-button no-popup" href="{crmURL p="civicrm/admin/setting/xcm" q="xaction=setdefault&pid=$pid"}">{ts domain="de.systopia.xcm"}set default{/ts}</a>
                {/if}
            </td>
        </tr>
{/foreach}
    </tbody>
</table>