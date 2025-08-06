<?php

/**
 * Email Sync Cron Job
 * 
 * This script connects to an IMAP server, fetches unread emails,
 * parses them, and stores them in the database.
 */

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/classes/MailParser.php';

// Load credentials if available
if (file_exists(__DIR__ . '/../config/credentials.php')) {
    require_once __DIR__ . '/../config/credentials.php';
}

class EmailSync
{
    private $db;
    private $mailbox;
    private $parser;
    
    public function __construct()
    {
        $this->db = new Database();
        $this->parser = new MailParser();
    }
    
    /**
     * Connect to IMAP server
     */
    public function connect()
    {
        $host = defined('IMAP_HOST') ? IMAP_HOST : 'localhost';
        $port = defined('IMAP_PORT') ? IMAP_PORT : 993;
        $username = defined('IMAP_USER') ? IMAP_USER : '';
        $password = defined('IMAP_PASS') ? IMAP_PASS : '';
        $encryption = defined('IMAP_ENCRYPTION') ? IMAP_ENCRYPTION : 'ssl';
        
        if (empty($username) || empty($password)) {
            throw new Exception('IMAP credentials not configured');
        }
        
        $connectionString = "{{$host}:{$port}/imap/{$encryption}}INBOX";
        
        $this->mailbox = imap_open($connectionString, $username, $password);
        
        if (!$this->mailbox) {
            throw new Exception('Failed to connect to IMAP server: ' . imap_last_error());
        }
        
        echo "Connected to IMAP server successfully\n";
    }
    
