<?php
// bootstrap civicrm environment
require_once '../sites/all/modules/civicrm/civicrm.config.php';
require_once '../sites/all/modules/civicrm/CRM/Core/Config.php';
$civicrm_config = CRM_Core_Config::singleton();

//Create connection
function connect_to_mysql($server,$username,$password,$db) {
  $conn = new mysqli($server, $username, $password, $db);

  if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
  }

  // Set charset of connection
  if (!$conn->set_charset('utf8')) {
    die('Error setting charset to UTF8');
  }
  return $conn;
}

$conn = connect_to_mysql('','','','');

/** *******************
* Change 1: Create new sector: Hospitality: hotels, restaurants & catering
* Change 2: Add new list of area's of expertise to sector 'Hospitality: hotels, restaurants & catering'
* Change 3: Collect existing area's of expertise from contacts
* Change 4: Create new sector: Hospitality: education
* Change 5: Add new list of area's of expertise to sector Hospitality: education
* Change 6: Put all active contacts from old sectors 'Hotels' & 'Hospitality: catering, restaurants & events' to new sector 'Hospitality: hotels, restaurants & catering'
* Change 7: Now close the old hotels sector for all contacts
* Change 8: Close sectors 'Hospitality: catering, restaurants & events' | 'Hospitality: large hotels (>25fte)' | 'Hospitality: small hotels (<25fte)' | 'Hotels'
* Change 9: Update civicrm_segment_tree
* |
* |-> Dit is het lijstje met stappen die moeten gebeuren
*
* [Geen wijzigingen aan: Hospitality: tourism & recreational services]
*
* Excel formula for name:
* =TRIM(LOWER(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(A1;":";"");".";"");" (";"_");") ";"_");" & ";"_");", ";"_");"(";"_");")";"_");" ";"_");" ";"_");"/";"_");"-";"_");"   ";"")))
*
* array(
*  'old_sector_label' => array('new_sector_name', 'new_sector_label')
* )
*
* key == area of expertise label to close, value == aoe name => aoe value to add for new sector hospitality
***********************/

/** Get all users currently in old sector **/
$query_hotels = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hotels','Hospitality: catering, restaurants & events')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hotels','Hospitality: catering, restaurants & events')))
  )
) AND is_active = 1") or die($conn->error);

$hotels_contacts_to_reenter = array();

while($row = $query_hotels->fetch_assoc()){
  $hotels_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

/** Reorder array to have role of contact **/
$hotels_contacts = array();
foreach($hotels_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      $hotels_contacts[$contact_id][$sector_id] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
    }
  }
}


/** Change 1: Create new sector: Hospitality: hotels, restaurants & catering ****************/
$new_hospitality_hotels_sector_id = NULL;

$params = array(
  'version' => 3,
  'sequential' => 1,
  'name' => 'hospitality_hotels_restaurants_catering',
  'label' => 'Hospitality: hotels, restaurants & catering',
  'is_active' => 1
);
$result = civicrm_api('Segment', 'create', $params);

$new_hospitality_hotels_sector_id = $result['id'];
/** End Change 1 **************************************/



/** Change 2: Sector Hospitality: hotels, restaurants & catering: Add new list of area's of expertise to sector 'Hospitality: hotels, restaurants & catering' ****************/
/** Area's of expertise new sector Hospitality: hotels, restaurants & catering **/

/** Sector Hospitality: catering, restaurants & events => Hospitality: hotels, restaurants & catering **/
$old_to_new_areas_of_expertise_hrc = array(
  array('catering_management' => 'Catering Management'),
  array('event_management' => 'Event Management'),
  array('food_beverage_services___kitchen' => 'Food & Beverage Services - Kitchen'),
  array('food_beverage_services___restaurant_bar' => 'Food & Beverage Services - Restaurant/Bar'),
  array('food_safety_and_haccp' => 'Food Safety and HACCP'),
  array('hospitality_coaching' => 'Hospitality Coaching'),
  array('hotel_management' => 'Hotel Management'),
  array('kitchen_management' => 'Kitchen Management'),
  array('marketing_sales_for_catering' => 'Marketing & Sales for Catering'),
  array('marketing_sales_for_hotels_restaurants' => 'Marketing & Sales for Hotels/Restaurants'),
  array('meetings_incentives_conferences_exhibitions_mice_' => 'Meetings, Incentives, Conferences & Exhibitions (MICE)'),
  array('yield_management' => 'Yield Management'),
);

