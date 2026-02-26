<?php

class CRM_Xcm_Matcher_ExternalIdentifierMatcher extends CRM_Xcm_Matcher_SingleAttributeMatcher {

  public function __construct() {
    parent::__construct('external_identifier', 'Contact', ['external_identifier']);
  }

}