<?php
require_once "config.php";
require_once 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$esClient = ClientBuilder::create()->setHosts($config['ELASTICSEARCH_HOST'])->build();

$setmap = [
    "settings" => [
        "index" => [
            "number_of_shards" => 1,
            "number_of_replicas" => 0
        ]
    ],
    "mappings" => [
        "properties" => [
            "id" => [
                "type" => "integer"
            ],
            "title" => [
                "search_analyzer" => "ik_smart",
                "analyzer" => "ik_smart",
                "type" => "text"
            ],
            "excerpt" => [
                "search_analyzer" => "ik_smart",
                "analyzer" => "ik_smart",
                "type" => "text"
            ],
            "keyword" => [
                "search_analyzer" => "ik_smart",
                "analyzer" => "ik_smart",
                "type" => "text"
            ],
            "markdown" => [
                "search_analyzer" => "ik_smart",
                "analyzer" => "ik_smart",
                "type" => "text"
            ],
            "catalog_info" => [
                "properties" => [
                    "name" => [
                        "search_analyzer" => "ik_smart",
                        "analyzer" => "ik_smart",
                        "type" => "text"
                    ],
                    "id" => [
                        "type" => "integer"
                    ]
                ]
            ],
            "status" => [
                "search_analyzer" => "ik_smart",
                "analyzer" => "ik_smart",
                "type" => "text"
            ],
            "views" => [
                "type" => "integer"
            ],
            "comments" => [
                "type" => "integer"
            ],
            "likes" => [
                "type" => "integer"
            ],
            "tags_info" => [
                "properties" => [
                    "name" => [
                        "search_analyzer" => "ik_smart",
                        "analyzer" => "ik_smart",
                        "type" => "text"
                    ],
                    "id" => [
                        "type" => "integer"
                    ]
                ]
            ]
        ]
    ]
];

function ESCreateIndex() {
    global $esClient, $config, $setmap;
    try {        
        if ($esClient->indices()->exists(["index" => $config['ELASTICSEARCH_INDEX']])){
            $esClient->indices()->delete(["index" => $config['ELASTICSEARCH_INDEX']]);
        }
        
        $ret = $esClient->indices()->create([
            "index" => $config['ELASTICSEARCH_INDEX'],
            "body" => $setmap]);
    } catch (Exception $ex) {
        echo "ex1:".$ex->getMessage();
        return;
    }
    
    $page      = 1;
    $page_size = 10;
    
    $article_total = db_query("select count(*) as total from blog_article where status = 'Published' order by id asc");
    $total = $article_total[0]["total"]; 
    try {    
        while ($total > 0) { 
            $offset = ($page - 1) * $page_size;
            $articles = db_query("select * from blog_article where status = 'Published' order by id asc limit {$page_size} offset {$offset}");
            $page += 1;
            $total -= count($articles);
            
            foreach($articles as $article) {
                $catalog_info = db_query("select * from blog_catalog where id ={$article['catalog_id']}");
                $catalog_infos = ["id" => $article["catalog_id"],"name" => $catalog_info[0]["name"]];
                $tags_info = db_query("select * from blog_tag a, article_tag b where a.id = b.tag_id and b.article_id={$article['id']}");
                
                $tags = [];
                foreach($tags_info as $tag) {
                    array_push($tags, array("id"          => $tag["id"],
                                            "name"        => $tag["name"])
                              );
                }
                $body = array(
                    "id"           => $article["id"],
                    "title"        => $article["title"],
                    "excerpt"      => $article["excerpt"],
                    "keyword"      => $article["keyword"],
                    "markdown"     => $article["markdown"],
                    "status"       => $article["status"],
                    "tags_info"    => $tags,
                    "catalog_info" => $catalog_infos,
                    "views"        => $article["views"],
                    "comments"     => $article["comments"],
                    "likes"        => $article["likes"] 
                ); 
                
                $ret = $esClient->index([
                    "index" => $config["ELASTICSEARCH_INDEX"], 
                    "id" => $article["id"],
                    "body" => $body]);
            }
        }
    } catch (Exception $ex) {
        echo "ex2:".$ex->getMessage();
    }
}


function ESUpdateIndex($article) {
    global $esClient,$config;
    if ($config['ELASTICSEARCH_ON']==false || $article['status'] != "Published") {
        return;
    }
    
    try {       
        $catalog_info = db_query("select * from blog_catalog where id ={$article['catalog_id']}");
        $catalog_infos = ["id" => $article["catalog_id"], "name" => $catalog_info[0]["name"]];
        $tags_info = db_query("select * from blog_tag a, article_tag b where a.id = b.tag_id and b.article_id={$article['id']}");
        $tags = [];
        foreach($tags_info as $tag) {
            array_push($tags, array("id"          => $tag["id"],
                                    "name"        => $tag["name"])
                      );
        }
        $body = array(
            "id"           => $article["id"],
            "title"        => $article["title"],
            "excerpt"      => $article["excerpt"],
            "keyword"      => $article["keyword"],
            "markdown"     => $article["markdown"],
            "status"       => $article["status"],
            "tags_info"    => $tags,
            "catalog_info" => $catalog_infos,
            "views"        => $article["views"],
            "comments"     => $article["comments"],
            "likes"        => $article["likes"]
        );
                
        $ret = $esClient->index([
            "index"   => $config["ELASTICSEARCH_INDEX"], 
            "refresh" => true,
            "id"      => $article["id"],
            "body"    => $body]);
    } catch (Exception $ex) {
        echo "ex3:".$ex->getMessage();
    }
}

function ESSearchIndex($page, $page_size, $search_text) {
    global $esClient, $config;
    if ($config['ELASTICSEARCH_ON']==false) {
        return ["count" => 0, "results" => []];
    }
    
    try {
        $ret = $esClient->search([
            "index" => $config['ELASTICSEARCH_INDEX'],
            "body"  => [
                "query" => [ 
                    "bool" => ["should" => [
                                   ["match" => ["title"              => $search_text]], 
                                   ["match" => ["excerpt"            => $search_text]],
                                   ["match" => ["keyword"            => $search_text]], 
                                   ["match" => ["markdown"           => $search_text]],
                                   ["match" => ["tags_info.name"     => $search_text]],
                                   ["match" => ["catalog_info.name"  => $search_text]]
                               ],
                               "must" => [
                                    "match" => ["status" => "Published"]
                               ]
                     ]
                ]
            ],
            "from" => ($page - 1) * $page_size,
            "size" => $page_size
        ]);
    } catch (Exception $ex) {
        return ["count" => 0, "results" => []];
    }
    $articleList = [];
    if ($ret != null) {
        if ($ret["hits"]) {
            foreach($ret["hits"]["hits"] as $article) {
                if ($article["_score"] > 1) {
                    array_push($articleList, ["object" => $article["_source"]]);  
                }
            }
            
            return ["count" => count($articleList), "results" => $articleList];
        }
    }
    return ["count" => 0, "results" => []];
}
?>