-- SkillSwap -- Peer-to-Peer Skill Exchange Platform
-- Import via phpMyAdmin or: mysql -u root -P 3306 < schema.sql
-- Creates database, schema, and demo seed data.
-- Session timestamps are expressed relative to NOW() (DATE_ADD/DATE_SUB) so
-- the upcoming/past session views, and the overlap/state-machine demo data,
-- always look live however long after import the demo actually happens --
-- same approach used elsewhere in this suite (e.g. Expense Tracker's
-- budget dates, Voice Memo App's seed timestamps).

CREATE DATABASE IF NOT EXISTS peer_skill_exchange
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE peer_skill_exchange;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS member_skills;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Schema
-- ============================================================

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('super_admin','member') NOT NULL DEFAULT 'member',
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    bio           VARCHAR(500) DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Master skills taxonomy, Super-Admin-managed. Members pick from this list
-- rather than free-typing a skill name (FEATURES.md module 2) -- avoids
-- "Python" / "python " / "Python3" duplicates. Deactivate-not-delete once a
-- skill has member_skills/session history, same pattern as this suite's
-- other taxonomies (e.g. Expense Tracker's categories).
CREATE TABLE skills (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    category      VARCHAR(60) NOT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Each row is one (member, skill, direction) fact: a member either TEACHes
-- a skill (with a self-rated proficiency) or wants to LEARN it (no
-- proficiency -- they don't have it yet). A member can hold both a teach
-- AND a learn row for the same skill (e.g. an intermediate teacher still
-- wants to sharpen it further with an expert) -- the unique key is on all
-- three columns, not just (user_id, skill_id).
CREATE TABLE member_skills (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL,
    skill_id          INT NOT NULL,
    type              ENUM('teach','learn') NOT NULL,
    proficiency_level ENUM('beginner','intermediate','advanced','expert') DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member_skill_type (user_id, skill_id, type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    INDEX idx_member_skills_skill (skill_id, type),
    INDEX idx_member_skills_user (user_id, type)
) ENGINE=InnoDB;

-- One-on-one session between two Members (requester/recipient) for one
-- skill. `status` is the small explicit state machine described in
-- FEATURES.md §3 -- transitions are enforced in the Session model, never
-- written directly from a controller.
CREATE TABLE sessions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    skill_id         INT NOT NULL,
    requester_id     INT NOT NULL,
    recipient_id     INT NOT NULL,
    scheduled_start  DATETIME NOT NULL,
    scheduled_end    DATETIME NOT NULL,
    status           ENUM('requested','confirmed','declined','cancelled','completed') NOT NULL DEFAULT 'requested',
    notes            VARCHAR(255) DEFAULT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (skill_id) REFERENCES skills(id),
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_requester (requester_id, status),
    INDEX idx_sessions_recipient (recipient_id, status),
    INDEX idx_sessions_status (status)
) ENGINE=InnoDB;

-- Private per-session chat thread. Every query against this table is
-- scoped by session_id AND a participant check at the model layer
-- (FEATURES.md §3 "Chat privacy is structural") -- the Admin's oversight
-- queries never select `body` at all.
CREATE TABLE messages (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    session_id    INT NOT NULL,
    sender_id     INT NOT NULL,
    body          VARCHAR(1000) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_session (session_id, created_at)
) ENGINE=InnoDB;

-- ============================================================
-- Seed data -- "SkillSwap" community
-- ============================================================

-- Passwords: Admin@123 (admin), Member@123 (all members) -- bcrypt hashes
-- via PHP's password_hash(), verified against password_verify().
INSERT INTO users (id, name, email, password, role, status, bio) VALUES
(1,  'Ananth Rao',          'admin@skillswap.test',   '$2y$10$18uLqH3IuyygSAoPhCo4nODZYAfx/IAhWirYXf7.87noI8BsWm1CC', 'super_admin', 'active', NULL),
(2,  'Rohan Verma',         'member1@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Backend developer by day, always up for trading code for conversation practice.'),
(3,  'Sneha Iyer',          'member2@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Spanish tutor and spreadsheet nerd. Learning to code on the side.'),
(4,  'Arjun Mehta',         'member3@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Java/SQL developer, terrified of public speaking and trying to fix that.'),
(5,  'Kavya Nair',          'member4@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Product designer. Happy to trade Figma tips for anything musical.'),
(6,  'Aditya Kulkarni',     'member5@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Weekend guitarist, weekday marketer-in-training.'),
(7,  'Meera Pillai',        'member6@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Certified yoga instructor who also happens to speak French.'),
(8,  'Vikram Chauhan',      'member7@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'C++ engineer trying to finally learn French before a trip to Paris.'),
(9,  'Ishita Bose',         'member8@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Graphic designer and part-time singer. Bad at databases, working on it.'),
(10, 'Karthik Subramanian', 'member9@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Toastmasters veteran, Excel wizard, aspiring pianist.'),
(11, 'Divya Reddy',         'member10@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Home baker and cook. Wants to learn how to actually market my bakery page.'),
(12, 'Nikhil Joshi',        'member11@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Full-stack-ish. Wants to pick up Figma and a bit of Japanese.'),
(13, 'Ananya Das',          'member12@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Nutrition coach who has been meaning to learn Japanese for years.'),
(14, 'Rahul Gupta',         'member13@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Yoga + home cooking. Trying to finally learn to code.'),
(15, 'Priyanka Menon',      'member14@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Freelance designer and growth marketer. Wants to finally learn guitar.'),
(16, 'Tanvi Shah',          'member15@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Self-taught Python dev and classically trained pianist.'),
(17, 'Aakash Nambiar',      'member16@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Database guy. Terrible at Excel somehow. Also would love to bake.'),
(18, 'Riya Kapoor',         'member17@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'active', 'Singer and French speaker, looking to break into tech.'),
(19, 'Manoj Pandey',        'member18@skillswap.test', '$2y$10$2UVfzCtrW1Im7KhQDJfn2uyZ1MAPpgp4vWbYlJ2LdSV5cpaUbrVaa', 'member', 'inactive', 'Account deactivated by the platform admin.');

-- Skill 22 (Adobe Flash Animation) is deactivated below on purpose -- it
-- already has member_skills history (a member taught it in the past), so
-- it's deactivated rather than deleted (deactivate-not-delete): still
-- shown correctly wherever it already appears, just hidden from the "add
-- a skill to your profile" picker going forward.
INSERT INTO skills (id, name, category, is_active) VALUES
(1,  'Python',                 'Programming', 1),
(2,  'JavaScript',              'Programming', 1),
(3,  'Java',                    'Programming', 1),
(4,  'SQL',                     'Programming', 1),
(5,  'C++',                     'Programming', 1),
(6,  'UI/UX Design',            'Design', 1),
(7,  'Graphic Design',          'Design', 1),
(8,  'Figma',                   'Design', 1),
(9,  'Spanish',                 'Language', 1),
(10, 'French',                  'Language', 1),
(11, 'Japanese',                'Language', 1),
(12, 'Guitar',                  'Music', 1),
(13, 'Piano',                   'Music', 1),
(14, 'Singing',                 'Music', 1),
(15, 'Public Speaking',         'Business', 1),
(16, 'Excel',                   'Business', 1),
(17, 'Digital Marketing',       'Business', 1),
(18, 'Yoga',                    'Wellness', 1),
(19, 'Nutrition Basics',        'Wellness', 1),
(20, 'Baking',                  'Cooking', 1),
(21, 'Indian Cooking',          'Cooking', 1),
(22, 'Adobe Flash Animation',   'Design', 0);

-- ------------------------------------------------------------
-- Member skill profiles (teach + learn rows per member).
--
-- Rohan (2) <-> Sneha (3) is the deliberately-traceable MUTUAL EXCHANGE
-- pair for the viva demo: Rohan teaches Python which Sneha wants, and
-- Sneha teaches Spanish which Rohan wants -- Member::matchesFor() should
-- show each of them on the other's Matches list with the mutual badge set.
-- Kavya's (5) "wants Java" is a deliberately NON-mutual match against both
-- Arjun (4) and Aakash (17), who teach Java but want neither of Kavya's
-- taught skills (UI/UX Design, Figma) -- demoes a match without the badge.
-- ------------------------------------------------------------
INSERT INTO member_skills (user_id, skill_id, type, proficiency_level) VALUES
-- Rohan Verma (2)
(2, 1, 'teach', 'advanced'),
(2, 2, 'teach', 'intermediate'),
(2, 9, 'learn', NULL),
(2, 12, 'learn', NULL),
-- Sneha Iyer (3)
(3, 9, 'teach', 'expert'),
(3, 16, 'teach', 'advanced'),
(3, 1, 'learn', NULL),
(3, 6, 'learn', NULL),
-- Arjun Mehta (4)
(4, 3, 'teach', 'advanced'),
(4, 4, 'teach', 'expert'),
(4, 22, 'teach', 'advanced'),
(4, 15, 'learn', NULL),
-- Kavya Nair (5)
(5, 6, 'teach', 'expert'),
(5, 8, 'teach', 'advanced'),
(5, 3, 'learn', NULL),
(5, 12, 'learn', NULL),
-- Aditya Kulkarni (6)
(6, 12, 'teach', 'advanced'),
(6, 13, 'teach', 'intermediate'),
(6, 2, 'learn', NULL),
(6, 17, 'learn', NULL),
-- Meera Pillai (7)
(7, 10, 'teach', 'advanced'),
(7, 18, 'teach', 'expert'),
(7, 7, 'learn', NULL),
-- Vikram Chauhan (8)
(8, 5, 'teach', 'advanced'),
(8, 17, 'teach', 'intermediate'),
(8, 10, 'learn', NULL),
(8, 20, 'learn', NULL),
-- Ishita Bose (9)
(9, 7, 'teach', 'advanced'),
(9, 14, 'teach', 'intermediate'),
(9, 4, 'learn', NULL),
(9, 19, 'learn', NULL),
-- Karthik Subramanian (10)
(10, 15, 'teach', 'expert'),
(10, 16, 'teach', 'intermediate'),
(10, 5, 'learn', NULL),
(10, 13, 'learn', NULL),
-- Divya Reddy (11)
(11, 20, 'teach', 'advanced'),
(11, 21, 'teach', 'expert'),
(11, 17, 'learn', NULL),
-- Nikhil Joshi (12)
(12, 2, 'teach', 'advanced'),
(12, 4, 'teach', 'intermediate'),
(12, 8, 'learn', NULL),
(12, 11, 'learn', NULL),
-- Ananya Das (13)
(13, 11, 'teach', 'advanced'),
(13, 19, 'teach', 'intermediate'),
(13, 14, 'learn', NULL),
-- Rahul Gupta (14)
(14, 18, 'teach', 'advanced'),
(14, 21, 'teach', 'intermediate'),
(14, 1, 'learn', NULL),
(14, 15, 'learn', NULL),
-- Priyanka Menon (15)
(15, 8, 'teach', 'advanced'),
(15, 17, 'teach', 'advanced'),
(15, 12, 'learn', NULL),
(15, 10, 'learn', NULL),
-- Tanvi Shah (16)
(16, 1, 'teach', 'intermediate'),
(16, 13, 'teach', 'advanced'),
(16, 6, 'learn', NULL),
(16, 18, 'learn', NULL),
-- Aakash Nambiar (17)
(17, 4, 'teach', 'advanced'),
(17, 3, 'teach', 'intermediate'),
(17, 16, 'learn', NULL),
(17, 20, 'learn', NULL),
-- Riya Kapoor (18)
(18, 14, 'teach', 'advanced'),
(18, 10, 'teach', 'intermediate'),
(18, 3, 'learn', NULL),
(18, 17, 'learn', NULL),
-- Manoj Pandey (19, inactive) -- history retained even though he can't log in
(19, 16, 'teach', 'intermediate'),
(19, 1, 'learn', NULL);

-- ------------------------------------------------------------
-- Sessions across every state-machine status, timestamps relative to
-- NOW() so upcoming/past views always look live. Session 3 (Kavya <->
-- Arjun, CONFIRMED, NOW()+2 days 09:00-10:30) is the deliberately-traceable
-- overlap-check fixture: a new request touching either participant's time
-- range within that window must be rejected on accept.
-- ------------------------------------------------------------
INSERT INTO sessions (id, skill_id, requester_id, recipient_id, scheduled_start, scheduled_end, status, notes) VALUES
(1,  9,  2,  3,  DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 10 HOUR, DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 11 HOUR, 'confirmed', 'Intro conversational Spanish practice'),
(2,  1,  3,  2,  DATE_ADD(CURDATE(), INTERVAL 5 DAY) + INTERVAL 15 HOUR, DATE_ADD(CURDATE(), INTERVAL 5 DAY) + INTERVAL 16 HOUR, 'confirmed', 'Python basics walkthrough'),
(3,  3,  5,  4,  DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 9 HOUR, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 10 HOUR + INTERVAL 30 MINUTE, 'confirmed', 'Java OOP concepts'),
(4,  12, 2,  6,  DATE_ADD(CURDATE(), INTERVAL 4 DAY) + INTERVAL 17 HOUR, DATE_ADD(CURDATE(), INTERVAL 4 DAY) + INTERVAL 18 HOUR, 'requested', 'Would love to finally learn a few chords'),
(5,  6,  16, 5,  DATE_ADD(CURDATE(), INTERVAL 6 DAY) + INTERVAL 12 HOUR, DATE_ADD(CURDATE(), INTERVAL 6 DAY) + INTERVAL 13 HOUR, 'requested', 'Portfolio review + UX basics'),
(6,  15, 14, 10, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 9 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR, 'declined', 'Clashes with my own Toastmasters meeting, sorry!'),
(7,  16, 17, 3,  DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 11 HOUR, DATE_ADD(CURDATE(), INTERVAL 7 DAY) + INTERVAL 12 HOUR, 'cancelled', 'Something came up, will re-request soon'),
(8,  4,  12, 17, DATE_SUB(CURDATE(), INTERVAL 5 DAY) + INTERVAL 14 HOUR, DATE_SUB(CURDATE(), INTERVAL 5 DAY) + INTERVAL 15 HOUR, 'completed', 'SQL joins review'),
(9,  17, 8,  15, DATE_SUB(CURDATE(), INTERVAL 10 DAY) + INTERVAL 10 HOUR, DATE_SUB(CURDATE(), INTERVAL 10 DAY) + INTERVAL 11 HOUR, 'completed', 'Digital marketing fundamentals'),
(10, 18, 16, 7,  DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 7 HOUR, DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 8 HOUR, 'completed', 'Morning yoga basics'),
(11, 20, 8,  11, DATE_ADD(CURDATE(), INTERVAL 8 DAY) + INTERVAL 16 HOUR, DATE_ADD(CURDATE(), INTERVAL 8 DAY) + INTERVAL 17 HOUR, 'requested', 'Bread baking basics, if you have time'),
(12, 11, 12, 13, DATE_ADD(CURDATE(), INTERVAL 9 DAY) + INTERVAL 11 HOUR, DATE_ADD(CURDATE(), INTERVAL 9 DAY) + INTERVAL 12 HOUR, 'confirmed', 'Japanese greetings and basics'),
(13, 13, 10, 6,  DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 19 HOUR, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 20 HOUR, 'requested', 'Piano scales practice'),
(14, 8,  12, 15, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 13 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 14 HOUR, 'confirmed', 'Figma auto-layout deep dive'),
(15, 10, 15, 18, DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 9 HOUR, 'declined', 'Swamped this week, try me again later'),
(16, 12, 5,  6,  DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 11 HOUR, 'cancelled', 'Had to cancel, rescheduling separately'),
(17, 19, 9,  13, DATE_SUB(CURDATE(), INTERVAL 15 DAY) + INTERVAL 9 HOUR, DATE_SUB(CURDATE(), INTERVAL 15 DAY) + INTERVAL 10 HOUR, 'completed', 'Nutrition basics 101'),
(18, 1,  19, 2,  DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 9 HOUR, 'requested', 'Requested before my account was deactivated'),
(19, 15, 4,  10, DATE_ADD(CURDATE(), INTERVAL 10 DAY) + INTERVAL 10 HOUR, DATE_ADD(CURDATE(), INTERVAL 10 DAY) + INTERVAL 11 HOUR, 'confirmed', 'Public speaking fundamentals'),
(20, 14, 13, 9,  DATE_SUB(CURDATE(), INTERVAL 20 DAY) + INTERVAL 16 HOUR, DATE_SUB(CURDATE(), INTERVAL 20 DAY) + INTERVAL 17 HOUR, 'completed', 'Vocal warmups and breathing basics');

-- ------------------------------------------------------------
-- Chat messages -- seeded on a handful of confirmed/completed sessions.
-- ------------------------------------------------------------
INSERT INTO messages (session_id, sender_id, body, created_at) VALUES
(1, 2, 'Hi Sneha! Looking forward to the Spanish session, should I prep anything?', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 3, 'Just bring a notebook, we will start with greetings and basic phrases.', DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 20 MINUTE),
(1, 2, 'Perfect, see you then!', DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 25 MINUTE),
(2, 3, 'Excited for the Python session, I have zero programming background so go easy on me.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 'No worries, we will start from variables and loops. Install Python beforehand if you can.', DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 15 MINUTE),
(2, 3, 'Already installed, ready to go!', DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 30 MINUTE),
(3, 5, 'Looking forward to finally understanding OOP properly.', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(3, 4, 'We will cover classes, objects and inheritance with real examples.', DATE_SUB(NOW(), INTERVAL 11 HOUR)),
(8, 12, 'Thanks again for the SQL joins session yesterday, it finally clicked.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8, 17, 'Glad it helped! INNER vs LEFT JOIN trips up everyone at first.', DATE_SUB(NOW(), INTERVAL 4 DAY) + INTERVAL 10 MINUTE),
(8, 12, 'Definitely booking another one for window functions.', DATE_SUB(NOW(), INTERVAL 4 DAY) + INTERVAL 25 MINUTE),
(9, 8, 'That digital marketing session was great, thanks Priyanka!', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(9, 15, 'Anytime! Let me know how the campaign goes.', DATE_SUB(NOW(), INTERVAL 9 DAY) + INTERVAL 30 MINUTE),
(9, 8, 'Will do, sharing results next time we chat.', DATE_SUB(NOW(), INTERVAL 9 DAY) + INTERVAL 40 MINUTE),
(10, 16, 'That morning yoga session set my whole week right, thank you!', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(10, 7, 'So glad to hear that! Consistency is the real trick.', DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 5 MINUTE),
(17, 9, 'The nutrition basics session was eye opening, thank you Ananya.', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(17, 13, 'Happy to help, ping me anytime with follow-up questions.', DATE_SUB(NOW(), INTERVAL 14 DAY) + INTERVAL 20 MINUTE),
(20, 13, 'Loved the vocal warmups, my range already feels different!', DATE_SUB(NOW(), INTERVAL 19 DAY)),
(20, 9, 'Keep practicing the breathing exercises daily, huge difference over time.', DATE_SUB(NOW(), INTERVAL 19 DAY) + INTERVAL 15 MINUTE),
(12, 12, 'Really looking forward to picking up some Japanese basics!', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(12, 13, 'It will be fun, we will start with hiragana and common greetings.', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(14, 12, 'Auto-layout has been confusing me for weeks, hoping this clears it up.', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(14, 15, 'It will make total sense after this session, promise!', DATE_SUB(NOW(), INTERVAL 2 HOUR));
