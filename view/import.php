<?php

require_once('../process/parser.php');
require_once('../process/importer.php');

print("<pre>");

$metadata = $_FILES['input_json'];

if ( $metadata['error'] != 0 )
    die("Error ".$metadata['error']." leyendo el archivo de entrada");



$subforum = parse(file_get_contents($metadata['tmp_name']));

import($subforum);

print("</pre>");