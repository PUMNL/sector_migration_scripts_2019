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


function is_main_sector($sector_id) {
  $conn = connect_to_mysql('','','','');
  $query_ismain = $conn->query("SELECT * FROM civicrm_segment WHERE id = '".$sector_id."'") or die($conn->error);

  while($row = $query_ismain->fetch_assoc()){
    if($row['parent_id'] == NULL){
      return TRUE;
    }
  }

  return FALSE;
}

function is_existing_cs_of_contact($cid,$segment_id,$role_value){
  $conn = connect_to_mysql('','','','');
  $query_isexisting = $conn->query("SELECT * FROM civicrm_contact_segment WHERE contact_id = '".$cid."' AND segment_id = '".$segment_id."' AND role_value = '".$role_value."' AND is_active = 1") or die($conn->error);

  while($row = $query_isexisting->fetch_assoc()){
    if (!empty($row['id'])){
      return TRUE;
    }
  }

  return FALSE;
}

function in_array_r($needle, $haystack, $strict = false) {
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }

    return false;
}

/** *******************
* Change 1: Sector Energy & Production & Services: Add area of expertise 'Energy (prod, engineering, logistics, etc.) of hydrogen'
* Change 2: Sector Energy & Production & Services: Change name of Energy Production/Services => Energy Production & Services
* Change 3: Create new sector: Water
* Change 4: Sector Water: Add new list of area's of expertise to sector 'Water'
* Change 5: Remove existing area's of expertise from contacts
* Change 6: Put all contacts from old sector 'Water & Waste Water' to new sector 'Water'
* Change 7: Sector Water: Close sector Water & Waste Water
* Change 8: Create new sector: Waste & Environment
* Change 9: Sector Waste & Environment: Add new list of area's of expertise to sector 'Waste & Environment'       == 4
* Change 10: Remove existing area's of expertise from contacts                                                    == 5
* Change 11: Put all contacts from old sector 'Waste Collection & Treatment' to new sector 'Waste & Environment'  == 6
* Change 12: Close sector Waste Collection & Treatment                                                            == 7
* Change 13: Remove existing area's of expertise from contacts in 'Environmental Matters/Corporate Social Responsibility'                  == 5
* Change 14: Put all contacts from old sector 'Environmental Matters/Corporate Social Responsibility' to new sector 'Waste & Environment'  == 6
* Change 15: Close sector 'Environmental Matters/Corporate Social Responsibility'                                                          == 7
* |
* |-> Dit is het lijstje met stappen die moeten gebeuren. Dit nu in onderstaand script verwerken
*
*
* Excel formula for name:
* =TRIM(LOWER(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(A1;":";"");".";"");" (";"_");") ";"_");" & ";"_");", ";"_");"(";"_");")";"_");" ";"_");" ";"_");"/";"_");"-";"_");"   ";"")))
*
* array(
*  'old_sector_label' => array('new_sector_name', 'new_sector_label')
* )
*
* key == area of expertise label to close, value == aoe name => aoe value to add for new sector energy
***********************/

/** Sector Energy Production/Services => Energy Production & Services **/
$old_to_new_areas_of_expertise_eps = array(
  '' => array('energy_prod_engineering_logistics_etc_of_hydrogen' => 'Energy (prod, engineering, logistics, etc.) of hydrogen')
);

/** Then get all users currently in old sector **/
$query_energyenvironment = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Energy Production/Services')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Energy Production/Services')))
  )
) AND end_date IS NULL") or die($conn->error);

$energyenvironment_contacts_to_reenter = array();

while($row = $query_energyenvironment->fetch_assoc()){
  $energyenvironment_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

/** Reorder array to have role of contact **/
$energyenvironment_contacts = array();
foreach($energyenvironment_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      if(isset($energyenvironment_contacts[$contact_id][$sector_id]) && !in_array($state['role_value'],$energyenvironment_contacts[$contact_id][$sector_id])){
        $energyenvironment_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
      }
    }
  }
}
/** **********************************************/

/** Get old sector energy **/
$query_energy_production_services = $conn->query("SELECT * FROM civicrm_segment WHERE parent_id IS NULL AND label = 'Energy Production/Services'") or die($conn->error);
$sectors = array();
while($row = $query_energy_production_services->fetch_assoc()){
  $sectors[] = $row;
}
if(count($sectors) != 1){
  echo 'duplicate sector \'Energy Production/Services\' found, or sector does not exist';
  exit();
} else {
  //go with the flow!

  foreach($sectors as $key => $sector){
    $sector_id_energy = $sector['id'];
  }
}

