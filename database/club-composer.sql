ALTER TABLE club_posts ADD COLUMN IF NOT EXISTS post_kind ENUM('post','article') NOT NULL DEFAULT 'article';
