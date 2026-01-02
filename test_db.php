<?php
require 'src/config/db.php';

$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll();

echo '<pre>';
print_r($tables);
