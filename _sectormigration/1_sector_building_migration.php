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
* Sector Building
*
* Excel formula for name:
* =LOWER(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(A22;":";"");" & ";"_");", ";"_");" (";"_");")";"_");" ";"_");" ";"_");"/";"_");"-";"_"))
* =TRIM(LOWER(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(SUBSTITUTE(E9;":";"");" (";"_");") ";"_");" & ";"_");", ";"_");"(";"_");")";"_");" ";"_");" ";"_");"/";"_");"-";"_");"   ";"")))
*
* array(
*  'old_sector_label' => array('new_sector_name', 'new_sector_label')
* )
*
* key == area of expertise label to close, value == aoe name => aoe value to add for new sector building
***********************/

$old_to_new_areas_of_expertise = array(
  array('bd_architectural_design' => 'BD_Architectural design'),
  'BM combined: cement/concrete products & processing' => array('bmat_building_materials_combined_cement_concrete_products_processing' => 'BMat_Building materials combined: cement/concrete products & processing'),
  'BM combined: products & processing ceramic brick, tile, sanitary' => array('bmat_building_materials_combined_products_processing_ceramic_brick_tile_sanitary' => 'BMat_Building materials combined: products & processing ceramic brick, tile, sanitary'),
  'BM combined: products & processing concrete elements' => array('bmat_building_materials_combined_products_processing_concrete_elements' => 'BMat_Building materials combined: products & processing concrete elements'),
  'BM combined: products & processing doors & windows' => array('bmat_building_materials_combined_products_processing_doors_windows' => 'BMat_Building materials combined: products & processing doors & windows'),
  'BM combined: products & processing of roof elements' => array('bmat_building_materials_combined_products_processing_of_roof_elements' => 'BMat_Building materials combined: products & processing of roof elements'),
  'BM combined: products & processing sewerage systems' => array('bmat_building_materials_combined_products_processing_sewerage_systems' => 'BMat_Building materials combined: products & processing sewerage systems'),
  'Building information & computer aided design' => array('bd_building_information_computer_aided_design' => 'BD_Building information & computer aided design'),
  'Building materials combined: products & processing aggregates' => array('bmat_building_materials_combined_products_processing_aggregates' => 'BMat_Building materials combined: products & processing aggregates'),
  'Building materials combined: products & processing of pavements' => array('bmat_building_materials_combined_products_processing_of_pavements' => 'BMat_Building materials combined: products & processing of pavements'),
  'Building materials: products & processing of aluminum products' => array('bmat_building_materials_products_processing_of_aluminum_products' => 'BMat_Building materials: products & processing of aluminum products'),
  'Building materials: products & processing of brick' => array('bmat_building_materials_products_processing_of_brick' => 'BMat_Building materials: products & processing of brick'),
  'Building materials: products & processing of concrete' => array('bmat_building_materials_products_processing_of_concrete' => 'BMat_Building materials: products & processing of concrete'),
  'Building materials: products & processing of natural stone' => array('bmat_building_materials_products_processing_of_natural_stone' => 'BMat_Building materials: products & processing of natural stone'),
  'Building materials: products & processing of plastic products' => array('bmat_building_materials_products_processing_of_plastic_products' => 'BMat_Building materials: products & processing of plastic products'),
  'Building materials: products & processing of steel products' => array('bmat_building_materials_products_processing_of_steel_products' => 'BMat_Building materials: products & processing of steel products'),
  'Building materials: products & processing of timber' => array('bmat_building_materials_products_processing_of_timber' => 'BMat_Building materials: products & processing of timber'),
  'Building materials: products, processing' => array('bmat_building_materials_products_processing' => 'BMat_Building materials: products, processing'),
  'Building physics & sustainable design' => array('bd_building_physics_sustainable_design' => 'BD_Building physics & sustainable design'),
  'Building projects/execution' => array('bm_building_projects_execution' => 'BM_Building projects/execution'),
  'Building projects/preparation' => array('bm_building_projects_preparation' => 'BM_Building projects/preparation'),
  'Building systems: General' => array('bmat_building_systems_general' => 'BMat_Building systems: General'),
  'Building systems: Poured concrete on site' => array('bmat_building_systems_poured_concrete_on_site' => 'BMat_Building systems: Poured concrete on site'),
  'Building systems: Prefab concrete elements' => array('bmat_building_systems_prefab_concrete_elements' => 'BMat_Building systems: Prefab concrete elements'),
  'Building systems: SIP (self-supporting insulated products)' => array('bmat_building_systems_sip_self_supporting_insulated_products_' => 'BMat_Building systems: SIP (self-supporting insulated products)'),
  'Building systems: stack work' => array('bmat_building_systems_stack_work' => 'BMat_Building systems: Stack work'),
  'Building systems: Timber and steel frame structures' => array('bmat_building_systems_timber_and_steel_frame_structures' => 'BMat_Building systems: Timber and steel frame structures'),
  'Contracting/tendering' => array('bm_contracting_tendering' => 'BM_Contracting/tendering'),
  'Contractor business management' => array('bm_contractor_business_management' => 'BM_Contractor business management'),
  'Design of civil works & infrastructure' => array('bd_design_of_civil_works_infrastructure' => 'BD_Design of civil works & infrastructure'),
  'Design of residential houses' => array('bd_design_of_residential_houses' => 'BD_Design of residential houses'),
  'Design of utility buildings' => array('bd_design_of_utility_buildings' => 'BD_Design of utility buildings'),
  'Dredging projects/ execution' => array('bm_dredging_projects__execution' => 'BM_Dredging projects/ execution'),
  'Dredging projects/ preparation' => array('bm_dredging_projects__preparation' => 'BM_Dredging projects/preparation'),
  'Education building technology' => array('bd_education_building_technology' => 'BD_Education building technology'),
  'Feasibility studies, quantity surveys, costs risks' => array('bd_feasibility_studies_quantity_surveys_costs_risks' => 'BD_Feasibility studies, quantity surveys, costs risks'),
  'Housing corporations & building regulations' => array('bd_housing_corporations_building_regulations' => 'BD_Housing corporations & building regulations'),
  'Industrial projects/execution' => array('bm_industrial_projects_execution' => 'BM_Industrial projects/execution'),
  'Industrial projects/preparation' => array('bm_industrial_projects_preparation' => 'BM_Industrial projects/preparation'),
  'Infrastructure projects/execution' => array('bm_infrastructure_projects_execution' => 'BM_Infrastructure projects/execution'),
  'Infrastructure projects/preparation' => array('bm_infrastructure_projects_preparation' => 'BM_Infrastructure projects/preparation'),
  'Installations/execution' => array('bm_installations_execution' => 'BM_Installations/execution'),
  'Installations/preparation' => array('bm_installations_preparation' => 'BM_Installations/preparation'),
  'Maintenance & facility management' => array('bd_maintenance_facility_management' => 'BD_Maintenance & facility management'),
  'Management of architectural/engineering firms' => array('bd_management_of_architectural_engineering_firms' => 'BD_Management of architectural/engineering firms'),
  'Preparatory design & engineering' => array('bd_preparatory_design_engineering' => 'BD_Preparatory design & engineering'),
  'Project development' => array('bd_project_development' => 'BD_Project development'),
  'Project management services' => array('bm_projectmanagement_services' => 'BM_Projectmanagement services'),
  'Safety/Quality/Laboratory' => array('bmat_safety_quality_laboratory' => 'BMat_Safety/Quality/Laboratory'),
  'Social and low income housing' => array('bd_social_and_low_income_housing' => 'BD_Social and low income housing'),
  'Structural design & engineering' => array('bd_structural_design_engineering' => 'BD_Structural design & engineering'),
  'Tools/machinery/equipment' => array('bmat_tools_machinery_equipment' => 'BMat_Tools/machinery/equipment'),
  'Trade, Building material handling' => array('bmat_trade_building_material_handling' => 'BMat_Trade, Building material handling'),
  'Trade, Finishing/plastering' => array('bmat_trade_finishing_plastering' => 'BMat_Trade, Finishing/plastering'),
  'Trade, Installations' => array('bmat_trade_installations' => 'BMat_Trade, Installations'),
  'Urban design & landscaping' => array('bd_urban_design' => 'BD_Urban design'),
  array('bd_landscaping_design' => 'BD_Landscaping design'),
  array('bd_interior_design' => 'BD_Interior design'),
  array('bd_re_using_of_buildings' => 'BD_Re-using of buildings'),
  array('bd_circulair_sustainable_eco_design_engineering' => 'BD_Circulair-Sustainable-Eco design & engineering'),
);

