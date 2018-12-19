# Contact Import

XCM provides a user interface for importing contacts using its API. This makes
sure your imported data is being matched using XCM's matching rules and merging
configuration.

## Import contacts

Find the XCM Contact Import form on *Contacts* → *Import contacts (XCM)*.

Currently, the import form takes a CSV file only, which has to be formatted as
follows:

- Field separator: `,`
- Column names in the first row
- Multi-value field values separated by: `,`
- Column names must match parameter names for XCM

There is no distinct parameter specification. The action takes any contact-
related parameters and relates them to one of these CiviCRM entities:

- *Contact*
- *Address*
- *Email*
- *Phone*
- *Website*

You may also submit custom field values with a key formatted as
<nobr>`<custom_group_name>.<custom_field_name>`</nobr>, which XCM will try to
resolve to the internal notation `custom_<custom_field_id>`.

## Error handling

You will receive error messages for the following cases:

- Missing import file
- File read failure
- Empty file
- Empty or duplicate column names
- Any CiviCRM API error

Rows with errors will be printed back to the user interface for identification
of what went wrong.
