<?php
/*-------------------------------------------------------+
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
+--------------------------------------------------------*/

use CRM_Xcm_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Xcm_Form_Import extends CRM_Core_Form {

  public function buildQuickForm() {
    $config = CRM_Core_Config::singleton();

    $uploadFileSize = self::formatUnitSize(
      $config->maxFileSize . 'm',
      TRUE
    );
    // Fetch uploadFileSize from php_ini when $config->maxFileSize is set to
    // "no limit".
    if (empty($uploadFileSize)) {
      $uploadFileSize = self::formatUnitSize(
        ini_get('upload_max_filesize'),
        TRUE
      );
    }
    $uploadSize = round(($uploadFileSize / (1024 * 1024)), 2);
    $this->assign('uploadSize', $uploadSize);
    $this->add(
      'File',
      'uploadFile',
      ts('Import Data File'),
      'size=30 maxlength=255',
      TRUE
    );
    $this->setMaxFileSize($uploadFileSize);
    $this->addRule(
      'uploadFile',
      ts('File size should be less than %1 MBytes (%2 bytes)',
        array(
          1 => $uploadSize,
          2 => $uploadFileSize,
        )
      ),
      'maxfilesize',
      $uploadFileSize
    );
    $this->addRule(
      'uploadFile',
      ts('Input file must be in CSV format'),
      'utf8File'
    );
    $this->addRule(
      'uploadFile',
      ts('A valid file must be uploaded.'),
      'uploadedfile'
    );

    $this->addElement('select',
        'profile_name',
        ts('Use Configuration Profile', array('domain' => 'de.systopia.xcm')),
        CRM_Xcm_Configuration::getProfileList(),
        array('class' => 'crm-select2 huge'));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // Export form elements.
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    if (isset($this->_submitFiles['uploadFile'])) {
      $uploadFile = $this->_submitFiles['uploadFile'];
    }
    else {
      CRM_Core_Session::setStatus(
        E::ts('No file was uploaded.'),
        E::ts('Import failure'),
        'no-popup'
      );
      return;
    }

    // TODO: Make separator configurable.
    $fieldSeparator = ',';

    $file = $uploadFile['tmp_name'];
    $filename = $uploadFile['name'];

    $fd = fopen($file, 'r');
    if (!$fd) {
      CRM_Core_Session::setStatus(
        E::ts('Could not read import file %1.', array(1 => $filename)),
        E::ts('Import failure'),
        'no-popup'
      );
      return;
    }
    if (filesize($file) == 0) {
      CRM_Core_Session::setStatus(
        E::ts('Import file %1 is empty. Please upload a valid file.', array(1 => $filename)),
        E::ts('Import failure'),
        'no-popup'
      );
      return;
    }

    // Support tab character as separator.
    if (strtolower($fieldSeparator) == 'tab' ||
      strtolower($fieldSeparator) == '\t'
    ) {
      $fieldSeparator = "\t";
    }

    $firstrow = fgetcsv($fd, 0, $fieldSeparator);

    $strtolower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
    $columns = array_map($strtolower, $firstrow);

    // Check for empty and duplicate column names.
    $duplicateColName = FALSE;
    if (count($columns) != count(array_unique($columns))) {
      $duplicateColName = TRUE;
    }
    if (in_array('', $columns) || $duplicateColName) {
      CRM_Core_Session::setStatus(
        E::ts('Empty or duplicate column names.'),
        E::ts('Import file format error'),
        'no-popup'
      );
      return;
    }

    $errors = array();
    $error_csv = implode($fieldSeparator, $firstrow);
    $success_count = 0;
    while ($record = fgetcsv($fd, 0, $fieldSeparator)) {
      try {
        // Separate multi-value field values with comma.
        foreach ($record as $column => &$value) {
          if (!empty($value) && CRM_Xcm_MatchingEngine::fieldIsMultivalue($firstrow[$column])) {
            $value = array_map('trim', explode(',', $value));
            if (count($value) == 1) {
              $value = $value[0];
            }
          }
        }
        $contact_data = array_combine($firstrow, $record);
        $contact_data['xcm_profile'] = $values['profile_name'];
        $result = civicrm_api3('Contact', 'getorcreate', $contact_data);
        if ($result['is_error']) {
          throw new Exception($result['error_message']);
        }
        else {
          $success_count += $result['count'];
        }
      }
      catch (Exception $exception) {
        $csv_record = implode($fieldSeparator, $record);
        $errors[] = '<p>' . $exception->getMessage() . '</p><pre>' . $csv_record . '</pre>';
        $error_csv .= "\n" . $csv_record;
      }
    }

    CRM_Core_Session::setStatus(
      E::ts('%1 contact(s) imported.', array(1 => $success_count)),
      '<p>' . E::ts('Import completed') . '</p>',
      'no-popup'
    );
    if (!empty($errors)) {
      CRM_Core_Session::setStatus(
        implode('', $errors)
        . '<p>' . E::ts('All rows with errors:') . '</p>'
        . '<pre>' . $error_csv . '</pre>',
        E::ts('Failed importing record(s)'),
        'no-popup'
      );
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * Copied from CiviCRM 4.7+
   *
   * @param string $size
   * @param bool $checkForPostMax
   *
   * @return int
   */
  public static function formatUnitSize($size, $checkForPostMax = FALSE) {
    // if the method exists in CiviCRM -> use that!
    if (method_exists('CRM_Utils_Number', 'formatUnitSize')) {
      return CRM_Utils_Number::formatUnitSize();
    }

    // doesn't exist -> we're probably on CiviCRM 4.6
    if ($size) {
      $last = strtolower($size{strlen($size) - 1});
      $size = (int) $size;
      switch ($last) {
        // The 'G' modifier is available since PHP 5.1.0

        case 'g':
          $size *= 1024;
        case 'm':
          $size *= 1024;
        case 'k':
          $size *= 1024;
      }

      if ($checkForPostMax) {
        $maxImportFileSize = self::formatUnitSize(ini_get('upload_max_filesize'));
        $postMaxSize = self::formatUnitSize(ini_get('post_max_size'));
        if ($maxImportFileSize > $postMaxSize && $postMaxSize == $size) {
          CRM_Core_Session::setStatus(ts("Note: Upload max filesize ('upload_max_filesize') should not exceed Post max size ('post_max_size') as defined in PHP.ini, please check with your system administrator."), ts("Warning"), "alert");
        }
        // respect php.ini upload_max_filesize
        if ($size > $maxImportFileSize && $size !== $postMaxSize) {
          $size = $maxImportFileSize;
          CRM_Core_Session::setStatus(ts("Note: Please verify your configuration for Maximum File Size (in MB) <a href='%1'>Administrator >> System Settings >> Misc</a>. It should support 'upload_max_size' as defined in PHP.ini.Please check with your system administrator.", array(1 => CRM_Utils_System::url('civicrm/admin/setting/misc', 'reset=1'))), ts("Warning"), "alert");
        }
      }
      return $size;
    }
  }

}