if(empty($sector_id_energy)){
  echo 'sector \'Energy Production/Services\' does not exist';
  exit();
}

/** Change 1: Now insert new area's of expertise under renamed sector Energy Production/Services **/
foreach($old_to_new_areas_of_expertise_eps as $old_aoe => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$sector_id_energy."',1)") or die($conn->error);
    }
  }
}
/** End Change 1 *****************************************************************************/



/** Change 2: Update Sector name Energy Production/Services => Energy Production & Services **/

$conn->query("UPDATE civicrm_segment SET `label` = 'Energy Production & Services' WHERE `id` = '".$sector_id_energy."'") or die($conn->error);

/** End Change 2 *****************************************************************************/



/** Change 3: Create new sector: Water ****************/
$new_water_sector_id = NULL;

$params = array(
  'version' => 3,
  'sequential' => 1,
  'name' => 'water',
  'label' => 'Water',
  'is_active' => 1
);
$result = civicrm_api('Segment', 'create', $params);

$new_water_sector_id = $result['id'];
/** End Change 3 **************************************/



/** Change 4: Sector Water: Add new list of area's of expertise to sector 'Water' ************************************************************/
/** Area's of expertise new sector Water **/
$old_to_new_areas_of_expertise_www = array(
  array('groundwater' => 'Groundwater'),
  array('irrigation' => 'Irrigation'),
  array('laboratory_management' => 'Laboratory management'),
  array('leakdetection' => 'Leakdetection'),
  array('reuse_wastewater' => 'Reuse wastewater'),
  array('sewerage' => 'Sewerage'),
  array('vocational_education' => 'Vocational education'),
  array('wastewatertreatment_domestic' => 'Wastewatertreatment/domestic'),
  array('wastewatertreatment_industrial' => 'Wastewatertreatment/industrial'),
  array('wastewatertreatment_membranes' => 'Wastewatertreatment/membranes'),
  array('waterdistribution_transportation' => 'Waterdistribution/transportation'),
  array('watersystem_management' => 'Watersystem/management'),
  array('watertreatment' => 'Watertreatment'),
  array('watertreatment_coolingwater_waterconditioning_legionella' => 'Watertreatment/coolingwater/waterconditioning/legionella'),
  array('watertreatment_membranes' => 'Watertreatment/membranes')
);

/** Now insert new area's of expertise under new sector Water */
foreach($old_to_new_areas_of_expertise_www as $key => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$new_water_sector_id."',1)") or die($conn->error);
    }
  }
}
/** End Change 4 ********************************************************************************************************************************/



/** Change 5: Collect existing area's of expertise from contacts *********************************************************************************/

//Alle contacten in sector Water & Waste Water
//Hier moet iets als:
// foreach contact where one of sectors is_active == 1, add new segment_id water for contact and put rest on is_active = 0

/** First collect all contacts currently in sector Water & Waste Water **/
$query_water_wastewater = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Water & Waste Water')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Water & Waste Water')))
  )
) AND end_date IS NULL") or die($conn->error);

$water_wastewater_contacts_to_reenter = array();

while($row = $query_water_wastewater->fetch_assoc()){
  $water_wastewater_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

$water_wastewater_contacts = array();
foreach($water_wastewater_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      if(isset($water_wastewater_contacts[$contact_id][$sector_id]) && !in_array($state['role_value'],$water_wastewater_contacts[$contact_id][$sector_id])){
        $water_wastewater_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
      } else {
        $water_wastewater_contacts[$contact_id][$sector_id] = array();
        $water_wastewater_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
      }
    }
  }
}
/** End Change 5 ********************************************************************************************************************************/


/** Change 6: Put all contacts from old sector 'Water & Waste Water' to new sector 'Water' ******************************************************/

