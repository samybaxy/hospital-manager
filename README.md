# Hospital Manager WordPress Plugin

A WordPress plugin for hospital management with a modern React frontend built with Vite and styled with Tailwind CSS.

## Development Setup

### Prerequisites

- Node.js (v18+)
- Yarn package manager
- WordPress development environment

### Installation

1. Clone this repository into your WordPress plugins directory
2. Navigate to the plugin directory
```bash
cd /path/to/wp-content/plugins/hospital-manager
```
3. Install dependencies using Yarn
```bash
yarn install
```

### Development Workflow

#### Development Mode Toggle

The plugin includes a sophisticated development mode toggle system for administrators and developers:

- **Visual Toggle**: Click the floating button (🔧) in the bottom-right corner
- **Console Commands**: Use browser console commands like `devMode.enable()`, `devMode.disable()`
- **URL Parameters**: Add `?dev_mode=true` or `?dev_mode=false` to any URL
- **Environment Detection**: Automatically detects localhost, development environments

**For detailed usage instructions, see [DEVELOPMENT_MODE_GUIDE.md](./DEVELOPMENT_MODE_GUIDE.md)**

#### Build Commands

You can use either Yarn directly or the provided development script:

#### Using Yarn

To start development with hot reloading:
```bash
yarn dev
```

To build assets for production:
```bash
yarn build
```

To watch for changes and rebuild automatically:
```bash
yarn watch
```

#### Using the Development Script

We've included a convenient script to manage common development tasks:

```bash
# Install dependencies
./dev.sh install

# Start development server
./dev.sh dev

# Build for production
./dev.sh build

# Watch for changes
./dev.sh watch

# Show help
./dev.sh help
```

### WordPress Integration

The plugin uses a shortcode `[hospital_manager]` to render the React application on any WordPress page or post.

## Testing

The PHP test suite (PHPUnit + the WordPress test library) runs against the Local
site's own PHP and MySQL. Start the site in Local, then from the plugin directory:

```bash
bin/test.sh                                  # whole suite
bin/test.sh --testsuite Models               # Models | Services | Controllers
bin/test.sh --filter DataIntegrityTest       # one test class
bin/test.sh --filter test_chat_activity      # one test method
composer test                                # same; pass PHPUnit flags after --:
composer test -- --filter DataIntegrityTest
```

Arguments are passed straight to PHPUnit. The script finds the site's PHP version and
MySQL socket in Local's `sites.json`, puts a `php` shim on `PATH` for the run (WordPress'
test bootstrap shells out to `php`), creates the `wordpress_test` database if needed and
reinstalls the WordPress test library into `/tmp` if it's missing — e.g. after a reboot.

Tests use the separate `wordpress_test` database and roll back after every test, so they
never touch the site's data.

**First-time setup:** install the dev dependencies (PHPUnit etc.):

```bash
bin/local-php.sh $(command -v composer) install
```

**Running other PHP commands:** there may be no system `php`; `bin/local-php.sh` runs the
site's PHP with the right libraries and `php.ini`:

```bash
bin/local-php.sh database/seeders.php clinical
```

Overrides (environment variables): `LOCAL_SITE_ID` (if the site isn't detected), `HM_PHP`
(use a specific PHP binary instead of Local's), `WP_TEST_DB_NAME`, `WP_TEST_DB_USER`,
`WP_TEST_DB_PASS`, `WP_TESTS_DIR`, `WP_CORE_DIR`.

### Writing tests

- Extend `HospitalManager\Tests\TestCase` (fixtures: `createTestPatient()`,
  `createTestDoctor()`, `createTestVisitation()`, `createUserWithRole()`, ...).
- **REST tests hit mocks by default.** Under PHPUnit, `hospital-manager.php` registers
  `tests/Mocks/Api/*` instead of the real controllers. To test production code, register
  the real controller on a fresh server, as `useRealControllers()` in
  `tests/Integration/UserRecordRelationshipTest.php` does.
- **Doctor/patient IDs:** appointments, visitations, lab and radiology records use the
  record IDs (`hm_doctors.ID`, `hm_patients.ID`); chats, notifications, `lab_tech_id` and
  `hm_medical_reports.doctor_id` use WordPress user IDs. Resolve a logged-in user with
  `Doctor::findByUserId()` / `Patient::findByUserId()`. Tests should make the two IDs differ
  so a mix-up can't pass by coincidence.
- Seeders live in `database/seeders/` (lowercase), which the PSR-4 autoloader can't map
  on case-sensitive filesystems — `require_once` them (see
  `tests/Integration/SeededPatientRelationshipsTest.php`).
- Don't run DDL (`TRUNCATE`, `ALTER TABLE`, ...) inside a test: it commits the
  per-test transaction and leaks data into later tests.

## Demo Data

Seed the database (see [database/seeders/README.md](./database/seeders/README.md)):

```bash
bin/local-php.sh database/seeders.php all        # everything (first run)
bin/local-php.sh database/seeders.php clinical   # rebuild clinical data for existing users
```

Demo logins use the password `demo1234`: `demo_administrator`, `demo_doctor`,
`demo_patient`, `demo_lab_tech`, `demo_hospital_nurse`, `demo_hospital_staff`,
`demo_pharmacy_staff`, `demo_inventory_manager`, `demo_developer`
(email `<role>@example.com`, e.g. `doctor@example.com`).

## Release Builds

```bash
bin/build-release.sh             # release/hospital-manager-<version>.zip, for production
bin/build-release.sh --staging   # release/hospital-manager-<version>-staging.zip, for test sites
```

Install either zip from Plugins > Add New > Upload Plugin. Activating creates the tables,
the roles and a "Hospital Manager" page with the app. It needs PHP 8.2 or newer.

### Seeding a staging site

Only the staging zip has seed data. **Never install it on a production site:** it creates
an administrator account whose password is published here.

1. Make sure the site reports a non-production environment. In `wp-config.php`:
   `define('WP_ENVIRONMENT_TYPE', 'staging');`
   (Many hosts set this for staging sites. If the site must stay on the production type,
   use `define('HOSPITAL_MANAGER_ALLOW_SEED', true);` instead.)
2. Go to **Tools > Hospital Manager Seed**, turn on **Seed demo data on this site**, and
   click **Run seed**. If a step fails, reload the page and resume.

Over SSH, this does the same from the plugin folder: `php database/seeders.php all`.
It has no empty-database or environment check, so run it once, on test sites only.

Seeding only runs against empty hospital tables. To start over, set
`hospital_manager_delete_data_on_uninstall` to `1`, delete the plugin and install it again.
The demo WordPress users stay behind, so remove them from Users.

## Project Structure

```
hospital-manager/
├── app/                     # PHP application files
│   ├── Controllers/         # MVC controllers
│   ├── Models/              # MVC models
│   └── Views/               # MVC views
├── assets/                  # Frontend assets
│   ├── css/
│   │   ├── dist/            # Compiled CSS
│   │   └── src/             # Source CSS (with Tailwind)
│   └── js/
│       ├── dist/            # Compiled JS bundle
│       └── src/             # React source files
├── node_modules/            # Node dependencies (not versioned)
├── package.json             # Node dependencies and scripts
├── tailwind.config.js       # Tailwind configuration
├── postcss.config.js        # PostCSS configuration
└── vite.config.js           # Vite bundler configuration
```

## Tailwind CSS

This project uses Tailwind CSS for styling. You can customize the theme in `tailwind.config.js`.

Custom utility classes are defined in `assets/css/src/frontend.css` using the `@layer components` directive.
