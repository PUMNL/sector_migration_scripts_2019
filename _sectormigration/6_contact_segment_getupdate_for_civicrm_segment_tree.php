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

  $conn = connect_to_mysql('','','','');
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

  echo '<pre>';
  print_r($main_sectors);
  echo '</pre>';

  $conn->query("TRUNCATE civicrm_segment_tree");

  foreach($main_sectors as $key => $value) {
    $conn->query("INSERT INTO civicrm_segment_tree (id) VALUES ('".$value."')");
  }
  
  echo 'contact segment tree update successfull if no errors are shown';
?>