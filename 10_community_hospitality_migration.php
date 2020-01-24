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
* NOTE: No changes to Community Hospitality: tourism & recreational services
*
* In General:
* Hospitality: catering, restaurants & events	    => Rename to Hospitality: hotels, restaurants & catering
* Hotels						                              => Close sector
* Cancelled - Hospitality: large hotels (>25fte)	=> Close sector
* Cancelled - Hospitality: small hotels (<25fte)	=> Close sector
* Hospitality: education				                  => New sector
* Hospitality: tourism & recreational services    => Geen wijzigingen
*
* Migrate all content from:
* - Hotels
* - Cancelled - Hospitality: large hotels (>25fte)
* - Cancelled - Hospitality: small hotels (<25fte)
* to new sector: Hospitality: hotels, restaurants & catering
*
*
* Change 1.1: Get segment id and sector coordinator of sector 'Hospitality: hotels, restaurants & catering'
* Change 1.2: Rename sector community 'Hospitality: catering, restaurants & events' to 'Hospitality: hotels, restaurants & catering'
* Change 1.3: Put new segment ID in community
*
* Change 2.1: Get segment id and sector coordinator of sector 'Hospitality: education'
* Change 2.2: Create new community: 'Hospitality: education'
* Change 2.3: Put new segment ID in community
*
* Change 3: Put all content from old sector community 'Hotels' to new sector community 'Hospitality: hotels, restaurants & catering'
* Change 3.1: Get taxonomy term ids of old sector 'Hotels'
* Change 3.2: Retrieve 'Hotels' nodes
* Change 3.3: Migrate content from old sector community 'Hotels' to new sector community 'Hospitality: hotels, restaurants & catering'
* NOTE: OLD Community: 'Hotels' is automatically closed because sector is inactive
*
* Change 4: Put all content from old sector community 'Cancelled - Hospitality: large hotels (>25fte)' to new sector community 'Hospitality: hotels, restaurants & catering'
* Change 4.1: Get taxonomy term ids of old sector 'Cancelled - Hospitality: large hotels (>25fte)'
* Change 4.2: Retrieve 'Cancelled - Hospitality: large hotels (>25fte)' nodes
* Change 4.3: Migrate content from old sector community 'Cancelled - Hospitality: large hotels (>25fte)' to new sector community 'Hospitality: hotels, restaurants & catering'
* NOTE: OLD Community: 'Cancelled - Hospitality: large hotels (>25fte)' is automatically closed because sector is inactive
*
* Change 5: Put all content from old sector community 'Cancelled - Hospitality: small hotels (<25fte)' to new sector community 'Hospitality: hotels, restaurants & catering'
* Change 5.1: Get taxonomy term ids of old sector 'Cancelled - Hospitality: small hotels (<25fte)'
* Change 5.2: Retrieve 'Cancelled - Hospitality: small hotels (<25fte)' nodes
* Change 5.3: Migrate content from old sector community 'Cancelled - Hospitality: small hotels (<25fte)' to new sector community 'Hospitality: hotels, restaurants & catering'
* NOTE: OLD Community: 'Cancelled - Hospitality: small hotels (<25fte)' is automatically closed because sector is inactive
*
***********************************************/

/** Change 1.1: Get segment id and sector coordinator of sector 'Hospitality: hotels, restaurants & catering' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';
$hotels_restaurants_sector_id = '';
$hotels_restaurants_sc_id = '';

/** Get contact segment id of sector Hospitality: hotels, restaurants & catering **/
$hotels_restaurants_sector = $conn_civicrm->query("SELECT id FROM civicrm_segment WHERE name = 'hospitality_hotels_restaurants_catering' AND parent_id IS NULL LIMIT 1")->fetch_assoc();
if(count($hotels_restaurants_sector) == 1) {
  $hotels_restaurants_sector_id = $hotels_restaurants_sector['id'];
} else {
  echo 'Hospitality: hotels, restaurants & catering sector not found. Unable to migrate community.';
  exit();
}
echo 'Hospitality: hotels, restaurants & catering Sector ID: '.$hotels_restaurants_sector_id;
echo '<br />';

/** Get sector coordinator of sector Hospitality: hotels, restaurants & catering **/
$hotels_restaurants_sc = $conn_civicrm->query("SELECT contact_id FROM civicrm_contact_segment WHERE segment_id = '".$hotels_restaurants_sector_id."' AND role_value = 'Sector Coordinator' AND end_date IS NULL AND is_active = 1 LIMIT 1")->fetch_assoc();
if(count($hotels_restaurants_sc) == 1) {
  $hotels_restaurants_sc_id = $hotels_restaurants_sc['contact_id'];
} else {
  echo 'Hospitality: hotels, restaurants & catering coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Hospitality: hotels, restaurants & catering".';
}
echo 'Hospitality: hotels, restaurants & catering Coordinator ID: '.$hotels_restaurants_sc_id;
echo '<br />';

/** End Change 1.1 **/

