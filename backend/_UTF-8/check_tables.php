�ｻｿ<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=minepiacourrier', 'root', '');
$result = $pdo->query('SHOW TABLES');
echo "Tables existantes:\n";
while ($row = $result->fetch()) {
    echo $row[0] . "\n";
}
