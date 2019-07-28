<?php

function transform_content($content) {
    $result = add_class_quote($content);

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