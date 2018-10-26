# Configuration

Before using the Extended Contact Matcher (XCM) on your system, you should
inspect your contact data and think about your common scenarios to find the most
suitable matching rules. Also, you should revisit the XCM configuration each
time you add custom fields to contact entities in order for them to be updated
or filled correctly, when a contact update using XCM's API action is performed.

Find the XCM configuration form on CiviCRM's *Administration Console* within the
*System settings* section (civicrm/admin/setting/xcm).

The following explains all configuration options.

## General Options

![Extended Contact Matcher (XCM) configuration form - General options](img/xcm-configuration-general.png "Extended Contact Matcher (XCM) configuration form - General options")

### Of multiple matches, pick

This setting defines XCM's behavior when multiple contacts were found with the
given matching rules. The options are:

- *the oldest contact*
- *the newest contact*
- *none (create new contact)*

### Attribute comparison

This settings defines how matching rules consider case sensitivity. The options
are:

- *case-sensitive*: The values must be exactly the same, e.g. "John Doe" will
  not be treated equal to "john doe".
- *not case-sensitive*: The values may differ regarding upper and lower case
  variations being the same value, e.g. "John Doe" will be treated equal to
  "john doe".

### Default location type

This settings defines the default location type used for postal and e-mail
addresses as well as phone numbers. All active location types are available as
options.

### Fill fields

Within this setting, you may define which contact fields XCM should fill for
existing matched contacts, when they are currently empty and a value has been
submitted. All core contact fields are available as well as all custom fields
for contact entities.

### Fill multi-value field values

This setting defines whether new values for multi-value fields should be added
to existing field values. This is different from the previous setting in that
not only empty fields will be filled, but existing values will be merged with
newly submitted ones. Note that existing values will never be removed.

### Fill details

This setting defines whether *Phone*, *Email* and *Website* entities should be
created when the matched contact does not have them already. As those values can
be any string, input should be normalised to avoid differently spelled
duplicates of e.g. phone numbers.

### Make new detail primary

This setting defines whether one of the created details should be primary, e.g.
a submitted e-mail address be the primarily used e-mail address for the matched
contact. This setting should be handled with care, as misspelled values may lead
to confident e-mail being sent to a wrong e-mail address.

### Fill address

This setting defines when to create an *Address* entity record for the matched
contact. The settings are:

- *Never*: No address will be added to the contact.
- *If contact has no address*: Addresses will only be added to the contact if
  they do not have an address at all.
- *If contact has no address of that type*: Addresses will only be added to the
  contact if they do not have one of the given location type already.


## Matching Rules

![Extended Contact Matcher (XCM) configuration form - Matching rules](img/xcm-configuration-matching-rules.png "Extended Contact Matcher (XCM) configuration form - Matching rules")

This section defines the rules XCM will use for matching contacts and also their
processing order.

XCM provides some common matching rules itself. Custom rules may be defined as
deduplicate rules on *Contacts* → *Find and Merge Duplicate Contacts*.

XCM will always start with the first rule and go on to the next one if there was
no distinct result.

If even the last rule does not yield a distinct contact, the
*Of multiple matches, pick* setting will decide which contact to use.

This configuration is highly dependent on your scenarios and data.

### Example matching rules

- If you have an external system with its own identifiers for contact records
  and want to synchronise them regularly using XCM, you may define an
  "External ID only" rule as the first one, which should match most of the time.
- If you want to merge data from newsletter subscription forms, where users,
  additionally to their e-mail address, will or will not provide their names,
  you may define a *first name and (any) email* rule, and as a fallback, a
  *Email only (any email)* rule, to get more accurate results when people share
  their e-mail address.
- If you use CiviSEPA or CiviBanking, and most of your contacts have a bank
  account recorded within the system, you may use XCM's *Birthday and IBAN*
  rule.


## Matched and created contacts

![Extended Contact Matcher (XCM) configuration form - Matched contacts](img/xcm-configuration-matched-contacts.png "Extended Contact Matcher (XCM) configuration form - Matched contacts")
![Extended Contact Matcher (XCM) configuration form - Created contacts](img/xcm-configuration-created-contacts.png "Extended Contact Matcher (XCM) configuration form - Created contacts")

Within this section, you decide whether matched and created contacts should be

- added to a group
- given a tag
- assigned to an activity

This is useful for keeping track of contact updates or creations done by XCM for
manual review.

## Duplicates activity

![Extended Contact Matcher (XCM) configuration form - Duplicates activity](img/xcm-configuration-duplicates-activity.png "Extended Contact Matcher (XCM) configuration form - Suplicates activity")

If XCM finds more than one matching contact, this section lets you define that
an activity be created, connecting all matched contacts as potential duplicates.

- *Generate Duplicates Activity*: Select an activity type.
- *Activity Subject*: Enter the subject of the activity.

## Difference handling

![Extended Contact Matcher (XCM) configuration form - Difference handling](img/xcm-configuration-difference-handling.png "Extended Contact Matcher (XCM) configuration form - Difference handling")

This section defines, what to do when a contact has been identified by XCM,
submitted data was different to the contact's current state, and thode different
properties have not been changed by XCM.

### Diff Activity

XCM provides the option to create a *Diff Activity* for processing differences,
which will contain an overview of the differing attributes for the contact and
be linked to the contact.

Several options are configurable for the activity:

- *Activity Type*
- *Subject*
- *Location type*
- *Bump old address to type*
- *Diff processing helper*

For more enhanced difference processing, you may use the
[i3val](https://github.com/systopia/be.aivl.i3val) extension to get a user
interface for manually stepping through differences and easily processing them
(e.g. updating contacts and communication details).
