-- =========================================================================
-- The Village Community Club — database installation script
-- MySQL 5.7+/MariaDB 10.3+, InnoDB, utf8mb4
--
-- This file creates every table needed by the website and admin system,
-- plus a small amount of demonstration content so the site is not empty
-- on first install. It deliberately does NOT create an administrator
-- account — run install.php in a browser after importing this file, and
-- it will walk you through creating the first admin account securely.
--
-- Import via cPanel phpMyAdmin ("Import" tab) or on the command line:
--   mysql -u your_db_user -p your_db_name < database/install.sql
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- admin_users
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('administrator', 'editor') NOT NULL DEFAULT 'editor',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_username (username),
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- login_attempts — brute-force protection
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_identifier (identifier),
    KEY idx_ip (ip_address),
    KEY idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- password_resets
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_id (admin_id),
    UNIQUE KEY uq_token_hash (token_hash),
    CONSTRAINT fk_reset_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- audit_log — records significant admin actions
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_id (admin_id),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- settings — site-wide key/value configuration editable from Admin
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- pages — editable rich-text content blocks (About, Membership, etc.)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    content LONGTEXT NULL,
    meta_title VARCHAR(190) NULL,
    meta_description VARCHAR(300) NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_page_key (page_key),
    CONSTRAINT fk_page_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- news_articles
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS news_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    summary VARCHAR(400) NULL,
    content LONGTEXT NULL,
    featured_image VARCHAR(255) NULL,
    author VARCHAR(120) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    meta_title VARCHAR(190) NULL,
    meta_description VARCHAR(300) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_news_slug (slug),
    KEY idx_news_status (status, published_at),
    KEY idx_news_featured (is_featured),
    CONSTRAINT fk_news_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- events
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description LONGTEXT NULL,
    featured_image VARCHAR(255) NULL,
    event_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    venue VARCHAR(190) NULL,
    address VARCHAR(255) NULL,
    booking_info VARCHAR(400) NULL,
    booking_link VARCHAR(255) NULL,
    is_free TINYINT(1) NOT NULL DEFAULT 1,
    ticket_price DECIMAL(8,2) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    recurrence ENUM('none', 'weekly', 'monthly') NOT NULL DEFAULT 'none',
    recurrence_end_date DATE NULL,
    meta_title VARCHAR(190) NULL,
    meta_description VARCHAR(300) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_event_slug (slug),
    KEY idx_event_date (event_date),
    KEY idx_event_status (status),
    KEY idx_event_featured (is_featured),
    CONSTRAINT fk_event_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- newsletters
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    issue_number VARCHAR(30) NULL,
    issue_month TINYINT UNSIGNED NULL,
    issue_year SMALLINT UNSIGNED NOT NULL,
    description VARCHAR(400) NULL,
    pdf_path VARCHAR(255) NOT NULL,
    cover_image VARCHAR(255) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_newsletter_year (issue_year),
    KEY idx_newsletter_published (is_published),
    CONSTRAINT fk_newsletter_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- gallery_albums
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallery_albums (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description VARCHAR(400) NULL,
    cover_media_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_album_slug (slug),
    CONSTRAINT fk_album_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- media_items — images and videos, always belonging to an album
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    album_id INT UNSIGNED NOT NULL,
    type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    file_path VARCHAR(255) NULL,
    thumbnail_path VARCHAR(255) NULL,
    video_embed_url VARCHAR(255) NULL,
    title VARCHAR(190) NULL,
    caption VARCHAR(400) NULL,
    alt_text VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_media_album (album_id, sort_order),
    CONSTRAINT fk_media_album FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gallery_albums
    ADD CONSTRAINT fk_album_cover_media FOREIGN KEY (cover_media_id) REFERENCES media_items(id) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- committee_members
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS committee_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    role_title VARCHAR(150) NOT NULL,
    photo VARCHAR(255) NULL,
    bio TEXT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- contact_enquiries
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_enquiries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    subject VARCHAR(190) NULL,
    message TEXT NOT NULL,
    consent TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('new', 'resolved') NOT NULL DEFAULT 'new',
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_enquiry_status (status),
    KEY idx_enquiry_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================
-- Default settings
-- =========================================================================
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'The Village Community Club'),
('site_village', 'Little Meadow Village'),
('site_tagline', 'A warm welcome for everyone in Little Meadow Village'),
('logo_path', ''),
('about_summary', '[PLACEHOLDER] The Village Community Club has been at the heart of Little Meadow Village for generations, bringing residents together through friendship, activities and shared community spirit. Replace this text from Admin > Pages > Home.'),
('footer_summary', '[PLACEHOLDER] The heart of Little Meadow Village — everyone is welcome.'),
('opening_hours', 'Clubhouse: Tuesday & Thursday, 7pm–10pm\nSaturday mornings: 10am–12pm\n[PLACEHOLDER — update from Admin > Settings]'),
('contact_phone', '01234 567890'),
('contact_email', 'hello@example.com'),
('address_line1', 'The Village Hall'),
('address_line2', 'High Street'),
('address_town', 'Little Meadow Village'),
('address_postcode', 'AB12 3CD'),
('address_country', 'GB'),
('map_embed_src', ''),
('social_facebook', ''),
('social_instagram', ''),
('social_x', ''),
('social_youtube', ''),
('home_hero_heading', 'Welcome to The Village Community Club'),
('home_hero_subheading', '[PLACEHOLDER] The friendly heart of Little Meadow Village'),
('home_welcome_message', '[PLACEHOLDER] Whether you have lived here for fifty years or fifty days, there is always a warm welcome waiting for you at the Club. Replace this from Admin > Pages > Home.'),
('membership_cta_text', '[PLACEHOLDER] Join today and become part of something special — membership starts from just £10 a year.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =========================================================================
-- Editable page content (demo/placeholder — replace from Admin > Pages)
-- =========================================================================
INSERT INTO pages (page_key, title, content, meta_title, meta_description) VALUES
('about', 'About the Club', '<p>[PLACEHOLDER CONTENT] The Village Community Club was founded to bring residents of Little Meadow Village together. Replace this paragraph from the admin area at Admin &gt; Pages &gt; About.</p><p>We host regular social evenings, seasonal celebrations, and activities for members of all ages.</p>', 'About Us', '[PLACEHOLDER] Learn about the history and mission of The Village Community Club.'),
('membership', 'Membership', '<p>[PLACEHOLDER CONTENT] Membership is open to all residents of Little Meadow Village and the surrounding area. Replace this text from Admin &gt; Pages &gt; Membership.</p><h3>Membership Rates</h3><ul><li>Adult: £10 per year</li><li>Family: £18 per year</li><li>Concession: £5 per year</li></ul>', 'Membership', '[PLACEHOLDER] Find out how to join The Village Community Club.'),
('facilities', 'Club Facilities & Activities', '<p>[PLACEHOLDER CONTENT] Our clubhouse offers a wide range of facilities and activities for members. Replace this text from Admin &gt; Pages &gt; Facilities.</p><ul><li>Main hall available for hire</li><li>Weekly craft group</li><li>Monthly quiz night</li><li>Table tennis and darts</li></ul>', 'Facilities & Activities', '[PLACEHOLDER] Discover the facilities and activities on offer at the Club.'),
('committee_intro', 'Committee & Club Officials', '<p>[PLACEHOLDER CONTENT] Our committee is made up of local volunteers who give their time to keep the Club running. Replace this text from Admin &gt; Pages &gt; Committee.</p>', 'Committee', '[PLACEHOLDER] Meet the volunteers who run The Village Community Club.'),
('privacy', 'Privacy Policy', '<p><strong>[PLACEHOLDER — this draft policy must be reviewed by the Club and, if in doubt, a qualified advisor before the site goes live. It is a starting point only, not legal advice.]</strong></p><h3>What information we collect</h3><p>[PLACEHOLDER] We collect information you provide through our contact form (name, email, phone number and message), and, if you apply for membership, the details on your membership form.</p><h3>How we use your information</h3><p>[PLACEHOLDER] We use your information only to respond to your enquiry or to administer your membership. We do not sell or share your data with third parties for marketing purposes.</p><h3>How long we keep your information</h3><p>[PLACEHOLDER] Contact form messages are kept for as long as needed to deal with your enquiry. Membership records are kept for the duration of membership plus a reasonable retention period.</p><h3>Your rights</h3><p>[PLACEHOLDER] You have the right to ask what information we hold about you, to ask us to correct it, and to ask us to delete it. Contact the Club using the details on our Contact page.</p>', 'Privacy Policy', '[PLACEHOLDER] How The Village Community Club collects, uses and protects your personal information.'),
('cookies', 'Cookie Policy', '<p><strong>[PLACEHOLDER — this draft policy must be reviewed by the Club before the site goes live.]</strong></p><h3>Essential cookies</h3><p>[PLACEHOLDER] We use one essential cookie to remember your cookie preference. This cookie does not track you and cannot be disabled without disabling cookies in your browser entirely.</p><h3>Optional cookies</h3><p>[PLACEHOLDER] If you choose to accept optional cookies, embedded content such as YouTube videos and Google Maps may set their own cookies. These are only loaded after you give consent, or when you click to load that specific item.</p><h3>Managing cookies</h3><p>[PLACEHOLDER] You can change your cookie choice at any time by clearing your browser cookies for this site and reloading the page.</p>', 'Cookie Policy', '[PLACEHOLDER] How and why The Village Community Club uses cookies on this website.'),
('accessibility', 'Accessibility Statement', '<p>[PLACEHOLDER] The Village Community Club wants this website to be accessible to everyone, including people with visual, hearing, motor or cognitive impairments.</p><h3>What we have done</h3><ul><li>Used clear, large, readable text throughout</li><li>Provided text alternatives for images</li><li>Ensured the site can be navigated by keyboard</li><li>Provided a "skip to content" link on every page</li><li>Used sufficient colour contrast</li></ul><h3>Feedback</h3><p>[PLACEHOLDER] If you find any part of this website difficult to use, please contact us using the details on our Contact page and we will do our best to help.</p>', 'Accessibility Statement', '[PLACEHOLDER] Our commitment to making this website accessible to all visitors.')
ON DUPLICATE KEY UPDATE page_key = page_key;

-- =========================================================================
-- Demonstration content — clearly marked as placeholder, safe to delete
-- =========================================================================
INSERT INTO news_articles (title, slug, summary, content, author, status, is_featured, published_at) VALUES
('[SAMPLE] Club Reopens After Summer Break', 'sample-club-reopens-after-summer-break', '[PLACEHOLDER] We are delighted to announce the Club is reopening its doors after the summer break, with a full programme of autumn activities.', '<p>[PLACEHOLDER ARTICLE] This is sample content — replace or delete it from Admin &gt; News. We are delighted to announce the Club is reopening its doors after the summer break, with a full programme of autumn activities planned for members of all ages.</p>', 'The Committee', 'published', 1, NOW()),
('[SAMPLE] New Craft Group Launching This Month', 'sample-new-craft-group-launching-this-month', '[PLACEHOLDER] A brand new craft group starts this month, meeting every Wednesday afternoon in the small hall.', '<p>[PLACEHOLDER ARTICLE] This is sample content — replace or delete it from Admin &gt; News.</p>', 'Jane Smith', 'published', 0, NOW() - INTERVAL 5 DAY),
('[SAMPLE] Thank You to Everyone Who Supported the Fete', 'sample-thank-you-village-fete', '[PLACEHOLDER] A huge thank you to everyone who came along and volunteered at this year''s village fete.', '<p>[PLACEHOLDER ARTICLE] This is sample content — replace or delete it from Admin &gt; News.</p>', 'The Committee', 'published', 0, NOW() - INTERVAL 12 DAY);

INSERT INTO events (title, slug, description, event_date, start_time, end_time, venue, address, booking_info, is_free, ticket_price, is_featured, status) VALUES
('[SAMPLE] Autumn Quiz Night', 'sample-autumn-quiz-night', '[PLACEHOLDER] Join us for a fun evening of trivia, raising funds for the Club. Teams of up to six — replace this from Admin &gt; Events.', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '19:00:00', '22:00:00', 'The Village Hall', 'High Street, Little Meadow Village', 'Tickets available on the door or in advance from the Club.', 0, 3.50, 1, 'published'),
('[SAMPLE] Family Craft Afternoon', 'sample-family-craft-afternoon', '[PLACEHOLDER] A relaxed drop-in craft session for all the family — replace this from Admin &gt; Events.', DATE_ADD(CURDATE(), INTERVAL 21 DAY), '14:00:00', '16:00:00', 'The Village Hall (Small Room)', 'High Street, Little Meadow Village', 'No booking required, all welcome.', 1, NULL, 0, 'published'),
('[SAMPLE] Annual General Meeting', 'sample-annual-general-meeting', '[PLACEHOLDER] All members are welcome to attend the Club''s Annual General Meeting — replace this from Admin &gt; Events.', DATE_ADD(CURDATE(), INTERVAL 35 DAY), '19:30:00', '21:00:00', 'The Village Hall', 'High Street, Little Meadow Village', 'Open to all paid-up members.', 1, NULL, 0, 'published');

INSERT INTO newsletters (title, issue_number, issue_month, issue_year, description, pdf_path, is_published) VALUES
('[SAMPLE] Village Club Newsletter', 'Issue 1', MONTH(CURDATE()), YEAR(CURDATE()), '[PLACEHOLDER] Sample newsletter entry — upload a real PDF and edit this from Admin > Newsletters.', 'newsletters/sample-placeholder.pdf', 1);

INSERT INTO gallery_albums (title, slug, description, sort_order) VALUES
('[SAMPLE] Summer Fete 2025', 'sample-summer-fete-2025', '[PLACEHOLDER] Sample album — replace with real photos from Admin > Gallery.', 1);

INSERT INTO committee_members (name, role_title, bio, is_published, sort_order) VALUES
('[PLACEHOLDER NAME]', 'Chairperson', '[PLACEHOLDER] Add a short biography for this committee member from Admin > Committee.', 1, 1),
('[PLACEHOLDER NAME]', 'Secretary', '[PLACEHOLDER] Add a short biography for this committee member from Admin > Committee.', 1, 2),
('[PLACEHOLDER NAME]', 'Treasurer', '[PLACEHOLDER] Add a short biography for this committee member from Admin > Committee.', 1, 3);
