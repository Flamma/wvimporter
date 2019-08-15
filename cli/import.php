<?php

require_once('../process/parser.php');
require_once('../process/importer.php');

if($argc < 2) {
    die("You must specify the file to be imported.\n");
}

$filename = $argv[1];

if(!file_exists($filename)) {
    die("Cannot find $filename\n");
}

$content = file_get_contents($filename);

if(!strlen($content) > 0)
    die('File is empty');

$subforum = parse($content);

import($subforum);

echo "DONE\n";