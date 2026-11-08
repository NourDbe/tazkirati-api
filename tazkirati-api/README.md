# Tazkirati API

A Laravel RESTful API for event management, ticket reservations, booking payments, and notifications.

## Requirements

- PHP 8.3 or later
- Composer
- Git
- SQLite
- Redis
- Laravel Sanctum
- Predis
- Laravel Horizon
- PHPUnit

## Project Setup

Run the following commands from the project root.

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

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

### 3. Configure SQLite

Set the database connection in `.env`:

```dotenv
DB_CONNECTION=sqlite
```

Create the SQLite database file if it does not already exist.

**Windows PowerShell:**

```powershell
if (!(Test-Path database/database.sqlite)) {
    New-Item database/database.sqlite -ItemType File
}
```

**Linux / macOS:**

```bash
touch database/database.sqlite
```

### 4. Configure Redis

Add or update the following settings in `.env`:

```dotenv
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
QUEUE_CONNECTION=redis
```

Make sure the Redis server is running before starting queue workers.

For local Windows development, Memurai can be used as a Redis-compatible server.

### 5. Configure Horizon Access

Add the authorized dashboard user's email to `.env`:

```dotenv
HORIZON_ADMIN_EMAIL=organizer1@tazkirati.test
```

Only the configured user is authorized to access the Horizon dashboard.

### 6. Run Migrations and Seeders

```bash
php artisan migrate --seed
php artisan optimize:clear
```

**Important:** Do not use `migrate:fresh` on a database containing data you need to keep.

### 7. Start the Development Server

```bash
php artisan serve
```

The application will be available at:

```text
http://127.0.0.1:8000
```

## Seed Data

The `TazkiratiSeeder` creates:

- 3 organizer users:
  - organizer1@tazkirati.test
  - organizer2@tazkirati.test
  - organizer3@tazkirati.test
- Development password: `password`
- 5 venues.
- 10 published events.
- Regular and VIP ticket types for each event.
- 2,000 paid bookings for the first event.
- 2,000 booking items.
- 2,000 generated ticket records.
- Updates the first event's Regular ticket type `sold_quantity` to 2,000.

The seeder does not create permanent Sanctum tokens.

## Authentication Tokens

The API uses Laravel Sanctum personal access tokens.

Open Tinker:

```bash
php artisan tinker
```

### Create a Customer Token

```php
$customer = App\Models\User::create([
    'name' => 'Postman Customer',
    'email' => 'postman.customer@tazkirati.test',
    'password' => Illuminate\Support\Facades\Hash::make('password'),
]);

$customerToken = $customer->createToken('postman-customer')->plainTextToken;

$customerToken;
```

### Create an Organizer Token

```php
$event = App\Models\Event::withoutGlobalScope(
    App\Models\Scopes\PublishedEventScope::class
)->first();

$organizer = App\Models\Organizer::findOrFail($event->organizer_id);

$organizerUser = App\Models\User::findOrFail($organizer->user_id);

$organizerToken = $organizerUser->createToken('postman-organizer')->plainTextToken;

$organizerToken;
$event->id;
```

Copy the generated values into Postman:

- `customer_token`
- `organizer_token`
- `organizer_event_id`

Keep tokens private. Do not commit them to Git or share them publicly.

## API Endpoints

All API endpoints are prefixed with `/api/v1`.

Base URL:

```text
http://127.0.0.1:8000
```

| Method | Endpoint | Authentication | Description |
|---|---|---|---|
| GET | `/api/v1/events` | Public | List published events |
| GET | `/api/v1/events/{event}` | Public | Show event details |
| GET | `/api/v1/events/{event}/remaining-tickets` | Public | Get remaining ticket inventory |
| POST | `/api/v1/bookings` | Sanctum | Create temporary booking |
| POST | `/api/v1/bookings/{booking}/confirm` | Sanctum | Confirm payment |
| POST | `/api/v1/bookings/{booking}/cancel` | Sanctum | Cancel temporary booking |
| POST | `/api/v1/organizer/events/{event}/cancel` | Organizer Sanctum | Cancel organizer-owned event |

Authenticated endpoints require a valid Sanctum bearer token:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

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

## Create a Booking

Request:

```http
POST /api/v1/bookings
Authorization: Bearer <customer_token>
Content-Type: application/json
Accept: application/json
```

Request body:

