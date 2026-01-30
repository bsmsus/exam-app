# Exam Management System

A containerized Exam Management System built with **Symfony 7 (PHP 8.4)**, **PostgreSQL 16**, and **React 19 (TypeScript)**.

Admins manage exams with configurable attempt limits and cooldown periods. Students take exams under strict constraints enforced by the backend.

---

## Prerequisites

- **Docker** (v20.10+)
- **Docker Compose** (v2.0+)

No local PHP, Composer, or Node.js installation required.

---

## Quick Start

### 1. Start the Application

```bash
docker-compose up -d --build
```

### 2. Run Database Migrations

```bash
docker-compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

### 3. Access the Application

| Service      | URL                               |
| ------------ | --------------------------------- |
| **Frontend** | http://localhost:3005             |
| **API**      | http://localhost:8000             |
| **Swagger**  | http://localhost:8000/swagger-ui/ |

---

## Run Tests

### Backend Tests (PHPUnit - API + Integration + Unit)

```bash
# Using Composer script
docker-compose exec api composer test

# Or directly with PHPUnit
docker-compose exec api env APP_ENV=test php bin/phpunit

# Run specific test class
docker-compose exec api env APP_ENV=test php bin/phpunit --filter AdminCreateOrUpdateExamTest

# Run with detailed output
docker-compose exec api env APP_ENV=test php bin/phpunit --display-notices
```

### Frontend Tests (Vitest + React Testing Library)

```bash
# Run tests in container
docker-compose exec ui npm test

