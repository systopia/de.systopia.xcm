#!/bin/sh

l10n_tools="../civi_l10n_tools"

# prep JS file
cp js/process_diff.js tmp_process_diff.php

# run string extractions
${l10n_tools}/bin/create-pot-files-extensions.sh de.systopia.xcm ./ l10n

# cleanup
rm -f tmp_process_diff.php