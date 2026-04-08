# Notification System

A Laravel 12 REST API that sends notifications when tasks are assigned to users. Built with Events, Listeners, queued Jobs, and Sanctum token authentication.

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm

## Setup Instructions

### 1. Clone and install dependencies

```bash
git clone <repository-url>
cd notification_system

composer install
npm install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

The default configuration uses **SQLite** and a **database queue driver** — no Redis or external services needed.

Confirm your `.env` contains:

```env
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
```

### 3. Run migrations and seed the database

```bash
php artisan migrate
php artisan db:seed
```

The seeder creates two users you can use immediately:

| Name  | Email             | Password |
|-------|-------------------|----------|
| Alice | alice@example.com | password |
| Bob   | bob@example.com   | password |

### 4. Start the development server

Run all services (HTTP server, queue worker, log viewer, Vite) in one command:

```bash
composer run dev
```

Or start them individually:

```bash
php artisan serve          # API at http://localhost:8000
php artisan queue:listen   # Process queued notification jobs
php artisan pail           # Live log viewer
npm run dev                # Vite (only needed for frontend assets)
```

### 5. Start the queue worker

Notifications are delivered asynchronously. The queue worker **must be running**, otherwise notifications will never appear.

```bash
php artisan queue:listen
```

To retry failed jobs:

```bash
php artisan queue:retry all
```

### 6. Run the tests

```bash
php artisan test
```

---

## API Endpoints

All endpoints are prefixed with `/api`. Protected endpoints require a `Bearer` token in the `Authorization` header.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/register` | No | Register a new user |
| POST | `/api/login` | No | Login and receive a token |
| POST | `/api/logout` | Yes | Revoke current token |
| POST | `/api/tasks` | Yes | Create a task and notify the assignee |
| GET | `/api/notifications` | Yes | List your notifications (paginated) |
| POST | `/api/notifications/{id}/read` | Yes | Mark a notification as read |

---

## Example Requests

### Register

```bash
curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "password",
    "password_confirmation": "password"
  }'
```

**Response `201`:**
```json
{
  "user": {
    "id": 3,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "created_at": "2026-04-07T10:00:00.000000Z"
  },
  "token": "3|abc123..."
}
```

---

### Login

```bash
curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "alice@example.com",
    "password": "password"
  }'
```

**Response `200`:**
```json
{
  "user": {
    "id": 1,
    "name": "Alice",
    "email": "alice@example.com",
    "created_at": "2026-04-07T10:00:00.000000Z"
  },
  "token": "1|xyz789..."
}
```

> Use the returned `token` as a `Bearer` token in all subsequent requests.

---

### Create a Task

Assigns a task to a user and triggers an asynchronous notification to the assignee.

```bash
curl -s -X POST http://localhost:8000/api/tasks \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|xyz789..." \
  -d '{
    "title": "Build API",
    "description": "Create REST endpoints",
    "assigned_to": 2
  }'
```

**Body parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Task title (max 255 chars) |
| `description` | string | No | Task description |
| `assigned_to` | integer | Yes | ID of the user to assign the task to |
| `status` | string | No | `pending` (default), `in_progress`, or `completed` |

**Response `201`:**
```json
{
  "data": {
    "id": 1,
    "title": "Build API",
    "description": "Create REST endpoints",
    "assigned_to": {
      "id": 2,
      "name": "Bob",
      "email": "bob@example.com",
      "created_at": "2026-04-07T10:00:00.000000Z"
    },
    "status": "pending",
    "created_at": "2026-04-07T10:05:00.000000Z",
    "updated_at": "2026-04-07T10:05:00.000000Z"
  }
}
```

After the queue worker processes the job, Bob receives a notification:
> *"User Alice assigned you task: Build API"*

**Validation error `422`:**
```json
{
  "message": "The title field is required. (and 1 more error)",
  "errors": {
    "title": ["The title field is required."],
    "assigned_to": ["The assigned to field is required."]
  }
}
```

Attempting to create a task with the same title for the same assignee returns `422`:
```json
{
  "message": "This task has already been assigned to the selected user.",
  "errors": {
    "title": ["This task has already been assigned to the selected user."]
  }
}
```

---

### Retrieve Notifications

Returns paginated notifications for the authenticated user, newest first (15 per page).

```bash
curl -s -X GET "http://localhost:8000/api/notifications?page=1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <bob-token>"
```

**Response `200`:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "task_assigned",
      "message": "User Alice assigned you task: Build API",
      "read_at": null,
      "is_read": false,
      "created_at": "2026-04-07T10:05:01.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/notifications?page=1",
    "last": "http://localhost:8000/api/notifications?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

---

### Mark a Notification as Read

```bash
curl -s -X POST http://localhost:8000/api/notifications/1/read \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <bob-token>"
```

**Response `200`:**
```json
{
  "message": "Notification marked as read."
}
```

Attempting to mark another user's notification returns `403 Forbidden`.

Attempting to mark an already-read notification returns `422`:
```json
{
  "message": "Notification has already been read.",
  "read_at": "2026-04-07T10:05:01.000000Z"
}
```

---

### Logout

```bash
curl -s -X POST http://localhost:8000/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|xyz789..."
```

**Response `200`:**
```json
{
  "message": "Logged out successfully."
}
```

---

## Architecture

```
POST /api/tasks
  → TaskController                        creates Task, dispatches Event
  → TaskAssigned Event                    carries task + assigner
  → SendTaskAssignedNotification Listener dispatches Job
  → CreateTaskNotification Job            ShouldQueue — creates Notification record
  → Notification Model                    persisted to database
```

### Notification types

Defined in `App\Enums\NotificationType`:

| Value | Description |
|-------|-------------|
| `task_assigned` | A task was assigned to the user |
| `task_completed` | A task was marked as completed |
| `task_commented` | A comment was added to a task |
