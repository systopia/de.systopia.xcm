<?php

declare(strict_types = 1);

class CRM_Xcm_Matcher_ExternalIdentifierOnlyMatcher extends CRM_Xcm_Matcher_SingleAttributeMatcher {

  public function __construct() {
    parent::__construct('external_identifier', 'Contact', ['external_identifier']);
  }

}
