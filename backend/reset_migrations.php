<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=minepiacourrier', 'root', '');
echo "Suppression de la table doctrine_migration_versions...\n";
$pdo->exec('DROP TABLE IF EXISTS doctrine_migration_versions');
echo "Table supprimée.\n";
