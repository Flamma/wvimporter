<?php

class Post {
    var $username;
    var $time;
    var $content;

    function __construct($ursername, $time, $content) {
        $this->username = $username;
        $this->time = $time;
        $this->content = $content;
    }
}

class Thread {
    var $title;
    var $posts;

    function __construct($title, $posts) {
        $this->title = $title;
        $this->posts = $posts;
    }

}

class Subforum {
    var $title;
    var $threads;

    function __construct($title, $threads) {
        $this->title = $title;
        $this->threads = $threads;
    }
}