//Alle experts in sector building
//Hier moet iets als:
// foreach contact where one of sectors is_active == 1, add new segment_id building for contact and put rest on is_active = 0

$query_building = $conn->query("SELECT * FROM civicrm_contact_segment WHERE civicrm_contact_segment.segment_id IN(
  SELECT id FROM civicrm_segment seg WHERE (
    (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Building Management: Contracting, Execution & Installation','Building Development: Architecture, Design & Engineering','Building Materials: Supplies & Systems')))
    OR
    (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Building Management: Contracting, Execution & Installation','Building Development: Architecture, Design & Engineering','Building Materials: Supplies & Systems')))
  )
) AND end_date IS NULL AND is_active = 1") or die($conn->error);

$building_contacts_to_reenter = array();

while($row = $query_building->fetch_assoc()){
  $building_contacts_to_reenter[$row['contact_id']][] = array($row['segment_id'] => array('is_active' => $row['is_active'], 'is_main' => $row['is_main'], 'role_value' => $row['role_value']));
}

/** Reorder array to have role of contact **/
$building_contacts = array();
foreach($building_contacts_to_reenter as $contact_id => $sectors) {
  $is_main = 0;
  foreach($sectors as $sector) {
    foreach($sector as $sector_id => $state) {
      if($state['is_main'] == 1) {
        $is_main = 1;
      }

      $building_contacts[$contact_id][$sector_id][] = array('is_active' => $state['is_active'], 'is_main' => $is_main, 'role_value' => $state['role_value']);
    }
  }
}
/** **********************************************/


/** Create new sector: Building **/
$new_building_sector_id = NULL;

$params = array(
  'version' => 3,
  'sequential' => 1,
  'name' => 'building',
  'label' => 'Building',
  'is_active' => 1
);
$result = civicrm_api('Segment', 'create', $params);

$new_building_sector_id = $result['id'];
/** End create new sector: Building **/




/**
* Determine the right role for each building contact
* This is neccessary because previously we had 3 building sectors and some users had different roles in each of these 3 sectors.
* f.e. some people were sc for sector Building Development and expert for sector Building Management
* To merge these, we first check 'Recruitment Team Member' then 'Sector Coordinator', then 'Expert' and then 'Customer'
* in this order of importance.
*
* After that, insert the new building sector for these contacts.
*
*/
foreach($building_contacts as $cid => $sector) {
  $determine_role = array('main_role'=>'','main_sector'=>'','rtm_is_main'=>0,'sc_is_main'=>0,'sc_shared_is_main'=>0,'expert_is_main'=>0,'customer_is_main'=>0,'role_value_rtm'=>NULL,'role_value_sc'=>NULL,'role_value_sc_shared'=>NULL,'role_value_expert'=>NULL,'role_value_customer'=>NULL);

  /** Determine right user role for sector */
  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {

      if(!isset($building_contacts[$cid][$sector_id]['role_value'])){
        $building_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Recruitment Team Member') {
        $determine_role['role_value_rtm'] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['rtm_is_main'] = 1;
          $determine_role['rtm_is_main_sector'] = $sector_id;

        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($building_contacts[$cid][$sector_id]['role_value'])){
        $building_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator') {
        $determine_role['role_value_sc'] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_is_main'] = 1;
          $determine_role['sc_is_main_sector'] = $sector_id;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($building_contacts[$cid][$sector_id]['role_value'])){
        $building_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Sector Coordinator (shared)') {
        $determine_role['role_value_sc_shared'] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['sc_shared_is_main'] = 1;
          $determine_role['sc_shared_is_main_sector'] = $sector_id;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($building_contacts[$cid][$sector_id]['role_value'])){
        $building_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Expert') {
        $determine_role['role_value_expert'] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

        if($sec_details['is_main'] == 1) {
          $determine_role['expert_is_main'] = 1;
          $determine_role['expert_is_main_sector'] = $sector_id;
        }
      }
    }
  }

  foreach($sector as $sector_id => $sector_details) {
    foreach($sector_details as $key => $sec_details) {
      if(!is_array($building_contacts[$cid][$sector_id]['role_value'])){
        $building_contacts[$cid][$sector_id]['role_value'] = array();
      }
      if($sec_details['role_value'] == 'Customer') {
        $determine_role['role_value_customer'] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['role_value'][] = $sec_details['role_value'];
        $building_contacts[$cid][$sector_id]['is_main'] = $sec_details['is_main'];

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

  $building_contacts[$cid]['role'] = $determine_role;

  /** Insert new building sector for contact */
  if(!empty($determine_role['main_role'])){
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_building_sector_id."','".$determine_role['main_role']."',CURDATE(),NULL,1,1)") or die($conn->error);
  }

  if(!empty($determine_role['role_value_rtm']) && $determine_role['role_value_rtm'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_building_sector_id."','".$determine_role['role_value_rtm']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc']) && $determine_role['role_value_sc'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_building_sector_id."','".$determine_role['role_value_sc']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_sc_shared']) && $determine_role['role_value_sc_shared'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_building_sector_id."','".$determine_role['role_value_sc_shared']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_expert']) && $determine_role['role_value_expert'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_building_sector_id."','".$determine_role['role_value_expert']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
  if(!empty($determine_role['role_value_customer']) && $determine_role['role_value_customer'] != $determine_role['main_role']) {
    $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES ('".$cid."','".$new_building_sector_id."','".$determine_role['role_value_customer']."',CURDATE(),NULL,1,0)") or die($conn->error);
  }
}

/** Now close the old building sector for all contacts */
foreach($building_contacts_to_reenter as $cid => $sectors) {
  foreach($sectors as $key => $sector) {
    $building_contacts_to_reenter[$cid][$key]['role'] = $building_contacts[$cid]['role'];
    foreach($sector as $sector_id => $state) {
      $conn->query("UPDATE civicrm_contact_segment SET `is_main` = '0', `is_active` = '0', `end_date` = CURDATE() WHERE contact_id = '".$cid."' AND segment_id = '".$sector_id."' AND is_active = 1") or die($conn->error);
    }
  }
}


/**
 * De building sector worden nu afgesloten incl. de onderliggende area's of expertise
 * Tevens wordt de nieuwe sector building nu toegevoegd.
 */

/** Now insert new area's of expertise under new sector building */
foreach($old_to_new_areas_of_expertise as $old_aoe => $new_aoe) {
  /** If new aoe is empty, aoe is cancelled, so don't insert **/
  if(!empty($new_aoe)){
    foreach($new_aoe as $name => $label) {
      $conn->query("INSERT INTO civicrm_segment (`name`,`label`,`parent_id`,`is_active`) VALUES ('".$name."','".$label."','".$new_building_sector_id."',1)") or die($conn->error);
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

/** First collect all current area's of expertise of building contacts **/

$contact_aoe = array();
foreach($building_contacts_to_reenter as $cid => $sectors) {
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
      $contact_aoe[$cid][$sid] = array('id'=>$row['id'],'name'=>$row['name'],'label'=>$row['label'],'parent_id'=>$row['parent_id'],'is_active'=>$row['is_active']);
    }
  }
}


/** Then use the labels of the old area's of expertise to fetch the new area of expertise id in the new building sector **/

$new_contact_aoe = array();
foreach($contact_aoe as $cid => $segment) {
  foreach($segment as $old_sid => $sector) {
    if($sector['is_active'] == 1) {
      if(array_key_exists($sector['label'],$old_to_new_areas_of_expertise)){
        foreach($old_to_new_areas_of_expertise[$sector['label']] as $new_aoe_key => $new_aoe_label)
        $query_new_aoe = $conn->query("SELECT * FROM civicrm_segment WHERE label = '".$new_aoe_label."' AND parent_id = '".$new_building_sector_id."' AND is_active = 1");
        while($row_new_segment_id = $query_new_aoe->fetch_assoc()) {
          $new_aoe_ids[$cid][$old_sid] = $row_new_segment_id;
        }
      }
    }
  }
}


/**
 * Now all new area's of expertise ids are loaded in $new_aoe_ids for each contact
 * So now we can just loop through this array and insert the new contact segments for each contact
 **/
foreach($new_aoe_ids as $cid => $sector) {
  foreach($sector as $old_sid => $new_sector_details) {
    if(array_key_exists($old_sid,$building_contacts[$cid]) && array_key_exists('role_value',$building_contacts[$cid][$old_sid]) && $old_sid != 'role'){
      foreach($building_contacts[$cid][$old_sid]['role_value'] as $role_value) {
        if($role_value == 'Recruitment Team Member' && $building_contacts[$cid]['role']['rtm_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('2: '.$conn->error);
        } else if($role_value == 'Recruitment Team Member' && $building_contacts[$cid]['role']['rtm_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('4: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator' && $building_contacts[$cid]['role']['sc_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('3: '.$conn->error);
        } else if($role_value == 'Sector Coordinator' && $building_contacts[$cid]['role']['sc_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('4: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator (shared)' && $building_contacts[$cid]['role']['sc_shared_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('5: '.$conn->error);
        } else if($role_value == 'Sector Coordinator (shared)' && $building_contacts[$cid]['role']['sc_shared_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('6: '.$conn->error);
        }

        if($role_value == 'Expert' && $building_contacts[$cid]['role']['expert_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('7: '.$conn->error);
        } else if($role_value == 'Expert' && $building_contacts[$cid]['role']['expert_is_main'] == 0){
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 0;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('8: '.$conn->error);
        }

        if($role_value == 'Customer' && $building_contacts[$cid]['role']['customer_is_main'] == 1 && is_main_sector($new_sector_details['id']) == TRUE) {
          $new_aoe_ids[$cid][$old_sid]['is_main'] = 1;
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','1')") or die('9: '.$conn->error);
        } else if($role_value == 'Customer' && $building_contacts[$cid]['role']['customer_is_main'] == 0){
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
    foreach($building_contacts[$cid][$old_sid]['role_value'] as $role_value) {
        if($role_value == 'Recruitment Team Member'){
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('2: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator') {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('4: '.$conn->error);
        }

        if($role_value == 'Sector Coordinator (shared)' && $building_contacts[$cid]['role']['sc_shared_is_main'] == 1) {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('6: '.$conn->error);
        }

        if($role_value == 'Expert' && $building_contacts[$cid]['role']['expert_is_main'] == 1) {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('8: '.$conn->error);
        }

        if($role_value == 'Customer' && $building_contacts[$cid]['role']['customer_is_main'] == 1) {
          $conn->query("INSERT INTO civicrm_contact_segment (`contact_id`,`segment_id`,`role_value`,`start_date`,`end_date`,`is_active`,`is_main`) VALUES('".$cid."','".$new_sector_details['id']."','".$role_value."',CURDATE(),NULL,'1','0')") or die('10: '.$conn->error);
        }
      }
  }
}


/**
 * The last thing we need to do is closing the old building sectors
 *
 **/
$query_close_old_building = $conn->query("SELECT id FROM civicrm_segment seg WHERE (
  (seg.parent_id IN (SELECT id FROM civicrm_segment WHERE label IN ('Building Management: Contracting, Execution & Installation','Building Development: Architecture, Design & Engineering','Building Materials: Supplies & Systems')))
  OR
  (seg.parent_id IS NULL AND id IN (SELECT id FROM civicrm_segment WHERE label IN ('Building Management: Contracting, Execution & Installation','Building Development: Architecture, Design & Engineering','Building Materials: Supplies & Systems')))
)") or die($conn->error);

while($row = $query_close_old_building->fetch_assoc()){
  $conn->query("UPDATE civicrm_segment SET `is_active` = '0' WHERE id = '".$row['id']."'") or die($conn->error);
}

echo 'done';