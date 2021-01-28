-- phpMyAdmin SQL Dump
-- version 4.4.10
-- http://www.phpmyadmin.net
--
-- Host: localhost:8889
-- Generation Time: 2018-03-02 10:02:19
-- 服务器版本： 5.5.42
-- PHP Version: 5.6.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `snake`
--

-- --------------------------------------------------------

--
-- 表的结构 `snake_chsi_user`
--

CREATE TABLE `snake_chsi_user` (
  `id` int(5) NOT NULL COMMENT '学信网用户表',
  `username` varchar(11) NOT NULL,
  `apikey` varchar(32) NOT NULL,
  `can_use_num` int(11) NOT NULL DEFAULT '0' COMMENT '可调用接口次数',
  `use_num` int(11) NOT NULL DEFAULT '0' COMMENT '已使用次数',
  `create_time` varchar(10) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `snake_chsi_user`
--

INSERT INTO `snake_chsi_user` (`id`, `username`, `apikey`, `can_use_num`, `use_num`, `create_time`) VALUES
(1, '18974285585', '00d4a2c8eb6840f0e3c428a96d78884e', 10, 0, '1519953090');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `snake_chsi_user`
--
ALTER TABLE `snake_chsi_user`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `snake_chsi_user`
--
ALTER TABLE `snake_chsi_user`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT COMMENT '学信网用户表',AUTO_INCREMENT=2;