    /**
     * Fetch and process unread emails
     */
    public function syncEmails()
    {
        // Search for unread emails
        $emails = imap_search($this->mailbox, 'UNSEEN');
        
        if (!$emails) {
            echo "No unread emails found\n";
            return;
        }
        
        echo "Found " . count($emails) . " unread emails\n";
        
        foreach ($emails as $emailNumber) {
            try {
                $this->processEmail($emailNumber);
                echo "Processed email #$emailNumber\n";
            } catch (Exception $e) {
                echo "Error processing email #$emailNumber: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Process a single email
     */
    private function processEmail($emailNumber)
    {
        // Fetch email headers
        $headers = imap_headerinfo($this->mailbox, $emailNumber);
        
        if (!$headers) {
            throw new Exception('Could not fetch email headers');
        }
        
        // Fetch raw email
        $rawEmail = imap_fetchheader($this->mailbox, $emailNumber) . "\n\n" . 
                   imap_body($this->mailbox, $emailNumber);
        
        // Parse email
        $parsedEmail = $this->parser->parse($rawEmail);
        
        // Process sender
        $sender = $this->processSender($parsedEmail);
        
        // Process thread
        $thread = $this->processThread($parsedEmail);
        
        // Insert email
        $this->insertEmail($parsedEmail, $sender, $thread);
        
        // Mark as read
        imap_setflag_full($this->mailbox, $emailNumber, "\\Seen");
    }
    
    /**
     * Process sender information
     */
    private function processSender($parsedEmail)
    {
        $sender = $parsedEmail['from'][0] ?? null;
        
        if (!$sender) {
            throw new Exception('No sender information found');
        }
        
        $email = $sender['email'];
        $name = $sender['name'];
        
        // Check if person exists
        $person = $this->db->query("SELECT p.* FROM persons p 
                                   JOIN email_addresses ea ON p.id = ea.person_id 
                                   WHERE ea.email_address = ?", [$email])->fetch();
        
        if (!$person) {
            // Create new person
            $this->db->query("INSERT INTO persons (name) VALUES (?)", [$name]);
            $personId = $this->db->lastInsertId();
            
            // Create email address
            $this->db->query("INSERT INTO email_addresses (person_id, email_address, is_primary) 
                             VALUES (?, ?, 1)", [$personId, $email]);
        } else {
            $personId = $person['id'];
        }
        
        return $personId;
    }
    
    /**
     * Process thread information
     */
    private function processThread($parsedEmail)
    {
        $subject = $parsedEmail['subject'];
        $inReplyTo = $parsedEmail['in_reply_to'];
        $messageId = $parsedEmail['message_id'];
        
        // Try to find existing thread
        $thread = null;
        
        if (!empty($inReplyTo)) {
            // Look for thread by In-Reply-To header
            $thread = $this->db->query("SELECT t.* FROM threads t 
                                      JOIN emails e ON t.id = e.thread_id 
                                      WHERE e.message_id_header = ?", [$inReplyTo])->fetch();
        }
        
        if (!$thread && !empty($subject)) {
            // Look for thread by subject (for new conversations)
            $thread = $this->db->query("SELECT * FROM threads WHERE subject = ?", [$subject])->fetch();
        }
        
        if (!$thread) {
            // Create new thread
            $this->db->query("INSERT INTO threads (subject, created_by_user_id) 
                             VALUES (?, 1)", [$subject]);
            $threadId = $this->db->lastInsertId();
        } else {
            $threadId = $thread['id'];
        }
        
        return $threadId;
    }
    
    /**
     * Insert email into database
     */
    private function insertEmail($parsedEmail, $senderId, $threadId)
    {
        $subject = $parsedEmail['subject'];
        $bodyText = $parsedEmail['body_text'];
        $bodyHtml = $parsedEmail['body_html'] ?? '';
        $messageId = $parsedEmail['message_id'];
        $date = $parsedEmail['date'];
        
        // Insert email
        $this->db->query("INSERT INTO emails (thread_id, user_id, subject, body_text, body_html, message_id_header, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)", 
                         [$threadId, 1, $subject, $bodyText, $bodyHtml, $messageId, $date]);
        
        $emailId = $this->db->lastInsertId();
        
        // Process recipients
        $this->processRecipients($emailId, $parsedEmail);
        
        // Process attachments
        $this->processAttachments($emailId, $parsedEmail);
        
        // Update thread last activity
        $this->db->query("UPDATE threads SET last_activity_at = ? WHERE id = ?", 
                         [date('Y-m-d H:i:s'), $threadId]);
    }
    
    /**
     * Process email recipients
     */
    private function processRecipients($emailId, $parsedEmail)
    {
        $recipients = $parsedEmail['to'] ?? [];
        
        foreach ($recipients as $recipient) {
            $email = $recipient['email'];
            
            // Check if person exists
            $person = $this->db->query("SELECT p.* FROM persons p 
                                       JOIN email_addresses ea ON p.id = ea.person_id 
                                       WHERE ea.email_address = ?", [$email])->fetch();
            
            if (!$person) {
                // Create new person
                $this->db->query("INSERT INTO persons (name) VALUES (?)", [$recipient['name']]);
                $personId = $this->db->lastInsertId();
                
                // Create email address
                $this->db->query("INSERT INTO email_addresses (person_id, email_address) 
                                 VALUES (?, ?)", [$personId, $email]);
            } else {
                $personId = $person['id'];
            }
            
            // Add recipient record
            $this->db->query("INSERT INTO email_recipients (email_id, person_id, type) 
                             VALUES (?, ?, 'to')", [$emailId, $personId]);
        }
    }
    
    /**
     * Process email attachments
     */
    private function processAttachments($emailId, $parsedEmail)
    {
        $attachments = $parsedEmail['attachments'] ?? [];
        
        foreach ($attachments as $attachment) {
            $filename = $attachment['filename'];
            $contentType = $attachment['content_type'];
            $data = $attachment['data'];
            $size = $attachment['size'];
            
            // Generate unique filename
            $uniqueFilename = uniqid() . '_' . $filename;
            $filepath = STORAGE_PATH_ATTACHMENTS . '/' . $uniqueFilename;
            
            // Ensure attachments directory exists
            if (!is_dir(STORAGE_PATH_ATTACHMENTS)) {
                mkdir(STORAGE_PATH_ATTACHMENTS, 0755, true);
            }
            
            // Save attachment to disk
            file_put_contents($filepath, $data);
            
            // Insert attachment record
            $this->db->query("INSERT INTO attachments (email_id, filename, mimetype, filesize_bytes, filepath_on_disk) 
                             VALUES (?, ?, ?, ?, ?)", 
                             [$emailId, $filename, $contentType, $size, $filepath]);
        }
    }
    
    /**
     * Close IMAP connection
     */
    public function close()
    {
        if ($this->mailbox) {
            imap_close($this->mailbox);
        }
    }
}

// Main execution
try {
    $sync = new EmailSync();
    $sync->connect();
    $sync->syncEmails();
    $sync->close();
    
    echo "Email sync completed successfully\n";
} catch (Exception $e) {
    echo "Error during email sync: " . $e->getMessage() . "\n";
    exit(1);
}
