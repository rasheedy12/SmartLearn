# SmartLearn

A PHP-based learning management platform for students, instructors, and admins.

## Features
- Student dashboard and course browsing
- Instructor course and student management
- Admin analytics and platform controls
- Course detail views and lesson access
- Authentication flow for login/register/logout
- Responsive UI built with Bootstrap and custom CSS

## Project Structure

```text
SmartLearn/
├── admin/                  # Dashboard and admin-facing pages
├── assets/                 # CSS, JS, and images
│   ├── images/
│   ├── js/
│   └── style.css
├── auth/                   # Authentication handlers
│   ├── handle_login.php
│   ├── handle_logout.php
│   └── handle_register.php
├── database/
│   └── Smartlearn.sql      # Database schema and seed data
├── includes/               # Shared layout and config files
│   ├── db_config.php
│   ├── header.php
│   ├── footer.php
│   ├── dashboard_header.php
│   ├── dashboard_footer.php
│   └── sidebar.php
├── pages/                  # Public-facing pages
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── browse_courses.php
│   ├── course_detail.php
│   └── subscribe.php
├── sections/               # Reusable homepage sections
│   ├── hero.php
│   ├── how_it_works.php
│   ├── powerful_features.php
│   ├── built_for_everyone.php
│   ├── testimonials.php
│   └── cta.php
├── .gitignore             # Optional git ignore file (if added later)
├── index.php              # Entry point redirect
├── README.md
└── LICENSE                # Optional if added later
```

## Requirements
- PHP 7.4+
- MySQL or MariaDB
- Apache or Nginx with PHP support
- Web browser

## Setup
1. Start your local PHP server from the project root:

```bash
php -S localhost:8000
```

2. Open the app in your browser:

```text
http://localhost:8000/
```

3. Import the database schema from:

```text
database/Smartlearn.sql
```

4. Update database credentials in:

```text
includes/db_config.php
```

## Default Database Configuration
The project currently uses:

```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'Smartlearn');
```

Adjust these values if your local MySQL setup differs.

## Notes
- The project uses relative paths and a folder-based structure for easier maintenance.
- Some pages are kept as redirect wrappers to preserve compatibility while using the new structure.
- If needed, update paths in `includes/header.php`, `includes/dashboard_header.php`, and `includes/sidebar.php` when moving files again.

## License
This project is currently distributed without a formal license file.

