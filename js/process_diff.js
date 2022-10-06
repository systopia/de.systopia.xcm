/*-------------------------------------------------------+
| Extended Contact Matcher XCM                           |
| Copyright (C) 2016 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
|         N. Bochan (bochan@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * This function will be executed after the activity is loaded
 * and perform all the changes necessary
 */
CRM.$(function() {
  let ts = CRM.ts('de.systopia.xcm');

  // gather some data
  let ACTIVITY_TARGET_CONTACT_ID = CRM.$('input[name="target_contact_id"').val();
  
  let outer_table   = CRM.$('table.crm-info-panel');
  let details       = outer_table.find('tr.crm-activity-form-block-details td.view-value');
  let address_table = details.find('table');
  
  // add an extra table column, if not there yet...
  address_table.find('tbody tr[class^=xcm]').each(function(i) {
    let tr = CRM.$(this);
    let attribute          = tr.attr('class').substring(4); // TR class holds 'xcm-' and the attribute name
    let column_exists      = (tr.find('#mh_field_' + i).length > 0);
    let faulty_template    = (tr.find('[id^="mh_field_"]').length > 1);
    let contains_response  = (['updated', 'added'].indexOf(tr.find('#mh_field_' + i).text()) > -1);
    let contains_old_value = (tr.children(':nth-child(2)').text().length > 0);
    let contains_new_value = (tr.children(':nth-child(3)').text().length > 0);
    if (faulty_template) {
      console.log('row ' + i + ' is faulty! stopping.');
      return;
    }

    // change header (2) title
    address_table.find('thead tr').children(':nth-child(2)').text(ts('Old Value'));

    // add buttons, if this hasn't been processed yet
    if (!contains_response) {
      // add our colum if not there yet
      if (!column_exists) {
        tr.append('<td class="mh_btn_row" id="mh_field_' + i + '">');
      }

      let attribute_class = getAttributeClass(attribute);

      // add UPDATE button
      if (   (attribute_class == 'contact' && contains_new_value)
          || (attribute_class == 'phone'   && contains_new_value && contains_old_value)
          || (attribute_class == 'address' && contains_new_value && contains_old_value)) 
      {
        tr.find('#mh_field_' + i).append('<button type="button" class="mh_ov_button" id="mh_ov_btn_' + i + '">' + ts('Adopt') + '</button>');
      }

      // add ADD button
      if (   (attribute_class == 'phone'   && contains_new_value))
      {
        tr.find('#mh_field_' + i).append('<button type="button" class="mh_ad_button" id="mh_ad_btn_' + i + '">' + ts('Add') + '</button>');
      }
    }
  });

  // add button to table header, if the address is complete
  let address_data = getAddressData('new', true);
  if (address_data) {
    address_table.find('thead').append(
      '<button type="button" class="mh_ad_address_btn">' + ts('Add new address') + '</button>'
    );    
  }

  // add 'complete' button (after all AJAX calls finished)
  cj.ajax({
    context: document.body,
    success: function(){
      cj("div.ui-dialog-buttonset").append('<button id="mh_uimods_complete" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary" type="button" role="button"><span class="ui-button-icon-primary ui-icon ui-icon-check"></span><span class="ui-button-text">Finish</span></button>');
      cj('#mh_uimods_complete').click(completeActivity);
    }
  });

  // add button handlers
  address_table.find('.mh_ov_button').click(dispatchClick);
  address_table.find('.mh_ad_button').click(dispatchClick);
  address_table.find('.mh_ad_address_btn').click(dispatchClick);




  /*-------------------------------------------------------+
  | Workflow functions                                     |
  +--------------------------------------------------------*/

  function dispatchClick() {
    // find and disable button
    let btn = CRM.$(this);
    btn.prop("disabled", true);

    // the data object will be handed down the chain of handlers
    let data = {'button': btn};

    // DISPATCH event to the handlers:
    if (btn.hasClass("mh_ad_address_btn")) {
      // ADD (WHOLE) ADDRESS:
      addAddress(data);
    
    } else {
      // this is a button related to an individual row
      data['attribute']  = btn.parent().parent().attr('class').substring(4);
      data['left']       = cleanValue(btn.parent().parent().children(':nth-child(2)').text());
      data['right']      = cleanValue(btn.parent().parent().children(':nth-child(3)').text());
      data['old']        = data['left'];
      data['new']        = data['right'];
      data['modus']      = (btn.hasClass("mh_ov_button")?'edit':'add');
      data['attributes'] = [];

      let command = getAttributeClass(data['attribute']) + '-' + data['modus'];

      switch (command) {
        case 'phone-edit':
          editPhone(data);
          break;
        case 'phone-add':
          addPhone(data);
          break;
        case 'contact-edit':
          editContact(data);
          break;
        case 'address-edit':
          editAddress(data);
          break;
        default:
          console.log('attribute/action is not supported: ' + data['attribute']);
      }
    }
  }

  // simply set activity to "Complete" and click "close"
  function completeActivity() {
    // save changes
    CRM.api3('Activity', 'create', {
      'id': CRM.vars['de.systopia.xcm'].targetActivityId,
      'status_id': 2 // Complete
    }).done(
      function(result) {
        if (result.is_error) {
          onError(result.error_message, null);
        } else {
          let activity_id = CRM.vars['de.systopia.xcm'].targetActivityId;
          cj("#rowid" + activity_id + " td:nth-child(8)").text(ts("Completed"));
          cj("button[data-identifier=_qf_Activity_cancel]").click();
        }
      }
    );    
  }



  /**
   * HANDLER: edit an existing phone
   */
  function editPhone(data) {
    // we need both, old and new value to override
    if (data['old'].length == 0) {
      onError('Die alte Telefonnummer zum überschreiben kann nicht identifiziert werden. Bitte mit "hinzufügen" versuchen.', null);
      return;
    }
    if (data['new'].length == 0) {
      onError('Keine neue Telefonnummer angegeben.', null);
      return;
    }

    // get type ids
    let location_type_id = CRM.vars['de.systopia.xcm'].location_type_current_address;
    let phone_type_id    = ((data['attribute'] == 'phone') ?
      CRM.vars['de.systopia.xcm'].phone_type_phone_value :
      CRM.vars['de.systopia.xcm'].phone_type_mobile_value);

    // lookup phone with the old phone number
    CRM.api3('Phone', 'getsingle', {
      'contact_id':       ACTIVITY_TARGET_CONTACT_ID,
      'phone':            data['old'],
      'phone_type_id':    phone_type_id,
      'location_type_id': location_type_id,
    }).done(function(result) {
      if (result.is_error) {
        return onError(ts("The previous phone number couldn't be found. Most likely the contact was modified in the meantime. Automatic update of the phone number not possible."), null);
      } else {
        // hand the result to the update handler
        updatePhone(data, result);
      }
    });
  }


  /**
   * HANDLER: add a new phone entry
   */
  function addPhone(data) {
    // pseudo-constants
    let location_type_id = CRM.vars['de.systopia.xcm'].location_type_current_address;
    let phone_type_id    = ((data['attribute'] == 'phone') ?
      CRM.vars['de.systopia.xcm'].phone_type_phone_value :
      CRM.vars['de.systopia.xcm'].phone_type_mobile_value);

    // aggregate the gathered data
    let phone_data = {};
    phone_data['contact_id']       = ACTIVITY_TARGET_CONTACT_ID;
    phone_data['phone']            = data['new'];
    phone_data['is_primary']       = 1;
    phone_data['phone_type_id']    = phone_type_id;
    phone_data['location_type_id'] = location_type_id;

    // save changes
    CRM.api3('Phone', 'create', phone_data).done(function(result) {
      if (result.is_error) {
        onError(result.error_message, null);
      } else {
        updateActivity(data);
      }
    });
  }


  /**
   * HANDLER: edit a contact
   */
  function editContact(data) {
    // look up data (like prefix->prefix_id)
    let request   = lookup(data['attribute'], data['new']);
    request['id'] = ACTIVITY_TARGET_CONTACT_ID;

    // save changes
    CRM.api3('Contact', 'create', request).done(function(result) {
      if (result.is_error) {
        onError(result.error_message, null);
      } else {
        updateActivity(data);
      }
    });
  }

  /**
   * HANDLER: edit an address
   */
  function editAddress(data) {
    address_data = getAddressData('current', false);
    address_data['contact_id'] = ACTIVITY_TARGET_CONTACT_ID;

    // lookup address with the old data
    CRM.api3('Address', 'getsingle', address_data).done(function(result) {
      if (result.is_error) {
        return onError(ts("The previous address couldn't be found. Most likely the contact was modified in the meantime. Automatic update of the address not possible."), null);
      } else {
        // hand the result to the update handler
        updateAddress(data, result);
      }
    });
  }

  /**
   * HANDLER: add a new address
   */
  function addAddress(data) {
    address_data = getAddressData('new', true);
    if (!address_data) {
      return onError(ts("The address data submitted here is insufficient for the creation of a new address."), null);
    }

    // create address request
    address_data['location_type_id'] = CRM.vars['de.systopia.xcm'].location_type_current_address;
    address_data['contact_id'] = ACTIVITY_TARGET_CONTACT_ID;
    address_data['is_primary'] = 1;

    // lookup address with the old data
    CRM.api3('Address', 'create', address_data).done(function(result) {
      if (result.is_error) {
        return onError(result.error_message, null);
      } else {
        // next step is to demote the old address
        findAndDemoteOldAddress(data, result);
      }
    });
  }



  /**
   * SUB-HANDLER: after adding the new address, identify the old one and demote it
   */
  function findAndDemoteOldAddress(data) {
    address_data = getAddressData('current', false);
    if (!address_data) {
      // there is no old address => that's it
      return updateActivity(data);
    
    } else {
      // find the old address
      // address_data['location_type_id'] = CRM.vars['de.systopia.xcm'].location_type_current_address;
      address_data['contact_id'] = ACTIVITY_TARGET_CONTACT_ID;

      // lookup address with the old data
      CRM.api3('Address', 'getsingle', address_data).done(function(result) {
        if (result.is_error) {
          return onError(ts("The current primary address couldn't be identified, contact was probably modified in the meantime. Automatic processing not possible."), null);
        } else {
          // next step is to add the new address
          demoteOldAddress(data, result);
        }
      });
    }
  }

  /**
   * SUB-HANDLER: demote the old address to 'old'
   */
  function demoteOldAddress(data, address_data) {
    if (CRM.vars['de.systopia.xcm'].location_type_old_address) { // if this is not there, it's disabled.
      // lookup address with the old data
      CRM.api3('Address', 'create', {
        'id': address_data.id,
        'is_primary': 0,
        'location_type_id': CRM.vars['de.systopia.xcm'].location_type_old_address
      }).done(function(result) {
        if (result.is_error) {
          return onError(ts("The previously used primary address couldn't be demoted."), null);
        } else {
          // next step is to add the new address
          data['attributes'] = ['street_address', 'city', 'postal_code', 'country'];
          updateActivity(data);
        }
      });
    }
  }


  /**
   * SUB-HANDLER: update an existing phone
   */
  function updatePhone(data, phone_data) {
    // update phone
    CRM.api3('Phone', 'create', {
      'id':     phone_data.id,
      'phone':  data['new'],
    }).done(function(result) {
      if (result.is_error) {
        return onError(result.error_message, null);
      } else {
        // hand the result to the update handler
        updateActivity(data);
      }
    });
  }


  /**
   * SUB-HANDLER: update table after done
   */
  function updateActivity(data) {
    let title = ((data['modus'] == 'edit') ? ts('updated') : ts('added'));


    // remove buttons and store result ("updated" or "added")
    address_table.find('tbody tr[class^=xcm]').each(function(i) {
      let row = CRM.$(this);
      let attribute = row.attr('class').substring(4);
      let btn_row   = row.children('td.mh_btn_row');

      if (  attribute == data['attribute'] 
         || data['attributes'].indexOf(attribute) > -1) {
        btn_row.empty();
        btn_row.append(title);
      }
    });

    let divContent = details.find('span.crm-frozen-field').clone();
    divContent.find('table tr td.mh_btn_row').each(clean);
    divContent = divContent.html();

    // prepare object
    let patch = {};
    patch['id'] = CRM.vars['de.systopia.xcm'].targetActivityId;
    patch['details'] = divContent;

    // save changes
    CRM.api3('Activity', 'create', patch).done(
      function(result) {
        if (result.is_error) {
          onError(result.error_message, null);
        } else {

          CRM.alert(ts("Attribute updated."), ts("Success."), 'success');
        }
      }
    );
  }


  /**
   * SUB-HANDLER: update an address
   */
  function updateAddress(data, result) {
    // use the old data...
    address_data       = getAddressData('current', false);
    address_data['id'] = result.id;

    // ..but change the one attribute
    address_data[data['attribute']] = data['new'];

    // save changes
    CRM.api3('Address', 'create', address_data).done(function(result) {
      if (result.is_error) {
        onError(result.error_message, null);
      } else {
        updateActivity(data);
      }
    });
  }









  /*-------------------------------------------------------+
  | Helper functions                                       |
  +--------------------------------------------------------*/

  /**
   * handle Errors that occurred during workflow
   */
  function onError(message, metadata) {
    CRM.alert(message, ts('Error'), 'error');
  }

  /**
   * get the attribute class ('phone', 'address', 'contact')
   * from the attribute name
   */
  function getAttributeClass(attribute_name) {
    switch (attribute_name) {
      case 'street_address':
      case 'city':
      case 'postal_code':
      case 'supplemental_address_1':
      case 'supplemental_address_2':
      case 'country':
      case 'country_id':
        return 'address';
      case 'phone':
      case 'mobile':
        return 'phone';
      case 'last_name':
      case 'first_name':
      case 'gender':
      case 'formal_title':
      case 'prefix':
        return 'contact';
      default:
        return 'invalid';
    }
  }

  /*
   * Clears html content of an element if it contains anything except
   * valid response values ("updated"/"added")
   */
  function clean() {
    let td = CRM.$(this);
    let is_occupied = (td.length > 0);
    let contains_response = (['updated', 'added'].indexOf(td.text()) > -1);
    if (is_occupied && !contains_response) {
      td.empty();
    }
    td.parent().parent().parent().parent().find('.mh_ad_address_btn').remove();
  }

  /*
   * some values need to be resolved
   */
  function lookup(name, value) {
    if (name == 'gender') {
      if (parseInt(value, 10) > 0) {
        // legacy: sometimes, it's still the gender_id
        return {'gender_id': parseInt(value, 10)};
      } else {
        let gender_map = CRM.vars['de.systopia.xcm'].gender_names;
        return {'gender_id': gender_map[value]};        
      }
    
    } else if (name == 'prefix') {
      let prefix_map = CRM.vars['de.systopia.xcm'].prefix_names;
      return {'prefix_id': prefix_map[value]};
    
    } else if (name == 'country') {
      let country_map = CRM.vars['de.systopia.xcm'].country_names;
      return {'country_id': country_map[value]};
    
    } else {
      return {name: value};
    }
  }

  /**
   * Get the address data from the form. Three modes:
   * 'new'     - only the new values
   * 'old'     - only the old values
   * 'current' - old values, unless already updated with new ones
   */
  function getAddressData(mode, complete_or_nothing) {
    // iterate through all rows and collect the address data
    let address_data = {};
    address_table.find('tbody tr[class^=xcm]').each(function(i) {
      let row = CRM.$(this);
      let attribute = row.attr('class').substring(4);
      let old_value = cleanValue(row.children(':nth-child(2)').text());
      let new_value = cleanValue(row.children(':nth-child(3)').text());

      if ('address' == getAttributeClass(attribute)) {
        if (mode == 'new') {
          address_data[attribute] = new_value;
        } else if (mode == 'old') {
          address_data[attribute] = old_value;
        } else if (mode == 'current') {
          // now this depends on whether this attribute was already updated
          if ('updated' == row.children('td.mh_btn_row').text() || ts('updated') == row.children('td.mh_btn_row').text()) {
            address_data[attribute] = new_value;
          } else {
            address_data[attribute] = old_value;
          }
        }
      }
    });

    // look up country
    if ('country' in address_data) {
      let tuple = lookup('country', address_data['country']);
      for (let key in tuple) {
        address_data[key] = tuple[key];
      }
    }

    // if complete_or_nothing, check if all mandatory attributes are present
    if (complete_or_nothing) {
      let mandatory_attributes = ['street_address', 'city', 'postal_code'];
      for (let i = mandatory_attributes.length - 1; i >= 0; i--) {
        if (!(mandatory_attributes[i] in address_data)) {
          return null;
        }
      }
    }
    return address_data;
  }

  /**
   * Strip certain data from the values
   *  - trailing/leading empty characters
   *  - trailing "(location_type)" info
   *
   * @param value
   */
  function cleanValue(value)
  {
    // remove trailing "(location type)"
    value = value.replace(/\([A-Za-z ]*\)/g, '');

    // trim
    value = cj.trim(value);

    return value;
  }


}); // end of wrapper function
