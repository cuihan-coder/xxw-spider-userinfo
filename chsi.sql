-- phpMyAdmin SQL Dump
-- version 4.4.10
-- http://www.phpmyadmin.net
--
-- Host: localhost:8889
-- Generation Time: Feb 12, 2018 at 03:25 PM
-- Server version: 5.5.42
-- PHP Version: 5.6.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `snake`
--

-- --------------------------------------------------------

--
-- Table structure for table `snake_chsi_api_log`
--

CREATE TABLE `snake_chsi_api_log` (
  `id` int(11) NOT NULL COMMENT '学信网接口定义表',
  `taskId` varchar(32) NOT NULL DEFAULT '0' COMMENT '任务ID',
  `createTime` varchar(10) NOT NULL COMMENT '任务创建时间',
  `finishTime` varchar(10) NOT NULL COMMENT '任务完成时间',
  `queryUser` varchar(15) NOT NULL COMMENT 'api接口调用者账号',
  `isOk` int(1) NOT NULL DEFAULT '0' COMMENT '0 未成功 1 成功爬取',
  `userName` varchar(25) NOT NULL COMMENT '查询的学信网账号',
  `passWord` varchar(20) NOT NULL COMMENT '查询的学信网密码',
  `paramVal` text NOT NULL COMMENT '任务存储的cookie路径,lt,action'
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `snake_chsi_api_log`
--

INSERT INTO `snake_chsi_api_log` (`id`, `taskId`, `createTime`, `finishTime`, `queryUser`, `isOk`, `userName`, `passWord`, `paramVal`) VALUES
(1, 'dc6fc5e84ed67b9361b8c893246b911d', '1518413060', '', '', 0, '', '', ''),
(2, '37f0a8524819737c968ef0f942fc46ca', '1518413074', '', '', 0, '', '', ''),
(3, '32b5b34e0b60336b49f68114b71c957b', '1518413251', '', '', 0, 'zhoouyan200@126.com', 'zhou871217', 'a:3:{s:6:"action";s:0:"";s:2:"lt";s:0:"";s:10:"cookiePath";s:56:"/Applications/MAMP/htdocs/snake/cookie/5a812ef7e3b39.txt";}'),
(4, 'e4bd7a239b34b3c8151e3ee6b4ac8c6f', '1518418243', '', '', 0, 'zhoouyan200@126.com', 'zhou871217', 'a:3:{s:6:"action";s:0:"";s:2:"lt";s:0:"";s:10:"cookiePath";s:56:"/Applications/MAMP/htdocs/snake/cookie/5a81398f744a3.txt";}'),
(5, '1a0874fe9c17e220187198b115a16995', '1518420053', '', '', 0, 'zhoouyan200@126.com', 'zhou871217', 'a:3:{s:6:"action";s:59:"/passport/login;jsessionid=2D988C076B5611439F0FACEC6A68A0E5";s:2:"lt";s:76:"_cDBB92716-941B-2FF0-BC8B-B21B429536B0_kC2432130-650A-0961-F4A6-83024824CA41";s:10:"cookiePath";s:56:"/Applications/MAMP/htdocs/snake/cookie/5a814063610dc.txt";}');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `snake_chsi_api_log`
--
ALTER TABLE `snake_chsi_api_log`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `snake_chsi_api_log`
--
ALTER TABLE `snake_chsi_api_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '学信网接口定义表',AUTO_INCREMENT=6;