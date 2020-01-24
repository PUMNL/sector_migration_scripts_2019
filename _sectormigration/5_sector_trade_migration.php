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
* Change 1: Collect all users currently in old sector 'Retail: Business to Consumer' & 'Wholesale: Business to Business'
* Change 2: Create new sector: 'Trade & Supply Chain Management'
* Change 3: Add new list of area's of expertise to sector 'Trade & Supply Chain Management'
* Change 4: Collect existing area's of expertise from contacts
* Change 5: Put all active contacts from old sectors 'Retail: Business to Consumer' & 'Wholesale: Business to Business' to new sector 'Trade & Supply Chain Management'
* Change 6: Now close the old sectors 'Retail: Business to Consumer' & 'Wholesale: Business to Business' for all contacts
* Change 7: Close old sectors 'Retail: Business to Consumer' & 'Wholesale: Business to Business'
* Change 8: Update civicrm_segment_tree
* |
* |-> Dit is het lijstje met stappen die moeten gebeuren
*
* Excel formula for name:
* =TRIM(LOWER(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(A1;":";"");".";"");" (";"_");") ";"_");" & ";"_");"&";"_");", ";"_");"(";"_");")";"_");" ";"_");" ";"_");"/";"_");"-";"_");"   ";"")))
*
***********************/

/** Change 1: Collect all users currently in old sector 'Retail: Business to Consumer' & 'Wholesale: Business to Business' **/
$query_trade = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Retail: Business to Consumer','Wholesale: Business to Business')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Retail: Business to Consumer','Wholesale: Business to Business')))
  )
) AND is_active = 1") or die($conn->error);

$trade_contacts_to_reenter = array();

while($row = $query_trade->fetch_assoc()){
  $trade_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

/** Reorder array to have role of contact **/
$trade_contacts = array();
foreach($trade_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      $trade_contacts[$contact_id][$sector_id] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
    }
  }
}
/** End Change 1 **************************************/

/** Change 2: Create new sector: 'Trade & Supply Chain Management' ****************/
$new_trade_sector_id = NULL;

$params = array(
  'version' => 3,
  'sequential' => 1,
  'name' => 'trade_supply_chain_management',
  'label' => 'Trade & Supply Chain Management',
  'is_active' => 1
);
$result = civicrm_api('Segment', 'create', $params);

$new_trade_sector_id = $result['id'];
/** End Change 2 **************************************/



/** Change 3: Sector Trade & Supply Chain Management: Add new list of area's of expertise to sector 'Trade & Supply Chain Management' ****************/
/** Area's of expertise new sector Trade & Supply Chain Management **/
/* 
 * In case of old to new area's of expertise:
 * array(
 *  'old_sector_label' => array('new_sector_name', 'new_sector_label')
 * )
 * 
 * Or in case only new area's of expertise: * 
 * array(
 *   array('new_sector_name', 'new_sector_label')
 * )
 *
 * key == area of expertise label to close, value == aoe name => aoe value to add for new sector trade & supply management */
$old_to_new_areas_of_expertise_trade = array(
  array('background_online' => 'Background: online'),
  array('background_production' => 'Background: production'),
  array('background_retail' => 'Background: retail'),
  array('background_services' => 'Background: services'),
  array('background_wholesale' => 'Background: wholesale'),
  array('scm_business_processes_description_development_and_optimisation' => 'SCM: business processes, description, development and optimisation'),
  array('scm_cold_storage_and_distribution' => 'SCM: cold storage and distribution'),
  array('scm_it_systems_erp' => 'SCM: IT systems, ERP'),
  array('scm_logistics_distribution_transport' => 'SCM: logistics, distribution, transport'),
  array('scm_storage_and_stock_management' => 'SCM: storage and stock management'),
  array('scm_transport' => 'SCM: transport'),
  array('specialties_advertisement_branding' => 'Specialties: advertisement & branding'),
  array('specialties_auditing_certification_iso_haccp' => 'Specialties: auditing, certification, ISO, HACCP'),
  array('specialties_customer_services' => 'Specialties: customer services'),
  array('specialties_online_selling_channels_development' => 'Specialties: online selling channels: development'),
  array('specialties_online_selling_channels_use_for_sales_marketing' => 'Specialties: online selling channels: use for sales & marketing'),
  array('specialties_packaging' => 'Specialties: packaging'),
  array('specialties_sales_operations_planning_s_op_' => 'Specialties: sales & operations planning (S&OP)'),
  array('specialties_training_and_coaching_staff' => 'Specialties: training and coaching staff'),
  array('trade_crm_systems_and_other_it_infrastructures' => 'Trade: CRM systems and other IT infrastructures'),
  array('trade_export' => 'Trade: export'),
  array('trade_marketing_product_development_market_development' => 'Trade: marketing, product development, market development'),
  array('trade_sales' => 'Trade: sales'),
  array('trade_shop_management' => 'Trade: shop management'),
  array('trade_sourcing_buying' => 'Trade: sourcing & buying'),
);

/** Now insert new area's of expertise under new sector Trade & Supply Chain Management */
foreach($old_to_new_areas_of_expertise_trade as $key => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$new_trade_sector_id."',1)") or die($conn->error);
    }
  }
}
/** End Change 3 ********************************************************************************************************************************/



/** Change 4: Collect existing area's of expertise from contacts *********************************************************************************/

