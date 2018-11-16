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

<div>{ts domain="de.systopia.xcm"}This is a list of the different XCM configurations. You might be perfectly fine with just the default configuration, but if you're using XCM in multiple contexts, different configuration profiles might come in handy.{/ts}</div>

{* TODO: beautify *}
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
                <a href="{crmURL p="civicrm/admin/setting/xcm_profile" q="reset=1&pid=$pid"}">{ts domain="de.systopia.xcm"}edit{/ts}</a>
                <a href="{crmURL p="civicrm/admin/setting/xcm_profile" q="reset=1&clone=$pid"}">{ts domain="de.systopia.xcm"}copy{/ts}</a>
                {if !$profile.is_default}
                    <a href="{crmURL p="civicrm/admin/setting/xcm" q="xaction=delete&pid=$pid"}">{ts domain="de.systopia.xcm"}delete{/ts}</a>
                    <a href="{crmURL p="civicrm/admin/setting/xcm" q="xaction=setdefault&pid=$pid"}">{ts domain="de.systopia.xcm"}set default{/ts}</a>
                {/if}
            </td>
        </tr>
{/foreach}
    </tbody>
</table>