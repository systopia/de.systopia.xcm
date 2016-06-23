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
<h3>{ts 1=$existing_contact.display_name domain=de.systopia.xcm}Differing attributes for contact "%1" submitted{/ts}</h3>
<table>
  <thead>
    <th>{ts domain=de.systopia.xcm}Attribute{/ts}</th>
    <th>{ts domain=de.systopia.xcm}Recorded Value{/ts}</th>
    <th>{ts domain=de.systopia.xcm}Submitted Value{/ts}</th>
  </thead>

  <tbody>
  {foreach from=$differing_attributes item=attribute}
    <tr>
      <td>{$attribute}</td>
      <td>{$existing_contact.$attribute}</td>
      <td>{$submitted_data.$attribute}</td>
    </tr>
  {/foreach}
  </tbody>
</table>
