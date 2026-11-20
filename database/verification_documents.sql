-- Verification documents uploaded by farmers and buyers during
-- registration (or later from their account page) for admin review.
CREATE TABLE IF NOT EXISTS `verification_documents` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) NOT NULL,
  `doc_type` ENUM('id','business_permit','other') NOT NULL DEFAULT 'other',
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vd_user` (`user_id`),
  CONSTRAINT `fk_vd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
