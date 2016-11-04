# CiviCRM Extended Contact Matcher (XCM)

Creates an API action `Contact` » `getorcreate` that will return a contact Id
for the input parameters. First a search is done for the given contact (see
settings in Installation section below), and if that's not conclusive a new
contact will be created.

Only the contact id is returned.

The settings page offers lots of options such as:

- determining how contacts are matched.
- recording an activity when contacts are created/updated
- adding contacts to groups or adding a tag.

## Installation

Install in the usual way (e.g. download the [latest release](https://github.com/systopia/de.systopia.xcm/releases) 
or clone this repository into your CiviCRM extensions directory, then go to Administer » System Settings » Extensions,
click Refresh then it should show up and you can click Install.)

Then go to Administer » Adminstration Console » Xtended Contact Matcher Settings (/civicrm/admin/setting/xcm) to 
visit the settings panel. **At the very least you need to visit that settings page and add a rule, or the extension will always create new contacts.**

## Usage

Let's say this person is not in your database:

    $result = civicrm_api3('Contact', 'getorcreate', array(
            'first_name' => "Wilma",
            'last_name' => "Flintstone",
            'email' => "wilma@example.com",
            ));
    // $result['id'] = 1234

They are now. Do the same thing again:

    $result = civicrm_api3('Contact', 'getorcreate', array(
            'first_name' => "Wilma",
            'last_name' => "Flintstone",
            'email' => "wilma@example.com",
            ));
    // $result['id'] = 1234

They were found and no duplicate contact was created.
