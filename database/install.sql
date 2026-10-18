-- =========================================================================
-- Nettlebed Village Club — database installation script
-- MySQL 5.7+/MariaDB 10.3+, InnoDB, utf8mb4
--
-- This file creates every table needed by the website and admin system,
-- plus club identity and editable page content
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
('site_name', 'Nettlebed Village Club'),
('site_village', 'Nettlebed'),
('site_tagline', 'Events, hall hire and membership at Nettlebed Village Club.'),
('logo_path', ''),
('hero_image_path', ''),
('hero_image_alt', 'The courtyard and club building on Nettlebed High Street'),
('about_summary', 'A community club on Nettlebed High Street with a bar, snooker room and two halls for hire.'),
('footer_summary', 'Your village club on the High Street. Get together, enjoy an evening out or plan an occasion of your own.'),
('opening_hours', 'Please check with the club for the latest opening hours before visiting.'),
('contact_phone', '01491 641617'),
('contact_email', 'thenettlebedclub@gmail.com'),
('address_line1', '32 High Street'),
('address_line2', ''),
('address_town', 'Nettlebed'),
('address_postcode', 'RG9 5DD'),
('address_country', 'GB'),
('map_embed_src', ''),
('social_facebook', 'https://www.facebook.com/NettlebedVillageClub'),
('social_instagram', ''),
('social_x', ''),
('social_youtube', ''),
('home_hero_heading', 'At the heart of Nettlebed.'),
('home_hero_subheading', 'Good company, familiar faces and room to celebrate. Your village club, right here on the High Street.'),
('home_welcome_message', 'A community club with a bar, a snooker room and two halls for hire. Come to an event, enquire about membership or bring your own group together in a space close to home.'),
('membership_cta_text', 'Interested in joining? Talk to the club about current membership options, prices and visiting arrangements.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =========================================================================
-- Editable page content (demo/placeholder — replace from Admin > Pages)
-- =========================================================================
INSERT INTO pages (page_key, title, content, meta_title, meta_description) VALUES
('about', 'Your village club', '<p>Nettlebed Village Club brings people together on the High Street. With a bar, a snooker room and two halls for hire, it is a place for social evenings, activities and occasions.</p><h2>A part of village life</h2><p>The building opened in 1913 and has been part of Nettlebed life ever since.</p><h2>Come and get to know us</h2><p>Ask about an upcoming event, membership or visiting the bar. The club can help you find the information you need before you come.</p>', 'Our club', 'Get to know Nettlebed Village Club on the High Street.'),
('membership', 'Make yourself part of it.', '<p>Membership is a way to be part of club life in Nettlebed. Get in touch to ask about current membership options, annual fees and how to apply.</p><h2>Thinking of visiting?</h2><p>Contact the club about guest arrangements and opening hours before your visit.</p><h2>Already a member?</h2><p>The club can help with renewal enquiries and questions about your membership.</p>', 'Membership', 'Ask about membership and visiting Nettlebed Village Club.'),
('hall_hire', 'Hall hire', '<h2>Two halls. Plenty of possibilities.</h2><p>Bring people together for a celebration, a meeting or a regular group activity at Nettlebed Village Club.</p><h2>Find the right space</h2><p>Tell the club what you have in mind, how many people you expect and your preferred date. The club will confirm room suitability, facilities, availability and current hire charges.</p><h2>Make an enquiry</h2><p>Use the enquiry form or telephone the club to discuss your plans. Please confirm capacities and arrangements with the club before booking.</p>', 'Hall hire', 'Enquire about hiring a hall at Nettlebed Village Club.'),
('facilities', 'Make time for your club.', '<h2>Meet at the bar</h2><p>The club has a bar and a snooker room. Please ask about membership, guest arrangements and opening hours before visiting.</p><h2>Get together in the halls</h2><p>Two halls provide space for activities and private hire. Contact the club to ask about current groups, events or a regular booking.</p>', 'Facilities & activities', 'Explore the bar, snooker room and halls at Nettlebed Village Club.'),
('committee_intro', 'The people behind the club', '<p>For committee enquiries, please contact the club using the details on the contact page.</p>', 'Committee', 'Contact the committee at Nettlebed Village Club.'),
('privacy', 'Privacy Policy', '<p><strong>[PLACEHOLDER — this draft policy must be reviewed by the Club and, if in doubt, a qualified advisor before the site goes live. It is a starting point only, not legal advice.]</strong></p><h3>What information we collect</h3><p>[PLACEHOLDER] We collect information you provide through our contact form (name, email, phone number and message), and, if you apply for membership, the details on your membership form.</p><h3>How we use your information</h3><p>[PLACEHOLDER] We use your information only to respond to your enquiry or to administer your membership. We do not sell or share your data with third parties for marketing purposes.</p><h3>How long we keep your information</h3><p>[PLACEHOLDER] Contact form messages are kept for as long as needed to deal with your enquiry. Membership records are kept for the duration of membership plus a reasonable retention period.</p><h3>Your rights</h3><p>[PLACEHOLDER] You have the right to ask what information we hold about you, to ask us to correct it, and to ask us to delete it. Contact the Club using the details on our Contact page.</p>', 'Privacy Policy', '[PLACEHOLDER] How Nettlebed Village Club collects, uses and protects your personal information.'),
('cookies', 'Cookie Policy', '<p><strong>[PLACEHOLDER — this draft policy must be reviewed by the Club before the site goes live.]</strong></p><h3>Essential cookies</h3><p>[PLACEHOLDER] We use one essential cookie to remember your cookie preference. This cookie does not track you and cannot be disabled without disabling cookies in your browser entirely.</p><h3>Optional cookies</h3><p>[PLACEHOLDER] If you choose to accept optional cookies, embedded content such as YouTube videos and Google Maps may set their own cookies. These are only loaded after you give consent, or when you click to load that specific item.</p><h3>Managing cookies</h3><p>[PLACEHOLDER] You can change your cookie choice at any time by clearing your browser cookies for this site and reloading the page.</p>', 'Cookie Policy', '[PLACEHOLDER] How and why Nettlebed Village Club uses cookies on this website.'),
('accessibility', 'Accessibility Statement', '<p>[PLACEHOLDER] Nettlebed Village Club wants this website to be accessible to everyone, including people with visual, hearing, motor or cognitive impairments.</p><h3>What we have done</h3><ul><li>Used clear, large, readable text throughout</li><li>Provided text alternatives for images</li><li>Ensured the site can be navigated by keyboard</li><li>Provided a "skip to content" link on every page</li><li>Used sufficient colour contrast</li></ul><h3>Feedback</h3><p>[PLACEHOLDER] If you find any part of this website difficult to use, please contact us using the details on our Contact page and we will do our best to help.</p>', 'Accessibility Statement', '[PLACEHOLDER] Our commitment to making this website accessible to all visitors.')
ON DUPLICATE KEY UPDATE page_key = page_key;

-- =========================================================================
