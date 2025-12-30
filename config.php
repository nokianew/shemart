<?php
echo "<pre>";

echo "MYSQL_URL via getenv(): ";
var_dump(getenv('MYSQL_URL'));

echo "\n\nMYSQL_URL via _SERVER:\n";
var_dump($_SERVER['MYSQL_URL'] ?? null);

echo "\n\n_ALL ENV:\n";
print_r($_ENV);

exit;