/**
* Determine the right role for each water & waste water contact
* This is neccessary because some users had different roles in these sectors.
* To merge these, we first check 'Recruitment Team Member' then 'Sector Coordinator', then 'Expert' and then 'Customer'
* in this order of importance.
*
* After that, insert the new Water sector for these contacts.
*
*/
foreach($water_wastewater_contacts as $cid => $sector) {
  $determine_role = array('main_role'=>'','main_sector'=>'','rtm_is_main'=>0,'sc_is_main'=>0,'sc_shared_is_main'=>0,'expert_is_main'=>0,'customer_is_main'=>0,'role_value_rtm'=>NULL,'role_value_sc'=>NULL,'role_value_sc_shared'=>NULL,'role_value_expert'=>NULL,'role_value_customer'=>NULL);

  /** Determine right user role for sector */
  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {

      if(!isset($water_wastewater_contacts[$cid][$sector_id]['role_value'])){
        $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Recruitment Team Member') {
        $determine_role['role_value_rtm'] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['rtm_is_main'] = 1;
          $determine_role['rtm_is_main_sector'] = $sector_id;

        }
      }
      $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array_unique($water_wastewater_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($water_wastewater_contacts[$cid][$sector_id]['role_value'])){
        $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator') {
        $determine_role['role_value_sc'] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_is_main'] = 1;
          $determine_role['sc_is_main_sector'] = $sector_id;
        }
      }
      $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array_unique($water_wastewater_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($water_wastewater_contacts[$cid][$sector_id]['role_value'])){
        $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator (shared)') {
        $determine_role['role_value_sc_shared'] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_shared_is_main'] = 1;
          $determine_role['sc_shared_is_main_sector'] = $sector_id;
        }
      }
      $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array_unique($water_wastewater_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($water_wastewater_contacts[$cid][$sector_id]['role_value'])){
        $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Expert') {
        $determine_role['role_value_expert'] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['expert_is_main'] = 1;
          $determine_role['expert_is_main_sector'] = $sector_id;
        }
      }
      $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array_unique($water_wastewater_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($water_wastewater_contacts[$cid][$sector_id]['role_value'])){
        $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Customer') {
        $determine_role['role_value_customer'] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $water_wastewater_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['customer_is_main'] = 1;
          $determine_role['customer_is_main_sector'] = $sector_id;
        }
      }
      $water_wastewater_contacts[$cid][$sector_id]['role_value'] = array_unique($water_wastewater_contacts[$cid][$sector_id]['role_value']);
    }
  }

  $determine_role['main_role'] = '';
  $determine_role['main_sector'] = '';

  if($determine_role['rtm_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_rtm'];
    $determine_role['main_sector'] = $determine_role['rtm_is_main_sector'];
  } else if($determine_role['sc_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_sc'];
    $determine_role['main_sector'] = $determine_role['sc_is_main_sector'];
  } else if($determine_role['sc_shared_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_sc_shared'];
    $determine_role['main_sector'] = $determine_role['sc_shared_is_main_sector'];
  } else if($determine_role['expert_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_expert'];
    $determine_role['main_sector'] = $determine_role['expert_is_main_sector'];
  } else if($determine_role['customer_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_customer'];
    $determine_role['main_sector'] = $determine_role['customer_is_main_sector'];
  }

  $water_wastewater_contacts[$cid]['role'] = $determine_role;

  /** Insert new water sector for contact */
  if(!empty($determine_role['main_role'])){
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_water_sector_id."','".$determine_role['main_role']."',CURDATE(),NULL,1,1)") or die($conn->error);
  }

  if(!empty($determine_role['role_value_rtm']) && $determine_role['role_value_rtm'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_water_sector_id."','".$determine_role['role_value_rtm']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc']) && $determine_role['role_value_sc'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_water_sector_id."','".$determine_role['role_value_sc']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc_shared']) && $determine_role['role_value_sc_shared'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_water_sector_id."','".$determine_role['role_value_sc_shared']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_expert']) && $determine_role['role_value_expert'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_water_sector_id."','".$determine_role['role_value_expert']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_customer']) && $determine_role['role_value_customer'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_water_sector_id."','".$determine_role['role_value_customer']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
}
/** End Change 6 ********************************************************************************************************************************/

/** Change 7: Sector Water: Close sector Water & Waste Water ************************************************************************************/
/** Now close the old water sector for all contacts */
foreach($water_wastewater_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $key => $sector) {
    foreach($sector as $sector_id => $state) {
      //$water_wastewater_contacts_to_reenter[$cid][$key][$sector_id]['role'] = $water_wastewater_contacts[$cid]['role'];
      $conn->query("UPDATE civicrm_contact_segment SET `is_main` = '0', `is_active` = '0', `end_date` = CURDATE() WHERE contact_id = '".$cid."' AND segment_id = '".$sector_id."' AND is_active = 1") or die($conn->error);
    }
  }
}

