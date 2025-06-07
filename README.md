# Epistol

**Tagline:** Your email, reimagined as a social feed.

## What is Epistol?

Epistol is a web application that transforms your personal email inbox into a modern, conversation-focused social media interface. Instead of a linear list of messages, Epistol organizes your communications into threads, builds profiles for the people you talk to, and presents it all in a familiar feed-style UI, much like Facebook or Twitter.

This project is designed for a **single user** who wants a more intuitive and person-centric way to manage their email. It pulls messages from your existing email account(s) and provides a new lens through which to view them.

## Key Features

-   **Social Feed View:** See the latest email conversations from all your contacts in a scrollable, easy-to-digest feed.
-   **Conversation Threads:** Email replies are grouped into a single, collapsible thread, making it easy to follow the entire conversation.
-   **Person Profiles:** Epistol automatically creates profiles for your contacts. A single person's profile can consolidate multiple email addresses they use, and you can add notes or a profile picture.
-   **Attachment Handling:** View and download attachments directly within the UI.
-   **Groups:** Create custom groups like "Family," "Work Colleagues," or "Project Phoenix" to filter your feed and organize your contacts.
-   **Send & Reply:** Compose and send emails directly from the interface.

## Technology Stack

This project is built with simplicity and accessibility in mind, using a classic web stack with no compilers or complex build steps.

-   **Backend:** PHP 8+
-   **Frontend:** Vanilla JavaScript (ES6+), HTML5, CSS3
-   **Database:** SQLite 3
-   **Email Protocol:** IMAP for fetching emails, SMTP for sending.

## Testing

This project uses [PHPUnit](https://phpunit.de/) for unit testing.

1.  **Install Dependencies:**
    If you haven't already, install the project dependencies using Composer:
    ```bash
    composer install
    ```

2.  **Run Tests:**
    To run the test suite, execute the following command from the project root:
    ```bash
    vendor/bin/phpunit
    ```

## Project Architecture

Epistol is composed of four primary components that work together:

1.  **Frontend (The View):** A pure HTML, CSS, and JavaScript single-page-style interface that renders the feed, threads, and profiles. It communicates with the backend via asynchronous `fetch()` requests.
2.  **Backend API (The Controller):** A set of PHP scripts located in the `/api` directory. These scripts handle requests from the frontend, query the database, and return data in JSON format.
3.  **Database (The Model):** A single SQLite file (`db/database.sqlite`) that serves as the single source of truth for all parsed emails, profiles, groups, and application data.
4.  **Mail Sync Engine (The Worker):** A critical PHP script (`cron/sync_emails.php`) that runs on a recurring schedule (via a cron job). It connects to your email server(s) via IMAP, fetches new messages, parses them, and intelligently populates the SQLite database.

## Setup and Installation

1.  **Clone the Repository:**
    ```bash
        git clone <your-repo-url> Epistol
            cd Epistol
                ```
                2.  **Configure Credentials:**
                    -   Copy `config/credentials.php.template` to `config/credentials.php`.
                        -   Edit `config/credentials.php` and enter your IMAP and SMTP server details and login credentials. **This file is git-ignored for security.**
                        3.  **Web Server Setup:**
                            -   Point your web server (Apache, Nginx, etc.) document root to the `/public` directory. This is crucial for security, as it prevents direct web access to your application logic, database, and credentials.
                                -   Ensure the PHP IMAP extension is installed and enabled (`php-imap`).
                                    -   Make sure the `/storage/attachments` directory is writable by the web server user.
                                    4.  **Initialize Database:**
                                        -   Run the initialization script from your terminal to create the `database.sqlite` file and its schema: `php scripts/init_db.php`.
                                        5.  **Set Up Cron Job:**
                                            -   Set up a system cron job to run the mail sync script periodically. For example, to run it every 5 minutes:
                                                ```
                                                    */5 * * * * /usr/bin/php /path/to/your/Epistol/cron/sync_emails.php >> /var/log/Epistol_cron.log 2>&1
                                                        ```