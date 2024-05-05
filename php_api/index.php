<?php

require_once "config.php";
require_once "utils.php";
require_once "user.php";
require_once "blog.php";


$handlers = array_merge($userHandlers, $blogHandlers);

$token = null;
if (array_key_exists($config['JWT_AUTH_COOKIE'], $_COOKIE)){
    $token = $_COOKIE[$config['JWT_AUTH_COOKIE']];
    $_COOKIE['user'] = Token::verify_token($token);
}

$fullPath = $_SERVER['REQUEST_METHOD']." ".$_SERVER['REQUEST_URI'];
$param = "";
foreach ($handlers as $path => $hand) {
    $ret2 = strlen($fullPath);

    if (strpos($path, ":id") !==false) { 
        $pathTemp = substr_replace($path, "", strlen($path)-3, 3);
        $ret = strpos($fullPath, $pathTemp);
        $ret3 = strlen($pathTemp);
        if ($ret !==false && $ret2 > $ret3) {
            $param = substr_replace($fullPath, "", 0, strlen($pathTemp));
            if (strstr($param, "/") != false) {
                $param = substr_replace($param, "", strlen($param) - 1, 1);
            }
            $pattern = "/^\d+$/";
            $matches = null;
            if (preg_match($pattern, $param, $matches)) { //param must be digital
                call_user_func($handlers[$path], $param);
                break;
            } else {
                $param = "";
            }    
        }
    }
}
$fullPath = $_SERVER['REQUEST_METHOD']." ".$_SERVER['REQUEST_URI'];
if ($param == "") {
    foreach ($handlers as $path => $hand) {
        $ret2 = strlen($fullPath);

        if (strpos($path, "@id") ==false) { // have param
            $ret = strpos($fullPath, $path);
            $ret3 = strlen($path);
            if ($ret !== false && $ret2 >= $ret3) {
                $param = substr_replace($fullPath, "", 0, strlen($path));
                call_user_func($handlers[$path], $param);
                break;
            }
        }
    }
}

?>