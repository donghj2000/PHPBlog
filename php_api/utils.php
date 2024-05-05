
<?php
require_once "config.php";
require_once 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$conn = mysqli_connect($config["MYSQL_HOST"], $config["MYSQL_USERNAME"],$config["MYSQL_PASSWORD"],$config["MYSQL_DB"],$config["MYSQL_PORT"]);
mysqli_options($conn,MYSQLI_OPT_INT_AND_FLOAT_NATIVE,true);  

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

function db_query($sql) {
    global $conn;
    $ret = [];
    $result = mysqli_query($conn, $sql);
    if ($result === true || $result === false)
        return $result;
    
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            array_push($ret, $row);
        }
    }
    return $ret;
}

try {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Timeout = 1000;
    $mail->Host = $config['EMAIL_SERVER_HOST'];
    $mail->Port = $config['EMAIL_PORT'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['EMAIL_HOST_USER'];
    $mail->Password = $config['EMAIL_HOST_PASSWORD'];
    $mail->SMTPSecure = "ssl";
    $mail->setFrom($config['EMAIL_HOST_USER'], 'From Name');
    $mail->isHTML(true);
} catch (Exception $e) {
    echo "Create Email client. Mailer Error: {$mail->ErrorInfo}";   
}
class Token
{
    static public function create_token($data)
    {
        global $config;
    
        $payload = [
            'iss' => 'pyg',                
            'exp' => time() + $config['JWT_EXPIRE_DAYS']*24*3600,     
            'aud' => 'admin',              
            'nbf' => time(),               
            'iat' => time(),               
            'data' => $data,           
        ];

        $token = JWT::encode($payload, $config['SECRET_KEY'], 'HS256');
        return $token;
    }

    static public function verify_token($token)
    {
        global $config;
        try {
            $Result = JWT::decode($token, new Key($config['SECRET_KEY'], 'HS256'));
            return $Result->data;
        } catch (SignatureInvalidException $e) { 
            echo $e->getMessage();
        } catch (BeforeValidException $e) {  
            echo $e->getMessage();
        } catch (ExpiredException $e) {  
            echo $e->getMessage();
        } catch (Exception $e) {  
            echo $e->getMessage();
        }

        return null;
    }
}

function send_mail($toMail, $subject, $text, $html) {
    global $mail;
    try {
        $mail->addAddress($toMail, 'Recipient Name');
        $mail->Subject = $subject;
        $mail->AltBody = $text;
        $mail->Body = $html;
        $ret = $mail->send();
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function get_hash256($val) {
    return hash('sha256', $val);
}
function get_random_password() {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $randomString = '';
    for ($i = 0; $i < 8; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }
    return $randomString;
}

function get_upload_filepath($base_path, $upload_name){
    global $config;
       
    $date_path = date("Y/m/d"); 
    $upload_path = "/".$config['UPLOAD_URL']."/".$base_path."/".$date_path;
    $dir = getcwd();
    $dir = dirname($dir);
    $full_path = $dir.$upload_path;
    make_sure_path_exist($full_path);
    
    return  array("full_file_path" => $full_path."/".$upload_name, 
                  "file_path"      => $upload_path."/".$upload_name);
}

function make_sure_path_exist($full_path){
    if (file_exists($full_path)==true)
        return;
    mkdir($full_path, 0777, true);
}

function encrypt($password){
    $hash = password_hash($password, PASSWORD_DEFAULT);
    return $hash;
}
function decrypt($password, $pass_hash){
    if (password_verify($password, $pass_hash)) {
        return true;
    } else {
        return false;
    }
}

?>