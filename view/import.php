<?php

require_once('../process/parser.php');
require_once('../process/importer.php');

print("<pre>");

$metadata = $_FILES['input_json'];

if ( $metadata['error'] != 0 )
    die("Error ".$metadata['error']." leyendo el archivo de entrada");


$content = file_get_contents($metadata['tmp_name']);

if(!strlen($content) > 0)
    die('File is empty');

$subforum = parse($content);

import($subforum);

echo "DONE\n";
print("</pre>");