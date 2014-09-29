CREATE TABLE `characters_getandcheck` (
  `getandcheck_device_key` text,
  `wow_account_id` int(10) unsigned NOT NULL DEFAULT '0',
  `joindate` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(15) DEFAULT NULL,
  `os` enum('ios','android','win') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