/** Now we need to close the old Water & Waste Water sectors */
$query_close_old_water_wastewater = $conn->query("SELECT id FROM civicrm_segment seg WHERE (
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Water & Waste Water')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Water & Waste Water')))
)") or die($conn->error);

while($row = $query_close_old_water_wastewater->fetch_assoc()){
  $conn->query("UPDATE civicrm_segment SET `is_active` = '0' WHERE id = '".$row['id']."'") or die($conn->error);
}

/** End Change 7 ********************************************************************************************************************************/

/** Change 8: Create new sector: Waste & Environment ********************************************************************************************/
$new_waste_environment_sector_id = NULL;

$params = array(
  'version' => 3,
  'sequential' => 1,
  'name' => 'waste_environment',
  'label' => 'Waste & Environment',
  'is_active' => 1
);
$result = civicrm_api('Segment', 'create', $params);

$new_waste_environment_sector_id = $result['id'];
/** End Change 8 ********************************************************************************************************************************/


/** Change 9: Sector Waste & Environment: Add new list of area's of expertise to sector 'Waste & Environment' ***********************************/
/** Area's of expertise new sector Waste & Environment **/
$old_to_new_areas_of_expertise_we = array(
  array('composting' => 'Composting'),
  array('conservation_in_agriculture_aquaculture' => 'Conservation in agriculture, aquaculture'),
  array('conservation_of_industrial_environment' => 'Conservation of industrial environment'),
  array('conservation_of_nature' => 'Conservation of nature'),
  array('dangerous_waste' => 'Dangerous waste'),
  array('eco_agriculture_and_aquaculture_enhancement' => 'Eco-agriculture and aquaculture enhancement'),
  array('eco_awareness_program' => 'Eco-awareness program'),
  array('ecological_evaluation_carbon_credit_programs' => 'Ecological evaluation, carbon credit programs'),
  array('eco_resorts_hotels_facilities_buildings' => 'Eco-resorts, hotels, facilities, buildings'),
  array('embedding_biological_conservation_in_air' => 'Embedding biological conservation in air'),
  array('embedding_biological_conservation_in_aqua' => 'Embedding biological conservation in aqua'),
  array('embedding_biological_conservation_in_sea' => 'Embedding biological conservation in sea'),
  array('environmental_management_systems_ems_' => 'Environmental Management Systems (EMS)'),
  array('hospital_waste' => 'Hospital waste'),
  array('incineration' => 'Incineration'),
  array('industrial_waste' => 'Industrial waste'),
  array('landfill' => 'Landfill'),
  array('landscape_and_landscaping' => 'Landscape and landscaping'),
  array('recycling_e_waste' => 'Recycling E-waste'),
  array('recycling_paper_glass_metal' => 'Recycling Paper/glass/metal'),
  array('recycling_plastic_rubber' => 'Recycling plastic/rubber'),
  array('strategic_plans' => 'Strategic plans'),
  array('vocational_education' => 'Vocational education')
);

/** Now insert new area's of expertise under new sector Waste & Environment */
foreach($old_to_new_areas_of_expertise_we as $key => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$new_waste_environment_sector_id."',1)") or die($conn->error);
    }
  }
}
/** End Change 9 ********************************************************************************************************************************/

/** Change 10: Collect existing area's of expertise from contacts *********************************************************************************/

/** First collect all contacts currently in sector Waste Collection & Treatment **/
$query_waste_collection_treatment = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Waste Collection & Treatment')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Waste Collection & Treatment')))
  )
) AND end_date IS NULL") or die($conn->error);

$waste_collection_treatment_contacts_to_reenter = array();

while($row = $query_waste_collection_treatment->fetch_assoc()){
  $waste_collection_treatment_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

$waste_collection_treatment_contacts = array();
foreach($waste_collection_treatment_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $key => $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      if(isset($waste_collection_treatment_contacts[$contact_id][$sector_id]) && !in_array($state['role_value'],$waste_collection_treatment_contacts[$contact_id][$sector_id])){
        $waste_collection_treatment_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
      } else {
        $waste_collection_treatment_contacts[$contact_id][$sector_id] = array();
        $waste_collection_treatment_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
      }
    }
  }
}
/** End Change 10 ********************************************************************************************************************************/


