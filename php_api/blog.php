<?php

require_once "config.php";
require_once "utils.php";
require_once "elasticsearch7.php";

function CreateArticle($param){
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    try {
        $title    = "";
        $cover    = "";
        $excerpt  = "";
        $keyword  = "";
        $markdown = "";
        $tags     = [];
        $catalog  = "";
        
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        if (array_key_exists('title', $data)) {
            $title = $data['title'];    
        }
        if (array_key_exists('cover', $data)) {
            $cover = $data['cover'];    
        }
        if (array_key_exists('excerpt', $data)) {
            $excerpt = $data['excerpt'];    
        }
        if (array_key_exists('keyword', $data)) {
            $keyword = $data['keyword'];    
        }
        if (array_key_exists('markdown', $data)) {
            $markdown = $data['markdown'];    
        }
        if (array_key_exists('tags', $data)) {
            $tags = $data['tags'];    
        }
        if (array_key_exists('catalog', $data)) {
            $catalog = $data['catalog'];    
        }
        
        $ret = db_query("select * from blog_article where title = '{$title}'");
        if (count($ret) > 0) {
             http_response_code(5000);
             echo json_encode(array("detail" => "Title exists！"));
             return ;
        }
        $sql = "insert into blog_article(title,cover,excerpt,keyword,markdown,catalog_id,author_id,creator,modifier, created_at, modified_at) ".
               "values('{$title}','{$cover}','{$excerpt}','{$keyword}', '{$markdown}',{$catalog},{$user->id},{$user->id},{$user->id},now(),now())";
        
        db_query($sql);
        
        if (count($tags) != 0) {
            $article = db_query("select * from blog_article where title= '{$title}'");
            $sql = "insert into article_tag values";

            foreach($tags as $i=> $tag_id) {
                if ($i!=0)
                    $sql = $sql.",";
                $sql = $sql."({$article[0]['id']}, {$tag_id})";    
            }

            db_query($sql);
        }
        
        ESUpdateIndex($article[0]);
        http_response_code(200);
        echo json_encode(array("detail" => "Save article success !"));  
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail" => "Internal error !"));
    } 
}

$ancestorIds = [];
function get_ancestors($parent_id) {
    global $ancestorIds;
    if ($parent_id != 0 && $parent_id != "") {
        array_unshift($ancestorIds,$parent_id);
        try {
            $ret = db_query("select parent_id from blog_catalog where id = {$parent_id}");
            if (count($ret) > 0) {
                $parent_id_tmp = $ret[0]["parent_id"];
                get_ancestors($parent_id_tmp);
            } else {
                return $ancestorIds;
            }
        } catch (Exception $ex) {
            return $ancestorIds;
        }
    } else {
        return $ancestorIds;
    }
}
        
$descendantsIds = [];
function get_descendants($catalog_id) {
    global $descendantsIds;
    array_push($descendantsIds, $catalog_id);
    try {
        $catalogs = db_query("select id from blog_catalog where parent_id = {$catalog_id}");
        if (count($catalogs) != 0) {
            foreach($catalogs as $cata) {
                get_descendants($cata["id"]);
            }
        } else {
            return $descendantsIds;
        }
    } catch (Exception $ex) {
        return $descendantsIds;
    }
}

