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

/** *******************
* Sector Metal
*
* Excel formula for name:
* =LOWER(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(A22;":";"");" & ";"_");", ";"_");" (";"_");")";"_");" ";"_");" ";"_");"/";"_");"-";"_"))
* =TRIM(LOWER(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(E9;":";"");" (";"_");") ";"_");" & ";"_");", ";"_");"(";"_");")";"_");" ";"_");" ";"_");"/";"_");"-";"_");"   ";"")))
*
* array(
*  'old_sector_label' => array('new_sector_name', 'new_sector_label')
* )
*
* key == area of expertise label to close, value == aoe name => aoe value to add for new sector metal
***********************/

$old_to_new_areas_of_expertise = array(
  'Ship construction (metal, wood, polyesters)' => NULL,
  'Ship design (sea and river going vessels)' => NULL,
  'Ship maintenance' => NULL,
  'Small aircraft maintenance' => NULL,
  'Workshop management' => NULL,
  'Product (making equipment for) agriculture' => array('product_making_equipment_for_agriculture' => 'Product (making equipment for) agriculture'),
  'Product (making equipment for) boilers' => array('product_making_equipment_for_boilers' => 'Product (making equipment for) boilers'),
  'Product (making equipment for) chemicals' => array('product_making_equipment_for_chemicals' => 'Product (making equipment for) chemicals'),
  'Product (making equipment for) food' => array('product_making_equipment_for_food' => 'Product (making equipment for) food'),
  'Product (making equipment for) heat exchangers' => array('product_making_equipment_for_heat_exchangers' => 'Product (making equipment for) heat exchangers'),
  'Product (making equipment for) heating/cooling' => array('product_making_equipment_for_heating_cooling' => 'Product (making equipment for) heating/cooling'),
  'Product (making equipment for) internal transport' => array('product_making_equipment_for_internal_transport' => 'Product (making equipment for) internal transport'),
  'Product (making equipment for) packaging' => array('product_making_equipment_for_packaging' => 'Product (making equipment for) packaging'),
  'Product (making equipment for) pressure vessels' => array('product_making_equipment_for_pressure_vessels' => 'Product (making equipment for) pressure vessels'),
  'Product (making equipment for) pulp & paper' => array('product_making_equipment_for_pulp_paper' => 'Product (making equipment for) pulp & paper'),
  'Product (making equipment for) textiles' => array('product_making_equipment_for_textiles' => 'Product (making equipment for) textiles'),
  'Product (making equipment for) welding equipment' => array('product_making_equipment_for_welding_equipment' => 'Product (making equipment for) welding equipment'),
  'Quality assurance equipment making' => array('quality_assurance_equipment_making' => 'Quality assurance equipment making'),
  'Workshop management equipment making' => array('workshop_management_equipment_making' => 'Workshop management equipment making'),
  'Design, manufacturing, maintenance for mechanization' => array('design_manufacturing_maintenance_for_mechanization' => 'Design, manufacturing, maintenance for mechanization'),
  'Design, manufacturing, maintenance of machine parts, spare parts' => array('design_manufacturing_maintenance_of_machine_parts_spare_parts' => 'Design, manufacturing, maintenance of machine parts, spare parts'),
  'Design, manufacturing, maintenance of machines, equipment' => array('design_manufacturing_maintenance_of_machines_equipment' => 'Design, manufacturing, maintenance of machines, equipment'),
  'Design, manufacturing, maintenance of structures' => array('design_manufacturing_maintenance_of_structures' => 'Design, manufacturing, maintenance of structures'),
  'Design, manufacturing, maintenance of vehicles, vessels' => array('design_manufacturing_maintenance_of_vehicles_vessels' => 'Design, manufacturing, maintenance of vehicles, vessels'),
  'Manufacturing finishing products for mechanization' => array('manufacturing_finishing_products_for_mechanization' => 'Manufacturing finishing products for mechanization'),
  'Manufacturing finishing products of machine parts, spare parts' => array('manufacturing_finishing_products_of_machine_parts_spare_parts' => 'Manufacturing finishing products of machine parts, spare parts'),
  'Manufacturing finishing products of machines, equipment' => array('manufacturing_finishing_products_of_machines_equipment' => 'Manufacturing finishing products of machines, equipment'),
  'Manufacturing finishing products of structures' => array('manufacturing_finishing_products_of_structures' => 'Manufacturing finishing products of structures'),
  'Manufacturing finishing products of vehicles, vessels' => array('manufacturing_finishing_products_of_vehicles_vessels' => 'Manufacturing finishing products of vehicles, vessels'),
  'Processing raw materials for mechanization' => array('processing_raw_materials_for_mechanization' => 'Processing raw materials for mechanization'),
  'Processing raw materials of machine parts, spare parts' => array('processing_raw_materials_of_machine_parts_spare_parts' => 'Processing raw materials of machine parts, spare parts'),
  'Processing raw materials of machines, equipment' => array('processing_raw_materials_of_machines_equipment' => 'Processing raw materials of machines, equipment'),
  'Processing raw materials of structures' => array('processing_raw_materials_of_structures' => 'Processing raw materials of structures'),
  'Processing raw materials of vehicles, vessels' => array('processing_raw_materials_of_vehicles_vessels' => 'Processing raw materials of vehicles, vessels'),
  'Quality assurance metal construction' => array('quality_assurance_metal_construction' => 'Quality assurance metal construction'),
  'Workshop management (metal construction, maintenance & repair)' => NULL,
  'Cast iron, steel, non-ferrous metal processing: Casting' => array('cast_iron_steel_non_ferrous_metal_processing_casting' => 'Cast iron, steel, non-ferrous metal processing: Casting'),
  'Cast iron, steel, non-ferrous metal processing: Drawing' => array('cast_iron_steel_non_ferrous_metal_processing_drawing' => 'Cast iron, steel, non-ferrous metal processing: Drawing'),
  'Cast iron, steel, non-ferrous metal processing: Extruding' => array('cast_iron_steel_non_ferrous_metal_processing_extruding' => 'Cast iron, steel, non-ferrous metal processing: Extruding'),
  'Cast iron, steel, non-ferrous metal processing: Forging' => array('cast_iron_steel_non_ferrous_metal_processing_forging' => 'Cast iron, steel, non-ferrous metal processing: Forging'),
  'Cast iron, steel, non-ferrous metal processing: Heat treatment' => array('cast_iron_steel_non_ferrous_metal_processing_heat_treatment' => 'Cast iron, steel, non-ferrous metal processing: Heat treatment'),
  'Cast iron, steel, non-ferrous metal processing: Hot/cold rolling' => array('cast_iron_steel_non_ferrous_metal_processing_hot_cold_rolling' => 'Cast iron, steel, non-ferrous metal processing: Hot/cold rolling'),
  'Cast iron, steel, non-ferrous metal processing: Melting' => array('cast_iron_steel_non_ferrous_metal_processing_melting' => 'Cast iron, steel, non-ferrous metal processing: Melting'),
  'Cast iron, steel, non-ferrous metal processing: Product processi' => array('cast_iron_steel_non_ferrous_metal_processing_product_processing' => 'Cast iron, steel, non-ferrous metal processing: Product processing'),
  'Cast iron, steel, non-ferrous metal processing: Refining' => array('cast_iron_steel_non_ferrous_metal_processing_refining' => 'Cast iron, steel, non-ferrous metal processing: Refining'),
  'Cast iron, steel, non-ferrous metal processing: Sand/die casting' => array('cast_iron_steel_non_ferrous_metal_processing_sand_die_casting' => 'Cast iron, steel, non-ferrous metal processing: Sand/die casting'),
  'Metal processing: equipment maintenance' => array('metal_processing_equipment_maintenance' => 'Metal processing: equipment maintenance'),
  'Metal processing: equipment operations' => array('metal_processing_equipment_operations' => 'Metal processing: equipment operations'),
  'Metallurgy and alloying' => array('metallurgy_and_alloying' => 'Metallurgy and alloying'),
  'Workshop management: metal processing' => NULL,
  '' => array('workshop_management' => 'Workshop management')
);

