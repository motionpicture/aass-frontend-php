--
-- テーブルの構造 `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `id` int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `application`
--

DROP TABLE IF EXISTS `application`;
CREATE TABLE `application` (
  `id` int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `media_id` int(11) UNSIGNED NOT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `event`
--

DROP TABLE IF EXISTS `event`;
CREATE TABLE `event` (
  `id` int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `held_from` datetime NOT NULL,
  `held_to` datetime NOT NULL,
  `place` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `media`
--

DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `id` int(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `event_id` int(11) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `uploaded_by` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `extension` varchar(255) DEFAULT NULL,
  `size` bigint UNSIGNED DEFAULT NULL,
  `playtime_string` varchar(255) DEFAULT NULL,
  `playtime_seconds` varchar(255) DEFAULT NULL,
  `url_thumbnail` varchar(255) DEFAULT NULL,
  `url_mp4` varchar(255) DEFAULT NULL,
  `url_streaming` varchar(255) DEFAULT NULL,
  `asset_id` varchar(255) DEFAULT NULL,
  `job_id` varchar(255) DEFAULT NULL,
  `job_state` tinyint(1) DEFAULT NULL,
  `job_start_at` datetime DEFAULT NULL,
  `job_end_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `admin`
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `application`
  ADD KEY `media_id` (`media_id`);

ALTER TABLE `event`
  ADD UNIQUE KEY `user_id` (`user_id`);

ALTER TABLE `media`
  ADD KEY `event_id` (`event_id`);

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `application`
--
ALTER TABLE `application`
  ADD CONSTRAINT `application_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- テーブルの制約 `media`
--
ALTER TABLE `media`
  ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