function ListArticles($param) {
    try {
        $status    = null;
        $search    = null;
        $tag       = null;
        $catalog   = null;
        $page      = 1;
        $page_size = 10;
        if (array_key_exists('status', $_GET)) {
            $status = $_GET['status'];
        }
        if (array_key_exists('search', $_GET)) {
            $search = $_GET['search'];
        }
        if (array_key_exists('tag', $_GET)) {
            $tag = $_GET['tag'];
        }
        if (array_key_exists('catalog', $_GET)) {
            $catalog = $_GET['catalog'];
        }
        if (array_key_exists('page', $_GET)) {
            $page = $_GET['page'];
        }
        if (array_key_exists('page_size', $_GET)) {
            $page_size = $_GET['page_size'];
        }

        $articles = null;
        $sql = "select * from blog_article ";
        $sql_where = [];
        if ($status != null && $status != "") {
            array_push($sql_where, "status = '{$status}'");
        }
        if ($search != null && $search != "") {
            array_push($sql_where, "locate('{$search}',title)!=0");
        }
        
        if ($tag != null) {
            $article_ids = db_query("select * from article_tag where tag_id = {$tag}");
            if (count($article_tags) > 0) {
                $ids = "id in(";
                foreach($article_tags as $i => $art_tag) {
                    if ($i != 0) {
                        $ids = $ids.",";
                    }
                    $ids = $ids."{$art_tag['article_id']}";
                }
                array_push($sql_where, $ids.")");
            }
        }
        
        if ($catalog != null) {
            global $descendantsIds;
            $descendantsIds = [];
            get_descendants((int)$catalog);
            if (count($descendantsIds) > 0) {
                $ids = "catalog_id in(";
                foreach($descendantsIds as $i => $cata_id) {
                    if ($i != 0) {
                        $ids = $ids.",";
                    }
                    $ids = $ids."{$cata_id}";
                }
                array_push($sql_where, $ids.")");
            }
        }
        
        if (count($sql_where)!=0) {
            $sql = $sql."where ";
            foreach($sql_where as $i => $sql_wh) {
                if ($i != 0) {
                    $sql = $sql." and ";
                }
                $sql = $sql.$sql_wh;
            }
        }

        $offset = ($page - 1) * $page_size;
        $sql = $sql." order by id asc ";
        $sql = $sql."limit {$page_size} offset {$offset}";
        
        $articles = db_query($sql); 
        
        $ret = [];
        $ret["count"] = count($articles);
        $ret["results"] = [];
        foreach ($articles as $article) {
            global $ancestorIds;
            $ancestorIds = [];
            get_ancestors($article["catalog_id"]);
            $catalog_info = db_query("select * from blog_catalog where id ={$article['catalog_id']}");
            if (count($catalog_info) > 0) {
                $catalog_name = $catalog_info[0]["name"];  
            } else {
                $catalog_name = "";
            }
            $catalog_infos = array("id"=>$article["catalog_id"],"name"=> $catalog_name,"parents"=> $ancestorIds);
            
            $tag_info = db_query("select * from blog_tag a, article_tag b where a.id = b.tag_id and b.article_id={$article['id']}");
            $tags = [];
            foreach($tag_info as $tag) {
                array_push($tags, array("id"          => $tag["id"],
                                        "name"        => $tag["name"], 
                                        "created_at"  => $tag["created_at"],
                                        "modified_at" => $tag["modified_at"])
                          );
            }
                                     
            array_push($ret["results"], array(
                    "id"           => $article["id"],
                    "title"        => $article["title"],
                    "excerpt"      => $article["excerpt"],
                    "cover"        => $article["cover"],
                    "status"       => $article["status"],
                    "created_at"   => $article["created_at"],
                    "modified_at"  => $article["modified_at"],
                    "tags_info"    => $tags,
                    "catalog_info" => $catalog_infos,
                    "views"        => $article["views"],
                    "comments"     => $article["comments"],
                    "words"        => $article["words"],
                    "likes"        => $article["likes"])); 
        }
        
        http_response_code(200);
        echo json_encode($ret);
    } catch (Exception  $e) { 
        http_response_code(200);
        echo json_encode(array("detail" => "Internal error !"));
    }
}

