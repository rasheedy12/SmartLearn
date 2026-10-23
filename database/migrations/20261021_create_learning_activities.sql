CREATE TABLE IF NOT EXISTS `learning_quiz_questions` (
  `question_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lesson_id` int(10) UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('single_choice','multiple_choice','true_false') NOT NULL DEFAULT 'single_choice',
  `order_index` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `idx_learning_quiz_questions_lesson_order` (`lesson_id`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_quiz_options` (
  `option_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` int(10) UNSIGNED NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`option_id`),
  KEY `idx_learning_quiz_options_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_quiz_attempts` (
  `attempt_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(10) UNSIGNED NOT NULL,
  `lesson_id` int(10) UNSIGNED NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `idx_learning_quiz_attempts_enrollment_lesson` (`enrollment_id`,`lesson_id`,`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_quiz_attempt_answers` (
  `answer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `option_id` int(10) UNSIGNED NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`answer_id`),
  KEY `idx_learning_quiz_answers_attempt` (`attempt_id`),
  KEY `idx_learning_quiz_answers_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_assignment_submissions` (
  `submission_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(10) UNSIGNED NOT NULL,
  `lesson_id` int(10) UNSIGNED NOT NULL,
  `submission_content` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `grade` decimal(5,2) DEFAULT NULL,
  `instructor_feedback` text DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `uq_learning_assignment_submission` (`enrollment_id`,`lesson_id`),
  KEY `idx_learning_assignment_review` (`lesson_id`,`graded_at`,`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
