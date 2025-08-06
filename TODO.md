# Epistol - Development TODO

This document outlines the development plan for Epistol, broken down into iterative milestones. Each milestone delivers a functional piece of the application, allowing for continuous testing and development.

---

### Milestone 0: Project Setup & Foundation (The Blueprint)

The goal of this milestone is to get the basic project structure, database, and configuration in place. No visible functionality yet.

-   [x] Initialize a `git` repository and push the initial folder structure.
-   [x] Finalize the database schema in `db/schema.sql`.
-   [x] Create a simple PHP script (`scripts/init_db.php`) that creates `db/database.sqlite` and executes the schema SQL.
-   [x] Create the configuration files: `config/config.php` for general settings (like DB path) and `config/credentials.php.template` for sensitive info.
-   [x] Ensure `.gitignore` is correctly set up to ignore `config/credentials.php`, the `/vendor` directory, and `db/database.sqlite`.

---

### Milestone 1: The Core Read-Only Loop (See Your World)

The goal is to get emails *into* the database and *display* them in a basic feed. We will start with test data to de-couple frontend and backend development.

-   [x] **Create Test Data Injector:**
    -   [x] Create a script `scripts/inject_test_data.php`.
    -   [x] This script should populate the database with fake `persons`, `email_addresses`, `threads`, and `emails` records.
    -   [x] This is **CRITICAL** as it allows the UI to be built and tested before the complex mail sync engine is complete.
-   [x] **Build the Backend Feed API:**
    -   [x] Create `api/get_feed.php`.
    -   [x] This endpoint should query the database (for the test data) and return a JSON array of the latest threads.
-   [x] **Build the Basic Frontend:**
    -   [x] Create `public/index.php` as the main layout file.
    -   [x] Create `public/css/style.css` with very basic styling for a feed.
    -   [x] In `public/js/app.js`, write a function to `fetch` data from `api/get_feed.php` on page load.
    -   [x] Write JavaScript to dynamically create HTML elements and render the fetched threads in the feed.
-   [x] **Build the Real Mail Sync Engine:**
    -   [x] Create `cron/sync_emails.php`.
    -   [x] Implement IMAP connection logic using the credentials from `config.php`.
    -   [x] Write logic to fetch unread emails.
    -   [x] Implement parsing logic (using `MailParser.php` class) to extract From, To, Subject, Body, and attachments.
    -   [x] Write the database insertion logic:
        -   [x] Check if the sender (`person` / `email_address`) exists; if not, create them.
        -   [x] Identify the correct `thread` (based on Subject or `In-Reply-To` headers).
        -   [x] Insert the `email` record, linking it to the thread and sender.
        -   [x] Handle saving attachments to the `/storage/`  and creating `attachments` records.

---

### Milestone 2: Making it Interactive (Sending & Replying)

The goal is to enable users to interact with the feed by sending replies or new emails.

-   [x] **UI for Composing:**
    -   [x] Design and implement a "Reply" button on each thread.
    -   [x] Clicking it should show a text area for composing a reply.
    -   [x] Add a "New Message" button to compose a new email thread.
-   [x] **Backend for Sending:**
    -   [x] Create the `api/send_email.php` endpoint. It should accept recipient(s), subject, and body.
    -   [x] Create an `SmtpMailer.php` class to handle the SMTP connection and sending logic.
    -   [x] After successfully sending the email via SMTP, the script must also save the sent message to the local SQLite database to make it appear in the thread instantly.
-   [x] **Frontend-Backend Integration:**
    -   [x] Hook up the "Send" button in the UI to make a POST request to `api/send_email.php`.
    -   [x] On success, the JavaScript should dynamically add the new message to the correct thread in the UI without a full page reload.

---

### Milestone 3: Profiles and Organization (Adding Context)

The goal is to build out the "social" aspect with profiles and groups.

-   [x] **Person Profiles:**
    -   [x] Create a profile page template (`profile.php` or similar).
    -   [x] Create a backend endpoint `api/get_profile.php` that takes a `person_id` and returns their details and all associated email threads.
    -   [x] Make names/avatars in the main feed clickable links to the profile page.
-   [x] **Groups:**
    -   [x] Design a simple UI for creating groups and adding/removing members (persons).
    -   [x] Create the necessary API endpoints (`api/create_group.php`, `api/add_member.php`, etc.).
    -   [x] Add a filter/dropdown on the main feed to show threads only from people in a specific group.

---

### Milestone 4: Polish and Quality of Life

The goal is to refine the experience and add high-value features.

-   [x] **Attachment Handling:**
    -   [x] Display a list of attachments within an email in the UI.
    -   [x] Make attachments downloadable.
    -   [x] Add a file input to the compose UI to allow attaching files to new emails.
-   [x] **Basic Search:**
    -   [x] Add a search bar to the UI.
    -   [x] Create a backend search endpoint that queries subjects, bodies, and sender names.
-   [x] **UI/UX Improvements:**
    -   [x] Add "is_read" functionality: mark threads as read/unread.
    -   [x] Add loading indicators for API calls.
    -   [x] Improve the CSS to make it look polished and professional.
    -   [x] Ensure the layout is reasonably responsive for mobile/tablet viewing.