function GetArticle($param) {
    try {
        $id = (int)$param;
        $article = db_query("select * from blog_article where id = {$id}"); 
        global $ancestorIds;
        $ancestorIds = [];
        get_ancestors($article[0]["catalog_id"]);
        $catalog_info = db_query("select * from blog_catalog where id = {$article[0]['catalog_id']}");
        $catalog_infos = array("id" => $article[0]["catalog_id"],"name" => $catalog_info[0]["name"],"parents" => $ancestorIds);
        $tag_infos = db_query("select * from blog_tag a, article_tag b where a.id = b.tag_id and b.article_id={$article[0]['id']}");
  
        $tags = [];
        foreach($tag_infos as $tag) {
            array_push($tags, array("id"          => $tag["id"],
                                    "name"        => $tag["name"], 
                                    "created_at"  => $tag["created_at"],
                                    "modified_at" => $tag["modified_at"])
                      );
        }

        $ret = array(
                "id"           => $article[0]["id"],
                "title"        => $article[0]["title"],
                "excerpt"      => $article[0]["excerpt"],
                "keyword"      => $article[0]["keyword"],
                "cover"        => $article[0]["cover"],
                "markdown"     => $article[0]["markdown"],
                "status"       => $article[0]["status"],
                "created_at"   => $article[0]["created_at"],
                "modified_at"  => $article[0]["modified_at"],
                "tags_info"    => $tags,
                "catalog_info" => $catalog_infos,
                "views"        => $article[0]["views"],
                "comments"     => $article[0]["comments"],
                "words"        => $article[0]["words"],
                "likes"        => $article[0]["likes"] 
        ); 
        db_query("update blog_article set views = views+1 where id={$article[0]['id']}");
        http_response_code(200);
        echo json_encode($ret);
    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(array("detail" => "Internal error !"));
    }
}

function PutArticle($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    try {
        $title    = "";
        $cover    = "";
        $excerpt  = "";
        $keyword  = "";
        $markdown = "";
        $tags     = [];
        $catalog  = 0;
        
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        $article_id = (int)$param;

        if (array_key_exists('title', $data)) {
            $title = $data['title'];
        }
        if (array_key_exists('cover', $data)) {
            $cover = $data['cover'];
        }
        if (array_key_exists('excerpt', $data)) {
            $excerpt = $data['excerpt'];
        }
        if (array_key_exists('keyword', $data)) {
            $keyword = $data['keyword'];
        }
        if (array_key_exists('markdown', $data)) {
            $markdown = $data['markdown'];
        }
        if (array_key_exists('tags', $data)) {
            $tags = $data['tags'];
        }
        if (array_key_exists('catalog', $data)) {
            $catalog = $data['catalog'];
        }
    
        $sql = "update blog_article set title='{$title}',cover='{$cover}',excerpt='{$excerpt}',keyword='{$keyword}',markdown='{$markdown}',catalog_id={$catalog} where id={$article_id}";
        db_query($sql);
        db_query("delete from article_tag where article_id={$article_id}");            
        if (count($tags) != 0) {
            $sql = "insert into article_tag values";
            foreach ($tags as $i => $tag_id) {
                if ($i != 0) {
                    $sql = $sql.",";    
                }
                $sql = $sql."({$article_id},{$tag_id})";
            }   
            db_query($sql);
        }
        $article = db_query("select * from blog_article where id = {$article_id}");
        ESUpdateIndex($article[0]);
    } catch (Exception $e) {
        http_response_code(200);
        echo $e->getMessage();
        echo json_encode(array("detail" => "Internal error !"));
    }
}

function PatchArticle($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    try {
        $status   = "";  
        $article_id = (int)$param;
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        $status = $data['status'];
        db_query("update blog_article set status='{$status}' where id={$article_id}");
        $article = db_query("select * from blog_article where id = {$article_id}");
        ESUpdateIndex($article[0]);
    } catch (Exception $e) {
        http_response_code(200);
        echo json_encode(array("detail" => "Internal error !"));
    }
}

