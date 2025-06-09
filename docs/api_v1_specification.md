# API v1 Specification

This document provides a specification for the API endpoints located under `/api/v1/`.

## Contents
- [/api/v1/add_member_to_group.php](#apiv1add_member_to_groupphp)
- [/api/v1/create_group.php](#apiv1create_groupphp)
- [/api/v1/delete_group.php](#apiv1delete_groupphp)
- [/api/v1/download_attachment.php](#apiv1download_attachmentphp)
- [/api/v1/get_feed.php](#apiv1get_feedphp)
- [/api/v1/get_group_members.php](#apiv1get_group_membersphp)
- [/api/v1/get_groups.php](#apiv1get_groupsphp)
- [/api/v1/get_profile.php](#apiv1get_profilephp)
- [/api/v1/get_thread.php](#apiv1get_threadphp)
- [/api/v1/remove_member_from_group.php](#apiv1remove_member_from_groupphp)
- [/api/v1/send_email.php](#apiv1send_emailphp)
- [/api/v1/set_email_status.php (Formerly set_post_status.php)](#apiv1set_email_statusphp-formerly-set_post_statusphp)
- [/api/v1/split_reply_to_post.php](#apiv1split_reply_to_postphp)


---

## /api/v1/add_member_to_group.php
- **Method**: `POST`
- **Description**: Adds a specified person (member) to a specified group.
- **Path**: `/api/v1/add_member_to_group.php`
- **Request Parameters (JSON Body)**:
    - `group_id` (integer, required): The ID of the group (`groups.id`).
    - `user_id` (integer, required): The ID of the user (`users.id`) to add to the group.
    - **Example JSON Input**:
      ```json
      {
        "group_id": 101,
        "user_id": 202
      }
      ```
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: Indicates the member was successfully added or was already a member.
      ```json
      {
        "status": "success",
        "data": {
          "message": "Member added to group successfully."
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid JSON input, or missing/invalid `group_id` or `user_id`.
    - `404 Not Found`: If the specified `group_id` or `user_id` does not exist.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Validates existence of `group_id` in `groups` table and `user_id` in `users` table.
    - Inserts a new record into `group_members` table (linking `user_id` and `group_id`).

---

## /api/v1/create_group.php
- **Method**: `POST`
- **Description**: Creates a new group. The user performing the action is set as `created_by_user_id`.
- **Path**: `/api/v1/create_group.php`
- **Request Parameters (JSON Body)**:
    - `name` (string, required): The name for the new group.
    - `description` (string, optional): A description for the group.
    - `created_by_user_id` (integer, required): The ID of the user creating the group.
    - **Example JSON Input**:
      ```json
      {
        "name": "Project Alpha Team",
        "description": "Team working on Project Alpha.",
        "created_by_user_id": 1
      }
      ```
- **Success Response**:
    - **Status Code**: `201 Created`
    - **Body**: Indicates the group was successfully created, returning the new `group_id`.
      ```json
      {
        "status": "success",
        "data": {
          "message": "Group created successfully.",
          "group_id": 123 // Example integer ID
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid JSON, missing `name` or `created_by_user_id`.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Inserts a new record into `groups` table with `name`, `description`, `created_by_user_id`, and `created_at`.

---

## /api/v1/delete_group.php
- **Method**: `POST`
- **Description**: Deletes a specified group. Also removes all members from `group_members` table.
- **Path**: `/api/v1/delete_group.php`
- **Request Parameters (JSON Body)**:
    - `group_id` (integer, required): The ID of the group to delete.
    - **Example JSON Input**:
      ```json
      {
        "group_id": 101
      }
      ```
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: Indicates successful deletion.
      ```json
      {
        "status": "success",
        "data": {
          "message": "Group deleted successfully."
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid JSON or missing `group_id`.
    - `404 Not Found`: If the `group_id` does not exist.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Deletes from `group_members` for the `group_id`.
    - Deletes from `groups` for the `group_id`. Uses a transaction.

---

## /api/v1/download_attachment.php
- **Method**: `GET`
- **Description**: Downloads an email attachment.
- **Path**: `/api/v1/download_attachment.php`
- **Request Parameters ($\_GET)**:
    - `attachment_id` (integer, required): The ID of the attachment file to download (corresponds to `attachments.id`).
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: The raw file content with appropriate `Content-Type` and `Content-Disposition` headers for download.
- **Error Responses**:
    - `400 Bad Request`: Missing `attachment_id`.
    - `404 Not Found`: If `attachment_id` does not correspond to an existing attachment.
    - `500 Internal Server Error`: Error accessing file or database.
- **Database Interaction (Brief)**:
    - Queries the `attachments` table to find the record by `id` (`attachment_id`).
    - Retrieves `filepath_on_disk`, original `filename`, and `mimetype` to serve the file from the path defined by `STORAGE_PATH_ATTACHMENTS`.

---

## /api/v1/get_feed.php
- **Method**: `GET`
- **Description**: Fetches a paginated feed of email threads for a given user. Allows filtering by group. Emails within each thread are ordered by creation date; threads are ordered by their last activity date.
- **Path**: `/api/v1/get_feed.php`
- **Request Parameters ($\_GET)**:
    - `user_id` (integer, required): The ID of the user (`users.id`) for whom to fetch the feed. Determines user-specific data like email status.
    - `page` (integer, optional, default: 1): Page number for pagination.
    - `limit` (integer, optional, default: from config, e.g., 20): Number of threads per page.
    - `group_id` (integer, optional): Filters threads to those belonging to this `threads.group_id`.
    - `status` (string, optional): Filters emails *within the paginated threads* by their status for the `user_id` (e.g., 'read', 'unread', 'sent').
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: Paginated list of threads.
      ```json
      {
        "status": "success",
        "data": {
          "threads": [
            {
              "thread_id": 123,
              "subject": "Discussion about Project X",
              "participants": ["Sender Name A", "Sender Name B"], // Array of sender names from emails in this thread
              "last_reply_time": "YYYY-MM-DD HH:MM:SS", // From threads.last_activity_at
              "emails": [ // Emails belonging to this thread, matching status filter if provided
                {
                  "email_id": 789,
                  "parent_email_id": null,
                  "subject": "Initial post subject",
                  "sender_user_id": 101,
                  "sender_person_id": 201, // Can be null
                  "sender_name": "Sender Name A",
                  "sender_avatar_url": "/avatars/senderA.png", // Can be null or default
                  "body_preview": "First email snippet...",
                  "timestamp": "YYYY-MM-DD HH:MM:SS", // From emails.created_at
                  "status": "read" // For the requesting user_id
                }
                // ... more emails for this thread if they match filters
              ]
            }
            // ... more threads
          ],
          "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total_items": 50, // Total threads matching group_id filter (if any)
            "total_pages": 3
          }
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid or missing `user_id`, `page`, or `limit`. Invalid `group_id`.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Fetches paginated thread IDs from `threads` table, applying `group_id` filter.
    - Fetches associated data from `emails`, `users`, `persons`, and `email_statuses` for the selected threads.
    - Applies `status` filter to the emails.
    - Groups emails into threads in PHP.

---

## /api/v1/get_group_members.php
- **Method**: `GET`
- **Description**: Fetches a paginated list of members for a specified group. Member details include user and person information.
- **Path**: `/api/v1/get_group_members.php`
- **Request Parameters ($\_GET)**:
    - `group_id` (integer, required): The ID of the group (`groups.id`).
    - `page` (integer, optional, default: 1): Page number.
    - `limit` (integer, optional, default: 10): Members per page.
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: List of members with pagination.
      ```json
      {
        "status": "success",
        "data": {
          "group_id": 101,
          "members": [
            {
              "user_id": 202, // users.id
              "username": "johndoe",
              "person_id": 303, // persons.id, can be null
              "person_name": "John Doe", // persons.name, can be null
              "person_avatar_url": "/avatars/person303.png", // persons.avatar_url, can be null
              "joined_at": "YYYY-MM-DD HH:MM:SS" // from group_members.joined_at
            }
            // ... more members
          ],
          "pagination": {
            "current_page": 1,
            "per_page": 10,
            "total_pages": 3,
            "total_members": 25
          }
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Missing or invalid `group_id`.
    - `404 Not Found`: If `group_id` does not exist.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Validates `group_id`. Queries `group_members` joined with `users` and `persons`. Implements pagination.

---

## /api/v1/get_groups.php
- **Method**: `GET`
- **Description**: Fetches a paginated list of all groups, including ID, name, creation date, and member count.
- **Path**: `/api/v1/get_groups.php`
- **Request Parameters ($\_GET)**:
    - `page` (integer, optional, default: 1): Page number.
    - `limit` (integer, optional, default: 10): Groups per page.
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: List of groups with pagination.
      ```json
      {
        "status": "success",
        "data": {
          "groups": [
            {
              "group_id": 101, // groups.id
              "name": "Administrators", // groups.name
              "description": "Site administrators group", // groups.description
              "created_by_user_id": 1, // users.id of creator
              "created_at": "YYYY-MM-DD HH:MM:SS", // groups.created_at
              "member_count": 5
            }
            // ... more groups
          ],
          "pagination": {
            "current_page": 1,
            "per_page": 10,
            "total_pages": 7,
            "total_groups": 68
          }
        }
      }
      ```
- **Error Responses**:
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Queries `groups` table. Uses a subquery or JOIN with `group_members` for `member_count`. Implements pagination.

---

## /api/v1/get_profile.php
- **Method**: `GET`
- **Description**: Fetches profile information for a specified person, including basic details and linked email addresses.
- **Path**: `/api/v1/get_profile.php`
- **Request Parameters ($\_GET)**:
    - `person_id` (integer, required): The ID of the person (`persons.id`).
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: Profile data.
      ```json
      {
        "status": "success",
        "data": {
          "id": 303, // persons.id
          "name": "John Doe", // persons.name
          "avatar_url": "/avatars/person303.png", // persons.avatar_url
          "created_at": "YYYY-MM-DD HH:MM:SS", // persons.created_at
          "email_addresses": [ // from email_addresses table
            {"email_address": "john.doe@example.com", "is_primary": true, "id": 505}
            // ... other email addresses for this person
          ]
          // "threads" array could be added in future if listing threads involving this person.
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Missing or invalid `person_id`.
    - `404 Not Found`: If `person_id` does not exist.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Fetches from `persons` table.
    - Fetches associated records from `email_addresses` table.

---

## /api/v1/get_thread.php
- **Method**: `GET`
- **Description**: Fetches all emails within a specific thread, thread metadata, and participants. User-specific email status and attachments are included.
- **Path**: `/api/v1/get_thread.php`
- **Request Parameters ($\_GET)**:
    - `thread_id` (integer, required): The ID of the thread (`threads.id`).
    - `user_id` (integer, required): The ID of the user (`users.id`) requesting, for email status context.
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: Thread details with emails.
      ```json
      {
        "status": "success",
        "data": {
          "id": 123, // threads.id
          "subject": "Important Discussion About New Schema", // threads.subject
          "participants": [ // Unique senders in this thread
            {
              "id": 201, // persons.id (nullable)
              "user_id": 101, // users.id
              "name": "Alice Wonderland",
              "avatar_url": "/avatars/alice.png"
            },
            {
              "id": 202,
              "user_id": 102,
              "name": "Bob The Builder",
              "avatar_url": "/avatars/bob.png"
            }
          ],
          "emails": [ // Ordered by emails.created_at ASC
            {
              "id": 789, // emails.id
              "parent_email_id": null, // emails.parent_email_id (nullable)
              "subject": "Initial thoughts on schema", // emails.subject
              "sender": {
                "id": 201, // persons.id (nullable)
                "user_id": 101, // users.id
                "name": "Alice Wonderland",
                "avatar_url": "/avatars/alice.png"
              },
              "body_html": "<p>Hello! Here are my initial thoughts...</p>",
              "body_text": "Hello! Here are my initial thoughts...",
              "timestamp": "YYYY-MM-DD HH:MM:SS", // emails.created_at
              "status": "read", // email_statuses.status for the requesting user_id
              "attachments": [
                {
                  "id": 1, // attachments.id
                  "filename": "schema_diagram.png",
                  "mimetype": "image/png",
                  "filesize_bytes": 123456
                }
              ]
            },
            {
              "id": 790, // emails.id
              "parent_email_id": 789,
              "subject": "Re: Initial thoughts on schema",
              "sender": {
                "id": 202,
                "user_id": 102,
                "name": "Bob The Builder",
                "avatar_url": "/avatars/bob.png"
              },
              "body_html": "<p>Thanks Alice, good points!</p>",
              "body_text": "Thanks Alice, good points!",
              "timestamp": "YYYY-MM-DD HH:MM:SS",
              "status": "unread",
              "attachments": []
            }
            // ... more emails
          ]
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Missing or invalid `thread_id` or `user_id`.
    - `404 Not Found`: If `thread_id` does not exist.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Fetches subject from `threads`.
    - Fetches emails from `emails` table, joining `users` and `persons` for sender details, and `email_statuses` for email status relative to the requesting `user_id`.
    - For each email, fetches its attachments from the `attachments` table.
    - Derives a list of unique participants from the senders of emails in the thread.

---

## /api/v1/remove_member_from_group.php
- **Method**: `POST`
- **Description**: Removes a specified user from a specified group.
- **Path**: `/api/v1/remove_member_from_group.php`
- **Request Parameters (JSON Body)**:
    - `group_id` (integer, required): The ID of the group (`groups.id`).
    - `user_id` (integer, required): The ID of the user (`users.id`) to remove.
    - **Example JSON Input**:
      ```json
      {
        "group_id": 101,
        "user_id": 202
      }
      ```
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: Indicates member removed or was not in the group.
      ```json
      {
        "status": "success",
        "data": {
          "message": "Member removed or was not in group."
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid JSON, or missing/invalid IDs.
    - `404 Not Found`: If `group_id` or `user_id` itself doesn't exist (though typically this means the membership link isn't found or user is not a person if that was a constraint).
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Deletes from `group_members` for the `group_id` and `user_id`.

---

## /api/v1/send_email.php
- **Method**: `POST`
- **Description**: Sends an email using an SMTP gateway and records the email, its recipients, attachments, and thread information in the database. The sender is determined by the authenticated user session. If replying to an existing email, it uses the existing thread; otherwise, a new thread is created.
- **Path**: `/api/v1/send_email.php`
- **Request Parameters (JSON Body)**:
    - `recipients` (array of strings, required): Recipient email addresses (e.g., `["user@example.com"]`).
    - `subject` (string, required): Email subject.
    - `body_html` (string, optional): HTML body content.
    - `body_text` (string, optional): Plain text body content. (At least one of `body_html` or `body_text` must be provided and contain content).
    - `in_reply_to_email_id` (integer, optional, nullable): ID of the email (`emails.id`) being replied to. If provided, the new email becomes a reply to this parent email and is added to its thread. If `null` or not provided, a new thread is created.
    - `attachments` (array of objects, optional): Each object must contain:
        - `filename` (string, required): The original filename of the attachment.
        - `content_base64` (string, required): Base64 encoded content of the attachment.
        - `mimetype` (string, required): The MIME type of the attachment (e.g., "application/pdf").
    - **Example JSON Input (New Thread)**:
      ```json
      {
        "recipients": ["recipient1@example.com", "recipient2@example.com"],
        "subject": "New Project Proposal",
        "body_html": "<p>Please find the new project proposal attached.</p>",
        "body_text": "Please find the new project proposal attached.",
        "attachments": [
          {
            "filename": "proposal.pdf",
            "content_base64": "JVBERi0xLjQKJ...",
            "mimetype": "application/pdf"
          }
        ]
      }
      ```
    - **Example JSON Input (Reply)**:
      ```json
      {
        "recipients": ["original_sender@example.com"],
        "subject": "Re: Your previous email", // Subject might be auto-prefixed by client, or server can do it
        "body_text": "Thanks for your email. Here's my response...",
        "in_reply_to_email_id": 789
      }
      ```
- **Success Response**:
    - **Status Code**: `200 OK`
    - **Body**: Confirmation with created `email_id` and `thread_id`.
      ```json
      {
        "status": "success",
        "data": {
          "message": "Email sent and saved successfully.",
          "email_id": 998, // Integer ID of the newly created email in 'emails' table
          "thread_id": 124 // Integer ID of the thread it belongs to (either new or existing)
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid JSON, missing required fields (recipients, subject, at least one body type), invalid email format in recipients, invalid attachment object structure or base64 content.
    - `404 Not Found`: If `in_reply_to_email_id` is provided but the corresponding email is not found.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`: SMTP gateway failure, database error during transaction (e.g., inserting into `threads`, `emails`, `persons`, `email_addresses`, `email_recipients`, `attachments`), or file system error when saving attachments.
- **Database Interaction (Brief)**:
    - After successful SMTP send:
    - Starts a transaction.
    - If `in_reply_to_email_id` provided: Fetches parent email's `thread_id`. New email's `parent_email_id` is `in_reply_to_email_id`.
    - Else (new thread): Inserts into `threads` (subject from input, `created_by_user_id` from authenticated user).
    - Inserts into `emails` (linking to thread, sender as authenticated `user_id`, content from input, `parent_email_id` if applicable, generated `message_id_header`).
    - Inserts status 'sent' into `email_statuses` for the sender.
    - For each recipient: Finds/creates `persons` and `email_addresses` records. Inserts into `email_recipients`.
    - For each attachment: Saves decoded content to disk (using `STORAGE_PATH_ATTACHMENTS`), inserts record into `attachments` table.
    - Updates `threads.last_activity_at`.
    - Commits transaction.

---

## /api/v1/set_email_status.php (Formerly set_post_status.php)
- **Method**: `POST`
- **Description**: Sets or updates the status for a specific email for a given user.
- **Path**: `/api/v1/set_post_status.php` (Filename might remain `set_post_status.php` for backward compatibility or until refactored, but it operates on emails).
- **Request Parameters (JSON Body)**:
    - `email_id` (integer, required): ID of the email (`emails.id`) whose status is being set. (Changed from `post_id`).
    - `user_id` (integer, required): ID of the user (`users.id`) for whom the status is being set.
    - `status` (string, required): Status to set. Allowed values: 'read', 'unread', 'sent', 'archived', 'deleted', 'follow-up', 'important-info'. (Ensure these values are handled by the backend).
    - **Example JSON Input**:
      ```json
      {
        "email_id": 789,
        "user_id": 101,
        "status": "read"
      }
      ```
- **Success Response**:
    - **Status Code**: `200 OK` (if updated or created, specific code might vary based on implementation).
    - **Body**: Confirmation message and the ID of the status entry.
      ```json
      {
        "status": "success",
        "data": {
          "message": "Email status updated/created successfully.",
          "id": 55 // Integer ID of the entry in 'email_statuses' table
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid JSON, missing fields, invalid `status` value.
    - `404 Not Found`: If `email_id` or `user_id` does not exist.
    - `405 Method Not Allowed`.
    - `500 Internal Server Error`.
- **Database Interaction (Brief)**:
    - Checks `email_statuses` table for an existing record matching `email_id` and `user_id`.
    - If found, updates the `status` and `updated_at` timestamp.
    - If not found, inserts a new record with `email_id`, `user_id`, `status`, and `created_at` timestamp.

---

## /api/v1/split_reply_to_post.php
- **Method**: `POST`
- **Description**: Converts an existing email message (typically a reply within a thread) into the first message of a brand new thread.
- **Path**: `/api/v1/split_reply_to_post.php`
- **Request Parameters (JSON Body)**:
    - `email_id` (integer, required): The ID of the email message (`emails.id`) to be split into a new thread.
    - `user_id` (integer, required): The ID of the user (`users.id`) performing this action. This user will be set as `created_by_user_id` for the newly created thread.
    - **Example JSON Input**:
      ```json
      {
        "email_id": 790,
        "user_id": 101
      }
      ```
- **Success Response (200 OK)**:
    - **Body**:
      ```json
      {
        "status": "success",
        "data": {
          "message": "Email successfully split into a new thread.",
          "new_thread_id": 125,  // Integer ID of the new thread in 'threads' table
          "updated_email_id": 790 // Integer ID of the email that was moved
        }
      }
      ```
- **Error Responses**:
    - `400 Bad Request`: Invalid JSON input, or missing/invalid `email_id` or `user_id`.
    - `404 Not Found`: If the specified `email_id` does not exist.
    - `405 Method Not Allowed`: If the request method is not `POST`.
    - `500 Internal Server Error`: Database error during transaction or other unexpected server issues.
- **Database Interaction (Brief)**:
    - Operates within a database transaction.
    - Fetches the target email to get its current `thread_id` (old_thread_id) and `subject`.
    - Creates a new thread in the `threads` table:
        - `subject` is taken from the email being split.
        - `created_by_user_id` is the `user_id` from the request.
        - `created_at` and `last_activity_at` are set to the current timestamp.
    - Updates the target email record in the `emails` table:
        - Sets its `thread_id` to the ID of the newly created thread.
        - Sets its `parent_email_id` to `NULL`.
    - Updates the `last_activity_at` timestamp for the original thread (`old_thread_id`):
        - This is set to the timestamp of the newest remaining email in the old thread.
        - If no emails remain in the old thread, `last_activity_at` is set to the old thread's `created_at` timestamp (or current time as a fallback).
    - Child replies of the split email (if any) are *not* moved and remain in the original thread.

---
Markdown content generated.
