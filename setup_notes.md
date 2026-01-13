# Setup Notes

This file documents the setup steps taken for the Laravel Skill Test project.

## System & PHP Installation (Ubuntu/Debian)

The following commands were run to update the system and install PHP 8.4 from the `sury.org` repository:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apt-transport-https ca-certificates lsb-release gnupg2
sudo wget -O /etc/apt/keyrings/php.gpg https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/etc/apt/keyrings/php.gpg] https://packages.sury.org/php/ bookworm main" | sudo tee /etc/apt/sources.list.d/php.list
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-common php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-sqlite3
```

## Git Configuration

The following Git commands were run to configure the project's remote repository and user identity:

```bash
# Set the remote repository URL
git remote set-url origin https://github.com/n0tx/skill-test.git

# Verify the new remote URL
git remote -v

# Configure local user name and email for this repository
git config --local user.name "n0tx"
git config --local user.email "rcandra91@msn.com"

# Configure Git to cache credentials
git config credential.helper cache

# Check the current status and final configuration
git status
git config --list
```

## Project Environment Setup

This section details the steps to prepare the local Laravel development environment.

### 1. Dependency Installation
First, PHP and Node.js dependencies were installed.

```bash
composer install
npm install
```

### 2. Laravel Configuration
The `.env` file was created from the example, and a unique application key was generated.

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup
An SQLite database was created, and the schema was migrated and seeded with sample data.

```bash
touch database/database.sqlite
php artisan migrate --seed
```

### 4. Initial Testing & Frontend Build
Running the initial test suite revealed multiple failures with a `500 Server Error`.

**Problem:** The tests that render views were failing because the frontend assets (JS/CSS) had not been compiled.
**Solution:** Build the frontend assets using Vite.

```bash
npm run build
```
After building the assets, the view-related tests passed, but many authentication tests still failed. It was decided to ignore these for now to focus on the primary task requirements.

## Feature Implementation: `posts.index` (TDD Workflow)

This section documents the Test-Driven Development process for implementing the `posts.index` endpoint.

### Step 1: Create the Test
A new test file, `tests/Feature/PostTest.php`, was created to verify the requirements for the `posts.index` endpoint.

**Command:** `php artisan test --filter=PostTest`

### Step 2: Iterative Fixing
The test was run repeatedly, and each failure was addressed sequentially.

1.  **Failure:** `BadMethodCallException` on `PostFactory::published()`.
    *   **Problem:** The factory didn't have the required states (`published`, `draft`, `scheduled`).
    *   **Solution:** Added state methods to `database/factories/PostFactory.php`.

2.  **Failure:** `BadMethodCallException` on `Post::author()`.
    *   **Problem:** The `Post` model was missing the `author` relationship.
    *   **Solution:** Added the `belongsTo` relationship to `app/Models/Post.php`.

3.  **Failure:** `404 Not Found`.
    *   **Problem:** The `/posts` route and controller method did not exist.
    *   **Solution:** Created `PostController` and added the route to `routes/web.php`. Also added an `active` query scope to the `Post` model to encapsulate the business logic.

4.  **Failure:** `ParseError` in `routes/web.php`.
    *   **Problem:** An `echo` command had written literal `\n` characters into the routes file.
    *   **Solution:** Manually corrected the syntax in `routes/web.php`.

5.  **Failure:** `Failed asserting that an array has the key 'slug'`.
    *   **Problem:** The database schema and factory were using `content` instead of `body` and were missing `slug`.
    *   **Solution:** Updated the `create_posts_table` migration and `PostFactory` to use the correct column names. The database was then reset.
    *   **Command:** `php artisan migrate:fresh --seed`

6.  **Failure:** `Failed asserting that an array has the key 'meta'`.
    *   **Problem:** The controller returned a raw paginator object, not a standard API resource structure.
    *   **Solution:** Created `PostResource` and `UserResource` to format the JSON output correctly and updated the `PostController` to use them.
    *   **Commands:** `php artisan make:resource PostResource`, `php artisan make:resource UserResource`

7.  **Failure:** `500 Server Error`.
    *   **Problem:** The `PostResource` would fail if `published_at` was `null`.
    *   **Solution:** Added a null-check in `app/Http/Resources/PostResource.php` to handle nullable dates gracefully.

### Step 3: Success
After all fixes were applied, the test passed, confirming the feature was implemented correctly.

**Final Command:** `php artisan test --filter=PostTest`

---

## Development Workflow Cheatsheet

This section provides a quick reference for common development tasks.

### Running the Development Server
To run the built-in Laravel server, use the following command. The application will be available at `http://127.0.0.1:8000`.

```bash
php artisan serve
```
> **Note:** A warning `WARN Unable to respect the PHP_CLI_SERVER_WORKERS` may appear. This is normal and can be safely ignored.

### Accessing the Database (SQLite)
The database is a single file located at `database/database.sqlite`.

**1. Via Command Line (CLI):**
```bash
# Open the database file
sqlite3 database/database.sqlite

# Useful commands inside sqlite shell
.tables        -- List all tables
.schema posts  -- Show the table structure for posts
SELECT * FROM posts; -- View all data in the posts table
.quit          -- Exit the shell
```

**2. Via GUI Application:**
For a visual interface, use an application like **DB Browser for SQLite**.
```bash
# Install on Debian/Ubuntu
sudo apt install sqlitebrowser
```
Then, open the application and use the "Open Database" button to select the `database.sqlite` file.

### Testing the API Endpoint

**1. From the Cloud VM (Directly):**
Use `curl` to make a request to the running server.
```bash
curl -i -H "Accept: application/json" http://127.0.0.1:8000/posts
```

**2. From Your Local Machine (via SSH Port Forwarding):**

**Step A: Start the SSH Tunnel**
Run this command in a **local terminal** and keep it running. This forwards your local port 8000 to the VM's port 8000.
```bash
ssh -i ~/.ssh/id_ed25519 -L 8000:localhost:8000 zerobyte365@136.110.59.73
```

**Step B: Test the API Locally**
Now, in **another local terminal**, you can run the same `curl` command. It will be securely tunneled to the VM.
```bash
curl -i -H "Accept: application/json" http://127.0.0.1:8000/posts
```

**Step C: Accessing the Database Locally**
Port forwarding does **not** give you direct access to the database file. You must copy it from the VM to your local machine using `scp`.
```bash
# Run this in a local terminal to copy the DB to your current directory
scp -i ~/.ssh/id_ed25519 zerobyte365@136.110.59.73:/home/zerobyte365/laravel-skill-test/database/database.sqlite .
```
> **Important:** You must re-run this `scp` command every time the database on the VM changes to get the latest version.