```json
{
    "items": [
        {
            "ticket_type_id": 1,
            "quantity": 2
        }
    ]
}
```

A successful reservation returns HTTP `201`.

The temporary reservation has a 10-minute payment window.

## Confirm Payment

```http
POST /api/v1/bookings/{booking}/confirm
Authorization: Bearer <customer_token>
Accept: application/json
```

Payment confirmation is simulated and is not connected to a real payment gateway.

## Cancel Booking

```http
POST /api/v1/bookings/{booking}/cancel
Authorization: Bearer <customer_token>
Accept: application/json
```

This endpoint is intended for pending temporary reservations.

## Cancel Event

```http
POST /api/v1/organizer/events/{event}/cancel
Authorization: Bearer <organizer_token>
Accept: application/json
```

Only the organizer who owns the event can cancel it.

## Background Jobs

The application uses Laravel Jobs to handle time-consuming operations outside HTTP requests.

| Job | Queue | Responsibility |
|---|---|---|
| `ExpireBookingJob` | Default | Expires unpaid reservations after 10 minutes |
| `SendBookingEmailJob` | Notifications | Sends booking confirmation emails |
| `SendBookingSmsJob` | Notifications | Sends booking confirmation SMS |
| `SendBulkAnnouncementJob` | Announcements | Sends event announcements in chunks |

The notification jobs use delivery logs and idempotency keys to reduce duplicate notifications during retries.

Bulk announcements process paid bookings in batches of 500 rather than loading all recipients into memory at once.

## Events and Listeners

The application uses events and listeners to separate business operations from notification delivery.

| Event | Listener | Responsibility |
|---|---|---|
| `BookingConfirmed` | `SendBookingConfirmationNotifications` | Dispatches booking confirmation notification jobs |
| `EventCancelled` | `SendEventCancellationNotifications` | Dispatches bulk event cancellation announcements |

Notification listeners are queued and configured to run after the relevant database transaction commits.

## Redis and Queue Workers

The project uses Redis through Predis.

Redis was tested locally with Memurai, and a Laravel Redis connection and queue worker were successfully exercised.

Start a regular queue worker:

```bash
php artisan queue:work redis --queue=notifications,default,announcements --tries=3 -v
```

Restart workers after code changes:

```bash
php artisan queue:restart
```

## Laravel Horizon

Laravel Horizon is installed and configured.

Its routes are registered, and the configuration includes separate supervisors for:

- `notifications`
- `default`
- `announcements`

The Horizon dashboard is available at:

```text
http://127.0.0.1:8000/horizon
```

Access is restricted through the `viewHorizon` authorization gate using the email configured in `HORIZON_ADMIN_EMAIL`.

### Windows Limitation

Horizon requires the PHP extensions `pcntl` and `posix` to run its process manager.

These extensions are unavailable in the current native Windows PHP environment. Therefore, Horizon's worker process cannot be started natively on this machine.

The dashboard can be accessed locally, but this does not mean that the Horizon worker is running.

The command:

```bash
php artisan horizon:status
```

may report that Horizon is inactive when no Horizon process is running.

To run Horizon, use a supported Linux environment:

```bash
php artisan horizon
```

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

- Events - event posters.
- Venues - venue photos.
- Organizers - organizer logos.

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

| Error | HTTP Status |
|---|---:|
| Validation errors | 422 |
| Unauthenticated requests | 401 |
| Unauthorized actions | 403 |
| Missing resources | 404 |
| Server errors | 500 |

## Running Tests

Run the test suite:

```bash
php artisan test
```

The test suite passed in the development environment.

Run tests again after installing dependencies or making code changes.

## Postman

The API can be tested using the following Postman files:

- `Tazkirati-API.postman_collection.json`
- `Tazkirati-Local.postman_environment.json`

Import both files into Postman.

Select the `Tazkirati Local` environment and configure the required authentication tokens.

The collection includes requests for:

- Listing published events.
- Showing event details.
- Checking remaining tickets.
- Creating a booking.
- Confirming payment.
- Cancelling a temporary booking.
- Cancelling an organizer-owned event.

Run the requests in order.

Use separate temporary bookings when testing payment confirmation and booking cancellation.

## Security Notes

- Never commit `.env` files.
- Do not expose authentication tokens.
- Use secure credentials in production.
- Payment confirmation is simulated and is not connected to a real payment gateway.
- Organizer ownership authorization must remain enabled.

## License

This project is developed as an educational backend API project.
