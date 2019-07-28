<?php

require_once '../vendor/autoload.php';

function transform_content($content) {
    $result = add_class_quote($content);
    $result = replace_no_href_links($result); // Due to a bug on HTMLConverter
    $result = html_to_bbcode($result);

    return $result;
}

function add_class_quote($content){
    return '
<style>
    .cite, .mia_cite     {
        color: red;
        font-size: 2em;
        background-color: black;
    }
</style>
    '.$content;
}

function html_to_bbcode($content) {
    $converter = new Converter\HTMLConverter($content);

    try {
        $result = $converter->toBBCode();
    } catch (Exception $e) {
        print("\nExcepción $e capturada con el contenido:");
        print("\n$content");
        throw $e;
    }

    return $result;
}

function replace_no_href_links($content) {
    preg_match_all("/<a([^>]*)>/", $content, $matches, PREG_SET_ORDER);

    foreach($matches as $matching) {
        if(strpos($matching[1], "href") === FALSE)
            $content = str_replace($matching[0], '<a href="#" '.$matching[1].'>', $content);
    }

    return $content;
}