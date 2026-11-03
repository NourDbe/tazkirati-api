# Tazkirati API

A RESTful API for an event ticketing and booking platform built with Laravel. The system supports event discovery, ticket reservations, simulated payments, booking cancellation, ticket generation, and asynchronous notifications.

## Tech Stack

- PHP 8.3
- Laravel 13
- SQLite (development database)
- Laravel Sanctum (API authentication)
- Laravel Queues and Jobs
- Laravel Cache
- Polymorphic Eloquent Relationships
- PHPUnit / Laravel Testing

> **Infrastructure note:** Redis and Laravel Horizon integration are planned but have not yet been installed and verified in the current development environment.

## Features

- Browse published events and view event details.
- Filter and paginate event listings.
- Check remaining ticket quantities.
- Create temporary ticket reservations.
- Automatically expire unpaid reservations after 10 minutes.
- Simulate payment confirmation.
- Generate tickets with unique UUID codes.
- Cancel pending reservations.
- Allow organizers to cancel their own events.
- Send booking confirmation and event cancellation notifications asynchronously.
- Send bulk announcements in chunks.
- Record notification delivery attempts and statuses.
- Cache upcoming events and remaining ticket quantities.
- Manage media through polymorphic relationships.
- Return consistent JSON API responses and Arabic validation messages.

## Requirements

- PHP 8.3 or later
- Composer
- SQLite
- Git

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/NourDbe/tazkirati-api.git
cd tazkirati-api
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the example environment file:

**Windows PowerShell:**

```powershell
Copy-Item .env.example .env
```

**Linux / macOS:**

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

### 4. Configure SQLite

Set the database connection in `.env`:

```env
DB_CONNECTION=sqlite
```

Create the SQLite database file if it does not exist.

**Windows PowerShell:**

```powershell
New-Item database/database.sqlite -ItemType File
```

**Linux / macOS:**

```bash
touch database/database.sqlite
```

### 5. Run Migrations and Seeders

```bash
php artisan migrate --seed
```

### 6. Start the Development Server

```bash
php artisan serve
```

The API will be available at:

```text
http://127.0.0.1:8000
```

## API Endpoints

All API endpoints are prefixed with `/api/v1`.

### Public Event Endpoints

| Method | Endpoint | Description | Authentication |
|---|---|---|---|
| GET | `/api/v1/events` | List published events | No |
| GET | `/api/v1/events/{event}` | View event details | No |
| GET | `/api/v1/events/{event}/remaining-tickets` | Check remaining tickets | No |

### Booking Endpoints

| Method | Endpoint | Description | Authentication |
|---|---|---|---|
| POST | `/api/v1/bookings` | Create a ticket reservation | Sanctum |
| POST | `/api/v1/bookings/{booking}/confirm` | Confirm simulated payment | Sanctum |
| POST | `/api/v1/bookings/{booking}/cancel` | Cancel a pending reservation | Sanctum |

### Organizer Endpoints

| Method | Endpoint | Description | Authentication |
|---|---|---|---|
| POST | `/api/v1/organizer/events/{event}/cancel` | Cancel an organizer's event | Sanctum |

Authenticated endpoints require a valid Sanctum bearer token:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

The project uses a seeded token for development and testing. It does not currently provide a full registration/login workflow.

## Booking Lifecycle

1. A customer submits a booking request.
2. The API validates the requested ticket types and quantities.
3. The system checks ticket availability and per-booking limits.
4. A pending booking is created, and the requested tickets are reserved.
5. The reservation expires after 10 minutes if payment is not confirmed.
6. The customer confirms the simulated payment.
7. Reserved quantities are converted into sold quantities.
8. Unique tickets are generated.
9. A `BookingConfirmed` event is dispatched after the database transaction commits.
10. Notification jobs are queued for email and SMS delivery.

Paid bookings cannot be cancelled through the pending-reservation cancellation flow.

## Background Jobs

The application separates time-consuming operations from HTTP requests using Laravel Jobs.

