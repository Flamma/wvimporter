<?php

require_once('model.php');

function parse($json) {
    $entity = json_decode($json);

    $result = null;

    if(isset($entity->threads)) $result = parse_subforum($entity);
    else if(isset($entity->posts)) $result = parse_thread($entity);

    return $result;
}

function parse_subforum($entity) {
    $threads = Array();

    foreach($entity->threads as $thread)
        $threads[] = $thread;

    return new Subforum($entity->title, $threads);

}

function parse_thread($entity) {
    $posts = Array();

    foreach($entity->posts as $post)
        $posts[] = $post;

    $threads = Array(new Thread($entity->title, $posts));

    return new Subforum($entity->title, $threads);
}

function parse_post($entity) {
    return new Post($entity->username, $entity->time, $entity->content);
}