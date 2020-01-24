<?php
// bootstrap civicrm environment
require_once 'sites/all/modules/civicrm/civicrm.config.php';
require_once 'sites/all/modules/civicrm/CRM/Core/Config.php';
$civicrm_config = CRM_Core_Config::singleton();

function connect_to_mysql($server,$username,$password,$db) {
  // Create connection
  $conn = new mysqli($server, $username, $password, $db);
  // Check connection
  if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
  }
  // Set charset
  if (!$conn->set_charset('utf8')) {
    die('Error setting charset to UTF8');
  }
  return $conn;
}

$conn_drupal = connect_to_mysql('127.0.0.1','','','');
$conn_civicrm = connect_to_mysql('127.0.0.1','','','');

/** Bootstrap Drupal **/
define('DRUPAL_ROOT', '/var/www/drupal');
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

/** *********************************************
* Change 1: Change community name of 'Energy Production/Services' to 'Energy Production & Services'
* Change 2: Get segment id and sector coordinator of sector 'Water'
* Change 3: Create new sector community 'Water'
* Change 4: Put all content from old sector community 'Water & Waste Water' to new sector community 'Water'
* Change 4.1: Get taxonomy term ids of old sector 'Water & Waste Water'
* Change 4.2: Retrieve 'Water & Waste Water' nodes
* Change 4.3: Migrate content from old sector community 'Water & Waste Water' to new sector community 'Water'
* NOTE: OLD Community: 'Water & Waste Water' is automatically closed because sector is inactive
*
* Change 5: Get segment id and sector coordinator of sector 'Waste Collection & Treatment'
* Change 6: Create new sector community 'Waste & Environment'
*
* Change 7: Put all content from old sector community 'Waste Collection & Treatment' to new sector community 'Waste & Environment'
* Change 7.1: Get taxonomy term ids of old sector 'Waste Collection & Treatment'
* Change 7.2: Retrieve 'Waste Collection & Treatment' nodes
* Change 7.3: Migrate content from old sector community 'Waste Collection & Treatment' to new sector community 'Waste & Environment'
* NOTE: OLD Community: 'Waste Collection & Treatment' is automatically closed because sector is inactive
*
* Change 8: Put all content from old sector community 'Environmental Matters/Corporate Social Responsibility' to new sector community 'Waste & Environment'
* Change 8.1: Get taxonomy term ids of old sector 'Environmental Matters/Corporate Social Responsibility'
* Change 8.2: Retrieve 'Environmental Matters/Corporate Social Responsibility' nodes
* Change 8.3: Migrate content from old sector community 'Environmental Matters/Corporate Social Responsibility' to new sector community 'Waste & Environment'
* NOTE: OLD Community: 'Environmental Matters/Corporate Social Responsibility' is automatically closed because sector is inactive
*
***********************************************/

/** Change 1: Change community name of 'Energy Production/Services' to 'Energy Production & Services' **/
$conn_drupal->query("UPDATE taxonomy_term_data SET name = 'Energy Production & Services' WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector') AND name = 'Energy Production/Services'");
/** End Change 1 **/


/** Change 2: Get segment id and sector coordinator of sector 'Water' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';
$water_sector_id = '';
$water_sc_id = '';

/** Get contact segment id of sector Water **/
$water_sector = $conn_civicrm->query("SELECT id FROM civicrm_segment WHERE name = 'water' AND parent_id IS NULL LIMIT 1")->fetch_assoc();
if(count($water_sector) == 1) {
  $water_sector_id = $water_sector['id'];
} else {
  echo 'Water sector not found. Unable to migrate community.';
  exit();
}
echo 'Water Sector ID: '.$water_sector_id;
echo '<br />';

/** Get sector coordinator of sector Water & Waste Water **/
$water_sc = $conn_civicrm->query("SELECT contact_id FROM civicrm_contact_segment WHERE segment_id = '".$water_sector_id."' AND role_value = 'Sector Coordinator' AND end_date IS NULL AND is_active = 1 LIMIT 1")->fetch_assoc();
if(count($water_sc) == 1) {
  $water_sc_id = $water_sc['contact_id'];
} else {
  echo 'Water coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Water".';
}
echo 'Water Coordinator ID: '.$water_sc_id;
echo '<br />';

/** End Change 2 **/


/** Change 3: Create new sector community 'Water' **/
$taxonomy_id_sector = $conn_drupal->query("SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector'")->fetch_object()->vid;
$new_water_taxonomy_term = new stdClass();
$new_water_taxonomy_term->name = 'Water';
$new_water_taxonomy_term->vid = $taxonomy_id_sector;
$new_water_taxonomy_term->field_pum_segment_id[LANGUAGE_NONE][0]['value'] = $water_sector_id;
if(!empty($water_sc_id)){
  $new_water_taxonomy_term->field_pum_coordinator_id[LANGUAGE_NONE][0]['value'] = $water_sc_id;
}
taxonomy_term_save($new_water_taxonomy_term);