# Or locally if you have Node.js:
cd ui && npm install && npm test
```

**Note:** All tests are automated. Admin and Student authentication is handled via Bearer tokens in test environment.

---

## Stop the Application

```bash
docker-compose down
```

To also remove volumes (database data):

```bash
docker-compose down -v
```

---

## Architecture

### Tech Stack

| Layer    | Technology                                       |
| -------- | ------------------------------------------------ |
| Backend  | Symfony 7.4.5, PHP 8.4, Doctrine ORM             |
| Frontend | React 19, TypeScript, Vite 7                     |
| Database | PostgreSQL 16                                    |
| Testing  | PHPUnit 12 (API), Vitest + React Testing Library |
| API Docs | OpenAPI 3.0 / Swagger                            |

---

## API Endpoints

### Authentication Endpoints

| Method | Endpoint                 | Description                  |
| ------ | ------------------------ | ---------------------------- |
| POST   | `/auth/admin/register`   | Register a new admin account |
| POST   | `/auth/admin/login`      | Admin login                  |
| POST   | `/auth/admin/refresh`    | Refresh admin access token   |
| POST   | `/auth/admin/logout`     | Admin logout                 |
| POST   | `/auth/student/register` | Register a new student       |
| POST   | `/auth/student/login`    | Student login                |
| POST   | `/auth/student/refresh`  | Refresh student access token |
| POST   | `/auth/student/logout`   | Student logout               |

### Admin Endpoints

| Method | Endpoint                         | Description                          | Auth  |
| ------ | -------------------------------- | ------------------------------------ | ----- |
| GET    | `/admin/exams`                   | List all exams                       | Admin |
| POST   | `/admin/exams`                   | Create a new exam                    | Admin |
| GET    | `/admin/exams/{examId}`          | Get exam details                     | Admin |
| PUT    | `/admin/exams/{examId}`          | Update exam (resets all attempts)    | Admin |
| GET    | `/admin/exams/{examId}/attempts` | View all student attempts (UTC time) | Admin |

### Student Endpoints

| Method | Endpoint                                  | Description                           | Auth    |
| ------ | ----------------------------------------- | ------------------------------------- | ------- |
| GET    | `/student/exams`                          | List all available exams              | Student |
| GET    | `/student/exams/{examId}`                 | Get exam details & eligibility status | Student |
| POST   | `/student/exams/{examId}/start`           | Start a new attempt                   | Student |
| GET    | `/student/exams/{examId}/current-attempt` | Get current in-progress attempt       | Student |
| POST   | `/student/attempts/{attemptId}/submit`    | Submit/complete an attempt            | Student |
| GET    | `/student/attempts`                       | View own attempt history              | Student |

---

## Business Rules Implemented

### Exam Management

- **Exam ID**: Auto-generated UUID v4
- **Title**: Supports any language and special characters
- **Max Attempts**: Integer between 1-1000
- **Cooldown Time**: Minutes between attempts (0-525,600)

### Critical Rules

1. **Update Resets Attempts**: When an admin updates an exam, ALL student attempt history is deleted (transactional) to ensure fairness and data consistency.

2. **Attempt Restrictions**:
   - Students cannot start a new attempt if they've reached max attempts
   - Students cannot start a new attempt during cooldown period
   - Students cannot start a new attempt if one is already in progress

3. **State Strictness**: In-progress attempts can ONLY be submitted. No state jumping allowed.

4. **Timezone Handling**:
   - Admin views: All times displayed in UTC
   - Student views: Times returned in ISO format for client-side timezone conversion (browser local time)

5. **Authentication**:
   - JWT Bearer tokens required for protected endpoints
   - Separate auth flows for admin and student roles
   - Automatic token refresh on 401 response
   - Refresh tokens stored securely for persistent sessions

---

## Validation

### Create/Update Exam Request

```json
{
  "title": "string (required)",
  "maxAttempts": "integer 1-1000 (required)",
  "cooldownMinutes": "integer 0-525600 (required)"
}
```

### Error Responses

| Code | Description                                                  |
| ---- | ------------------------------------------------------------ |
| 400  | Validation error (invalid input)                             |
| 404  | Resource not found (exam/attempt)                            |
| 409  | Conflict (cooldown active, no attempts, already in progress) |

---

## Test Coverage

### Backend Tests

| Test File                         | Type        | Coverage                                      |
| --------------------------------- | ----------- | --------------------------------------------- |
| `AdminAuthTest`                   | API         | Admin register/login/refresh/logout flows     |
| `StudentAuthTest`                 | API         | Student register/login/refresh/logout flows   |
| `AdminCreateOrUpdateExamTest`     | API         | Create exam, update resets attempts           |
| `AdminExamIntegrationTest`        | API         | Admin exam endpoints and attempt history      |
| `StudentStartAttemptTest`         | API         | Start attempt, cooldown blocking, validation  |
| `StudentExamIntegrationTest`      | API         | Student exam endpoints and attempt management |
| `UpdateExamResetsAttemptsTest`    | Integration | Service-level attempt reset functionality     |
| `ValidationExceptionListenerTest` | Unit        | Exception handling and error responses        |

### Frontend Tests

| Test File                 | Coverage                                             |
| ------------------------- | ---------------------------------------------------- |
| `App.test.tsx`            | Login flow, role-based routing, logout               |
| `AdminExam.test.tsx`      | Admin exam creation, update, attempt loading         |
| `StudentExam.test.tsx`    | Student exam dashboard, start/submit attempts        |
| `Login.test.tsx`          | Authentication forms, role selection                 |
| `ProtectedRoute.test.tsx` | Route protection and authorization                   |
| `api.test.ts`             | API utility functions, error handling, token refresh |

---

## Services (Docker)

| Service | Description      | Internal Port | External Port |
| ------- | ---------------- | ------------- | ------------- |
| api     | Symfony REST API | 8000          | 8000          |
| db      | PostgreSQL 16    | 5432          | 55432         |
| ui      | React Frontend   | 80            | 3005          |
| swagger | Swagger UI       | 8080          | -             |

---

## Development Notes

- Application runs in **dev mode** for assessment/demo purposes
- Swagger/OpenAPI documentation enabled at `/api/docs`
- CORS configured for local development
- Database migrations use Doctrine Migrations Bundle

---

## Design Decisions

### Authentication & Security

- **JWT Bearer Tokens**: Secure token-based authentication using Firebase PHP-JWT library
- **Role-Based Access Control**: Separate auth flows and endpoints for admin and student roles
- **Token Refresh Mechanism**: Automatic token refresh on 401 response for seamless user experience
- **Refresh Tokens**: Stored securely for persistent sessions without re-authentication
- **Test Authentication**: Bearer tokens used for API testing without user registration

### Clean Architecture

- **Domain Layer**: Pure PHP value objects and business rules (no framework dependencies)
- **Application Layer**: Services orchestrating business logic (UpdateExamService, JwtService, PasswordService)
- **Infrastructure Layer**: Doctrine entities and persistence, event listeners for exception handling
- **Event Listeners**: ValidationExceptionListener and InvalidUuidExceptionListener for centralized error handling

### State Management (Frontend)

- **Redux Toolkit**: Centralized state management for auth, admin, and student stores
- **Async Thunks**: Async operations for API calls with automatic loading/error states
- **Persistent Auth**: AuthSlice persists tokens to localStorage for session continuity

### Routing & UI

- **React Router v7**: Client-side routing with protected routes
- **ProtectedRoute Component**: Role-based route protection with automatic redirects
- **Login Component**: Unified authentication UI with role toggle (admin/student)
- **Dynamic UI**: Conditional rendering based on user role and auth state

### Strict Typing

- PHP 8.4 with `declare(strict_types=1)` throughout
- TypeScript in frontend for type safety
- Request DTOs with validation constraints for API inputs

### Validation

- Backend validation using Symfony Validator component
- Request DTOs with validation constraints
- Client-side validation in React components
- Centralized error handling via event listeners

### Testing Strategy

- **Unit Tests**: Domain logic and exception handling
- **Integration Tests**: Service layer with real database
- **API Tests**: Full HTTP request/response cycle with authenticated users
- **Frontend Tests**: Component testing with React Testing Library
- **Test Environment**: Automatic test database setup via migrations

---

## Development Workflow

### Quick Commands Reference

```bash
# Start fresh
docker-compose down && docker-compose up -d --build

# Run all tests
docker-compose exec api composer test          # Backend tests
docker-compose exec ui npm test                # Frontend tests

# Database operations
docker-compose exec db psql -U exam exam       # Connect to database
docker-compose exec api php bin/console doctrine:migrations:migrate  # Run migrations

# Rebuild containers
docker-compose build --no-cache                # Full rebuild
docker-compose build --no-cache api            # Rebuild API only
docker-compose build ui                        # Rebuild UI only

# View logs
docker-compose logs api                        # API logs
docker-compose logs api -f                     # Follow API logs
docker-compose logs db                         # Database logs

# Container access
docker-compose exec api sh                     # Shell into API container
docker-compose exec db sh                      # Shell into DB container
docker-compose exec ui sh                      # Shell into UI container

# Cleanup
docker-compose down                            # Stop all services
docker-compose down -v                         # Stop and remove volumes (database data)
```

---

## Troubleshooting

### Clear Application Cache

```bash
docker-compose exec api php bin/console cache:clear
```

### Rebuild Database from Scratch

```bash
# Remove volumes and rebuild
docker-compose down -v
docker-compose up -d --build
docker-compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

### Database Connection Issues

```bash
# Check if database is ready
docker-compose exec db pg_isready

# View database logs
docker-compose logs db
```

### API Not Responding

```bash
# Check API logs
docker-compose logs api

# Restart API container
docker-compose restart api
```

---

## Author

Rahul Meshram

---

## License

Proprietary - Assessment Project
