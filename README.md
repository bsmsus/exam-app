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
docker-compose exec api php bin/phpunit
```

### Frontend Tests (Vitest + React Testing Library)

```bash
docker-compose exec ui npm test
```

Or run locally if you have Node.js:

```bash
cd ui && npm install && npm test
```

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

### Project Structure

```
exam-app/
├── api/                          # Symfony Backend
│   ├── src/
│   │   ├── Application/          # Application services (business logic)
│   │   ├── Controller/
│   │   │   ├── Admin/            # Admin endpoints
│   │   │   └── Student/          # Student endpoints
│   │   ├── Domain/               # Domain models & rules
│   │   │   ├── Attempt/          # Attempt value objects
│   │   │   ├── Exam/             # Exam value objects
│   │   │   └── Rules/            # Business rules (ExamRules)
│   │   ├── Http/                 # Request DTOs
│   │   └── Infrastructure/       # Doctrine entities
│   ├── tests/
│   │   ├── Api/                  # API integration tests
│   │   ├── Domain/               # Unit tests
│   │   └── Integration/          # Service integration tests
│   └── migrations/               # Database migrations
├── ui/                           # React Frontend
│   ├── src/
│   │   ├── admin/                # Admin components
│   │   ├── student/              # Student components
│   │   └── test/                 # Test setup
│   └── Dockerfile
└── docker-compose.yml
```

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

### Admin Endpoints

| Method | Endpoint                         | Description                          |
| ------ | -------------------------------- | ------------------------------------ |
| POST   | `/admin/exams`                   | Create a new exam                    |
| PUT    | `/admin/exams/{examId}`          | Update exam (resets all attempts)    |
| GET    | `/admin/exams/{examId}/attempts` | View all student attempts (UTC time) |

### Student Endpoints

| Method | Endpoint                               | Description                           |
| ------ | -------------------------------------- | ------------------------------------- |
| GET    | `/student/exams/{examId}`              | Get exam details & eligibility status |
| POST   | `/student/exams/{examId}/start`        | Start a new attempt                   |
| POST   | `/student/attempts/{attemptId}/submit` | Submit/complete an attempt            |
| GET    | `/student/attempts`                    | View own attempt history              |

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
   - Student views: Times returned in ISO format for client-side timezone conversion

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

| Test File                      | Type        | Coverage                            |
| ------------------------------ | ----------- | ----------------------------------- |
| `AdminCreateOrUpdateExamTest`  | API         | Create exam, update resets attempts |
| `StudentStartAttemptTest`      | API         | Start attempt, cooldown blocking    |
| `ExamRulesTest`                | Unit        | Domain rules (cooldown logic)       |
| `UpdateExamResetsAttemptsTest` | Integration | Service-level attempt reset         |

### Frontend Tests

| Test File      | Coverage                                 |
| -------------- | ---------------------------------------- |
| `App.test.tsx` | View switching between Admin and Student |

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

### Clean Architecture

- **Domain Layer**: Pure PHP value objects and business rules (no framework dependencies)
- **Application Layer**: Services orchestrating business logic
- **Infrastructure Layer**: Doctrine entities and persistence

### Strict Typing

- PHP 8.4 with `declare(strict_types=1)` throughout
- TypeScript in frontend for type safety

### Validation

- Backend validation using Symfony Validator component
- Request DTOs with validation constraints
- Client-side validation in React components

### Testing Strategy

- **Unit Tests**: Domain logic (ExamRules)
- **Integration Tests**: Service layer with real database
- **API Tests**: Full HTTP request/response cycle

---

## Troubleshooting

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

### Clear Cache

```bash
docker-compose exec api php bin/console cache:clear
```

---

## Author

Rahul Meshram

---

## License

Proprietary - Assessment Project
