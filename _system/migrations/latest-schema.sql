DROP DATABASE IF EXISTS wpdev;
CREATE DATABASE wpdev;

USE wpdev;

DROP TABLE IF EXISTS `mail`;
CREATE TABLE `mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `from` varchar(200) NOT NULL,
  `to` varchar(200) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `headers` text NOT NULL,
  `body` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
