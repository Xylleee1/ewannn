-- Create user_notifications table for system notifications
CREATE TABLE `user_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related record (request_id, transaction_id, etc.)',
  `related_type` varchar(50) DEFAULT NULL COMMENT 'Type of related record (borrow_request, penalty, etc.)',
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_user_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
