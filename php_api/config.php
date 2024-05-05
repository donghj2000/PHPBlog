<?php
$config = [ 
  "BASE_DIR" => 'getcwd',
  "UPLOAD_URL" => "upload",
  "HOST_SITE" => "192.168.1.20",
  "SECRET_KEY" => "adslfjalsdjflasjkdf",
  "JWT_EXPIRE_DAYS" => 7,
  "JWT_AUTH_COOKIE" => "JwtCookie",

  "EMAIL_SERVER_TYPE" => "qq", //类型qq邮箱
  "EMAIL_SERVER_HOST" => "smtp.qq.com",
  "EMAIL_PORT" => 465,
  "EMAIL_HOST_USER" => "81037981@qq.com", // 发送方的邮箱
  "EMAIL_HOST_PASSWORD" => "avlwwqbacoowbhag",//"hlqzyssfrifkbjea", // smtp 的授权码
  
  "MYSQL_DB" => "tornadoblog",
  "MYSQL_USERNAME" => "root",
  "MYSQL_PASSWORD" => "123456",
  "MYSQL_HOST" => "127.0.0.1",
  "MYSQL_PORT" => 3306,

  "ELASTICSEARCH_ON" => true,
  "ELASTICSEARCH_INDEX" => "tornadoblog",
  "ELASTICSEARCH_HOST" => ["127.0.0.1:9200"]
];
?>