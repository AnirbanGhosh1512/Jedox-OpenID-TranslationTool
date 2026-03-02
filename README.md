# Jedox OpenID Translation Tool

A full-stack localization management system built with a custom OpenID Connect Identity Provider, a REST API backend, and a PHP frontend. Manage string identifiers (SIDs) and their translations across multiple languages with secure authentication.

---

## Table of Contents

- [Architecture](#architecture)
- [Prerequisites](#prerequisites)
- [Getting Started](#getting-started)
- [Services](#services)
- [Usage](#usage)
- [Project Structure](#project-structure)
- [Running Tests](#running-tests)
- [Development Notes](#development-notes)

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                    Browser                          │
└──────────┬──────────────────────────┬───────────────┘
           │                          │
     localhost:8080             localhost:8090
           │                          │
┌──────────▼──────────┐   ┌──────────▼──────────┐
│   PHP Frontend      │   │  OpenIddict IdP     │
│   (Apache + PHP)    │──▶│  (ASP.NET Core 8)   │
│   Container: tt_ui  │   │  Container: tt_idp  │
└──────────┬──────────┘   └─────────────────────┘
           │
     localhost:8091 (internal: api:8091)
           │
┌──────────▼──────────┐   ┌─────────────────────┐
│   REST API          │──▶│   PostgreSQL 16      │
│   (ASP.NET Core 8)  │   │   Container: tt_db   │
│   Container: tt_api │   │   Port: 5432         │
└─────────────────────┘   └─────────────────────┘
```

**Authentication Flow:**
1. Browser clicks "Sign in" → redirected to IdP at `localhost:8090`
2. User logs in → IdP issues authorization code
3. PHP backend exchanges code for access token (server-to-server via `idp:8090`)
4. Access token attached to all API requests as Bearer token
5. API validates token against IdP's JWKS endpoint

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) 
- [.NET 8 SDK](https://dotnet.microsoft.com/download/dotnet/8.0) (for running C# tests locally)
- [Git](https://git-scm.com/)

---

## Getting Started

### 1. Clone the repository

```bash
git clone https://github.com/your-org/Jedox-OpenID-TranslationTool.git
cd Jedox-OpenID-TranslationTool
```

### 2. Start all services

```bash
docker compose up --build
```

This starts 4 containers: PostgreSQL, IdP, API, and the PHP frontend. The database is initialized automatically with the schema from `db/init.sql`.

### 3. Open the app

Navigate to [http://localhost:8080](http://localhost:8080) in your browser.

### 4. Sign in

Use one of the built-in test accounts:

| Username | Password  | Role  |
|----------|-----------|-------|
| `admin`  | `admin123`| Admin |
| `user`   | `user123` | User  |

---

## Services

| Service     | URL                          | Description                        |
|-------------|------------------------------|------------------------------------|
| PHP UI      | http://localhost:8080        | Main web interface                 |
| IdP         | http://localhost:8090        | OpenIddict identity provider       |
| REST API    | http://localhost:8091        | Translation CRUD API               |
| API Docs    | http://localhost:8091/swagger| Swagger UI                         |
| PostgreSQL  | localhost:5432               | Database (user: postgres/postgres) |

---

## Usage

### Viewing Translations

1. Select a language from the dropdown (top right of the translations table)
2. Only SIDs that have a translation in the selected language are shown
3. The **Default (en-US)** column shows the English fallback text for non-English views

### Creating a SID

1. Select the target language from the dropdown
2. Click **+ New SID**
3. Enter the SID key (e.g. `app.greeting`)
4. Enter the text for the selected language
5. Optionally add a translation in another language
6. Click **Create SID**

> A SID created in German will only appear in the German view. It will not appear in other languages until a translation is added for them.

### Editing a Translation

1. Double-click any row in the translations table
2. Select the language you want to edit from the dropdown
3. Enter or update the translation text
4. Click **Save**

### Deleting a SID

1. Double-click a row to open the edit page
2. Scroll to the **Danger Zone** section
3. Click **Delete SID** and confirm

---

## Project Structure

```
Jedox-OpenID-TranslationTool/
├── Backend/                        # ASP.NET Core 8 REST API
│   ├── Controllers/
│   │   └── SidsController.cs       # CRUD endpoints for SIDs and translations
│   ├── DTOs/                       # Request/response data transfer objects
│   ├── Models/                     # EF Core entity models
│   ├── AppDbContext.cs             # PostgreSQL database context
│   ├── Program.cs                  # App startup, auth, DI configuration
│   └── TranslationApi.csproj
│
├── IDP/                            # ASP.NET Core 8 OpenIddict Identity Provider
│   ├── Controllers/
│   │   ├── AccountController.cs    # Login/logout endpoints
│   │   └── AuthorizationController.cs # OIDC authorize/token/userinfo endpoints
│   ├── Models/
│   │   └── LoginViewModel.cs
│   ├── Views/
│   │   ├── Account/Login.cshtml    # Login page
│   │   └── Shared/_Layout.cshtml  # Shared layout
│   ├── AppDbContext.cs             # In-memory DB for OpenIddict entities
│   ├── SeedWorker.cs               # Registers OIDC clients on startup
│   ├── Program.cs                  # OpenIddict server configuration
│   └── IDP.csproj
│
├── Frontend/                       # PHP 8.3 + Apache web UI
│   ├── src/
│   │   ├── config.php              # OIDC and API configuration
│   │   └── oidc.php                # OIDC helpers (PKCE, token exchange, API calls)
│   ├── index.php                   # Login page
│   ├── callback.php                # OIDC callback handler
│   ├── view.php                    # Translations list page
│   ├── edit.php                    # Create/edit/delete SID page
│   ├── logout.php                  # Session destroy and redirect
│   ├── apache.conf                 # Apache virtual host config
│   └── Dockerfile
│
├── db/
│   └── init.sql                    # PostgreSQL schema initialization
│
├── tests/
│   ├── Backend.Tests/              # xUnit tests for REST API
│   │   ├── SidsControllerTests.cs  # 15 unit tests
│   │   ├── TestDbHelper.cs         # In-memory DB factory with seed data
│   │   └── Backend.Tests.csproj
│   │
│   ├── Idp.Tests/                  # xUnit tests for IdP
│   │   ├── AccountControllerTests.cs # 13 unit tests
│   │   ├── SeedWorkerTests.cs      # 9 unit tests
│   │   └── Idp.Tests.csproj
│   │
│   └── PhpUI.Tests/                # PHPUnit tests for PHP frontend
│       ├── tests/Unit/
│       │   ├── OidcHelperTest.php  # 32 unit tests
│       │   ├── SidLogicTest.php    # 25 unit tests
│       │   ├── ViewPageTest.php    # 22 unit tests
│       │   ├── EditPageTest.php    # 28 unit tests
│       │   └── IndexPageTest.php   # 14 unit tests
│       ├── composer.json
│       └── phpunit.xml
│
├── docker-compose.yml
├── Jedox-OpenID-TranslationTool.sln
└── README.md
```

---

## Running Tests

### C# Tests (Backend + IdP)

```bash
# Run all C# tests from the solution root
dotnet test

# Run only Backend tests
dotnet test tests/Backend.Tests

# Run only IdP tests
dotnet test tests/Idp.Tests

# Run with detailed output
dotnet test --logger "console;verbosity=normal"
```

### PHP Tests (Frontend)

PHP tests run inside Docker — no local PHP or Composer installation required.

```bash
# Install dependencies (first time only)
docker run --rm -v "$(pwd)/tests/PhpUI.Tests:/app" -w /app \
  composer:latest install

# Run all unit tests
docker run --rm -v "$(pwd)/tests/PhpUI.Tests:/app" -w /app \
  php:8.3-cli vendor/bin/phpunit tests/Unit
```

### Test Coverage Summary

| Project              | File                        | Tests | Type        |
|----------------------|-----------------------------|-------|-------------|
| Backend.Tests        | SidsControllerTests.cs      | 15    | Unit        |
| Idp.Tests            | AccountControllerTests.cs   | 13    | Unit        |
| Idp.Tests            | SeedWorkerTests.cs          | 9     | Unit        |
| PhpUI.Tests          | OidcHelperTest.php          | 32    | Unit        |
| PhpUI.Tests          | SidLogicTest.php            | 25    | Unit        |
| PhpUI.Tests          | ViewPageTest.php            | 22    | Unit        |
| PhpUI.Tests          | EditPageTest.php            | 28    | Unit        |
| PhpUI.Tests          | IndexPageTest.php           | 14    | Unit        |
| **Total**            |                             | **158**|            |

---

## Development Notes

### Resetting the Database

To wipe all data and re-run the init script:

```bash
docker compose down -v
docker compose up --build
```

### Rebuilding a Single Service

```bash
docker compose up --build api
docker compose up --build idp
docker compose up --build php-ui
```

### Viewing Logs

```bash
docker compose logs -f          # all services
docker compose logs -f api      # API only
docker compose logs -f idp      # IdP only
docker compose logs -f php-ui   # Frontend only
```

### OIDC Configuration

The IdP uses HTTP (not HTTPS) for local development. The issuer is locked to `http://localhost:8090` so tokens are valid whether requests come from the browser or from inside Docker containers.

The PHP frontend uses two different URLs for the IdP:
- **Browser redirects** (authorize, logout) → `http://localhost:8090` 
- **Server-side calls** (token exchange, userinfo) → `http://idp:8090`

### Adding a New Language

Add the language code and label to the `$languages` array in `Frontend/edit.php` and `Frontend/view.php`:

```php
$languages = [
    'en-US' => '🇺🇸 English (US)',
    'de-DE' => '🇩🇪 German',
    'fr-FR' => '🇫🇷 French',
    // add new language here
    'nl-NL' => '🇳🇱 Dutch',
];
```

### API Endpoints

| Method | Endpoint                                      | Description                        |
|--------|-----------------------------------------------|------------------------------------|
| GET    | `/api/sids`                                   | List all SIDs                      |
| GET    | `/api/sids/{sid}`                             | Get SID with all translations      |
| GET    | `/api/sids/view?lang={langId}`                | Get SIDs filtered by language      |
| POST   | `/api/sids`                                   | Create a new SID                   |
| PUT    | `/api/sids/{sid}/translations/{langId}`       | Add or update a translation        |
| DELETE | `/api/sids/{sid}`                             | Delete a SID and all translations  |

All endpoints require a valid Bearer token in the `Authorization` header.
