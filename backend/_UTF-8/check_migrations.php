�ｻｿ<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=minepiacourrier', 'root', '');
$result = $pdo->query('SELECT version FROM doctrine_migration_versions ORDER BY executed_at');
echo "Migrations appliquﾃｩes:\n";
while ($row = $result->fetch()) {
    echo $row[0] . "\n";
}
