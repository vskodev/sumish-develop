<?php

/*
Logining to error.log with composer.json

"autoload-dev": {
  "files": [
    "helpers.php"
  ]
}
*/

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/log/error.log');

function cs($output) {
    fwrite(STDERR, "\n-------------------------- Console output --------------------------\n");
    fwrite(STDERR, $output);
    fwrite(STDERR, "\n--------------------------------------------------------------------\n");
}

function lg($data) {
    error_log($data);
}
