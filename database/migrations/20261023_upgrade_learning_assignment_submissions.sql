ALTER TABLE `lessons`
  ADD COLUMN `due_at` datetime DEFAULT NULL AFTER `text_content`;

ALTER TABLE `learning_assignment_submissions`
  MODIFY COLUMN `submitted_at` timestamp NULL DEFAULT NULL,
  ADD COLUMN `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted' AFTER `submission_content`,
  ADD COLUMN `draft_content` text DEFAULT NULL AFTER `status`,
  ADD COLUMN `draft_version` int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `draft_content`;

CREATE TABLE `learning_assignment_attachments` (
  `attachment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` int(10) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` char(64) NOT NULL,
  `mime_type` varchar(127) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `status` enum('draft','submitted','superseded') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  UNIQUE KEY `uq_learning_assignment_attachment_stored_name` (`stored_name`),
  KEY `idx_learning_assignment_attachments_submission_status` (`submission_id`,`status`),
  CONSTRAINT `fk_learning_assignment_attachments_submission`
    FOREIGN KEY (`submission_id`)
    REFERENCES `learning_assignment_submissions` (`submission_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