function ListArchive($param) {
    $page      = 1;
    $page_size = 10;
    
    if (array_key_exists('page', $_GET)) {
        $page = $_GET['page'];
    }
    if (array_key_exists('page_size', $_GET)) {
        $page_size = $_GET['page_size'];
    }
    
    try {
        $article_total = db_query("select count(*) as total from blog_article where status='Published' order by id desc");
        $total = $article_total[0]["total"];
        $offset = ($page - 1) * $page_size;
        $articles = db_query("select * from blog_article where status='Published' order by id desc limit {$page_size} offset {$offset}");             
    } catch (Exception $ex) {
        http_response_code(500);
        echo json_encode(array("detail" => "Internal error !"));
        return;
    }
    $ret = [];
    if ($total > 0) {
        $ret = array(
            "count"    => $total,
            "next"     => null,
            "previous" => null,
            "results"  => []);

        $years = [];
        foreach ($articles as $article) {
            $year = $article["created_at"];
            $date = date_parse($article['created_at']);
            $year = $date['year'];
            
            if (array_key_exists($year, $years)) {
                $articles_year = $years[$year];
            } else {
                $articles_year = [];
                $years[$year] = $articles_year;
            }
            
            array_push($years[$year],array(
                                  "id"         => $article["id"],
                                  "title"      => $article["title"],
                                  "created_at" => $article["created_at"]));
        }
        
        foreach ($years as $k => $v) {
            array_push($ret["results"],array(
                "year" => $k,
                "list" => $v));
        }       
    }
    http_response_code(200);
    echo json_encode($ret);
}

function CreateComment($param) {
    $user = $_COOKIE['user'];
    if (!$user) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "Please login in!"));
        return;   
    }
    
    try {
        $article_id = null;
        $user_id    = null;
        $reply_id   = null;
        $content    = "";

        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        if (array_key_exists('article', $data)) {
            $article_id = $data['article'];    
        }
        if (array_key_exists('user', $data)) {
            $user_id = $data['user'];    
        }
        if (array_key_exists('reply', $data)) {
            $reply_id = $data['reply'];    
        }
        if (array_key_exists('content', $data)) {
            $content = $data['content'];    
        }

        $sql = "insert into blog_comment(article_id, user_id, content,creator,modifier,created_at,modified_at";
        if ($reply_id != null) {
            $sql = $sql.",reply_id";
        }
        $sql = $sql.") values({$article_id},{$user_id},'{$content}',{$user_id},{$user_id},now(),now()";
        if ($reply_id != null) {
            $sql = $sql.",{$reply_id}";
        }
        $sql = $sql.")";

        db_query($sql);
        db_query("update blog_article set comments = comments+1 where id={$article_id}");
        http_response_code(200);
        echo json_encode(array("detail" => "Comment success !")); 
    } catch (Exception $ex) { 
        echo $ex->getMessage();
        http_response_code(500);
        echo json_encode(array("detail" => "Internal error !")); 
    }
}
 
function getReplies($replies) {
    $comment_replies = [];
    if (count($replies)==0) {
        return [];
    }
    foreach($replies as $reply) {
        $user_rep = db_query("select * from blog_user where id={$reply['user_id']}");
        $reply_replies = db_query("select * from blog_comment where reply_id={$reply['id']}");
        if (count($user_rep) != 0) {
            array_push($comment_replies,array(
                "id"               => $reply["id"],
                "content"          => $reply["content"],
                "user_info"        => array(
                                       "id"     => $user_rep[0]["id"],
                                       "name"   => $user_rep[0]["nickname"]? $user_rep[0]["nickname"] : $user_rep[0]["username"],
                                       "avatar" => $user_rep[0]["avatar"],   
                                       "role"   => $user_rep[0]["is_superuser"]==1 ? "Admin" : "" 
                                      ),
                "created_at"       => $reply["created_at"],
                "comment_replies"  => getReplies($reply_replies))
            );
        }
    }
    return $comment_replies;
}

