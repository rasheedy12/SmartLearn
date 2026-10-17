CREATE TABLE IF NOT EXISTS `study_buddy_connections` (
  `connection_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `user_low_id` bigint(20) UNSIGNED NOT NULL,
  `user_high_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`connection_id`),
  UNIQUE KEY `uq_study_buddy_pair` (`user_low_id`,`user_high_id`),
  KEY `idx_study_buddy_recipient_status` (`recipient_id`,`status`),
  KEY `idx_study_buddy_requester_status` (`requester_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `study_buddy_groups` (
  `group_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `course_id` int(10) UNSIGNED DEFAULT NULL,
  `creator_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`group_id`),
  KEY `idx_study_buddy_groups_course` (`course_id`),
  KEY `idx_study_buddy_groups_creator` (`creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `study_buddy_group_members` (
  `group_member_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('member','admin') NOT NULL DEFAULT 'member',
  PRIMARY KEY (`group_member_id`),
  UNIQUE KEY `uq_study_buddy_group_member` (`group_id`,`user_id`),
  KEY `idx_study_buddy_member_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