//All experts in sector metal
//foreach contact where one of sectors is_active == 1, add new segment_id metal for contact and put rest on is_active = 0

$query_metal = $conn->query("SELECT * FROM civicrm_contact_segment WHERE civicrm_contact_segment.segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Metal: aircraft maintenance & shipbuilding, repair','Metal: Machine Engineering & Construction','Metal: Metal Construction, Maintenance & Repair','Metal: Metal Processing')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Metal: aircraft maintenance & shipbuilding, repair','Metal: Machine Engineering & Construction','Metal: Metal Construction, Maintenance & Repair','Metal: Metal Processing')))
  )
) AND end_date IS NULL AND is_active = 1") or die($conn->error);

$metal_contacts_to_reenter = array();

while($row = $query_metal->fetch_assoc()){
  $metal_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

/** Reorder array to have role of contact **/
$metal_contacts = array();
foreach($metal_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      $metal_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
    }
  }
}

/** Create new sector: Metal **/
$new_metal_sector_id = NULL;

$params = array(
  'version' => 3,
  'sequential' => 1,
  'name' => 'metal',
  'label' => 'Metal',
  'is_active' => 1
);
$result = civicrm_api('Segment', 'create', $params);

$new_metal_sector_id = $result['id'];
/** End create new sector: Metal **/



