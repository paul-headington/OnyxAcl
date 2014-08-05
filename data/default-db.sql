/*
 Navicat MySQL Data Transfer

 Source Server         : Business Sorter Local
 Source Server Type    : MySQL
 Source Server Version : 50537
 Source Host           : localhost
 Source Database       : bs-dev

 Target Server Type    : MySQL
 Target Server Version : 50537
 File Encoding         : utf-8

 Date: 08/05/2014 16:10:36 PM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `onyx_acl_resource`
-- ----------------------------
DROP TABLE IF EXISTS `onyx_acl_resource`;
CREATE TABLE `onyx_acl_resource` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `roleid` int(10) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `lastupdated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `postdate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `roleid` (`roleid`),
  CONSTRAINT `roleid` FOREIGN KEY (`roleid`) REFERENCES `onyx_acl_role` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Records of `onyx_acl_resource`
-- ----------------------------
BEGIN;
INSERT INTO `onyx_acl_resource` VALUES ('1', '1', 'dashboard', null, '2014-05-08 17:27:03'), ('2', '1', 'sorter/index', null, '2014-05-08 17:27:03'), ('3', '2', 'aclrole', null, '2014-05-08 17:27:03'), ('4', '2', 'createform', null, '2014-05-08 17:27:03'), ('5', '2', 'register', null, '2014-05-08 17:29:56'), ('6', '1', 'home', null, '2014-05-08 17:35:00'), ('7', '1', 'register', null, '2014-05-08 17:35:00'), ('10', '1', 'modal', null, '2014-05-08 17:35:00'), ('11', '1', 'user', null, '2014-05-08 17:35:00'), ('12', '1', 'system', null, '2014-05-08 17:35:00'), ('13', '1', 'acl', null, '2014-05-08 17:35:00'), ('14', '1', 'aclrole', null, '2014-05-08 17:35:00'), ('15', '1', 'aclresource', null, '2014-05-08 17:35:00'), ('16', '1', 'createmodel', null, '2014-05-08 17:35:00'), ('17', '1', 'createform', null, '2014-05-08 17:35:00'), ('19', '2', 'aclresource', null, '2014-05-08 17:35:54'), ('20', '2', 'modal', null, '2014-05-08 17:36:44'), ('22', '1', 'about-us', null, '2014-05-12 15:28:09'), ('24', '1', 'onyx-rest', null, '2014-05-20 15:57:33'), ('25', '3', 'onyx-rest', null, '2014-05-20 15:57:33'), ('26', '2', 'onyx-rest', null, '2014-05-20 15:57:33'), ('27', '1', 'rest-api', null, '2014-05-21 17:11:29'), ('28', '1', 'rest-add', null, '2014-05-22 17:25:59'), ('30', '1', 'rest-delete', null, '2014-05-22 20:54:46'), ('33', '1', 'sorter', null, '2014-05-25 23:37:43'), ('34', '1', 'home/default', null, '2014-05-28 16:31:33');
COMMIT;

-- ----------------------------
--  Table structure for `onyx_acl_role`
-- ----------------------------
DROP TABLE IF EXISTS `onyx_acl_role`;
CREATE TABLE `onyx_acl_role` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `inheritance_order` int(10) DEFAULT NULL,
  `updatedon` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `postdate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Records of `onyx_acl_role`
-- ----------------------------
BEGIN;
INSERT INTO `onyx_acl_role` VALUES ('1', 'guest', '0', null, '2014-05-07 17:06:35'), ('2', 'admin', '1', '2014-05-12 22:16:01', '2014-05-08 13:52:50'), ('3', 'member', '0', null, '2014-05-12 22:03:41');
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
