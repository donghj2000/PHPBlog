<?php

require_once "config.php";
require_once "utils.php";

function CreateUser($param) {
    global $config;
    $jsonData = file_get_contents('php://input');
    $user = json_decode($jsonData, true);

    try {
        $user['password'] = encrypt($user['password']);
        $sql = "INSERT INTO blog_user (password,is_superuser,username,email,is_active,creator,modifier, avatar,nickname,description, created_at, modified_at) ".
        "VALUES ('{$user['password']}', 0, '{$user['username']}', '{$user['email']}', 0, 0, 0, '{$user['avatar']}', '{$user['nickname']}', '{$user['desc']}', now(), now())";
           
        if (!db_query($sql)) {
            http_response_code(500);
            echo json_encode(array("detail" => "Internal error"));
            return;
        }
        $sql = "select id from blog_user where username = '{$user['username']}'";
        $row = db_query($sql);
        if (!$row) {
            http_response_code(500);
            echo json_encode(array("detail" => "Internal error"));
            return;
        }

        $sign = get_hash256(get_hash256($config['SECRET_KEY'].$row[0]['id']));
        $site = $config['HOST_SITE'];
        $path = "/api/account/result";
        $url  = "http://{$site}{$path}?type=validation&id={$row[0]['id']}&sign={$sign} ";
        $content =  "<p>Please click the link to verify your Account</p><a href='{$url}' rel='bookmark'>{$url}</a>Thank you！<br />If the link can't be opend，please copy the link to your browser。{$url}";
        send_mail($user['email'], "Verify your email address", "Verify your email address", $content); 

        http_response_code(201);
        echo json_encode(array("detail" => "An email has been send to your email address，please go to your email to verify it。"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error!"));
    }

}
function ListUsers($param) {   
    $sql = "";
    $where = [];
    $option = "";
    
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    if (array_key_exists('username', $_GET) && $_GET['username'] !== "") {
        array_push($where, "username = '{$_GET['username']}'");
    }
    if (array_key_exists('is_active', $_GET) && $_GET['is_active'] !== "") {
        $is_active = 1;
        if ($_GET['is_active'] == "false")
            $is_active = 0;
        array_push($where, "is_active = ".$is_active);
    }
    
    if (array_key_exists('is_superuser', $_GET) && $_GET['is_superuser'] !== "") {
        $is_superuser = 1;
        if ($_GET['is_superuser'] == "false")
            $is_superuser = 0;
        array_push($where, "is_superuser = ".$is_superuser);
    }    
    
    if (array_key_exists('page', $_GET)      && $_GET['page']      !== "" && 
        array_key_exists('page_size', $_GET) && $_GET['page_size'] !== ""){
        $page = (int)$_GET['page'];
        $page_size = (int)$_GET['page_size'];
        $offset = ($page - 1) * $page_size;
        $limit = $page_size;
        $option = " limit ".$limit." offset ".$offset;
    }
    
    $sql = "select * from blog_user "; 
    $cnt=count($where);
    if ($cnt > 0) {
        $sql = $sql."where ";
        for($i=0;$i < $cnt;$i++)
        {
            $sql = $sql.$where[$i];
            if ($i < $cnt - 1) {
                $sql = $sql." and ";    
            }
        }
    }
    
    if ($option != "") {
        $sql = $sql.$option;
    }

    $ret = [];
    try {
        $result = db_query($sql);
        $count = count($result);

        $ret['count'] = $count;
        $ret['results'] = [];
        if ($count > 0) {
            foreach($result as $row) {
                $user = [
                    'id'           => $row['id'],
                    'username'     => $row['username'],
                    'last_login'   => $row['last_login'],
                    'email'        => $row['email'],
                    'avatar'       => $row['avatar'],
                    'nickname'     => $row['nickname'],
                    'is_active'    => $row['is_active']==0?false:true,
                    'is_superuser' => $row['is_superuser']==0?false:true,
                    'created_at'   => $row['created_at']
                ];
                array_push($ret['results'], $user);
            }
        }
        
        http_response_code(200);
        echo json_encode($ret);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}
function GetUser($param) {
    $id = (int)$param;
    $user = $_COOKIE['user'];
    if (!$user || (!$user->is_superuser && $user->id != $id)) {
        http_response_code(400);
        echo json_encode(array("detail" =>"Your are not administrator"));
        return;   
    }
    try {
        $sql = "select * from blog_user where id = $id";
        $row = db_query($sql);
        if (count($row) > 0) {            
            $user = [
                'id'           => $row[0]['id'],
                'username'     => $row[0]['username'],
                'last_login'   => $row[0]['last_login'],
                'email'        => $row[0]['email'],
                'avatar'       => $row[0]['avatar'],
                'nickname'     => $row[0]['nickname'],
                'is_active'    => $row[0]['is_active']==0?false:true,
                'is_superuser' => $row[0]['is_superuser']==0?false:true,
                'created_at'   => $row[0]['created_at']
            ];     
            
            http_response_code(200);
            echo json_encode($user);
        } else {
            http_response_code(500);
            echo json_encode(array("detail" => "Internal error !"));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}
function JwtLogin($param) {
    global $config;
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    try {
        $sql = "select * from blog_user where username = '{$data['username']}'";
        $row = db_query($sql);
        if (count($row) > 0) {
            if ($row[0]['is_active'] == 0) {
                http_response_code(400);
                echo json_encode(array("detail" => "Account is not activated"));
                return; 
            }
            
            $passwordOK = decrypt($data['password'], $row[0]['password']);
            if (!$passwordOK) {
                http_response_code(400);
                echo json_encode(array("detail" => "Account or password is wrong !"));
                return;   
            }  
         
            $token = Token::create_token([
                'username'     =>   $row[0]['username'],
                'id'           =>   $row[0]['id'],
                'is_superuser' =>   $row[0]['is_superuser']
            ]);
                       
            $expire=time()+60*60*24*$config['JWT_EXPIRE_DAYS'];
            setcookie($config['JWT_AUTH_COOKIE'], $token, $expire);

            $user = [
                'id'           => $row[0]['id'],
                'username'     => $row[0]['username'],
                'last_login'   => $row[0]['last_login'],
                'email'        => $row[0]['email'],
                'avatar'       => $row[0]['avatar'],
                'nickname'     => $row[0]['nickname'],
                'is_active'    => $row[0]['is_active'],
                'is_superuser' => $row[0]['is_superuser'],
                'created_at'   => $row[0]['created_at']
            ];

            $body = [
                'expire_days' => $config['JWT_EXPIRE_DAYS'],
                'token'       => $token, 
                'user'        => $user
            ];        
            
            $sql = "update blog_user set last_login = Now() where id = {$row[0]['id']}";
            if (!db_query($sql)) {
                http_response_code(500);
                echo json_encode(array("detail" => "Internal error !"));
                return;
            }             
            
            http_response_code(200);
            echo json_encode($body);
        } else {
            http_response_code(400);
            echo json_encode(array("detail" => "Account or password is wrong !"));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}
function UpdateUser($param) {
    $id = (int)$param;
    $user = $_COOKIE['user'];
    if (!$user || (!$user->is_superuser && $user->id != $id)) {
        http_response_code(400);
        echo json_encode(array("detail" =>"Can't modify any other's account !"));
        return;   
    }
    
    $jsonData = file_get_contents('php://input');
    $userInfo = json_decode($jsonData, true);

    if (array_key_exists('is_active', $userInfo) && $userInfo['is_active'] !== "") {
        $is_active = 1;
        if ($userInfo['is_active'] == false)
            $is_active = 0;
        $sql = "update blog_user set is_active = $is_active where id = $id";        
    } else {
        $sql = "update blog_user set nickname = '{$userInfo['nickname']}', email = '{$userInfo['email']}', description = '{$userInfo['desc']}', avatar = '{$userInfo['avatar']}', modified_at = Now() where id = $id";
    }
    
    try {
        if (!db_query($sql)) {
            http_response_code(500);
            echo json_encode(array("detail" => "Internal error !"));
            return;
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(array("detail"=> "Internal error !"));
    }    
}
function UpdatePassword($param) {
    $user = $_COOKIE['user'];
    if (!$user) {
        http_response_code(400);
        echo json_encode(array("detail" => "Please login in"));
        return;
    }

    $jsonData = file_get_contents('php://input');
    $userInfo = json_decode($jsonData, true);
    
    try {
        $sql = "select * from blog_user where id = $user->id";
        $row = db_query($sql);
        if (count($row) > 0) {
            $passwordOK = decrypt($userInfo['password'], $row[0]['password']);
            if ($passwordOK != true) {
                http_response_code(400);
                echo json_encode(array("detail" => "Password is wrong"));
                return;
            } 
            
            $userInfo['new_password'] = encrypt($userInfo['new_password']);
            $sql = "update blog_user set password = '{$userInfo['new_password']}' where id = $user->id";
            if (!db_query($sql)) {
                http_response_code(500);
                echo json_encode(array("detail" => "Internal error !"));
                return;
            }
        } else {
            http_response_code(500);
            echo json_encode(array("detail" => "Account does not exist !"));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}
function ForgetPassword($param) {
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    try {
        $sql = "select * from blog_user where username = '{$data['username']}'";
        $row = db_query($sql);
        if (count($row) > 0) {
            if ($row[0]['is_active'] == 0) {
                http_response_code(400);
                echo json_encode(array("detail" => "Account is not actived！"));
                return; 
            }
            
            $password = get_random_password();
            send_mail($row[0]['email'], "New password in your blog", 
                     "Please verify your password", 
                     "Hi: your new password: \n{$password}");
            $password = encrypt($password);
            $sql = "update blog_user set password = '{$password}' where id = {$row[0]['id']}";
            if (!db_query($sql)) {
                http_response_code(500);
                echo json_encode(array("detail" => "Internal error!"));
                return;
            }             
            
            http_response_code(200);
            echo json_encode(array("detail" => "New password has been send to your email."));
        } else {
            http_response_code(400);
            echo json_encode(array("detail" => "Account or password is wrong!"));
        }
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode(array("detail"=> "Account does not exists!"));
    }
}
function GetConstant($param) {

}
function UploadImage($param) {
    $path = get_upload_filepath($param, $_FILES["file"]["name"]);
    move_uploaded_file($_FILES["file"]["tmp_name"], $path["full_file_path"]);
    http_response_code(200);
    echo json_encode(array("url" => $path['file_path']));
}
function AccountResult($param) {
    global $config;
    $type = "";
    $sign = "";
    $id = 0;
    if (array_key_exists('type', $_GET) && $_GET['type'] !== "") {
        $type = $_GET['type'];
    }
    if (array_key_exists('id', $_GET) && $_GET['id'] !== "") {
        $id = (int)$_GET['id'];
    }
    if (array_key_exists('sign', $_GET) && $_GET['sign'] !== "") {
        $sign = $_GET['sign'];
    }

    try {
        $sql = "select * from blog_user where id = $id";    
        $row = db_query($sql);
        if (count($row) > 0) {
            if ($row[0]['is_active'] == 1) {
                http_response_code(200);
                echo json_encode(array("detail"=> "Verify success, please login in !"));
                return;
            }       
        }
        
        if ($type == "validation") {
            $c_sign = get_hash256(get_hash256($config['SECRET_KEY'].$id));
            if ($sign != $c_sign) {
                http_response_code(400);
                echo json_encode(array("detail"=> "Verify failed !"));
                return ;
            }
            
            $sql = "update blog_user set is_active = 1";
            db_query($sql);
                        
            http_response_code(200);
            echo json_encode(array("detail"=> "Verify success,congratulations,please login in !"));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }    
}

$userHandlers = [
    "POST /api/user/"         => "CreateUser",
    "GET /api/user/"          => "ListUsers",
    "POST /api/jwt_login"     => "JwtLogin",

    "PUT /api/pwd"            => "ForgetPassword",
    "POST /api/pwd"           => "UpdatePassword",

    "GET /api/user/:id"       => "GetUser",
    "PATCH /api/user/:id"     => "UpdateUser",

    "GET /api/constant"       => "GetConstant",
    "POST /api/upload/"       => "UploadImage",
    "GET /api/account/result" => "AccountResult",
];

?>