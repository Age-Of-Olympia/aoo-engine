-- Create notifications table to track player notification preferences
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    email_bonus TINYINT(1) NOT NULL DEFAULT 0,
    notify_season TINYINT(1) NOT NULL DEFAULT 0,
    notify_quest TINYINT(1) NOT NULL DEFAULT 0,
    notify_turn TINYINT(1) NOT NULL DEFAULT 0,
    notify_missive TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for faster lookups
CREATE INDEX idx_notifications_player ON notifications(player_id);
