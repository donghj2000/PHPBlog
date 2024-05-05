<?php

require_once "config.php";
require_once "utils.php";

function CreateAdmin() {
    try {
        $password = encrypt("123456");
        $sql = "INSERT INTO blog_user (password,is_superuser,username,email,is_active,creator,modifier, avatar,nickname,description, created_at, modified_at) ".
        "VALUES ('{$password}', 1, 'admin', 'xxxxxxxx@yy.com', 1, 1, 1, '', '', '', now(), now())";
           
        db_query($sql);
    } catch (Exception $e) {

    }
}

CreateAdmin();

?>