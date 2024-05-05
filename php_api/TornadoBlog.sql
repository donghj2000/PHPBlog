/*
Navicat MySQL Data Transfer

Source Server         : aa
Source Server Version : 80026
Source Host           : localhost:3306
Source Database       : TornadoBlog

Target Server Type    : MYSQL
Target Server Version : 80026
File Encoding         : 65001

*/
DROP DATABASE `TornadoBlog` IF EXIST;
CREATE DATABASE `TornadoBlog` DEFAULT CHARACTER SET utf8;
use TornadoBlog

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `article_tag`
-- ----------------------------
DROP TABLE IF EXISTS `article_tag`;
CREATE TABLE `article_tag` (
  `article_id` int NOT NULL,
  `tag_id` int NOT NULL,
  PRIMARY KEY (`article_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `article_tag_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `blog_article` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `article_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `blog_tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for `blog_article`
-- ----------------------------
DROP TABLE IF EXISTS `blog_article`;
CREATE TABLE `blog_article` (
  `id` int NOT NULL AUTO_INCREMENT,
  `creator` int DEFAULT NULL,
  `modifier` int DEFAULT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cover` text COLLATE utf8mb4_unicode_ci,
  `excerpt` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keyword` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `markdown` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `views` int NOT NULL DEFAULT '0',
  `comments` int NOT NULL DEFAULT '0',
  `likes` int NOT NULL DEFAULT '0',
  `words` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  `catalog_id` int NOT NULL,
  `author_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`),
  KEY `catalog_id` (`catalog_id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `blog_article_ibfk_1` FOREIGN KEY (`catalog_id`) REFERENCES `blog_catalog` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `blog_article_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `blog_user` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for `blog_catalog`
-- ----------------------------
DROP TABLE IF EXISTS `blog_catalog`;
CREATE TABLE `blog_catalog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `creator` int DEFAULT NULL,
  `modifier` int DEFAULT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` int DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `blog_catalog_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `blog_catalog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for `blog_comment`
-- ----------------------------
DROP TABLE IF EXISTS `blog_comment`;
CREATE TABLE `blog_comment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `creator` int DEFAULT NULL,
  `modifier` int DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  `article_id` int NOT NULL,
  `reply_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `reply_id` (`reply_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `blog_comment_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `blog_article` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `blog_comment_ibfk_2` FOREIGN KEY (`reply_id`) REFERENCES `blog_comment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `blog_comment_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `blog_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for `blog_like`
-- ----------------------------
DROP TABLE IF EXISTS `blog_like`;
CREATE TABLE `blog_like` (
  `id` int NOT NULL AUTO_INCREMENT,
  `creator` int DEFAULT NULL,
  `modifier` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  `article_id` int NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `blog_like_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `blog_article` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `blog_like_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `blog_user` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for `blog_message`
-- ----------------------------
DROP TABLE IF EXISTS `blog_message`;
CREATE TABLE `blog_message` (
  `id` int NOT NULL AUTO_INCREMENT,
  `creator` int DEFAULT NULL,
  `modifier` int DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for `blog_tag`
-- ----------------------------
DROP TABLE IF EXISTS `blog_tag`;
CREATE TABLE `blog_tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `creator` int DEFAULT NULL,
  `modifier` int DEFAULT NULL,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for `blog_user`
-- ----------------------------
DROP TABLE IF EXISTS `blog_user`;
CREATE TABLE `blog_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `password` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_superuser` tinyint(1) NOT NULL,
  `username` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `creator` int DEFAULT NULL,
  `modifier` int DEFAULT NULL,
  `avatar` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nickname` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