/** Change 1.2: Rename sector community 'Hospitality: catering, restaurants & events' to 'Hospitality: hotels, restaurants & catering' **/
$conn_drupal->query("UPDATE taxonomy_term_data SET name = 'Hospitality: hotels, restaurants & catering' WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector') AND name = 'Hospitality: catering, restaurants & events'");
/** End Change 1.2 **/

/** Change 1.3: Put new segment ID in community **/
$taxonomy_id_sector = $conn_drupal->query("SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector'")->fetch_object()->vid;
$new_hospitalityhotels_taxonomy_term = new stdClass();
$new_hospitalityhotels_taxonomy_term->name = 'Hospitality: hotels, restaurants & catering';
$new_hospitalityhotels_taxonomy_term->vid = $taxonomy_id_sector;
$new_hospitalityhotels_taxonomy_term->field_pum_segment_id[LANGUAGE_NONE][0]['value'] = $hotels_restaurants_sector_id;
if (!empty($hotels_restaurants_sc_id)){
  $new_hospitalityhotels_taxonomy_term->field_pum_coordinator_id[LANGUAGE_NONE][0]['value'] = $hotels_restaurants_sc_id;
} else {
  echo 'Hospitality: hotels, restaurants & catering coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Hospitality: hotels, restaurants & catering".';
  echo '<br />';
}
taxonomy_term_save($new_hospitalityhotels_taxonomy_term);

/** End Change 1.3 **/



/** Change 2.1: Get segment id and sector coordinator of sector 'Hospitality: education' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';
$hospitality_education_sector_id = '';
$hospitality_education_sc_id = '';

/** Get contact segment id of sector Hospitality: education **/
$hospitality_education_sector = $conn_civicrm->query("SELECT id FROM civicrm_segment WHERE name = 'hospitality_education' AND parent_id IS NULL LIMIT 1")->fetch_assoc();
if(count($hospitality_education_sector) == 1) {
  $hospitality_education_sector_id = $hospitality_education_sector['id'];
} else {
  echo 'Hospitality: education sector not found. Unable to migrate community.';
  exit();
}
echo 'Hospitality: education Sector ID: '.$hospitality_education_sector_id;
echo '<br />';

/** Get sector coordinator of sector Hospitality: education **/
$hospitality_education_sc = $conn_civicrm->query("SELECT contact_id FROM civicrm_contact_segment WHERE segment_id = '".$hospitality_education_sector_id."' AND role_value = 'Sector Coordinator' AND end_date IS NULL AND is_active = 1 LIMIT 1")->fetch_assoc();
if(count($hospitality_education_sc) == 1) {
  $hospitality_education_sc_id = $hospitality_education_sc['contact_id'];
} else {
  echo 'Hospitality: education coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Hospitality: education".';
}
echo 'Hospitality: education Coordinator ID: '.$hospitality_education_sc_id;
echo '<br />';

/** End Change 2.1 **/


/** Change 2.2: Create new community: 'Hospitality: education' &
    Change 2.3: Put new segment ID in community **/
$taxonomy_id_sector = $conn_drupal->query("SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector'")->fetch_object()->vid;
$new_hospitality_education_taxonomy_term = new stdClass();
$new_hospitality_education_taxonomy_term->name = 'Hospitality: education';
$new_hospitality_education_taxonomy_term->vid = $taxonomy_id_sector;
$new_hospitality_education_taxonomy_term->field_pum_segment_id[LANGUAGE_NONE][0]['value'] = $hospitality_education_sector_id;
if(!empty($hospitality_education_sc_id)){
  $new_hospitality_education_taxonomy_term->field_pum_coordinator_id[LANGUAGE_NONE][0]['value'] = $hospitality_education_sc_id;
}
taxonomy_term_save($new_hospitality_education_taxonomy_term);

echo 'New water taxonomy ID: '.$new_hospitality_education_taxonomy_term->tid;
echo '<br />';

/** End Change 2.2 & 2.3 **/



/** Change 3: Put all content from old sector community 'Hotels' to new sector community 'Hospitality: hotels, restaurants & catering' **/

/** Change 3.1: Get taxonomy term ids of old sector 'Hotels' **/
if($query_result = $conn_drupal->query(
      "SELECT tid, name FROM taxonomy_term_data
       WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
       AND name IN ('Hotels')")) {

    while($row = $query_result->fetch_assoc()){
      $old_community_taxonomies[$row['tid']] = $row['name'];
    }
    $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
}

echo '$old_community_taxonomies_ids Hotels: '.$old_community_taxonomies_ids;
echo '<br />';

/** Change 3.2: Retrieve 'Hotels' nodes **/
$community_hotels_nodes = array();
$all_hotels_nodes = array();

if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
  while($row = $query_result->fetch_assoc()){
    $community_hotels_nodes[$row['tid']][] = $row['nid'];
    $all_hotels_nodes[] = $row['nid'];
  }
}
$all_hotels_nodes = array_unique($all_hotels_nodes);

