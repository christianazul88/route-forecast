<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$source = file_get_contents(__DIR__ . '/schema.sql');
if ($source === false) {
    throw new RuntimeException('Could not read schema.sql.');
}
$hosting = preg_replace('/\ACREATE DATABASE[^\n]*\RUSE route_forecast;\R/u', '', $source, 1, $count);
if ($hosting === null || $count !== 1) {
    throw new RuntimeException('Unexpected schema.sql header.');
}
$header = "-- Select the existing database in phpMyAdmin before importing this file.\n";
file_put_contents(__DIR__ . '/hosting_schema.sql', $header . $hosting);
fwrite(STDOUT, "Created database/hosting_schema.sql\n");