/** Now insert new area's of expertise under new sector Hospitality: hotels, restaurants & catering */
foreach($old_to_new_areas_of_expertise_hrc as $key => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$new_hospitality_hotels_sector_id."',1)") or die($conn->error);
    }
  }
}
/** End Change 2 ********************************************************************************************************************************/



/** Change 3: Collect existing area's of expertise from contacts *********************************************************************************/

//Alle contacten in sectors Hotels & Hospitality: catering, restaurants & events
//Hier moet iets als:
// foreach contact where one of sectors is_active == 1, add new segment_id water for contact and put rest on is_active = 0

/** First collect all contacts currently in sectors Hospitality: catering, restaurants & events | Hotels **/
$query_hotels = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: catering, restaurants & events','Hotels')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: catering, restaurants & events','Hotels')))
  )
) AND end_date IS NULL") or die($conn->error);

$hotels_contacts_to_reenter = array();

while($row = $query_hotels->fetch_assoc()){
  $hotels_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

$hotels_contacts = array();
foreach($hotels_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      $hotels_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
    }
  }
}
/** End Change 3 ********************************************************************************************************************************/


/** Change 4: Create new sector: Hospitality: education ********************************************************************************************/
$new_hospitality_education_sector_id = NULL;

$params = array(
  'version' => 3,
  'sequential' => 1,
  'name' => 'hospitality_education',
  'label' => 'Hospitality: education',
  'is_active' => 1
);
$result = civicrm_api('Segment', 'create', $params);

$new_hospitality_education_sector_id = $result['id'];
/** End Change 4 ********************************************************************************************************************************/


/** Change 5: Sector Hospitality: education: Add new list of area's of expertise to sector 'Hospitality: education' ***********************************/
/** Area's of expertise new sector Hospitality: education **/
$old_to_new_areas_of_expertise_he = array(
  array('hotel_education' => 'Hotel education'),
  array('restaurant_education' => 'Restaurant education'),
  array('catering_education' => 'Catering education'),
  array('event_education' => 'Event education'),
  array('tourism_education' => 'Tourism education'),
  array('recreational_services_education' => 'Recreational services education')
);

/** Now insert new area's of expertise under new sector Hospitality: education */
foreach($old_to_new_areas_of_expertise_he as $key => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$new_hospitality_education_sector_id."',1)") or die($conn->error);
    }
  }
}
/** End Change 5 ********************************************************************************************************************************/


/** Change 6: Put all contacts from old sectors 'Hotels' & 'Hospitality: catering, restaurants & events' to new sector 'Hospitality: hotels, restaurants & catering' ************************/