/**
* Determine the right role for each metal contact
* This is neccessary because previously we had 3 metal sectors and some users had different roles in each of these 3 sectors.
* f.e. some people were sc for sector Metal Construction and expert for sector Metal Processing
* To merge these, we first check 'Recruitment Team Member' then 'Sector Coordinator', then 'Expert' and then 'Customer'
* in this order of importance.
*
* After that, insert the new metal sector for these contacts.
*
*/
foreach($metal_contacts as $cid => $sector) {
  $determine_role = array('main_role'=>'','main_sector'=>'','rtm_is_main'=>0,'sc_is_main'=>0,'sc_shared_is_main'=>0,'expert_is_main'=>0,'customer_is_main'=>0,'role_value_rtm'=>NULL,'role_value_sc'=>NULL,'role_value_sc_shared'=>NULL,'role_value_expert'=>NULL,'role_value_customer'=>NULL);

  /** Determine right user role for sector */
  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {

      if(!isset($metal_contacts[$cid][$sector_id]['role_value'])){
        $metal_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Recruitment Team Member') {
        $determine_role['role_value_rtm'] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['rtm_is_main'] = 1;
          $determine_role['rtm_is_main_sector'] = $sector_id;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!isset($metal_contacts[$cid][$sector_id]['role_value'])){
        $metal_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator') {
        $determine_role['role_value_sc'] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_is_main'] = 1;
          $determine_role['sc_is_main_sector'] = $sector_id;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($metal_contacts[$cid][$sector_id]['role_value'])){
        $metal_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator (shared)') {
        $determine_role['role_value_sc_shared'] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_shared_is_main'] = 1;
          $determine_role['sc_shared_is_main_sector'] = $sector_id;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($metal_contacts[$cid][$sector_id]['role_value'])){
        $metal_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Expert') {
        $determine_role['role_value_expert'] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['expert_is_main'] = 1;
          $determine_role['expert_is_main_sector'] = $sector_id;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($metal_contacts[$cid][$sector_id]['role_value'])){
        $metal_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Customer') {
        $determine_role['role_value_customer'] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $metal_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['customer_is_main'] = 1;
          $determine_role['customer_is_main_sector'] = $sector_id;
        }
      }
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

  $metal_contacts[$cid]['role'] = $determine_role;

  /** Insert new metal sector for contact */
  if(!empty($determine_role['main_role'])){
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_metal_sector_id."','".$determine_role['main_role']."',CURDATE(),NULL,1,1)") or die($conn->error);
  }

  if(!empty($determine_role['role_value_rtm']) && $determine_role['role_value_rtm'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_metal_sector_id."','".$determine_role['role_value_rtm']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc']) && $determine_role['role_value_sc'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_metal_sector_id."','".$determine_role['role_value_sc']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc_shared']) && $determine_role['role_value_sc_shared'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_metal_sector_id."','".$determine_role['role_value_sc_shared']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_expert']) && $determine_role['role_value_expert'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_metal_sector_id."','".$determine_role['role_value_expert']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_customer']) && $determine_role['role_value_customer'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_metal_sector_id."','".$determine_role['role_value_customer']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
}

/** Now close the old metal sector for all contacts */
foreach($metal_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $key => $sector) {
    $metal_contacts_to_reenter[$cid][$key]['role'] = $metal_contacts[$cid]['role'];
    foreach($sector as $sector_id => $state) {
      $conn->query("UPDATE civicrm_contact_segment SET `is_main` = '0', `is_active` = '0', `end_date` = CURDATE() WHERE contact_id = '".$cid."' AND segment_id = '".$sector_id."' AND is_active = 1") or die($conn->error);
    }
  }
}


/**
 * De metal sector worden nu afgesloten incl. de onderliggende area's of expertise
 * Tevens wordt de nieuwe sector metal nu toegevoegd.
 */

/** Now insert new area's of expertise under new sector metal **/
foreach($old_to_new_areas_of_expertise as $old_aoe => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$new_metal_sector_id."',1)") or die($conn->error);
    }
  }
}

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



/**
 * Now update area's of expertise on contacts
 */

/** First collect all current area's of expertise of metal contacts **/

$contact_aoe = array();
foreach($metal_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      $query_result = $conn->query("SELECT * FROM civicrm_segment WHERE id = '".$sector_id."' AND parent_id IS NOT NULL");
      while($row = $query_result->fetch_assoc()){
        $contact_aoe[$cid][$row['id']] = $state;
      }
    }
  }
}


