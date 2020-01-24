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
* In General:
* Retail: Business to Consumer	                  => Close sector
* Wholesale: Business to Business                 => Close sector
* Trade & Supply Chain Management                 => New sector
*
* Migrate all content from:
* - Retail: Business to Consumer
* - Wholesale: Business to Business
* to new sector: Trade & Supply Chain Management
*
* Change 1.1: Get segment id and sector coordinator of sector 'Trade & Supply Chain Management'
* Change 1.2: Create new community: 'Trade & Supply Chain Management'
* Change 1.3: Put new segment ID in community
*
* Change 2: Put all content from old sector community 'Retail: Business to Consumer' to new sector community 'Trade & Supply Chain Management'
* Change 2.1: Get taxonomy term ids of old sector 'Retail: Business to Consumer'
* Change 2.2: Retrieve 'Retail: Business to Consumer' nodes
* Change 2.3: Migrate content from old sector community 'Retail: Business to Consumer' to new sector community 'Trade & Supply Chain Management'
* NOTE: OLD Community: 'Retail: Business to Consumer' is automatically closed because sector is inactive
*
* Change 3: Put all content from old sector community 'Wholesale: Business to Business' to new sector community 'Trade & Supply Chain Management'
* Change 3.1: Get taxonomy term ids of old sector 'Wholesale: Business to Business'
* Change 3.2: Retrieve 'Wholesale: Business to Business' nodes
* Change 3.3: Migrate content from old sector community 'Wholesale: Business to Business' to new sector community 'Trade & Supply Chain Management'
* NOTE: OLD Community: 'Wholesale: Business to Business' is automatically closed because sector is inactive
*
***********************************************/

/** Change 1.1: Get segment id and sector coordinator of sector 'Trade & Supply Chain Management' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';
$trade_supply_chain_sector_id = '';
$trade_supply_chain_sc_id = '';

/** Get contact segment id of sector 'Trade & Supply Chain Management' **/
$trade_supply_chain_sector = $conn_civicrm->query("SELECT id FROM civicrm_segment WHERE name = 'trade_supply_chain_management' AND parent_id IS NULL LIMIT 1")->fetch_assoc();
if(count($trade_supply_chain_sector) == 1) {
  $trade_supply_chain_sector_id = $trade_supply_chain_sector['id'];
} else {
  echo 'Trade & Supply Chain Management sector not found. Unable to migrate community.';
  exit();
}
echo 'Trade & Supply Chain Management Sector ID: '.$trade_supply_chain_sector_id;
echo '<br />';

/** Get sector coordinator of sector Trade & Supply Chain Management **/
$trade_supply_chain_sc = $conn_civicrm->query("SELECT contact_id FROM civicrm_contact_segment WHERE segment_id = '".$trade_supply_chain_sector_id."' AND role_value = 'Sector Coordinator' AND end_date IS NULL AND is_active = 1 LIMIT 1")->fetch_assoc();
if(count($trade_supply_chain_sc) == 1) {
  $trade_supply_chain_sc_id = $trade_supply_chain_sc['contact_id'];
} else {
  echo 'Trade & Supply Chain Management coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Trade & Supply Chain Management".';
}
echo 'Trade & Supply Chain Management Coordinator ID: '.$trade_supply_chain_sc_id;
echo '<br />';

/** End Change 1.1 **/

/** Change 1.2: Create new community: 'Trade & Supply Chain Management' &
    Change 1.3: Put new segment ID in community **/
$taxonomy_id_sector = $conn_drupal->query("SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector'")->fetch_object()->vid;
$new_trade_supply_chain_taxonomy_term = new stdClass();
$new_trade_supply_chain_taxonomy_term->name = 'Trade & Supply Chain Management';
$new_trade_supply_chain_taxonomy_term->vid = $taxonomy_id_sector;
$new_trade_supply_chain_taxonomy_term->field_pum_segment_id[LANGUAGE_NONE][0]['value'] = $trade_supply_chain_sector_id;
if(!empty($trade_supply_chain_sc_id)){
  $new_trade_supply_chain_taxonomy_term->field_pum_coordinator_id[LANGUAGE_NONE][0]['value'] = $trade_supply_chain_sc_id;
}
taxonomy_term_save($new_trade_supply_chain_taxonomy_term);

