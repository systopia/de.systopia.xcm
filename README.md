# Extended Contact Matcher (XCM)

Creates an API action `Contact.getorcreate` that will return a contact Id
for the input parameters. First a search is done for the given contact (see
settings in Installation section below), and if that's not conclusive a new
contact will be created.

Only the contact id is returned.

The settings page offers lots of options such as:

- determining how contacts are matched.
- recording an activity when contacts are created/updated
- adding contacts to groups or adding a tag.

## Installation

Install in the usual way. Then go to *Administer* → *Adminstration Console* →
*Xtended Contact Matcher Settings* (/civicrm/admin/setting/xcm) to visit the
settings panel.

**At the very least you need to visit that settings page and add a rule, or the
extension will always create new contacts.**

## Usage

Let's say this person is not in your database:

```php
<?php
$result = civicrm_api3('Contact', 'getorcreate', array(
  'first_name' => "Wilma",
  'last_name' => "Flintstone",
  'email' => "wilma@example.com",
));
// $result['id'] = 1234
```

They are now. Do the same thing again:

```php
<?php
$result = civicrm_api3('Contact', 'getorcreate', array(
  'first_name' => "Wilma",
  'last_name' => "Flintstone",
  'email' => "wilma@example.com",
));
// $result['id'] = 1234
```

They were found and no duplicate contact was created.

## Documentation
[docs.civicrm.org](https://docs.civicrm.org/xcm/en/latest/)

## We need your support
This CiviCRM extension is provided as Free and Open Source Software, 
and we are happy if you find it useful. However, we have put a lot of work into it 
(and continue to do so), much of it unpaid for. So if you benefit from our software, 
please consider making a financial contribution so we can continue to maintain and develop it further.

If you are willing to support us in developing this CiviCRM extension, 
please send an email to info@systopia.de to get an invoice or agree a different payment method. 
Thank you!