/** Then fetch the labels of these area's of expertise for each contact **/

foreach($contact_aoe as $cid => $segment_id) {
  foreach($segment_id as $sid => $sector) {
    $query_result2 = $conn->query("SELECT * FROM civicrm_segment WHERE id = '".$sid."'");
    while($row = $query_result2->fetch_assoc()){
      $contact_aoe[$cid][$sid] = array('name'=>$row['name'],'label'=>$row['label'],'parent_id'=>$row['parent_id'],'is_active'=>$row['is_active']);
    }
  }
}


/** Then use the labels of the old area's of expertise to fetch the new area of expertise id in the new metal sector **/

$new_contact_aoe = array();
foreach($contact_aoe as $cid => $segment) {
  foreach($segment as $old_sid => $sector) {
    if($sector['is_active'] == 1) {
      $query_new_aoe = $conn->query("SELECT * FROM civicrm_segment WHERE label = '".$sector['label']."' AND parent_id = '".$new_metal_sector_id."' AND is_active = 1");
      while($row_new_segment_id = $query_new_aoe->fetch_assoc()) {
        $new_aoe_ids[$cid][$old_sid] = $row_new_segment_id;
      }
    }
  }
}


/**
 * Now all new area's of expertise ids are loaded in $new_aoe_ids for each contact
 * So now we can just loop through this array and insert the new contact segments for each contact
 *
 **/
foreach($new_aoe_ids as $cid => $sector) {
  foreach($sector as $old_sid => $new_sector_details) {
    if(array_key_exists($old_sid,$metal_contacts[$cid]) && array_key_exists('role_value',$metal_contacts[$cid][$old_sid]) && $old_sid != 'role'){
      foreach($metal_contacts[$cid][$old_sid]['role_value'] as $role_value) {
        if($role_value == 'Recruitment Team Member' && $metal_contacts[$cid]['role']['rtm_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('2: '.$conn->error);
        } else if($role_value == 'Recruitment Team Member' && $metal_contacts[$cid]['role']['rtm_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('4: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator' && $metal_contacts[$cid]['role']['sc_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('3: '.$conn->error);
        } else if($role_value == 'Sector Coordinator' && $metal_contacts[$cid]['role']['sc_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('4: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator (shared)' && $metal_contacts[$cid]['role']['sc_shared_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('5: '.$conn->error);
        } else if($role_value == 'Sector Coordinator (shared)' && $metal_contacts[$cid]['role']['sc_shared_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('6: '.$conn->error);
        }

        if($role_value == 'Expert' && $metal_contacts[$cid]['role']['expert_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('7: '.$conn->error);
        } else if($role_value == 'Expert' && $metal_contacts[$cid]['role']['expert_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('8: '.$conn->error);
        }

        if($role_value == 'Customer' && $metal_contacts[$cid]['role']['customer_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('9: '.$conn->error);
        } else if($role_value == 'Customer' && $metal_contacts[$cid]['role']['customer_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('10: '.$conn->error);
        }
      }
    }
  }
}

/** Add area of expertises */
foreach($new_aoe_ids as $cid => $sector) {
  foreach($sector as $old_sid => $new_sector_details) {
    foreach($metal_contacts[$cid][$old_sid]['role_value'] as $role_value) {
        if($role_value == 'Recruitment Team Member'){
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('2: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator') {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('4: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator (shared)' && $metal_contacts[$cid]['role']['sc_shared_is_main'] == 1) {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('6: '.$conn->error);
        }

        if($role_value == 'Expert' && $metal_contacts[$cid]['role']['expert_is_main'] == 1) {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('8: '.$conn->error);
        }

        if($role_value == 'Customer' && $metal_contacts[$cid]['role']['customer_is_main'] == 1) {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('10: '.$conn->error);
        }
      }
  }
}


/**
 * The last thing we need to do is closing the old metal sectors
 *
 **/
$query_close_old_metal = $conn->query("SELECT id FROM civicrm_segment seg WHERE (
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Metal: aircraft maintenance & shipbuilding, repair','Metal: Machine Engineering & Construction','Metal: Metal Construction, Maintenance & Repair','Metal: Metal Processing')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Metal: aircraft maintenance & shipbuilding, repair','Metal: Machine Engineering & Construction','Metal: Metal Construction, Maintenance & Repair','Metal: Metal Processing')))
)") or die($conn->error);

while($row = $query_close_old_metal->fetch_assoc()){
  $conn->query("UPDATE civicrm_segment SET `is_active` = '0' WHERE id = '".$row['id']."'") or die($conn->error);
}

echo 'done';