function ListComments($param) {
    $user_id    = null;
    $search     = "";
    $article_id = null;
    $page       = 1;
    $page_size  = 10;
    
    if (array_key_exists('user', $_GET)) {
        $user = $_GET['user'];
    }
    if (array_key_exists('search', $_GET)) {
        $search = $_GET['search'];
    }
    if (array_key_exists('article', $_GET)) {
        $article_id = $_GET['article'];
    }
    if (array_key_exists('page', $_GET)) {
        $page = $_GET['page'];
    }
    if (array_key_exists('page_size', $_GET)) {
        $page_size = $_GET['page_size'];
    }
    
    try {
        $sql = "select * from blog_comment ";
        $sql_where = [];    
        if ($user_id != null and $user_id != "") {
            array_push($sql_where, "user_id = {$user_id} ");
        }
        if ($search != null and $search != "") {
            array_push($sql_where, "locate('{$search}',content)!=0 ");
        }
        if ($article_id != null and $article_id != "") {
            array_push($sql_where, "article_id = {$article_id} ");
        }
        if (count($sql_where)!=0) {
            $sql .= "where ";
            foreach($sql_where as $i => $sql_wh) {
                if ($i != 0) {
                    $sql .= " and ";
                }
                $sql .=$sql_wh;
            }
        }
        $sql .= " order by id asc ";
        $offset = ($page - 1) * $page_size;
        if ($page != null and $page != "") {
            $sql .= " limit {$page_size} offset {$offset}";
        }
             
        $comments = db_query($sql); 
    } catch (Exception $ex) {
        http_response_code(500);
        echo $ex->getMessage();
        echo json_encode(array("detail" => "Internal error !"));
        return;
    }
    
    $ret = [];
    $ret["count"] = count($comments);
    $ret["results"] = [];
    foreach($comments as $comment) {
        $user = db_query("select * from blog_user where id = {$comment['user_id']}");
        $user_info = [
            "id"     => $user[0]["id"],
            "name"   => $user[0]["nickname"]? $user[0]["nickname"] : $user[0]["username"],
            "avatar" => $user[0]["avatar"],
            "role"   => $user[0]["is_superuser"]==1 ?"Admin" : ""
        ];
        $article = db_query("select id,title from blog_article where id={$comment['article_id']}");
        $article_info = [
            "id"    => $article[0]["id"], 
            "title" => $article[0]["title"] 
        ];

        $replies = db_query("select * from blog_comment where reply_id={$comment['id']}");
        $comment_replies = getReplies($replies);
        array_push($ret["results"], array(
            "id"              => $comment["id"],
            "user"            => $comment["user_id"],
            "user_info"       => $user_info,
            "article"         => $comment["article_id"], 
            "article_info"    => $article_info,
            "created_at"      => $comment["created_at"], 
            "reply"           => $comment["reply_id"], 
            "content"         => $comment["content"],
            "comment_replies" => $comment_replies ));
    }
    http_response_code(200);
    echo json_encode($ret);
}

function CreateLike($param) {
    $user = $_COOKIE['user'];
    if (!$user) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "Please login in!"));
        return;   
    }
    
    try {
        $article_id = null;
        $user_id    = null;
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        if (array_key_exists('article', $data)) {
            $article_id = $data['article'];    
        }
        if (array_key_exists('user', $data)) {
            $user_id = $data['user'];    
        }
        
        $sql = "insert into blog_like(article_id, user_id, creator,modifier) values({$article_id},{$user_id},{$user_id},{$user_id})";
        db_query($sql);
        db_query("update blog_article set likes = likes+1 where id={$article_id}");
        
        http_response_code(200);
        json_encode(array("detail" => "Like success !")); 
    } catch (Exception $ex) { 
        http_response_code(500);
        json_encode(array("detail" => "Internal error !"));
    }
}

function CreateMessage($param) {
    try {    
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        $sql = "insert into blog_message(email, content, phone, name, created_at, modified_at) values('{$data['email']}', '{$data['content']}', '{$data['phone']}', '{$data['name']}', now(), now())";
        db_query($sql);
        http_response_code(200);
        echo json_encode(array("detail"=> "Create message success !"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));  
    }
}

