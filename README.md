# PHPBlog

php 博客后端，包括注册/登录，个人信息修改，文章列表，全文搜索，文章后台管理界面等,支持markdown编辑器。

### 安装依赖包
```
cd php_api
composer install
```

### 创建数据库和管理员账号
```
1.开启mysql服务。
2.登入mysql，执行TornadoBlog.sql建立数据库。
3.创建管理员账号。
php create_db.php。
```

### 创建elasticsearch 索引
```
1.开启elasticsearch服务。
2.创建索引。
php create_index.php。
```

### 部署
```
将工程拷贝到nginx执行文件的同个目录，
1.开启cgi。
php-cgi -b 9000
2.开启 nginx。
nginx -c conf/nginx.conf
```


