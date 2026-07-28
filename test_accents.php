<?php
require 'includes/config.php';
$db = Core\Database::getConnection();
$row = $db->query('SELECT nombre FROM programas LIMIT 1')->fetch();
var_dump($row);
echo "\n";
echo "DB Charset: " . $db->query("SELECT @@character_set_database")->fetchColumn();
