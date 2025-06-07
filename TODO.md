# Epistol - Development TODO

This document outlines the development plan for Epistol, broken down into iterative milestones. Each milestone delivers a functional piece of the application, allowing for continuous testing and development.

---

### Milestone 0: Project Setup & Foundation (The Blueprint)

The goal of this milestone is to get the basic project structure, database, and configuration in place. No visible functionality yet.

-   [ ] Initialize a `git` repository and push the initial folder structure.
-   [ ] Finalize the database schema in `db/schema.sql`.
-   [ ] Create a simple PHP script (`scripts/init_db.php`) that creates `db/database.sqlite` and executes the schema SQL.
-   [ ] Create the configuration files: `config/config.php` for general settings (like DB path) and `config/credentials.php.template` for sensitive info.
-   [ ] Ensure `.gitignore` is correctly set up to ignore `config/credentials.php`, the `/vendor` directory, and `db/database.sqlite`.

---

### Milestone 1: The Core Read-Only Loop (See Your World)

The goal is to get emails *into* the database and *display* them in a basic feed. We will start with test data to de-couple frontend and backend development.

-   [ ] **Create Test Data Injector:**
    -   [ ] Create a script `scripts/inject_test_data.php`.
        -   [ ] This script should populate the database with fake `persons`, `email_addresses`, `threads`, and `emails` records.
            -   [ ] This is **CRITICAL** as it allows the UI to be built and tested before the complex mail sync engine is complete.
            -   [ ] **Build the Backend Feed API:**
                -   [ ] Create `api/get_feed.php`.
                    -   [ ] This endpoint should query the database (for the test data) and return a JSON array of the latest threads.
                    -   [ ] **Build the Basic Frontend:**
                        -   [ ] Create `public/index.php` as the main layout file.
                            -   [ ] Create `public/css/style.css` with very basic styling for a feed.
                                -   [ ] In `public/js/app.js`, write a function to `fetch` data from `api/get_feed.php` on page load.
                                    -   [ ] Write JavaScript to dynamically create HTML elements and render the fetched threads in the feed.
                                    -   [ ] **Build the Real Mail Sync Engine:**
                                        -   [ ] Create `cron/sync_emails.php`.
                                            -   [ ] Implement IMAP connection logic using the credentials from `config.php`.
                                                -   [ ] Write logic to fetch unread emails.
                                                    -   [ ] Implement parsing logic (using `MailParser.php` class) to extract From, To, Subject, Body, and attachments.
                                                        -   [ ] Write the database insertion logic:
                                                                -   Check if the sender (`person` / `email_address`) exists; if not, create them.
                                                                        -   Identify the correct `thread` (based on Subject or `In-Reply-To` headers).
                                                                                -   Insert the `email` record, linking it to the thread and sender.
                                                                                        -   Handle saving attachments to the `/storage/attachments` directory and creating `attachments` records.

                                                                                        ---

                                                                                        ### Milestone 2: Making it Interactive (Sending & Replying)

                                                                                        The goal is to enable users to interact with the feed by sending replies or new emails.

                                                                                        -   [ ] **UI for Composing:**
                                                                                            -   [ ] Design and implement a "Reply" button on each thread.
                                                                                                -   [ ] Clicking it should show a text area for composing a reply.
                                                                                                    -   [ ] Add a "New Message" button to compose a new email thread.
                                                                                                    -   [ ] **Backend for Sending:**
                                                                                                        -   [ ] Create the `api/send_email.php` endpoint. It should accept recipient(s), subject, and body.
                                                                                                            -   [ ] Create an `SmtpMailer.php` class to handle the SMTP connection and sending logic.
                                                                                                                -   [ ] After successfully sending the email via SMTP, the script must also save the sent message to the local SQLite database to make it appear in the thread instantly.
                                                                                                                -   [ ] **Frontend-Backend Integration:**
                                                                                                                    -   [ ] Hook up the "Send" button in the UI to make a POST request to `api/send_email.php`.
                                                                                                                        -   [ ] On success, the JavaScript should dynamically add the new message to the correct thread in the UI without a full page reload.

                                                                                                                        ---

                                                                                                                        ### Milestone 3: Profiles and Organization (Adding Context)

                                                                                                                        The goal is to build out the "social" aspect with profiles and groups.

                                                                                                                        -   [ ] **Person Profiles:**
                                                                                                                            -   [ ] Create a profile page template (`profile.php` or similar).
                                                                                                                                -   [ ] Create a backend endpoint `api/get_profile.php` that takes a `person_id` and returns their details and all associated email threads.
                                                                                                                                    -   [ ] Make names/avatars in the main feed clickable links to the profile page.
                                                                                                                                    -   [ ] **Groups:**
                                                                                                                                        -   [ ] Design a simple UI for creating groups and adding/removing members (persons).
                                                                                                                                            -   [ ] Create the necessary API endpoints (`api/create_group.php`, `api/add_member.php`, etc.).
                                                                                                                                                -   [ ] Add a filter/dropdown on the main feed to show threads only from people in a specific group.

                                                                                                                                                ---

                                                                                                                                                ### Milestone 4: Polish and Quality of Life

                                                                                                                                                The goal is to refine the experience and add high-value features.

                                                                                                                                                -   [ ] **Attachment Handling:**
                                                                                                                                                    -   [ ] Display a list of attachments within an email in the UI.
                                                                                                                                                        -   [ ] Make attachments downloadable.
                                                                                                                                                            -   [ ] Add a file input to the compose UI to allow attaching files to new emails.
                                                                                                                                                            -   [ ] **Basic Search:**
                                                                                                                                                                -   [ ] Add a search bar to the UI.
                                                                                                                                                                    -   [ ] Create a backend search endpoint that queries subjects, bodies, and sender names.
                                                                                                                                                                    -   [ ] **UI/UX Improvements:**
                                                                                                                                                                        -   [ ] Add "is_read" functionality: mark threads as read/unread.
                                                                                                                                                                            -   [ ] Add loading indicators for API calls.
                                                                                                                                                                                -   [ ] Improve the CSS to make it look polished and professional.
                                                                                                                                                                                    -   [ ] Ensure the layout is reasonably responsive for mobile/tablet viewing.

                                                                                                                                                                                    ---

                                                                                                                                                                                    ### Future Ideas (Post-MVP)

                                                                                                                                                                                    -   [ ] Full-text search using SQLite's FTS5 extension.
                                                                                                                                                                                    -   [ ] "Archive" and "Delete" functionality.
                                                                                                                                                                                    -   [ ] User-configurable settings page (e.g., number of items per feed, theme).
                                                                                                                                                                                    -   [ ] Markdown support for composing emails.
                                                                                                                                                                                    -   [ ] A dedicated notifications system for new messages.