---

### Milestone 5: Advanced Features & Polish

New tasks that have emerged during development:

-   [x] **Email Status Management:**
    -   [x] Implement read/unread status tracking for emails and threads.
    -   [x] Add API endpoints to mark emails as read/unread.
    -   [x] Add visual indicators for unread messages in the UI.
-   [x] **Enhanced Search & Filtering:**
    -   [x] Add date range filtering to search results.
    -   [x] Add filter by sender/recipient in search.
    -   [x] Implement search result highlighting.
-   [ ] **Email Composition Enhancements:**
    -   [ ] Add rich text editor for email composition.
    -   [ ] Add support for HTML email composition.
    -   [ ] Add email templates or drafts functionality.
-   [ ] **Performance Optimizations:**
    -   [ ] Implement pagination for large email feeds.
    -   [ ] Add caching for frequently accessed data.
    -   [ ] Optimize database queries for better performance.
-   [ ] **Security & Validation:**
    -   [ ] Add input validation and sanitization for all API endpoints.
    -   [ ] Implement CSRF protection for forms.
    -   [ ] Add rate limiting for API endpoints.
-   [ ] **Testing & Quality Assurance:**
    -   [ ] Add unit tests for core classes (MailParser, SmtpMailer).
    -   [ ] Add integration tests for API endpoints.
    -   [ ] Add automated testing for the email sync process.
-   [ ] **Documentation:**
    -   [ ] Create user documentation for setting up and using Epistol.
    -   [ ] Add API documentation for all endpoints.
    -   [ ] Create developer documentation for extending the application.

---

### Milestone 6: Frontend UI/UX Improvements (Current Focus)

Based on user feedback, the following improvements are needed for a better user experience:

#### Mail Thread Styling & Feed Experience
-   [ ] **Minimalistic Facebook-like Feed Design:**
    -   [ ] Remove blue borders on unread emails and replace with subtle indicators
    -   [ ] Implement cleaner card-based design with better spacing
    -   [ ] Add smooth hover effects and transitions
    -   [ ] Improve typography and visual hierarchy
    -   [ ] Make threads more compact and feed-like
    -   [ ] Add better visual separation between threads

#### Filtering System Fixes
-   [ ] **Fix Group Filtering:**
    -   [ ] Ensure groups are properly loaded in left sidebar
    -   [ ] Fix group filter dropdown functionality
    -   [ ] Add proper group management UI
-   [ ] **Fix Status Filtering:**
    -   [ ] Implement proper read/unread status filtering
    -   [ ] Fix important/follow-up status filtering
    -   [ ] Add visual indicators for different statuses
-   [ ] **Fix Right Sidebar Filters:**
    -   [ ] Ensure all advanced filters work properly
    -   [ ] Fix date range filtering
    -   [ ] Fix attachment filtering
    -   [ ] Fix sender/recipient filtering

#### Groups Management
-   [ ] **Groups Display & Management:**
    -   [ ] Ensure groups are visible in left sidebar
    -   [ ] Add group editing functionality
    -   [ ] Add group member management
    -   [ ] Implement group creation and deletion
    -   [ ] Add group-specific filtering

#### Profile Management
-   [ ] **Profile Editing:**
    -   [ ] Add profile edit buttons/triggers
    -   [ ] Implement profile editing modal
    -   [ ] Add profile picture upload functionality
    -   [ ] Add email address management
    -   [ ] Add profile information editing

#### Default Feed Behavior
-   [ ] **Unread Email Focus:**
    -   [ ] Change default feed to show only unread emails
    -   [ ] Remove distracting blue borders
    -   [ ] Add subtle unread indicators instead
    -   [ ] Implement "mark as read" functionality
    -   [ ] Add unread count badges

#### Timeline Enhancement
-   [ ] **Google Photos-style Timeline:**
    -   [ ] Improve timeline dot positioning
    -   [ ] Make hover date follow the blue dot
    -   [ ] Add smooth timeline scrolling
    -   [ ] Implement better date navigation
    -   [ ] Add timeline zoom functionality

#### Additional UI Improvements
-   [ ] **Responsive Design:**
    -   [ ] Improve mobile layout
    -   [ ] Add better tablet support
    -   [ ] Implement collapsible sidebars
-   [ ] **Performance:**
    -   [ ] Optimize feed loading
    -   [ ] Add infinite scrolling
    -   [ ] Implement lazy loading for images
-   [ ] **Accessibility:**
    -   [ ] Add proper ARIA labels
    -   [ ] Improve keyboard navigation
    -   [ ] Add screen reader support

---

### Future Ideas (Post-MVP)

-   [ ] Full-text search using SQLite's FTS5 extension.
-   [ ] "Archive" and "Delete" functionality.
-   [ ] User-configurable settings page (e.g., number of items per feed, theme).
-   [ ] Markdown support for composing emails.
-   [ ] A dedicated notifications system for new messages.
-   [ ] Email threading visualization (conversation view).
-   [ ] Email forwarding and CC/BCC support.
-   [ ] Calendar integration for email scheduling.
-   [ ] Multi-language support.
-   [ ] Dark mode theme.