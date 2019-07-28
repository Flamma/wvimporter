<?php

require_once('model.php');
require_once('../config/database.php');
require_once('mia_bb_transformer.php');

$IMPORT_FORUM_NAME = "IMPORT ".date('c')." ";

function import($subforum) {
    global $DB_CONFIG;
    $mysqli = connect($DB_CONFIG['database']);

    if ($mysqli->connect_errno) {
       die ("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
    }

    if(!$mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE)) {
        $mysqli->close();
        die ("Failed to begin transaction");
    }

    $sf_id = create_subforum($subforum, $mysqli);

    $usernames = get_users_from_subforum($subforum);

    $users = get_users_from_database($usernames, $mysqli);

    if(count($users['unregistered']) > 0) {
        register_users($users['unregistered'], $mysqli);

        $users = get_users_from_database($usernames, $mysqli);
    }

    print_r($users);

    create_threads($subforum, $sf_id, $users['registered'], $mysqli);

    $mysqli->commit();

    echo "HECHO";

}

function connect($config) {
    $mysqli = new mysqli($config['hostname'],$config['username'], $config['password'], $config['database']);

    if (!$mysqli->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $mysqli->error);
      exit();
    }

    return $mysqli;
}

function get_users_from_subforum($subforum) {
    $usernames = Array();

    foreach($subforum->threads as $thread)
        foreach($thread->posts as $post)
            $usernames[$post->username] = true;

    return array_keys($usernames);
}

function get_users_from_database($usernames, $mysqli) {
    $users = Array(
        'registered' => Array(),
        'unregistered' => Array()
    );

    $escaped_users = Array();

    foreach($usernames as $username) {
        $escaped_users[] = $mysqli->real_escape_string($username);
    }

    $query_users = "'" . join($escaped_users,"', '") . "'";

    $sql = "select user_id, username from phpbb_users where username in ($query_users)";

    $res = query($sql, $mysqli);

    $res->data_seek(0);
    while($row = $res->fetch_assoc()) {
        $users['registered'][$row['username']] = $row['user_id'];
    }

    $users['unregistered'] = array_diff($usernames, array_keys($users['registered']));

    return $users;
}

function register_users($usernames, $mysqli) {
    $insert_users = Array();

    foreach($usernames as $username) {
        $insert_users[] = "('$username', '', '', '".strtolower($username)."', ".time().")";
    }

    $sql = "insert into phpbb_users(username, user_permissions, user_sig, username_clean, user_regdate)
        values ".join($insert_users, ",");

    query($sql, $mysqli);
}

function create_subforum($subforum, $mysqli) {
    // Create subforum
    global $IMPORT_FORUM_NAME;
    $forum_name = $IMPORT_FORUM_NAME.$mysqli->real_escape_string($subforum->title);

    $sql = "insert into phpbb_forums(forum_name, forum_parents, forum_desc, forum_rules, forum_type, left_id,  right_id)
        (select '$forum_name', '', '', '', 1, max(right_id)+1, max(right_id)+2 from phpbb_forums)";

    query($sql, $mysqli);

    // Get subforum_id
    $sql = "select forum_id from phpbb_forums where forum_name ='$forum_name' limit 1";

    $res = query($sql, $mysqli);

    $res->data_seek(0);
    $row = $res->fetch_assoc();

    return $row['forum_id'];

}

function create_threads($subforum, $sf_id, $users, $mysqli) {
    foreach($subforum->threads as $thread) {
        create_thread($thread, $sf_id, $users, $mysqli);
    }
}

function create_thread($thread, $sf_id, $users, $mysqli) {
    // Create thread
    $title = $mysqli->real_escape_string($thread->title);

    $sql = "insert into phpbb_topics(forum_id, topic_title, topic_posts_approved)
        values ($sf_id, '$title', ".count($thread->posts).")";

    query($sql, $mysqli);

    // Get thread id
    $sql = "select max(topic_id) from phpbb_topics";

    $res = query($sql, $mysqli);

    $res->data_seek(0);
    $row = $res->fetch_row();

    $thread_id = $row[0];

    // Create posts
    create_posts($thread->posts, $sf_id, $thread_id, $users, $mysqli);
}

function create_posts($posts, $sf_id, $thread_id, $users, $mysqli) {
    $post_insert = Array();

    foreach($posts as $post)
        $post_insert[] = get_post_insert($post, $sf_id, $thread_id, $users, $mysqli);

    $sql = "insert into phpbb_posts(forum_id, topic_id, poster_id, post_time, post_text, post_visibility)
        values ".join($post_insert, ", ");

    query($sql, $mysqli);
}

function get_post_insert($post, $sf_id, $thread_id, $users, $mysqli) {
    $user_id = $users[$post->username];
    $time = strtotime($post->time);
    $content = $mysqli->real_escape_string(transform_content($post->content));

    return "($sf_id, $thread_id, $user_id, $time,'$content', 1)";
}

function query($sql, $mysqli) {
    global $SQL_DEBUG;
    if($SQL_DEBUG) print("\n$sql");

    $res = $mysqli->query($sql);

    if($mysqli->errno) {
        print("\nError ".$mysqli->errno." executing the query.");
        print("\n$sql\n");
        print_r($mysqli->error_list);
        if(!$mysqli->rollback()) print("\nError rolling back");
        $myqli->close();
        die();
    }

    return $res;
}