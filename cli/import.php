<?php

require_once('../process/parser.php');
require_once('../process/importer.php');

if($argc < 2) {
    die("You must specify the files to be imported.\n");
}

$error_files = Array();

$filenames = array_slice($argv, 1);

foreach($filenames as $filename) {
    print("Importing '$filename'...\n");

    if(!file_exists($filename)) {
        $error_files[] = $filename;
        print("Cannot find '$filename'\n");
    }

    $content = file_get_contents($filename);

    if(!strlen($content) > 0) {
        $error_files = $filename;
        print("'$filename' is empty\n");
    }

    $subforum = parse($content);

    import($subforum);
}

echo "DONE\n";

if(count($error_files) > 0) {
    print("The following files could not be imported: \n");
    foreach($error_files as $error_file)
        print("\t$error_file\n");

    die();
}
