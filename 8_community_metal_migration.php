<?php
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

/** Retrieve metal communities **/
$old_community_taxonomies = array();
$old_community_taxonomies_ids = '';
$metal_sector_id = '';
$metal_sc_id = '';

/** Get contact segment id of sector metal **/
$metal_sector = $conn_civicrm->query("SELECT id FROM civicrm_segment WHERE name = 'metal' AND parent_id IS NULL LIMIT 1")->fetch_assoc();
if(count($metal_sector) == 1) {
  $metal_sector_id = $metal_sector['id'];
} else {
  echo 'Metal sector not found. Unable to migrate community.';
  exit();
}
echo 'Metal Sector ID: '.$metal_sector_id;
echo '<br />';

/** Get sector coordinator of sector metal **/
$metal_sc = $conn_civicrm->query("SELECT contact_id FROM civicrm_contact_segment WHERE segment_id = '".$metal_sector_id."' AND role_value = 'Sector Coordinator' AND end_date IS NULL AND is_active = 1 AND is_main = 1")->fetch_assoc();
if(count($metal_sc) == 1) {
  $metal_sc_id = $metal_sc['contact_id'];
} else {
  echo 'Metal coordinator not found, or multiple coordinators found. Please correct later in taxonomy "Metal".';
}
echo 'Metal Coordinator ID: '.$metal_sc_id;
echo '<br />';

/** Get taxonomy term ids of old metal sectors **/
if($query_result = $conn_drupal->query(
      "SELECT tid, name FROM taxonomy_term_data
       WHERE vid = (SELECT vid FROM taxonomy_vocabulary WHERE taxonomy_vocabulary.machine_name = 'sector')
       AND name IN ('Metal: Metal Processing','Metal: Machine Engineering & Construction','Metal: aircraft maintenance & shipbuilding, repair','Metal: Metal Construction, Maintenance & Repair')")) {

    while($row = $query_result->fetch_assoc()){
      $old_community_taxonomies[$row['tid']] = $row['name'];
    }
    $old_community_taxonomies_ids = implode(',', array_keys($old_community_taxonomies));
}

echo '$old_community_taxonomies_ids: '.$old_community_taxonomies_ids;
echo '<br />';

/** Retrieve metal nodes **/
$community_metal_nodes = array();
$all_metal_nodes = array();

if($query_result = $conn_drupal->query("SELECT nid, tid FROM taxonomy_index WHERE tid IN (".$old_community_taxonomies_ids.")")) {
  while($row = $query_result->fetch_assoc()){
    $community_metal_nodes[$row['tid']][] = $row['nid'];
    $all_metal_nodes[] = $row['nid'];
  }
}
$all_metal_nodes = array_unique($all_metal_nodes);

/** Create new community 'Metal' **/
$taxonomy_id_sector = $conn_drupal->query("SELECT vid FROM taxonomy_vocabulary WHERE machine_name = 'sector'")->fetch_object()->vid;
$new_metal_taxonomy_term = new stdClass();
$new_metal_taxonomy_term->name = 'Metal';
$new_metal_taxonomy_term->vid = $taxonomy_id_sector;
$new_metal_taxonomy_term->field_pum_segment_id[LANGUAGE_NONE][0]['value'] = $metal_sector_id;
$new_metal_taxonomy_term->field_pum_coordinator_id[LANGUAGE_NONE][0]['value'] = $metal_sc_id;
taxonomy_term_save($new_metal_taxonomy_term);

echo 'New metal taxonomy ID: '.$new_metal_taxonomy_term->tid;
echo '<br />';

/** Update all nodes to new community 'Metal' */
$changed = array();
foreach($all_metal_nodes as $nid) {
  $current_node = node_load($nid);
  $changed[$nid] = $current_node->changed;

  if (!empty($current_node->nid)) {
    $current_node->field_pum_sector['und'][]['tid'] = $new_metal_taxonomy_term->tid;
    $current_node->changed = $current_node->changed;

    foreach($current_node->field_pum_sector['und'] as $key => $value) {
      if(array_key_exists($value['tid'], $community_metal_nodes)) {
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

echo 'done';
?>