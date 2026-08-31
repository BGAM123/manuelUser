<?php

$output = shell_exec('php bin/console nelmio:apidoc:dump --format=json --no-pretty 2>&1');

// Écrire en UTF-8
file_put_contents('openapi.json', $output);

echo "Swagger generated successfully\n";