echo 'New Trade & Supply Chain Management taxonomy ID: '.$new_trade_supply_chain_taxonomy_term->tid;
echo '<br />';

/** End Change 1.2 & 1.3 **/


/** Change 2: Put all content from old sector community 'Retail: Business to Consumer' to new sector community 'Trade & Supply Chain Management' **/

/** Change 2.1: Get taxonomy term ids of old sector 'Retail: Business to Consumer' **/
if($query_result = $conn_drupal->query(
      "SELECT tid, name FROM taxonomy_term_data
       WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
       AND name IN ('Retail: Business to Consumer')")) {

    while($row = $query_result->fetch_assoc()){
      $old_community_taxonomies[$row['tid']] = $row['name'];
    }
    $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
}

echo '$old_community_taxonomies_ids Retail: Business to Consumer: '.$old_community_taxonomies_ids;
echo '<br />';

/** Change 2.2: Retrieve 'Retail: Business to Consumer' nodes **/
$community_retail_business_to_consumer_nodes = array();
$all_retail_business_to_consumer_nodes = array();

if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
  while($row = $query_result->fetch_assoc()){
    $community_retail_business_to_consumer_nodes[$row['tid']][] = $row['nid'];
    $all_retail_business_to_consumer_nodes[] = $row['nid'];
  }
}
$all_retail_business_to_consumer_nodes = array_unique($all_retail_business_to_consumer_nodes);

/** Change 2.3: Migrate content from old sector community 'Retail: Business to Consumer' to new sector community 'Trade & Supply Chain Management' */
$changed = array();
foreach($all_retail_business_to_consumer_nodes as $nid) {
  $current_node = node_load($nid);
  $changed[$nid] = $current_node->changed;

  if (!empty($current_node->nid)) {
    $current_node->field_pum_sector['und'][]['tid'] = $new_trade_supply_chain_taxonomy_term->tid;
    $current_node->changed = $current_node->changed;

    foreach($current_node->field_pum_sector['und'] as $key => $value) {
      if(array_key_exists($value['tid'], $community_retail_business_to_consumer_nodes)) {
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
/** End Change 2.3 **/

/** NOTE: OLD Community: 'Retail: Business to Consumer' is automatically closed because sector is inactive **/



/** Change 3: Put all content from old sector community 'Wholesale: Business to Business' to new sector community 'Trade & Supply Chain Management' **/

/** Change 3.1: Get taxonomy term ids of old sector 'Wholesale: Business to Business' **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';

if($query_result = $conn_drupal->query(
      "SELECT tid, name FROM taxonomy_term_data
       WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
       AND name IN ('Wholesale: Business to Business')")) {

    while($row = $query_result->fetch_assoc()){
      $old_community_taxonomies[$row['tid']] = $row['name'];
    }
    $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
}

echo '$old_community_taxonomies_ids Wholesale: Business to Business: '.$old_community_taxonomies_ids;
echo '<br />';

/** Change 3.2: Retrieve 'Wholesale: Business to Business' nodes **/
$community_wholesale_business_to_business_nodes = array();
$all_wholesale_business_to_business_nodes = array();

if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
  while($row = $query_result->fetch_assoc()){
    $community_wholesale_business_to_business_nodes[$row['tid']][] = $row['nid'];
    $all_wholesale_business_to_business_nodes[] = $row['nid'];
  }
}
$all_wholesale_business_to_business_nodes = array_unique($all_wholesale_business_to_business_nodes);

/** Change 3.3: Migrate content from old sector community 'Wholesale: Business to Business' to new sector community 'Trade & Supply Chain Management' */
$changed = array();
foreach($all_wholesale_business_to_business_nodes as $nid) {
  $current_node = node_load($nid);
  $changed[$nid] = $current_node->changed;

  if (!empty($current_node->nid)) {
    $current_node->field_pum_sector['und'][]['tid'] = $new_trade_supply_chain_taxonomy_term->tid;
    $current_node->changed = $current_node->changed;

    foreach($current_node->field_pum_sector['und'] as $key => $value) {
      if(array_key_exists($value['tid'], $community_wholesale_business_to_business_nodes)) {
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

/** NOTE: OLD Community: 'Cancelled - Hospitality: large hotels (>25fte)' is automatically closed because sector is inactive **/

echo 'done';
?>