//Alle contacten in sectors 'Retail: Business to Consumer' & 'Wholesale: Business to Business'
//Hier moet iets als:
// foreach contact where one of sectors is_active == 1, add new segment_id for contact and put rest on is_active = 0

/** First collect all contacts currently in sectors: Retail: Business to Consumer | Wholesale: Business to Business **/
$query_retail_wholesale = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Retail: Business to Consumer','Wholesale: Business to Business')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Retail: Business to Consumer','Wholesale: Business to Business')))
  )
) AND end_date IS NULL") or die($conn->error);

$retailwholesale_contacts_to_reenter = array();

while($row = $query_retail_wholesale->fetch_assoc()){
  $retailwholesale_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

$retailwholesale_contacts = array();
foreach($retailwholesale_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      $retailwholesale_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
    }
  }
}
/** End Change 4 ********************************************************************************************************************************/


/** Change 5: Put all contacts from old sectors 'Retail: Business to Consumer' & 'Wholesale: Business to Business' to new sector 'Trade & Supply Chain Management' ************************/

/**
* Determine the right role for each contact
* This is neccessary because some users had different roles in these sectors.
* To merge these, we first check 'Recruitment Team Member' then 'Sector Coordinator', then 'Expert' and then 'Customer'
* in this order of importance.
*
* After that, insert the new 'Trade & Supply Chain Management' sector for these contacts.
*
*/
foreach($retailwholesale_contacts as $cid => $sector) {
  $determine_role = array('main_role'=>'','rtm_is_main'=>0,'sc_is_main'=>0,'expert_is_main'=>0,'customer_is_main'=>0,'role_value_rtm'=>NULL,'role_value_sc'=>NULL,'role_value_expert'=>NULL,'role_value_customer'=>NULL);

  /** Determine right user role for sector */
  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!isset($retailwholesale_contacts[$cid][$sector_id]['role_value'])){
        $retailwholesale_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Recruitment Team Member') {
        $determine_role['role_value_rtm'] = $sec_details['role_value'];
        $retailwholesale_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

        if($sec_details['is_main'] == 1) {
          $determine_role['rtm_is_main'] = 1;

        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($retailwholesale_contacts[$cid][$sector_id]['role_value'])){
        $retailwholesale_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator') {
        $determine_role['role_value_sc'] = $sec_details['role_value'];
        $retailwholesale_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_is_main'] = 1;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($retailwholesale_contacts[$cid][$sector_id]['role_value'])){
        $retailwholesale_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Expert') {
        $determine_role['role_value_expert'] = $sec_details['role_value'];
        $retailwholesale_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

        if($sec_details['is_main'] == 1) {
          $determine_role['expert_is_main'] = 1;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($retailwholesale_contacts[$cid][$sector_id]['role_value'])){
        $retailwholesale_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Customer') {
        $determine_role['role_value_customer'] = $sec_details['role_value'];
        $retailwholesale_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];

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

  $retailwholesale_contacts[$cid]['role'] = $determine_role;

  /** Insert new Trade & Supply Chain Management sector for contact */
  if(!empty($determine_role['main_role'])){
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_trade_sector_id."','".$determine_role['main_role']."',CURDATE(),NULL,1,1)") or die($conn->error);
  }

  if(!empty($determine_role['role_value_rtm']) && $determine_role['role_value_rtm'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_trade_sector_id."','".$determine_role['role_value_rtm']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc']) && $determine_role['role_value_sc'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_trade_sector_id."','".$determine_role['role_value_sc']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_expert']) && $determine_role['role_value_expert'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_trade_sector_id."','".$determine_role['role_value_expert']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_customer']) && $determine_role['role_value_customer'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_trade_sector_id."','".$determine_role['role_value_customer']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
}
/** End Change 5 ********************************************************************************************************************************/


/** Change 6: Now close the old sectors 'Retail: Business to Consumer' & 'Wholesale: Business to Business' for all contacts ************************************************************************************/
foreach($retailwholesale_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $key => $sector) {
    $retailwholesale_contacts_to_reenter[$cid][$key]['role'] = $retailwholesale_contacts[$cid]['role'];
    foreach($sector as $sector_id => $state) {
      $conn->query("UPDATE civicrm_contact_segment SET `is_main` = '0', `is_active` = '0', `end_date` = CURDATE() WHERE contact_id = '".$cid."' AND segment_id = '".$sector_id."' AND is_active = 1") or die($conn->error);
    }
  }
}
/** End Change 6 ********************************************************************************************************************************/


/** Change 7: Now we need to close the old sectors: Retail: Business to Consumer | Wholesale: Business to Business | **/
$query_close_old_retailwholesale = $conn->query("SELECT id FROM civicrm_segment seg WHERE (
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Retail: Business to Consumer')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Retail: Business to Consumer')))
  OR
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Wholesale: Business to Business')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Wholesale: Business to Business')))
)") or die($conn->error);

while($row = $query_close_old_retailwholesale->fetch_assoc()){
  $conn->query("UPDATE civicrm_segment SET `is_active` = '0' WHERE id = '".$row['id']."'") or die($conn->error);
}
/** End Change 7 ********************************************************************************************************************************/


/** Change 8: Update civicrm_segment_tree **/
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
/** End Change 8: Update civicrm_segment_tree **/

echo 'done';