| Job | Queue | Tries | Backoff | Responsibility |
|---|---|---:|---|---|
| `ExpireBookingJob` | Default queue | Not separately verified | Delayed until reservation expiry | Expires unpaid reservations after 10 minutes |
| `SendBookingEmailJob` | `notifications` | 3 | 10, 30, 60 seconds | Sends booking confirmation email |
| `SendBookingSmsJob` | `notifications` | 3 | 10, 30, 60 seconds | Sends booking confirmation SMS |
| `SendBulkAnnouncementJob` | `announcements` | 3 | 30, 60, 120 seconds | Sends event announcements in chunks |

The notification jobs use delivery logs and idempotency keys to reduce duplicate notifications during retries.

Bulk announcements process paid bookings in batches of 500 rather than loading all recipients into memory at once.

## Events and Listeners

The application uses events and listeners to keep business operations separate from notification delivery.

| Event | Listener | Responsibility |
|---|---|---|
| `BookingConfirmed` | `SendBookingConfirmationNotifications` | Dispatch booking confirmation notification jobs |
| `EventCancelled` | `SendEventCancellationNotifications` | Dispatch bulk event cancellation announcements |

Notification listeners are queued and configured to run after the relevant database transaction commits.

## Caching

`EventCacheService` manages cached event data.

- Remaining ticket quantities are cached for 5 minutes.
- Upcoming event listings are cached for 10 minutes.
- Cache invalidation is triggered when booking operations change ticket inventory.
- Upcoming event cache versions are updated to prevent stale listings from continuing to be served.

The service uses Laravel's Cache facade, allowing the cache driver to be configured through the application environment.

## Media Management

The application uses a polymorphic media relationship to associate media files with different models.

Supported media owners include:

- Events - event posters
- Venues - venue photos
- Organizers - organizer logos

This allows media handling to be extended without creating a separate media table for each model.

## Validation and Authorization

- Form Requests handle request validation and authorization.
- Validation messages are provided in Arabic.
- Customers can only access and manage their own bookings.
- Organizers can cancel only their own events.
- Event visibility is restricted through a published-event Global Scope.
- Organizer operations intentionally bypass that scope when they need to locate an event for cancellation.

## API Error Responses

API exceptions are handled centrally to return consistent JSON responses.

The application handles common API errors, including:

- Validation errors - HTTP 422
- Unauthenticated requests - HTTP 401
- Unauthorized actions - HTTP 403
- Missing resources - HTTP 404
- Other server errors - HTTP 500

## Running Tests

Run the test suite:

```bash
php artisan test
```

The latest recorded test run completed with:

- 30 passing tests
- 118 assertions

Run tests again after installing dependencies or making code changes.

## Queue Processing

The application currently uses Laravel's queue system for asynchronous work.

To run a local queue worker:

```bash
php artisan queue:work
```

For production, queue workers should be managed by a process supervisor. Workers should be restarted after deployment so they load the latest application code:

```bash
php artisan queue:restart
```

> Redis-backed queues, Horizon dashboards, multiple worker processes, and production worker configuration remain pending implementation and verification.

## Environment Configuration

Notification failure simulation is controlled through the `services.tazkirati_notifications` configuration.

The following environment settings are available in `.env.example`:

```env
Tazkirati_EMAIL_FAIL=false
Tazkirati_SMS_FAIL=false
```

Set either value to `true` to simulate a provider failure during notification delivery.

After changing environment configuration, clear the configuration cache if necessary:

```bash
php artisan config:clear
```

## Project Status

| Component | Status |
|---|---|
| Event and ticket management | Implemented |
| Booking and reservation lifecycle | Implemented |
| Simulated payment confirmation | Implemented |
| Reservation expiration | Implemented |
| Email and SMS notification jobs | Implemented |
| Bulk announcements | Implemented |
| Notification delivery logging | Implemented |
| Event caching | Implemented |
| Polymorphic media | Implemented |
| API resources and pagination | Implemented |
| Validation and authorization | Implemented |
| Redis queue integration | Pending verification |
| Laravel Horizon | Pending |
| Postman collection / documented request scenarios | To be confirmed |

## License

This project is developed as an educational backend API project.