echo 'New water taxonomy ID: '.$new_water_taxonomy_term->tid;
echo '<br />';

/** End Change 3 **/


/** Change 4: Put all content from old sector community 'Water & Waste Water' to new sector community 'Water' **/

/** Change 4.1: Get taxonomy term ids of old sector 'Water & Waste Water' **/
if($query_result = $conn_drupal->query(
      "SELECT tid, name FROM taxonomy_term_data
       WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
       AND name IN ('Water & Waste Water')")) {

    while($row = $query_result->fetch_assoc()){
      $old_community_taxonomies[$row['tid']] = $row['name'];
    }
    $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
}

echo '$old_community_taxonomies_ids Water & Waste Water: '.$old_community_taxonomies_ids;
echo '<br />';

/** Change 4.2: Retrieve 'Water & Waste Water' nodes **/
$community_water_nodes = array();
$all_waterwastewater_nodes = array();

if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
  while($row = $query_result->fetch_assoc()){
    $community_water_nodes[$row['tid']][] = $row['nid'];
    $all_waterwastewater_nodes[] = $row['nid'];
  }
}
$all_waterwastewater_nodes = array_unique($all_waterwastewater_nodes);

/** Change 4.3: Migrate content from old sector community 'Water & Waste Water' to new sector community 'Water' */
$changed = array();
foreach($all_waterwastewater_nodes as $nid) {
  $current_node = node_load($nid);
  $changed[$nid] = $current_node->changed;

  if (!empty($current_node->nid)) {
    $current_node->field_pum_sector['und'][]['tid'] = $new_water_taxonomy_term->tid;
    $current_node->changed = $current_node->changed;

    foreach($current_node->field_pum_sector['und'] as $key => $value) {
      if(array_key_exists($value['tid'], $community_water_nodes)) {
        unset($current_node->field_pum_sector['und'][$key]);
      }
    }

    node_save($current_node);

    //sleep for couple of microseconds to prevent that node is being updated afterwards
    usleep(200);

    /** Update change date of node to last change date, to prevent all nodes being updated due to sector change **/
    $conn_drupal->query("UPDATE node SET changed = '".$changed[$nid]."' WHERE nid = '".$nid."'");
  }
}
/** End Change 4 **/

/** NOTE: OLD Community: 'Water & Waste Water' is automatically closed because sector is inactive **/



/** Change 5: Get segment id and sector coordinator of sector 'Waste Collection & Treatment' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';
$wastecollection_sector_id = '';
$wastecollection_sc_id = '';

/** Get contact segment id of sector Waste Collection & Treatment **/
$wastecollection_sector = $conn_civicrm->query("SELECT id FROM civicrm_segment WHERE name = 'waste_collection_treatment' AND parent_id IS NULL LIMIT 1")->fetch_assoc();
if(count($wastecollection_sector) == 1) {
  $wastecollection_sector_id = $wastecollection_sector['id'];
} else {
  echo 'Waste Collection & Treatment sector not found. Unable to migrate community.';
  exit();
}
echo 'Waste Collection & Treatment Sector ID: '.$wastecollection_sector_id;
echo '<br />';

/** Get sector coordinator of sector Waste Collection & Treatment **/
$wastecollection_sc = $conn_civicrm->query("SELECT contact_id FROM civicrm_contact_segment WHERE segment_id = '".$wastecollection_sector_id."' AND role_value = 'Sector Coordinator' AND end_date IS NULL AND is_active = 1 AND is_main = 1")->fetch_assoc();
if(count($wastecollection_sc) == 1) {
  $wastecollection_sc_id = $wastecollection_sc['contact_id'];
} else {
  echo 'Waste Collection & Treatment coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Waste Collection & Treatment".';
  echo '<br />';
}
echo 'Waste Collection & Treatment Coordinator ID: '.$wastecollection_sc_id;
echo '<br />';

/** End Change 5 **/


/** Change 6: Create new sector community 'Waste & Environment' **/
$taxonomy_id_sector = $conn_drupal->query("SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector'")->fetch_object()->vid;
$new_wasteenvironment_taxonomy_term = new stdClass();
$new_wasteenvironment_taxonomy_term->name = 'Waste & Environment';
$new_wasteenvironment_taxonomy_term->vid = $taxonomy_id_sector;
$new_wasteenvironment_taxonomy_term->field_pum_segment_id[LANGUAGE_NONE][0]['value'] = $wastecollection_sector_id;
if (!empty($wastecollection_sc_id)){
  $new_wasteenvironment_taxonomy_term->field_pum_coordinator_id[LANGUAGE_NONE][0]['value'] = $wastecollection_sc_id;
} else {
  echo 'Waste & Environment coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Waste & Environment".';
  echo '<br />';
}
taxonomy_term_save($new_wasteenvironment_taxonomy_term);

