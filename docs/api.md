# API

The Extended Contact manager (XCM) provides two CiviCRM API actions
`getorcreate` and `createifnotexists` for the `Contact` entity, which both
perform similar functions:

- `getorcreate` *always* returns a contact ID.

- `createifnotexists` returns an array with the contact ID and whether it had to
   be created. It also has an option that prevents creating a contact if one
   cannot be found.

   ```
   [
     'contact_id'  => Int|NULL,
     'was_created' => TRUE|FALSE
   ]
   ```

The actions take any contact-related parameters and relates them to one
of these CiviCRM entities:

- *Contact*
- *Address*
- *Email*
- *Phone*
- *Website*

You may also submit custom field values with a key formatted as
<nobr>`<custom_group_name>.<custom_field_name>`</nobr>, which XCM will try to
resolve to the internal notation `custom_<custom_field_id>`.

The actions also take these optional parameters

- `xcm_profile` the name of the profile to use. Without it the default
  profile is used.

- `contact_type` without this the default for the profile is used.

- `match_only` **only for `createifnotexists`**. If set to truthy (e.g.
  `TRUE` or `1`) and no contact is found by the matching, no contact is
  created. In this case the return array will be  
   ```
   [ 'contact_id' => NULL, 'was_created' => FALSE ]
   ```

## Example

Assume a matching rule being configured containing one or multiple of

- first name
- last name
- e-mail address

Let's say this person is not in your database:

```php
<?php
$result = civicrm_api3('Contact', 'getorcreate', array(
  'first_name' => "John",
  'last_name' => "Doe",
  'email' => "john.doe@example.org",
  ));

// $result['id'] = 1234

// if createifnotexists was used instead of getorcreate:
// $result['contact_id'] == 1234;
// $result['was_created] == TRUE
```

They are now. Do the same thing again:

```php
<?php
$result = civicrm_api3('Contact', 'getorcreate', array(
  'first_name' => "John",
  'last_name' => "Doe",
  'email' => "john.doe@example.org",
  ));

// $result['id'] = 1234

// if createifnotexists was used instead of getorcreate:
// $result['contact_id'] == 1234;
// $result['was_created] == FALSE;
```

They were found and no duplicate contact was created.
