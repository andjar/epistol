-- Persons table (New - for sender/recipient details beyond users table)
CREATE TABLE persons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255),
    avatar_url VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Email Addresses table (New - linked to persons)
CREATE TABLE email_addresses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER NOT NULL REFERENCES persons(id) ON DELETE CASCADE,
    email_address VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (person_id, email_address),
    UNIQUE (email_address) -- Ensuring email addresses themselves are unique across the system
);

-- Users table (Now with person_id)
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    person_id INTEGER UNIQUE REFERENCES persons(id) ON DELETE SET NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Groups table (Kept as is)
CREATE TABLE groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Threads table (New)
CREATE TABLE threads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject VARCHAR(255) NOT NULL,
    created_by_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    group_id INTEGER REFERENCES groups(id) ON DELETE SET NULL, -- Optional: if a whole thread can belong to a group
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Emails table (Replaces Posts table)
CREATE TABLE emails (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    thread_id INTEGER NOT NULL REFERENCES threads(id) ON DELETE CASCADE,
    parent_email_id INTEGER REFERENCES emails(id) ON DELETE SET NULL, -- For nesting replies
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, -- Sender/author of the email
    group_id INTEGER REFERENCES groups(id) ON DELETE SET NULL, -- If email is directly associated with a group
    subject VARCHAR(255), -- Individual email subject, can be inherited
    body_text TEXT, -- Changed from 'content'
    body_html TEXT, -- For HTML emails
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- Kept from posts.created_at
    message_id_header VARCHAR(255) UNIQUE -- Common for emails, e.g. <uuid@domain.com>
);

-- Group members table (Kept as is)
CREATE TABLE group_members (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    group_id INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, group_id) -- Composite primary key
);

-- Email statuses table (Formerly post_statuses)
CREATE TABLE email_statuses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_id INTEGER NOT NULL REFERENCES emails(id) ON DELETE CASCADE, -- Renamed from post_id
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(255) NOT NULL, -- e.g., 'read', 'unread', 'archived', 'deleted'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Email Recipients table (New - links emails to their recipients)
CREATE TABLE email_recipients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_id INTEGER NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
    person_id INTEGER REFERENCES persons(id) ON DELETE SET NULL, -- Who received it, if known person
    email_address_id INTEGER REFERENCES email_addresses(id) ON DELETE SET NULL, -- The specific email address it was sent to
    type VARCHAR(10) NOT NULL, -- e.g., 'to', 'cc', 'bcc'
    CONSTRAINT chk_recipient_type CHECK (type IN ('to', 'cc', 'bcc')),
    CONSTRAINT ensure_person_or_address CHECK (person_id IS NOT NULL OR email_address_id IS NOT NULL) -- Must have at least one
);

-- Attachments table (New - for email attachments)
CREATE TABLE attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_id INTEGER NOT NULL REFERENCES emails(id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    mimetype VARCHAR(255) NOT NULL,
    filesize_bytes INTEGER NOT NULL,
    filepath_on_disk VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for faster lookups

-- Users table indexes
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_person_id ON users(person_id); -- New

-- Groups table indexes
CREATE INDEX IF NOT EXISTS idx_groups_name ON groups(name);
CREATE INDEX IF NOT EXISTS idx_groups_created_by_user_id ON groups(created_by_user_id); -- New

-- Threads table indexes
CREATE INDEX IF NOT EXISTS idx_threads_created_by_user_id ON threads(created_by_user_id);
CREATE INDEX IF NOT EXISTS idx_threads_group_id ON threads(group_id);
CREATE INDEX IF NOT EXISTS idx_threads_last_activity_at ON threads(last_activity_at); -- New

-- Emails table indexes
CREATE INDEX IF NOT EXISTS idx_emails_thread_id ON emails(thread_id);
CREATE INDEX IF NOT EXISTS idx_emails_user_id ON emails(user_id);
CREATE INDEX IF NOT EXISTS idx_emails_group_id ON emails(group_id);
CREATE INDEX IF NOT EXISTS idx_emails_parent_email_id ON emails(parent_email_id);
CREATE INDEX IF NOT EXISTS idx_emails_message_id_header ON emails(message_id_header);
CREATE INDEX IF NOT EXISTS idx_emails_created_at ON emails(created_at); -- New

-- Group members table indexes
CREATE INDEX IF NOT EXISTS idx_group_members_user_id ON group_members(user_id);
CREATE INDEX IF NOT EXISTS idx_group_members_group_id ON group_members(group_id);

-- Email statuses table indexes
CREATE INDEX IF NOT EXISTS idx_email_statuses_email_id ON email_statuses(email_id);
CREATE INDEX IF NOT EXISTS idx_email_statuses_user_id ON email_statuses(user_id);
CREATE INDEX IF NOT EXISTS idx_email_statuses_status ON email_statuses(status); -- New

-- Persons table indexes
CREATE INDEX IF NOT EXISTS idx_persons_name ON persons(name);

-- Email Addresses table indexes
CREATE INDEX IF NOT EXISTS idx_email_addresses_person_id ON email_addresses(person_id);
CREATE INDEX IF NOT EXISTS idx_email_addresses_email_address ON email_addresses(email_address);
CREATE INDEX IF NOT EXISTS idx_email_addresses_is_primary ON email_addresses(is_primary); -- New

-- Email Recipients table indexes
CREATE INDEX IF NOT EXISTS idx_email_recipients_email_id ON email_recipients(email_id);
CREATE INDEX IF NOT EXISTS idx_email_recipients_person_id ON email_recipients(person_id);
CREATE INDEX IF NOT EXISTS idx_email_recipients_email_address_id ON email_recipients(email_address_id);
CREATE INDEX IF NOT EXISTS idx_email_recipients_type ON email_recipients(type); -- New

-- Attachments table indexes
CREATE INDEX IF NOT EXISTS idx_attachments_email_id ON attachments(email_id);
CREATE INDEX IF NOT EXISTS idx_attachments_mimetype ON attachments(mimetype); -- New

-- It's good practice to also explicitly set an index on foreign key columns if not already covered by other indexes.
-- Most are covered above.

-- Remove old indexes from 'posts' table if they were not dropped with the table (depends on RDBMS behavior)
-- Since we are overwriting the file, explicit DROP INDEX for old tables is not strictly needed here
-- as the old schema will be gone. However, in a migration script, you would drop them.
-- DROP INDEX IF EXISTS idx_posts_user_id;
-- DROP INDEX IF EXISTS idx_posts_group_id;
-- DROP INDEX IF EXISTS idx_post_statuses_post_id;

-- End of schema