echo 'New waste & environment taxonomy ID: '.$new_wasteenvironment_taxonomy_term->tid;
echo '<br />';

/** End Change 6 **/


/** Change 7: Put all content from old sector community 'Waste Collection & Treatment' to new sector community 'Waste & Environment' **/

  /** Change 7.1: Get taxonomy term ids of old sector 'Waste Collection & Treatment' **/
  if($query_result = $conn_drupal->query(
        "SELECT tid, name FROM taxonomy_term_data
         WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
         AND name IN ('Waste Collection & Treatment')")) {

      while($row = $query_result->fetch_assoc()){
        $old_community_taxonomies[$row['tid']] = $row['name'];
      }
      $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
  }

  echo '$old_community_taxonomies_ids Waste Collection & Treatment: '.$old_community_taxonomies_ids;
  echo '<br />';

  /** Change 7.2: Retrieve 'Waste Collection & Treatment' nodes **/
  $community_wastecollection_nodes = array();
  $all_wastecollection_nodes = array();

  if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
    while($row = $query_result->fetch_assoc()){
      $community_wastecollection_nodes[$row['tid']][] = $row['nid'];
      $all_wastecollection_nodes[] = $row['nid'];
    }
  }
  $all_wastecollection_nodes = array_unique($all_wastecollection_nodes);

  /** Change 7.3: Migrate content from old sector community 'Waste Collection & Treatment' to new sector community 'Waste & Environment' */
  $changed = array();
  foreach($all_wastecollection_nodes as $nid) {
    $current_node = node_load($nid);
    $changed[$nid] = $current_node->changed;

    if (!empty($current_node->nid)) {
      $current_node->field_pum_sector['und'][]['tid'] = $new_wasteenvironment_taxonomy_term->tid;
      $current_node->changed = $current_node->changed;

      foreach($current_node->field_pum_sector['und'] as $key => $value) {
        if(array_key_exists($value['tid'], $community_wastecollection_nodes)) {
          unset($current_node->field_pum_sector['und'][$key]);
        }
      }

      node_save($current_node);

      //sleep for couple of microseconds to prevent that node is being updated afterwards
      usleep(200);

      /** Update change date of node to last change date, to prevent all nodes being updated due to sector change **/
      $conn_drupal->query("UPDATE node SET changed = '".$changed[$nid]."' WHERE nid = '".$nid."'");
    }
  }

/** End Change 7 **/


/** Change 8: Put all content from old sector community 'Environmental Matters/Corporate Social Responsibility' to new sector community 'Waste & Environment' **/

  $old_community_taxonomies = array();
  $old_community_taxonomies_ids = '';

  /** Change 8.1: Get taxonomy term ids of old sector 'Environmental Matters/Corporate Social Responsibility' **/
  if($query_result = $conn_drupal->query(
        "SELECT tid, name FROM taxonomy_term_data
         WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
         AND name IN ('Environmental Matters/Corporate Social Responsibility')")) {

      while($row = $query_result->fetch_assoc()){
        $old_community_taxonomies[$row['tid']] = $row['name'];
      }
      $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
  }

  echo '$old_community_taxonomies_ids Environmental Matters/Corporate Social Responsibility: '.$old_community_taxonomies_ids;
  echo '<br />';

  /** Change 8.2: Retrieve 'Environmental Matters/Corporate Social Responsibility' nodes **/
  $community_csr_nodes = array();
  $all_csr_nodes = array();

  if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
    while($row = $query_result->fetch_assoc()){
      $community_csr_nodes[$row['tid']][] = $row['nid'];
      $all_csr_nodes[] = $row['nid'];
    }
  }
  $all_csr_nodes = array_unique($all_csr_nodes);

  /** Change 8.3: Migrate content from old sector community 'Environmental Matters/Corporate Social Responsibility' to new sector community 'Waste & Environment' */
  $changed = array();
  foreach($all_csr_nodes as $nid) {
    $current_node = node_load($nid);
    $changed[$nid] = $current_node->changed;

    if (!empty($current_node->nid)) {
      $current_node->field_pum_sector['und'][]['tid'] = $new_wasteenvironment_taxonomy_term->tid;
      $current_node->changed = $current_node->changed;

      foreach($current_node->field_pum_sector['und'] as $key => $value) {
        if(array_key_exists($value['tid'], $community_csr_nodes)) {
          unset($current_node->field_pum_sector['und'][$key]);
        }
      }

      node_save($current_node);

      //sleep for couple of microseconds to prevent that node is being updated afterwards
      usleep(200);

      /** Update change date of node to last change date, to prevent all nodes being updated due to sector change **/
      $conn_drupal->query("UPDATE node SET changed = '".$changed[$nid]."' WHERE nid = '".$nid."'");
    }
  }

/** End Change 8 **/

echo 'done';
?>