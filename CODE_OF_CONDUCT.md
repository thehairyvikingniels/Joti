# Jotify — Code of Conduct (Coding Standards)

> This document defines the coding standards, conventions, and architectural rules for the Jotify project.
> All contributors (human and AI) must follow these rules when writing or modifying code.

---

## 1. Language Rules

### 1.1 Code Language: English
All code constructs **must** be written in English:
- Variable names, function names, class names, constants
- Database table names and column names
- CSS class names and HTML element IDs
- JavaScript function names and variable names
- Comments and documentation
- Git commit messages

### 1.2 Exceptions: Dutch Page URLs
Page filenames that form user-facing URLs may remain in Dutch to preserve SEO and user familiarity:
- ✅ `vossen.php`, `kaarten.php`, `groepen.php`, `opdrachten.php`, `hints.php`, `nieuws.php`
- ✅ `instellingen.php`, `autos.php`, `punten.php`, `voslocaties.php`

New pages should follow the same convention: Dutch if the page name is a user-facing concept, English for technical/API pages (`kiosk.php`, `login.php`, `offline.php`).

### 1.3 UI Text: Dutch
All user-facing text (labels, buttons, error messages, tooltips) remains in Dutch as the application serves a Dutch audience.

---

## 2. Naming Conventions

### 2.1 PHP
| Element | Convention | Example |
|---|---|---|
| Variables | `$snake_case` | `$first_name`, `$target_page`, `$fox_count` |
| Functions | `camelCase` | `getClientIP()`, `generateToken()`, `convertRdToWgs()` |
| Constants | `UPPER_SNAKE_CASE` | `MAX_RETRY_COUNT`, `DEFAULT_REFRESH_INTERVAL` |
| File names (pages) | `lowercase.php` (Dutch OK for pages) | `vossen.php`, `home.php` |
| File names (helpers) | `snake_case.php` | `kiosk_helper.php`, `whiteboard_helper.php` |

### 2.2 JavaScript
| Element | Convention | Example |
|---|---|---|
| Variables | `camelCase` with `const`/`let` (never `var`) | `const targetPage`, `let refreshInterval` |
| Functions | `camelCase` | `checkKioskStatus()`, `resetActivity()` |
| File names | `snake_case.js` | `kiosk_controller.js`, `number_plate.js`, `push.js` |
| DOM IDs | `kebab-case` | `#token-display`, `#progress-fill` |
| CSS classes | Tailwind utilities or `kebab-case` custom classes | `.theme-card`, `.progress-bar-fill` |

### 2.3 Database
| Element | Convention | Example |
|---|---|---|
| Table names | `PascalCase_With_Underscores` | `Users`, `Fox_Locations`, `Kiosk_Accounts` |
| Column names | `snake_case` | `first_name`, `last_seen`, `target_page` |
| Primary keys | `id` (auto-increment) | `id` |
| Foreign keys | `{referenced_table}_id` | `user_id`, `car_id` |

---

## 3. File Structure & Responsibilities

### 3.1 Directory Layout
```
/var/www/Joti/
├── admin/              # Admin-only pages (priv >= 2)
│   ├── *.php           # Admin page views
│   └── *_helper.php    # Admin AJAX/POST handlers
├── api/                # REST API endpoints (JSON responses)
├── cron/               # Background scheduled tasks
├── DB/                 # Database schema and migrations
├── includes/           # Shared PHP components and utilities
│   ├── auth.php        # Session bootstrap, user loading, privilege checks
│   ├── helpers.php     # General utility functions (time formatting, IP, tokens)
│   ├── db.php          # Database helper functions (centralized queries)
│   ├── globals.php     # Global constants and site settings loader
│   ├── sidebar.php     # Navigation sidebar component
│   ├── topbar.php      # Top navigation bar component
│   ├── footer.php      # Footer component (view only, no function definitions)
│   └── theme.php       # Theme configuration and CSS variable injection
├── js/                 # Standalone JavaScript files
├── media/              # Static assets (images, audio, icons)
│   ├── icons/          # Map pins and app icons
│   └── profiles/       # User profile avatars
├── *.php               # Public page views
└── *_helper.php        # Public AJAX/POST handlers
```

### 3.2 File Responsibility Rules

**A file should have ONE responsibility.** Do not mix concerns.

#### View files (`*.php` pages)
- **MAY**: Render HTML, include components, contain page-specific `<script>` initialization
- **MAY NOT**: Define reusable functions, contain AJAX/POST processing logic
- Page-specific inline `<script>` is allowed **only** for simple initialization (DOM ready handlers, variable passing from PHP to JS). Larger scripts must be in separate `.js` files.

#### Helper files (`*_helper.php`)
- **MAY**: Process POST/AJAX requests, validate input, execute database queries, return JSON
- **MAY NOT**: Render full HTML pages, define reusable utility functions

#### Include files (`includes/*.php`)
- **MAY**: Define reusable functions, load global state, render shared UI components
- **MAY NOT**: Process form submissions, handle AJAX endpoints

#### UI components (`includes/sidebar.php`, `includes/topbar.php`, `includes/footer.php`)
- **MAY**: Render HTML, read global variables (`$site_settings`, session data)
- **MAY NOT**: Define functions, execute database queries, process form data