/**
* Determine the right role for each hotels/hospitality contact
* This is neccessary because some users had different roles in these sectors.
* To merge these, we first check 'Recruitment Team Member' then 'Sector Coordinator', then 'Expert' and then 'Customer'
* in this order of importance.
*
* After that, insert the new Hospitality sector for these contacts.
*
*/
foreach($hotels_contacts as $cid => $sector) {
  $determine_role = array('main_role'=>'','rtm_is_main'=>0,'sc_is_main'=>0,'expert_is_main'=>0,'customer_is_main'=>0,'role_value_rtm'=>NULL,'role_value_sc'=>NULL,'role_value_expert'=>NULL,'role_value_customer'=>NULL);

  /** Determine right user role for sector */
  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!isset($hotels_contacts[$cid][$sector_id]['role_value'])){
        $hotels_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Recruitment Team Member') {
        $determine_role['role_value_rtm'] = $sec_details['role_value'];
        $hotels_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

        if($sec_details['is_main'] == 1) {
          $determine_role['rtm_is_main'] = 1;

        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($hotels_contacts[$cid][$sector_id]['role_value'])){
        $hotels_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator') {
        $determine_role['role_value_sc'] = $sec_details['role_value'];
        $hotels_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_is_main'] = 1;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($hotels_contacts[$cid][$sector_id]['role_value'])){
        $hotels_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Expert') {
        $determine_role['role_value_expert'] = $sec_details['role_value'];
        $hotels_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

        if($sec_details['is_main'] == 1) {
          $determine_role['expert_is_main'] = 1;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($hotels_contacts[$cid][$sector_id]['role_value'])){
        $hotels_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Customer') {
        $determine_role['role_value_customer'] = $sec_details['role_value'];
        $hotels_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

        if($sec_details['is_main'] == 1) {
          $determine_role['customer_is_main'] = 1;
        }
      }
    }
  }

  $determine_role['main_role'] = '';

  if($determine_role['rtm_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_rtm'];
  } else if($determine_role['sc_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_sc'];
  } else if($determine_role['expert_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_expert'];
  } else if($determine_role['customer_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_customer'];
  }

  $hotels_contacts[$cid]['role'] = $determine_role;

  /** Insert new hospitality sector for contact */
  if(!empty($determine_role['main_role'])){
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_hospitality_hotels_sector_id."','".$determine_role['main_role']."',CURDATE(),NULL,1,1)") or die($conn->error);
  }

  if(!empty($determine_role['role_value_rtm']) && $determine_role['role_value_rtm'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_hospitality_hotels_sector_id."','".$determine_role['role_value_rtm']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc']) && $determine_role['role_value_sc'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_hospitality_hotels_sector_id."','".$determine_role['role_value_sc']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_expert']) && $determine_role['role_value_expert'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_hospitality_hotels_sector_id."','".$determine_role['role_value_expert']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_customer']) && $determine_role['role_value_customer'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_hospitality_hotels_sector_id."','".$determine_role['role_value_customer']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
}
/** End Change 6 ********************************************************************************************************************************/


/** Change 7: Now close the old hotels sector for all contacts ************************************************************************************/
foreach($hotels_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $key => $sector) {
    $hotels_contacts_to_reenter[$cid][$key]['role'] = $hotels_contacts[$cid]['role'];
    foreach($sector as $sector_id => $state) {
      $conn->query("UPDATE civicrm_contact_segment SET `is_main` = '0', `is_active` = '0', `end_date` = CURDATE() WHERE contact_id = '".$cid."' AND segment_id = '".$sector_id."' AND is_active = 1") or die($conn->error);
    }
  }
}
/** End Change 7 ********************************************************************************************************************************/


/** Change 8: Now we need to close the old sectors: Hospitality: catering, restaurants & events | Hospitality: large hotels (>25fte) | Hospitality: small hotels (<25fte) | Hospitality: tourism & recreational services | Hotels | **/
$query_close_old_hospitality = $conn->query("SELECT id FROM civicrm_segment seg WHERE (
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: catering, restaurants & events')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: catering, restaurants & events')))
  OR
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: large hotels (>25fte)')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: large hotels (>25fte)')))
  OR
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: small hotels (<25fte)')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hospitality: small hotels (<25fte)')))
  OR
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hotels')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Hotels')))
)") or die($conn->error);

while($row = $query_close_old_hospitality->fetch_assoc()){
  $conn->query("UPDATE civicrm_segment SET `is_active` = '0' WHERE id = '".$row['id']."'") or die($conn->error);
}
/** End Change 8 ********************************************************************************************************************************/


/** Change 9: Update civicrm_segment_tree **/
$conn->query("TRUNCATE civicrm_segment_tree");
if($query_result = $conn->query("SELECT * FROM civicrm_segment WHERE parent_id IS NULL ORDER BY label")) {
  while($row = $query_result->fetch_assoc()){
    $main_sectors[] = $row['id'];

    $query_result2 = $conn->query("SELECT * FROM civicrm_segment WHERE parent_id = ".$row['id']." ORDER BY label");
    while($row2 = $query_result2->fetch_assoc()){
      $main_sectors[] = $row2['id'];
    }
    $query_result2->free();
  }
  $query_result->free();
}
foreach($main_sectors as $key => $value) {
  $conn->query("INSERT INTO civicrm_segment_tree (id) VALUES ('".$value."')") or die($conn->error);
}
/** End Change 9: Update civicrm_segment_tree **/

echo 'done';