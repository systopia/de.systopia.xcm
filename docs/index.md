#Introduction

Importing contacts using the CiviCRM API is a usual task with a usual problem:
Duplicates. Each API action that wants to add or update contacts needs to look
up contacts prior to creating them to avoid duplicates. Each of those API
actions must therefore make assumptions which data duplicate matching should be
based on.

The Extended Contact Matcher extension (XCM) provides a new API action for
Contact entities that utilises configurable matching logic and merging behavior.

This makes importing contacts via the API more predictable and reduces
duplicates. Also, when matching rules for XCM change, each API action that makes
use of XCM, does not have to be adapted.

Extension developers are encouraged to make use of XCM's functionality by
relying on its API action `Contact.getorcreate`.