/** Change 11: Put all contacts from old sector 'Waste Collection & Treatment' to new sector 'Waste & Environment' ******************************************************/

/**
* Determine the right role for each Waste Collection & Treatment contact
* This is neccessary because some users had different roles in these sectors.
* To merge these, we first check 'Recruitment Team Member' then 'Sector Coordinator', then 'Expert' and then 'Customer'
* in this order of importance.
*
* After that, insert the new Waste & Environment sector for these contacts.
*
*/
foreach($waste_collection_treatment_contacts as $cid => $sector) {
  $determine_role = array('main_role'=>'','main_sector'=>'','rtm_is_main'=>0,'sc_is_main'=>0,'sc_shared_is_main'=>0,'expert_is_main'=>0,'customer_is_main'=>0,'role_value_rtm'=>NULL,'role_value_sc'=>NULL,'role_value_sc_shared'=>NULL,'role_value_expert'=>NULL,'role_value_customer'=>NULL);

  /** Determine right user role for sector */
  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {

      if(!isset($waste_collection_treatment_contacts[$cid][$sector_id]['role_value'])){
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Recruitment Team Member') {
        $determine_role['role_value_rtm'] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['rtm_is_main'] = 1;
          $determine_role['rtm_is_main_sector'] = $sector_id;
        }
      }
      $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array_unique($waste_collection_treatment_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($waste_collection_treatment_contacts[$cid][$sector_id]['role_value'])){
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator') {
        $determine_role['role_value_sc'] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_is_main'] = 1;
          $determine_role['sc_is_main_sector'] = $sector_id;
        }
      }
      $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array_unique($waste_collection_treatment_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($waste_collection_treatment_contacts[$cid][$sector_id]['role_value'])){
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator (shared)') {
        $determine_role['role_value_sc_shared'] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_shared_is_main'] = 1;
          $determine_role['sc_shared_is_main_sector'] = $sector_id;
        }
      }
      $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array_unique($waste_collection_treatment_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($waste_collection_treatment_contacts[$cid][$sector_id]['role_value'])){
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Expert') {
        $determine_role['role_value_expert'] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['expert_is_main'] = 1;
          $determine_role['expert_is_main_sector'] = $sector_id;
        }
      }
      $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array_unique($waste_collection_treatment_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($waste_collection_treatment_contacts[$cid][$sector_id]['role_value'])){
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Customer') {
        $determine_role['role_value_customer'] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $waste_collection_treatment_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['customer_is_main'] = 1;
          $determine_role['customer_is_main_sector'] = $sector_id;
        }
      }
      $waste_collection_treatment_contacts[$cid][$sector_id]['role_value'] = array_unique($waste_collection_treatment_contacts[$cid][$sector_id]['role_value']);
    }
  }

  $determine_role['main_role'] = '';
  $determine_role['main_sector'] = '';

  if($determine_role['rtm_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_rtm'];
    $determine_role['main_sector'] = $determine_role['rtm_is_main_sector'];
  } else if($determine_role['sc_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_sc'];
    $determine_role['main_sector'] = $determine_role['sc_is_main_sector'];
  } else if($determine_role['sc_shared_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_sc_shared'];
    $determine_role['main_sector'] = $determine_role['sc_shared_is_main_sector'];
  } else if($determine_role['expert_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_expert'];
    $determine_role['main_sector'] = $determine_role['expert_is_main_sector'];
  } else if($determine_role['customer_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_customer'];
    $determine_role['main_sector'] = $determine_role['customer_is_main_sector'];
  }

  $waste_collection_treatment_contacts[$cid]['role'] = $determine_role;

  /** Insert new waste environment sector for contact */
  if(!empty($determine_role['main_role'])){
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['main_role']."',CURDATE(),NULL,1,1)") or die($conn->error);
    }
  }

  if(!empty($determine_role['role_value_rtm']) && $determine_role['role_value_rtm'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_rtm']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_sc']) && $determine_role['role_value_sc'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_sc']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_sc_shared']) && $determine_role['role_value_sc_shared'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_sc_shared']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_expert']) && $determine_role['role_value_expert'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_expert']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_customer']) && $determine_role['role_value_customer'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_customer']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
}
/** End Change 11 ********************************************************************************************************************************/