/** Change 3.3: Migrate content from old sector community 'Hotels' to new sector community 'Hospitality: hotels, restaurants & catering' */
$changed = array();
foreach($all_hotels_nodes as $nid) {
  $current_node = node_load($nid);
  $changed[$nid] = $current_node->changed;

  if (!empty($current_node->nid)) {
    $current_node->field_pum_sector['und'][]['tid'] = $new_hospitalityhotels_taxonomy_term->tid;
    $current_node->changed = $current_node->changed;

    foreach($current_node->field_pum_sector['und'] as $key => $value) {
      if(array_key_exists($value['tid'], $community_hotels_nodes)) {
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
/** End Change 3.3 **/

/** NOTE: OLD Community: 'Hotels' is automatically closed because sector is inactive **/



/** Change 4: Put all content from old sector community 'Cancelled - Hospitality: large hotels (>25fte)' to new sector community 'Hospitality: hotels, restaurants & catering' **/

/** Change 4.1: Get taxonomy term ids of old sector 'Cancelled - Hospitality: large hotels (>25fte)' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';

if($query_result = $conn_drupal->query(
      "SELECT tid, name FROM taxonomy_term_data
       WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
       AND name IN ('Cancelled - Hospitality: large hotels (>25fte)')")) {

    while($row = $query_result->fetch_assoc()){
      $old_community_taxonomies[$row['tid']] = $row['name'];
    }
    $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
}

echo '$old_community_taxonomies_ids Cancelled - Hospitality: large hotels (>25fte): '.$old_community_taxonomies_ids;
echo '<br />';

/** Change 4.2: Retrieve 'Cancelled - Hospitality: large hotels (>25fte)' nodes **/
$community_hotels_large_hotels_nodes = array();
$all_hotels_large_hotels_nodes = array();

if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
  while($row = $query_result->fetch_assoc()){
    $community_hotels_large_hotels_nodes[$row['tid']][] = $row['nid'];
    $all_hotels_large_hotels_nodes[] = $row['nid'];
  }
}
$all_hotels_large_hotels_nodes = array_unique($all_hotels_large_hotels_nodes);

/** Change 4.3: Migrate content from old sector community 'Cancelled - Hospitality: large hotels (>25fte)' to new sector community 'Hospitality: hotels, restaurants & catering' */
$changed = array();
foreach($all_hotels_large_hotels_nodes as $nid) {
  $current_node = node_load($nid);
  $changed[$nid] = $current_node->changed;

  if (!empty($current_node->nid)) {
    $current_node->field_pum_sector['und'][]['tid'] = $new_hospitalityhotels_taxonomy_term->tid;
    $current_node->changed = $current_node->changed;

    foreach($current_node->field_pum_sector['und'] as $key => $value) {
      if(array_key_exists($value['tid'], $community_hotels_large_hotels_nodes)) {
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
/** End Change 4.3 **/

/** NOTE: OLD Community: 'Cancelled - Hospitality: large hotels (>25fte)' is automatically closed because sector is inactive **/



/** Change 5: Put all content from old sector community 'Cancelled - Hospitality: small hotels (<25fte)' to new sector community 'Hospitality: hotels, restaurants & catering' **/

/** Change 5.1: Get taxonomy term ids of old sector 'Cancelled - Hospitality: small hotels (<25fte)' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';

if($query_result = $conn_drupal->query(
      "SELECT tid, name FROM taxonomy_term_data
       WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
       AND name IN ('Cancelled - Hospitality: small hotels (<25fte)')")) {

    while($row = $query_result->fetch_assoc()){
      $old_community_taxonomies[$row['tid']] = $row['name'];
    }
    $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
}

echo '$old_community_taxonomies_ids Hospitality: small hotels (<25fte): '.$old_community_taxonomies_ids;
echo '<br />';

/** Change 5.2: Retrieve 'Cancelled - Hospitality: small hotels (<25fte)' nodes **/
$community_hotels_small_hotels_nodes = array();
$all_hotels_small_hotels_nodes = array();

if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
  while($row = $query_result->fetch_assoc()){
    $community_hotels_small_hotels_nodes[$row['tid']][] = $row['nid'];
    $all_hotels_small_hotels_nodes[] = $row['nid'];
  }
}
$all_hotels_small_hotels_nodes = array_unique($all_hotels_small_hotels_nodes);

/** Change 5.3: Migrate content from old sector community 'Cancelled - Hospitality: small hotels (<25fte)' to new sector community 'Hospitality: hotels, restaurants & catering' */
$changed = array();
foreach($all_hotels_small_hotels_nodes as $nid) {
  $current_node = node_load($nid);
  $changed[$nid] = $current_node->changed;

  if (!empty($current_node->nid)) {
    $current_node->field_pum_sector['und'][]['tid'] = $new_hospitalityhotels_taxonomy_term->tid;
    $current_node->changed = $current_node->changed;

    foreach($current_node->field_pum_sector['und'] as $key => $value) {
      if(array_key_exists($value['tid'], $community_hotels_small_hotels_nodes)) {
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
/** End Change 5.3 **/

/** NOTE: OLD Community: 'Cancelled - Hospitality: small hotels (<25fte)' is automatically closed because sector is inactive **/

echo 'done';
?>