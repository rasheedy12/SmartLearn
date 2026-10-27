CREATE TABLE IF NOT EXISTS `oauth_identities` (
  `identity_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `provider` enum('google','microsoft') NOT NULL,
  `issuer` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`identity_id`),
  UNIQUE KEY `uq_oauth_provider_subject` (`provider`,`issuer`,`subject`),
  UNIQUE KEY `uq_oauth_user_provider` (`user_id`,`provider`),
  CONSTRAINT `fk_oauth_identity_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