#### `includes/auth.php` (Bootstrap)
- **MUST**: Start session (with defensive check), load `dblogin.php`, fetch user data, load site settings
- **MUST**: Set standard variables: `$user_id`, `$user_name`, `$privilege`, `$site_settings`
- **MUST**: Handle kiosk session fallbacks
- Every page **MUST** `require_once` this file as its first include

#### `includes/helpers.php` (Utilities)
- **MAY**: Define stateless utility functions (`getClientIP()`, `generateToken()`, `formatTime()`, `convertRdToWgs()`)
- **MAY NOT**: Access `$_SESSION`, execute database queries, render HTML

#### `includes/db.php` (Data Access)
- **MAY**: Define functions that execute database queries and return data
- **MAY NOT**: Render HTML, access `$_SESSION` directly (accept parameters instead)

---

## 4. PHP Style Rules

### 4.1 Indentation
- **4 spaces** for all indentation. Never tabs.

### 4.2 Quotes
- **Single quotes** by default: `'string'`
- **Double quotes** only when using string interpolation: `"Hello $name"`
- SQL queries use **double quotes** for the outer string: `"SELECT * FROM Users WHERE id = ?"`

### 4.3 Includes
- **`require_once`** for critical dependencies (auth, database, helpers) — script must fail if missing
- **`include_once`** for optional UI components (footer, sidebar) — page can degrade gracefully
- Always use `require_once` / `include_once` (never bare `require` / `include`)

### 4.4 Session Handling
- Always use defensive session start:
  ```php
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  ```
- This logic lives in `includes/auth.php` — individual pages should not call `session_start()` directly.

### 4.5 Error Handling
- **API/AJAX endpoints**: Return proper HTTP status codes with JSON: `http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit();`
- **Page redirects**: Use `header('Location: ...');` with `exit();`
- **Database errors**: Log with `error_log()`, never expose to users
- **Never** use bare `echo "Error: ..."` or `die("error message")` in production views

---

## 5. SQL Rules

### 5.1 Always Use Prepared Statements
```php
// ✅ Correct
$stmt = $conn->prepare('SELECT * FROM Users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

// ❌ Never do this
$result = $conn->query("SELECT * FROM Users WHERE id = $id");
$result = mysqli_query($conn, $sql);
```

### 5.2 OOP Style Only
Always use the object-oriented `mysqli` interface (`$conn->prepare()`), never the procedural interface (`mysqli_query()`).

### 5.3 Query Formatting
- SQL keywords in UPPERCASE: `SELECT`, `FROM`, `WHERE`, `INSERT INTO`, `UPDATE`, `SET`, `JOIN`
- Table and column names as-is (PascalCase tables, snake_case columns)

---

## 6. JavaScript Rules

### 6.1 Modern Syntax Only
- **`const`** by default, **`let`** when reassignment is needed, **never `var`**
- **`fetch()`** for HTTP requests, never `XMLHttpRequest` or `ActiveXObject`
- **Arrow functions** for callbacks: `.then(response => response.json())`
- **Template literals** for string building: `` `Hello ${name}` ``

### 6.2 No Legacy Browser Support
Do not include IE6/IE5/IE7 fallbacks. The target browsers are modern evergreen browsers (Chrome, Firefox, Safari, Edge).

### 6.3 Script Organization
- **Shared scripts** go in `js/` or `includes/` as separate `.js` files
- **Page-specific initialization** may be inline `<script>` at the bottom of the page, but should be minimal (< 30 lines)
- Larger page-specific scripts (> 30 lines) should be extracted to `js/{page_name}.js`

---

## 7. Comment Rules

### 7.1 Language
All comments must be in **English**.

### 7.2 When to Comment

#### Must have comments:
- **Function docblocks**: Every function gets a PHPDoc or JSDoc block describing purpose, parameters, and return value
  ```php
  /**
   * Convert RD (Rijksdriehoekstelsel) coordinates to WGS84 lat/lon.
   *
   * @param float $rd_x  RD X coordinate
   * @param float $rd_y  RD Y coordinate
   * @return array{lat: float, lon: float}
   */
  function convertRdToWgs(float $rd_x, float $rd_y): array
  ```
- **Complex logic**: Algorithms, non-obvious calculations, business rules
- **Workarounds**: Any hack or non-standard approach with a brief explanation of why
- **File headers**: Each PHP file should have a one-line comment describing its purpose

#### Do not comment:
- Self-explanatory variable assignments: `$count = 0;`
- Simple loops and conditionals with clear intent
- Standard boilerplate (session start, database connection)
- Closing braces (no `// end if`, `// end foreach`)

---

## 8. Git Conventions

### 8.1 Commit Messages
Follow [Conventional Commits](https://www.conventionalcommits.org/):
```
feat: add kiosk account management page
fix: resolve kaarten.php redirect for kiosk users
refactor: centralize user authentication in auth.php
style: standardize indentation to 4 spaces
docs: add code of conduct
chore: remove legacy IE fallback code
```

### 8.2 Branch Naming
- `feature/{description}` for new features
- `fix/{description}` for bug fixes
- `refactor/{description}` for code improvements

### 8.3 No Direct Commits
Never commit or push to `main` or `dev` directly without explicit user approval.

---

## 9. Security Rules

- **Never** expose database credentials, API keys, or secrets in source code (use `dblogin.php` which is `.gitignore`d)
- **Always** use prepared statements for SQL queries
- **Always** escape output with `htmlspecialchars()` when rendering user input in HTML
- **Always** validate and sanitize POST/GET input server-side
- **Never** trust client-side validation alone