/** Change 12: Sector Waste & Environment: Close sector Waste Collection & Treatment ************************************************************************************/
/** Now close the old Waste Collection & Treatment sector for all contacts **/
foreach($waste_collection_treatment_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $key => $sector) {
    $waste_collection_treatment_contacts_to_reenter[$cid][$key]['role'] = $waste_collection_treatment_contacts[$cid]['role'];
    foreach($sector as $sector_id => $state) {
      $conn->query("UPDATE civicrm_contact_segment SET `is_main` = '0', `is_active` = '0', `end_date` = CURDATE() WHERE contact_id = '".$cid."' AND segment_id = '".$sector_id."' AND is_active = 1") or die($conn->error);
    }
  }
}

/**
 * The last thing we need to do is closing the old Waste Collection & Treatment sectors
 *
 **/
$query_close_old_waste_collection_treatment = $conn->query("SELECT id FROM civicrm_segment seg WHERE (
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Waste Collection & Treatment')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Waste Collection & Treatment')))
)") or die($conn->error);

while($row = $query_close_old_waste_collection_treatment->fetch_assoc()){
  $conn->query("UPDATE civicrm_segment SET `is_active` = '0' WHERE id = '".$row['id']."'") or die($conn->error);
}


/** Change 13: Collect existing area's of expertise from contacts *********************************************************************************/

//Alle contacten in sector Environmental Matters/Corporate Social Responsibility
//Hier moet iets als:
// foreach contact where one of sectors is_active == 1, add new segment_id water for contact and put rest on is_active = 0

/** First collect all contacts currently in sector Environmental Matters/Corporate Social Responsibility **/
$query_csr = $conn->query("SELECT * FROM civicrm_contact_segment WHERE segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Environmental Matters/Corporate Social Responsibility')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Environmental Matters/Corporate Social Responsibility')))
  )
) AND end_date IS NULL") or die($conn->error);

$csr_contacts_to_reenter = array();

while($row = $query_csr->fetch_assoc()){
  $csr_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

$csr_contacts = array();
foreach($csr_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      if(isset($csr_contacts[$contact_id][$sector_id]) && !in_array_r($state['role_value'],$csr_contacts[$contact_id][$sector_id])){
        $csr_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
      } else {
        $csr_contacts[$contact_id][$sector_id] = array();
        $csr_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
      }
    }
  }
}
/** End Change 13 ********************************************************************************************************************************/


/** Change 14: Put all contacts from old sector 'Environmental Matters/Corporate Social Responsibility' to new sector 'Waste & Environment' **********************/