function ListMessages($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }
        
    $search = "";
    $page = 1;
    $page_size = 10;
    if (array_key_exists('search', $_GET)) {
        $search = $_GET['search'];
    }
    if (array_key_exists('page', $_GET)) {
        $page = (int)$_GET['page'];
    }
    if (array_key_exists('page_size', $_GET)) {
        $page_size = (int)$_GET['page_size'];
    }
    
    $sql = "select * from blog_message ";       
    if ($search != "") {
        $sql = $sql."where locate('{$search}',name)!=0 or locate('{$search}',email)!=0 or locate('{$search}',phone)!=0 or locate('{$search}',content)!=0 ";
    }
    $offset = ($page-1)*$page_size;
    $sql = $sql."limit {$page_size} offset {$offset} ";
    try {
        $messages = db_query($sql);
        $ret = [];
        $ret['count'] = count($messages);
        $ret['results'] = $messages;
        
        http_response_code(200);
        echo json_encode($ret);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}

function CreateTag($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    try {
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        $sql = "insert into blog_tag(creator, modifier, name, created_at, modified_at) ".
               "values($user->id, $user->id, '{$data['name']}', now(), now())";
        db_query($sql);
        
        http_response_code(200);
        echo json_encode(array("detail"=> "Create catalog success !"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
        return ;
    }
}

function ListTags($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    $name = "";
    $page = 1;
    $page_size = 10;
    if (array_key_exists('name', $_GET)) {
        $name = $_GET['name'];
    }
    if (array_key_exists('page', $_GET)) {
        $page = $_GET['page'];
    }
    if (array_key_exists('page_size', $_GET)) {
        $page_size = $_GET['page_size'];
    }
            
    try {
        $sql = "select * from blog_tag";
        $offset = ($page - 1) * $page_size;
        $sql = "select * from blog_tag ";        
        if ($name != null and $name != "") {
            $sql .= "where locate('{$name}',name)!=0 ";
        }
        $sql .= "limit {$page_size} offset {$offset}";
        
        $rows = db_query($sql);
        $ret = [];
        $ret['count'] = count($rows);            
        $ret['results'] = $rows;
        http_response_code(200);
        echo json_encode($ret);        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}

function PutTag($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    $id = (int)$param;
    try {
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        $sql = "update blog_tag set name = '{$data['name']}', modified_at = now() where id = {$id}";
        db_query($sql);
        http_response_code(200);
        echo json_encode(array("detail"=> "Modify tag success !"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}

function DeleteTag($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    $id = (int)$param;
    try {
        $sql = "delete from blog_tag where id = {$id}";
        db_query($sql);
        http_response_code(200);
        echo json_encode(array("detail"=> "Delete tag success !"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}


function CreateCatalog($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    try {
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        $sql = "insert into blog_catalog(creator, modifier, name, created_at, modified_at, parent_id) ".
               "values($user->id, $user->id, '{$data['name']}', now(), now(), {$data['parent']})";
        db_query($sql);
        
        http_response_code(200);
        echo json_encode(array("detail"=> "Create catalog success !"));
    } catch (Exception $e) {
        http_response_code(500);
        echo $e->getMessage();
        echo json_encode(array("detail"=> "Internal error !"));
        return ;
    }
}
function buildTree(array $elements, $parentId) {
    $branch = array();
 
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = buildTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

function ListCatalogs($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }
    
    try {
        $catas = db_query("select * from blog_catalog");
        if (count($catas) == 0) {
            http_response_code(500);
            echo json_encode(array("detail"=> "Get catalog error !"));
            return;
        }
        $ret = buildTree($catas, null);
        
        http_response_code(200);
        echo json_encode($ret); 
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}

function PatchCatalog($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }

    $id = (int)$param;
    try {    
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        $sql = "update blog_catalog set name = '{$data['name']}', modifier = {$user->id}, modified_at = now() where id = {$id}";
        
        db_query($sql);
        http_response_code(200);
        echo json_encode(array("detail"=> "Modify catalog success !"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
        return ;
    }
}

function DeleteCatalog($param) {
    $user = $_COOKIE['user'];
    if (!$user || !$user->is_superuser) {        
        http_response_code(400);
        echo json_encode(array("detail"=> "You are not administrator!"));
        return;   
    }
    
    $id = (int)$param;
    try {
        $sql = "delete from blog_catalog where id = $id";
        db_query($sql);
        http_response_code(200);
        echo json_encode(array("detail"=> "Delete catalog success !"));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
        return ;
    }
}

function GetNumbers($param) {
    try {
        $sql = "select SUM(views), SUM(likes), SUM(comments), COUNT(*) FROM blog_article";
        $results = db_query($sql);
        
        $sql = "select COUNT(*) from blog_message";
        $results2 = db_query($sql);
        $ret = array(
            "views"     => (int)$results[0]['SUM(views)'],
            "likes"     => (int)$results[0]['SUM(likes)'],
            "comments"  => (int)$results[0]['SUM(comments)'],
            "messages"  => (int)$results2[0]['COUNT(*)']
        );
        
        http_response_code(200);
        echo json_encode($ret);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("detail"=> "Internal error !"));
    }
}

function GetTops($param) {
    try {
        $sql = "select * from blog_article order by views desc limit 10";
        $results = db_query($sql);
        
        if (count($results) > 0) {
            $ret = [];
            $ret['count'] = count($results);
            $ret['results'] = [];
            foreach($results as $article) {
                array_push($ret['results'], 
                              array(
                              'id'    => $article['id'],
                              'title' => $article['title'],
                              'views' => $article['views'],
                              'likes' => $article['likes'])
                          );
            };
            http_response_code(200);
            echo json_encode($ret);
        }
    } catch (Exception $e) {
        http_response_code(200);
        echo json_encode(array("detail" => "内部错误！"));
    }
}

function GetElasticSearch($param) {
    $text = "";
    $page = 1;
    $page_size = 10;
    if (array_key_exists('text', $_GET)) {
        $text = $_GET['text'];
    }
    if (array_key_exists('page', $_GET)) {
        $page = $_GET['page'];
    }
    if (array_key_exists('page_size', $_GET)) {
        $page_size = $_GET['page_size'];
    }
        
    $ret = ESSearchIndex($page, $page_size, $text);
    
    http_response_code(200);
    echo json_encode($ret);
}


$blogHandlers = [
    "POST /api/article/"       => "CreateArticle",
    "GET /api/article/"        => "ListArticles",
    "GET /api/article/:id"     => "GetArticle",
    "PUT /api/article/:id"     => "PutArticle",
    "PATCH /api/article/:id"   => "PatchArticle",
    
    "GET /api/archive/"        => "ListArchive",

    "POST /api/comment/"       => "CreateComment",
    "GET /api/comment/"        => "ListComments",

    "POST /api/like/"          => "CreateLike",

    "POST /api/message/"       => "CreateMessage",
    "GET /api/message/"        => "ListMessages",

    "POST /api/tag/"           => "CreateTag",
    "GET /api/tag/"            => "ListTags",
    "PUT /api/tag/:id"         => "PutTag",
    "DELETE /api/tag/:id"      => "DeleteTag",

    "POST /api/catalog/"       => "CreateCatalog",
    "GET /api/catalog/"        => "ListCatalogs",
    "PATCH /api/catalog/:id"   => "PatchCatalog",
    "DELETE /api/catalog/:id"  => "DeleteCatalog",

    "GET /api/number/"         => "GetNumbers",
    "GET /api/top/"            => "GetTops",
    "GET /api/es/"             => "GetElasticSearch"
]; 
?>