/**
* Determine the right role for each 'Environmental Matters/Corporate Social Responsibility' contact
* This is neccessary because some users had different roles in these sectors.
* To merge these, we first check 'Recruitment Team Member' then 'Sector Coordinator', then 'Expert' and then 'Customer'
* in this order of importance.
*
* After that, insert the new Waste & Environment sector for these contacts.
*
*/
foreach($csr_contacts as $cid => $sector) {
  $determine_role = array('main_role'=>'','main_sector'=>'','rtm_is_main'=>0,'sc_is_main'=>0,'sc_shared_is_main'=>0,'expert_is_main'=>0,'customer_is_main'=>0,'role_value_rtm'=>NULL,'role_value_sc'=>NULL,'role_value_sc_shared'=>NULL,'role_value_expert'=>NULL,'role_value_customer'=>NULL);

  /** Determine right user role for sector */
  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {

      if(!isset($csr_contacts[$cid][$sector_id]['role_value'])){
        $csr_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Recruitment Team Member') {
        $determine_role['role_value_rtm'] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['rtm_is_main'] = 1;
          $determine_role['rtm_is_main_sector'] = $sector_id;
        }
      }
      $csr_contacts[$cid][$sector_id]['role_value'] = array_unique($csr_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($csr_contacts[$cid][$sector_id]['role_value'])){
        $csr_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator') {
        $determine_role['role_value_sc'] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_is_main'] = 1;
          $determine_role['sc_is_main_sector'] = $sector_id;
        }
      }
      $csr_contacts[$cid][$sector_id]['role_value'] = array_unique($csr_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($csr_contacts[$cid][$sector_id]['role_value'])){
        $csr_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator (shared)') {
        $determine_role['role_value_sc_shared'] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_shared_is_main'] = 1;
          $determine_role['sc_shared_is_main_sector'] = $sector_id;
        }
      }
      $csr_contacts[$cid][$sector_id]['role_value'] = array_unique($csr_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($csr_contacts[$cid][$sector_id]['role_value'])){
        $csr_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Expert') {
        $determine_role['role_value_expert'] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['expert_is_main'] = 1;
          $determine_role['expert_is_main_sector'] = $sector_id;
        }
      }
      $csr_contacts[$cid][$sector_id]['role_value'] = array_unique($csr_contacts[$cid][$sector_id]['role_value']);
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($csr_contacts[$cid][$sector_id]['role_value'])){
        $csr_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Customer') {
        $determine_role['role_value_customer'] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $csr_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['customer_is_main'] = 1;
          $determine_role['customer_is_main_sector'] = $sector_id;
        }
      }
      $csr_contacts[$cid][$sector_id]['role_value'] = array_unique($csr_contacts[$cid][$sector_id]['role_value']);
    }
  }

  $determine_role['main_role'] = '';
  $determine_role['main_sector'] = '';

  if($determine_role['rtm_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_rtm'];
    $determine_role['main_sector'] = $determine_role['rtm_is_main_sector'];
  } else if($determine_role['sc_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_sc'];
    $determine_role['main_sector'] = $determine_role['sc_is_main_sector'];
  } else if($determine_role['sc_shared_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_sc_shared'];
    $determine_role['main_sector'] = $determine_role['sc_shared_is_main_sector'];
  } else if($determine_role['expert_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_expert'];
    $determine_role['main_sector'] = $determine_role['expert_is_main_sector'];
  } else if($determine_role['customer_is_main'] == 1) {
    $determine_role['main_role'] = $determine_role['role_value_customer'];
    $determine_role['main_sector'] = $determine_role['customer_is_main_sector'];
  }

  $csr_contacts[$cid]['role'] = $determine_role;

  /** Insert new waste & environment sector for contact */
  if(!empty($determine_role['main_role'])){
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['main_role']."',CURDATE(),NULL,1,1)") or die($conn->error);
    }
  }

  if(!empty($determine_role['role_value_rtm']) && $determine_role['role_value_rtm'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_rtm']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_sc']) && $determine_role['role_value_sc'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_sc']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_sc_shared']) && $determine_role['role_value_sc_shared'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_sc_shared']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_expert']) && $determine_role['role_value_expert'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_expert']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
  if(!empty($determine_role['role_value_customer']) && $determine_role['role_value_customer'] != $determine_role['main_role']) {
    if(!is_existing_cs_of_contact($cid,$new_waste_environment_sector_id,$determine_role['main_role'])) {
      $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_waste_environment_sector_id."','".$determine_role['role_value_customer']."',CURDATE(),NULL,1,0)") or die($conn->error);
    }
  }
}
/** End Change 14 ****************************************************************************************************************************************************************/


/** Change 15: Sector Environmental Matters/Corporate Social Responsibility: Close sector 'Environmental Matters/Corporate Social Responsibility' ********************************/
/** Now close the old sector for all contacts */
foreach($csr_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $key => $sector) {
    foreach($sector as $sector_id => $state) {
      $conn->query("UPDATE civicrm_contact_segment SET `is_main` = '0', `is_active` = '0', `end_date` = CURDATE() WHERE contact_id = '".$cid."' AND segment_id = '".$sector_id."' AND is_active = 1") or die($conn->error);
    }
  }
}

/** Now we need to close the old Waste & Environment sectors */
$query_close_old_csr = $conn->query("SELECT id FROM civicrm_segment seg WHERE (
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Environmental Matters/Corporate Social Responsibility')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Environmental Matters/Corporate Social Responsibility')))
)") or die($conn->error);

while($row = $query_close_old_csr->fetch_assoc()){
  $conn->query("UPDATE civicrm_segment SET `is_active` = '0' WHERE id = '".$row['id']."'") or die($conn->error);
}

/** End Change 15 ********************************************************************************************************************************/


/** Update civicrm_segment_tree **/
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
/** End update civicrm_segment_tree **/

echo 'done';