# Stampo Backend API

This document is the HTTP contract implemented by [`server/index.mjs`](../server/index.mjs) and consumed by [`services/api.ts`](../services/api.ts).

The included backend is a development server built with TinyHTTP and LowDB. It persists application data to JSON and keeps authentication sessions in memory. A production backend should preserve this contract while replacing the development storage and session implementation.

## Quick start

Install dependencies and start the API:

```bash
npm install
npm run server
```

The default URL is:

```text
http://localhost:3001
```

Configure the Expo client:

```env
EXPO_PUBLIC_API_URL=http://localhost:3001
```

For an Android emulator, use the host address that reaches the development machine, commonly:

```env
EXPO_PUBLIC_API_URL=http://10.0.2.2:3001
```

Server environment variables:

| Variable  | Default          | Purpose         |
| --------- | ---------------- | --------------- |
| `PORT`    | `3001`           | HTTP port       |
| `HOST`    | `0.0.0.0`        | Bind address    |
| `DB_FILE` | `server/db.json` | LowDB JSON file |
| `ADMIN_API_KEY` | `stampo-local-admin` | Admin dashboard/API bearer key; must be replaced outside local development |

## Conventions

- Requests and responses use JSON unless the response is `204 No Content`.
- Dates use `YYYY-MM-DD`.
- Timestamps use ISO 8601.
- Country codes use ISO 3166-1 alpha-2, such as `FR` and `US`.
- Continent codes are `AF`, `AN`, `AS`, `EU`, `NA`, `OC`, and `SA`.
- IDs are strings. The development server creates UUIDs for server-owned records.
- JSON properties use `camelCase`.
- The API enables CORS for development.
- The mobile client uses an 8-second request timeout.

## Authentication

User-owned endpoints require a user bearer token. Public catalog, image-resolution,
collection-detail, and Daily Destination endpoints accept anonymous requests. Admin
endpoints require the separate `ADMIN_API_KEY` bearer token.

```http
Authorization: Bearer dev-session-token
Accept: application/json
Content-Type: application/json
```

Development sessions are stored in memory and are lost when the server restarts. The mobile client stores the current token using Expo SecureStore.

### Error format

Errors use an HTTP status and a JSON message:

```json
{
  "message": "Unauthenticated."
}
```

Common statuses:

| Status | Meaning                                 |
| ------ | --------------------------------------- |
| `200`  | Successful read or update               |
| `201`  | Resource created                        |
| `204`  | Successful action with no response body |
| `401`  | Missing, invalid, or expired session    |
| `404`  | User-owned resource not found           |
| `422`  | Invalid request or credentials          |

## Data models

### Public user

```json
{
  "id": "5ec53967-acde-4ccf-bc78-3f80ee8da15d",
  "name": "Robb",
  "email": "robb@example.com",
  "language": "English",
  "plan": "free"
}
```

`plan` is either `free` or `pro`.

### Profile

```json
{
  "id": "5ec53967-acde-4ccf-bc78-3f80ee8da15d",
  "name": "Robb",
  "email": "robb@example.com",
  "language": "English",
  "plan": "free",
  "nationality": "United States",
  "dateOfBirth": "1990-05-14",
  "photoUri": null
}
```

### Visit

```json
{
  "id": "56aac7ea-8a05-482c-a762-995678f74395",
  "cityId": "2988507",
  "cityName": "Paris",
  "country": "France",
  "countryCode": "FR",
  "continentCode": "EU",
  "subcountry": "Ile-de-France",
  "visitedAt": "2026-08-10",
  "note": "Beautiful city.",
  "places": [
    {
      "id": "cdg-airport",
      "name": "Charles de Gaulle Airport",
      "type": "airport"
    }
  ],
  "userId": "5ec53967-acde-4ccf-bc78-3f80ee8da15d"
}
```

`places[].type` is `sight` or `airport`. The server adds `id` and `userId`; clients must not use `userId` to choose ownership.

## Endpoint summary

| Method   | Path                            | Authentication | Purpose                                                      |
| -------- | ------------------------------- | -------------- | ------------------------------------------------------------ |
| `POST`   | `/auth/register`                | No             | Create account and session                                   |
| `POST`   | `/auth/login`                   | No             | Create session                                               |
| `GET`    | `/auth/me`                      | Yes            | Restore current user                                         |
| `PUT`    | `/auth/password`                | Yes            | Change password                                              |
| `POST`   | `/auth/logout`                  | Yes            | Revoke current session                                       |
| `GET`    | `/profile`                      | Yes            | Get complete profile                                         |
| `PUT`    | `/profile`                      | Yes            | Update profile                                               |
| `GET`    | `/visits`                       | Yes            | List the user's visits                                       |
| `POST`   | `/visits`                       | Yes            | Add a visit                                                  |
| `PUT`    | `/visits/:id`                   | Yes            | Replace a visit                                              |
| `DELETE` | `/visits/:id`                   | Yes            | Delete a visit                                               |
| `GET`    | `/me/travel-state`              | Yes            | Hydrate completions, wishlist, rewards and plan              |
| `GET`    | `/me/home`                      | Yes            | Get the calculated homepage dashboard                        |
| `PUT`    | `/me/completions/:sightId`      | Yes            | Set sight completion state                                   |
| `PUT`    | `/me/wishlist/:targetId`        | Yes            | Set wishlist state                                           |
| `GET`    | `/collections?status=all`       | Optional       | List backend-managed collections and user progress           |
| `PUT`    | `/me/collections/:collectionId` | Yes            | Update collection progress                                   |
| `GET`    | `/api/countries/:code`          | Optional       | Imported country, collections, cities, sights and user stats |
| `GET`    | `/api/countries/:code/cities`   | Optional       | Featured imported cities                                     |
| `GET`    | `/api/cities/:id`               | Optional       | City detail with sights                                      |
| `GET`    | `/api/cities/:id/sights`        | Optional       | Sights in a city                                             |
| `GET`    | `/api/sights/:id`               | Optional       | Sight detail and image attribution                           |
| `GET`    | `/api/city-image`               | Optional       | Resolve and cache a city image                               |
| `GET`    | `/api/place-image`              | Optional       | Resolve and cache a place image                              |
| `GET`    | `/api/collections/:id`          | Optional       | Managed collection detail and locations                     |
| `GET`    | `/daily-destinations?date=`     | Optional       | Published Daily Destination quiz lessons                    |
| `GET`    | `/community/leaderboard`        | Yes            | Global or friends leaderboard                               |
| `GET`    | `/me/friend-code`               | Yes            | Current user's QR/deep-link friend code                      |
| `POST`   | `/me/friends/scan`              | Yes            | Add a friend from a scanned code                            |
| `GET`    | `/admin`                        | No             | Web admin shell; API calls require admin authentication      |
| `GET`    | `/admin/api/meta`               | Admin          | Country/city choices for editors                             |
| CRUD     | `/admin/api/sights`             | Admin          | Manage Top Sights                                            |
| CRUD     | `/admin/api/collections`        | Admin          | Manage collections and their location lists                  |
| CRUD     | `/admin/api/daily-destinations` | Admin          | Manage lessons shown on the `+` page                         |

Catalog records use normalized `countries`, `cities`, `sights`, `collections`, `countryCollections`, and `imageCredits` arrays. When `GET /api/countries/:code` requests a supported country that is not already curated, the server builds an in-memory catalog and responds with `202 { status: "importing" }`. The app polls until the completed payload is available. Automatically downloaded reference data is not written to the main LowDB file; only explicit user actions persist user data.

The country-detail response also includes published `managedCollections` whose
location records match the country name. This is the only data source for the
country page's “Collections in Country” rows.

## Authentication endpoints

### Register

```http
POST /auth/register
```

Request:

```json
{
  "name": "Robb",
  "email": "robb@example.com",
  "password": "secret-password",
  "passwordConfirmation": "secret-password"
}
```

Rules:

- Name, email, and password are required.
- Password must contain at least 6 characters.
- Password confirmation must match.
- Email comparison is case-insensitive.
- Email must be unique.

Response: `201 Created`

```json
{
  "token": "dev-7bd7c61d-95bd-4331-896f-e3e69e38a47e",
  "user": {
    "id": "5ec53967-acde-4ccf-bc78-3f80ee8da15d",
    "name": "Robb",
    "email": "robb@example.com",
    "language": "English",
    "plan": "free"
  }
}
```

Passwords are stored as salted scrypt hashes. Legacy plain-text development records are accepted only for migration compatibility.

### Login

```http
POST /auth/login
```

Request:

```json
{
  "email": "robb@example.com",
  "password": "secret-password",
  "deviceName": "Stampo mobile app"
}
```

`deviceName` is accepted by the client contract but is not currently persisted by the development server.

Response: `200 OK`, using the same `{ token, user }` shape as registration.

Invalid credentials return `422`.

### Current user

```http
GET /auth/me
```

Response: `200 OK` with the Public user model. Invalid sessions return `401`.

### Update password

```http
PUT /auth/password
```

Request:

```json
{
  "currentPassword": "secret-password",
  "newPassword": "new-secret-password"
}
```

Rules:

- The current password must match.
- The new password must contain at least 8 characters.

Response: `204 No Content`.

### Logout

```http
POST /auth/logout
```

Revokes the bearer token used for the request. Response: `204 No Content`.

## Profile endpoints

### Get profile

```http
GET /profile
```

Response: `200 OK` with the Profile model.

### Update profile

```http
PUT /profile
```

Accepted properties:

```json
{
  "name": "Robb",
  "email": "robb@example.com",
  "language": "English",
  "nationality": "United States",
  "dateOfBirth": "1990-05-14",
  "photoUri": "file:///local/profile-photo.jpg"
}
```

Only supplied accepted properties are updated. Response: `200 OK` with the complete updated Profile.

The development server currently stores `photoUri` as a string; it does not upload image bytes. Production should use an authenticated upload endpoint and return a durable HTTPS URL.

## Visit endpoints

Every visit operation is scoped to the authenticated user.

### List visits

```http
GET /visits
```

Response: `200 OK` with a plain array of Visit objects.

### Create visit

```http
POST /visits
```

Request:

```json
{
  "cityId": "2988507",
  "cityName": "Paris",
  "country": "France",
  "countryCode": "FR",
  "continentCode": "EU",
  "subcountry": "Ile-de-France",
  "visitedAt": "2026-08-10",
  "note": "Beautiful city.",
  "places": []
}
```

Response: `201 Created` with the created Visit. The server generates `id`, assigns the authenticated `userId`, and defaults `places` to an empty array.

### Update visit

```http
PUT /visits/:id
```

The client sends the complete Visit. The server preserves the route record's `id`, forces ownership to the authenticated user, and replaces the remaining fields.

Response: `200 OK` with the updated Visit. A missing or foreign visit returns `404`.

### Delete visit

```http
DELETE /visits/:id
```

Response: `204 No Content`. A missing or foreign visit returns `404`.

## Travel state

### Get travel state

```http
GET /me/travel-state
```

Response:

```json
{
  "completedSightIds": ["eiffel-tower"],
  "wishlistIds": ["city-paris", "country-JP"],
  "rewards": [
    {
      "id": "reward-id",
      "userId": "5ec53967-acde-4ccf-bc78-3f80ee8da15d",
      "title": "Paris Explorer",
      "krooPoints": 0.8,
      "unlocked": true
    }
  ],
  "challengePoints": 0.8,
  "collections": [
    {
      "id": "wonders",
      "title": "Seven Wonders",
      "detail": "Visit all 7 wonders",
      "progress": 12,
      "status": "active"
    }
  ],
  "plan": "free"
}
```

`challengePoints` is calculated from unlocked rewards and capped at `6.25`.

## Collections

The Explore page calls this feature Collections. Collection definitions and
location lists always come from the backend. The supported filters are `all`,
`active`, and `completed`.

### List collections

```http
GET /collections?status=all
```

`status` is optional and defaults to `all`. Accepted query values are `all`,
`active`, and `completed`. Authentication is optional: signed-in responses
include that user's derived progress; anonymous responses use `0`. Returned
records can have status `inactive` (`0%`), `active` (`1–99%`), or `completed`
(`100%`).

Response:

```json
[
  {
    "id": "wonders",
    "title": "Seven Wonders",
    "detail": "Visit all 7 wonders",
    "progress": 12,
    "status": "active"
  },
  {
    "id": "seas",
    "title": "Seven Seas",
    "detail": "Sail or visit all 7 seas",
    "progress": 100,
    "status": "completed",
    "updatedAt": "2026-08-11T08:00:00.000Z"
  }
]
```

### Update collection progress

```http
PUT /me/collections/:collectionId
```

Request:

```json
{
  "progress": 100
}
```

Progress must be from `0` through `100`. Status is derived by the server: `0`
is inactive, `1–99` is active, and `100` is completed. The endpoint creates or
updates the authenticated user's progress and returns the updated collection.

### Set sight completion

```http
PUT /me/completions/:sightId
```

Request:

```json
{
  "completed": true
}
```

The operation is idempotent. `false` removes an existing completion; any other value adds it when missing.

Response:

```json
{
  "sightId": "eiffel-tower",
  "completed": true
}
```

### Set wishlist state

```http
PUT /me/wishlist/:targetId
```

Request:

```json
{
  "saved": true
}
```

Target IDs are client-defined namespaced strings such as `city-paris` and `country-FR`. The operation is idempotent.

Response:

```json
{
  "targetId": "city-paris",
  "saved": true
}
```

## Homepage dashboard

```http
GET /me/home
```

This is the server-authoritative summary used by the Home tab.

Response:

```json
{
  "counts": {
    "continents": 2,
    "countries": 3,
    "cities": 4,
    "airports": 1,
    "sights": 6
  },
  "score": 2.792,
  "level": "Wanderer",
  "challengePoints": 0,
  "worldProgress": 2,
  "visitedCountryCodes": ["BR", "FR", "US"],
  "continentCounts": {
    "AF": 0,
    "AN": 0,
    "AS": 0,
    "EU": 1,
    "NA": 1,
    "OC": 0,
    "SA": 1
  },
  "updatedAt": "2026-08-11T08:00:00.000Z"
}
```

Counts use unique continent, country, and city IDs. Airport places are counted from visits. Sights combine unique visit sight IDs and checklist completions.

### Kroo Score

| Category   | Points each | Maximum |
| ---------- | ----------: | ------: |
| Continents |       `1.0` |   `7.0` |
| Countries  |      `0.25` | `48.75` |
| Cities     |     `0.005` |  `10.0` |
| Airports   |      `0.01` |   `8.0` |
| Sights     |     `0.002` |  `20.0` |
| Challenges |    Variable |  `6.25` |
| Total      |             |   `100` |

The server caps every category and the final total. The score is rounded to three decimal places.

### Levels

| Level       |       Score |
| ----------- | ----------: |
| Wanderer    |   `0–4.999` |
| Traveler    |  `5–14.999` |
| Explorer    | `15–29.999` |
| Wayfarer    | `30–49.999` |
| Voyager     | `50–74.999` |
| Kroo Master |    `75–100` |

`worldProgress` is the rounded percentage of 195 recognized countries visited.

## Community and friends

### Leaderboard

```http
GET /community/leaderboard?scope=global
```

`scope` is `global` or `friends`. The friends response includes the current user
and users connected through a friend record. Results are ordered by descending
Kroo Score and then name. Each item contains `id`, `name`, `photoUri`, `score`,
`level`, and counts for countries, continents, cities, and completed collections.

### Friend code

```http
GET /me/friend-code
```

Returns `{ "code": "stampo://friend/opaque-token" }`. The token is generated
once per user and stored with the account.

### Add friend

```http
POST /me/friends/scan
```

Request: `{ "code": "stampo://friend/opaque-token" }`. Self-adds and invalid
codes return `422`. Repeating a valid request is idempotent. The response is the
new friend's Community Profile.

## Daily Destination (`+` page)

```http
GET /daily-destinations?date=2026-08-24
```

Returns published lessons whose `publishDate` is blank (evergreen) or matches
the requested date, ordered by `displayOrder`. The mobile client falls back to
its bundled lesson set if this request is unavailable.

```json
{
  "id": "daily-france-louvre",
  "name": "The Louvre",
  "country": "France",
  "city": "Paris",
  "imageUrl": "https://cdn.example.com/louvre.jpg",
  "icon": "🖼️",
  "content": "The lesson text.",
  "question": "Which museum... ?",
  "options": ["The Louvre", "The Prado"],
  "correctAnswer": 0,
  "publishDate": "2026-08-24",
  "isPublished": true,
  "isPremium": false,
  "displayOrder": 0
}
```

`correctAnswer` is a zero-based index into `options`. At least two non-empty
options are required.

## Web administration

Open `http://localhost:3001/admin`. The browser keeps the admin key in
`sessionStorage`, so closing the tab/session removes it. Admin API requests use:

```http
Authorization: Bearer YOUR_ADMIN_API_KEY
```

The local default key is only a convenience. Set a long random
`ADMIN_API_KEY` in every shared or deployed environment. Admin authorization is
separate from user sessions, compares keys in constant time, and returns `401`
without revealing which part was incorrect.

Every admin resource supports:

| Method | Path | Result |
| --- | --- | --- |
| `GET` | `/admin/api/{resource}` | Full list |
| `POST` | `/admin/api/{resource}` | Create, `201` |
| `PUT` | `/admin/api/{resource}/:id` | Replace editable fields |
| `DELETE` | `/admin/api/{resource}/:id` | Delete, `204` |

Resources are `sights`, `collections`, and `daily-destinations`.

### Top Sight administration

Top Sight input fields are `id` (optional on create), `countryId`, `cityId`,
`name`, `slug`, `image`/`imageUrl`, `content`/`description`, `category`,
`latitude`, `longitude`, `displayOrder`, `isFeatured`, and either `unlocked` or
`isPremium`. The city must belong to the submitted country. Setting
`unlocked: false` stores `isPremium: true`. Deletion also removes associated
image-credit records. User completion history is retained as an audit-friendly
orphan and becomes effective again if the same stable sight ID is restored.

### Collection administration

A collection contains `id`, `title`, `detail`, `description`, `imageUrl`,
`displayOrder`, `isPublished`, `isPremium`, and `places`. Each place contains:

```json
{
  "id": "christ-redeemer",
  "name": "Christ the Redeemer",
  "city": "Rio de Janeiro",
  "country": "Brazil",
  "imageUrl": "https://cdn.example.com/christ.jpg",
  "content": "Location description.",
  "isPremium": false
}
```

Stable place IDs are important: completion keys are stored as
`collection-{collectionId}-{placeId}`. Deleting a collection removes its saved
percentage records. The mobile API derives progress from completed place IDs
when no explicit legacy percentage exists.

### Daily Destination administration

Daily Destination CRUD uses the model documented in the preceding section.
`isPublished: false` immediately removes a lesson from public responses.
`publishDate` can schedule a lesson for one date; blank dates are evergreen.

### Admin metadata

```http
GET /admin/api/meta
```

Returns normalized countries and cities used by the Top Sight editor. Import a
country through the catalog importer before assigning a sight to it.

## Kroo+ billing and GPS boundaries

Kroo+ purchases are not processed by this development API. The mobile client
uses RevenueCat's native SDK, platform-specific public SDK keys, the
`kroo_plus` entitlement, and the current monthly/annual Offering. CustomerInfo
is the subscription authority. Do not add card data, store receipts, or secret
RevenueCat keys to these endpoints. A production service may validate
RevenueCat webhooks and mirror entitlement state, but the webhook must verify
RevenueCat's authorization header and remain separate from the public mobile
contract.

GPS arrival detection and accuracy checks run through Expo Location on-device.
An accepted suggestion becomes a normal `POST /visits` request with its
`verification` object. The server stores that evidence but does not trust a
client-supplied `plan` field to grant Kroo+ access.

## Client synchronization

On startup, the client:

1. Restores the bearer token from SecureStore.
2. Calls `GET /auth/me`.
3. Loads `GET /visits` and `GET /me/travel-state` in parallel.
4. Loads `GET /me/home` into the Redux dashboard slice.

The dashboard is refreshed after creating a visit, updating visit places, completing a sight, signing in, returning to Home, or pulling to refresh.

AsyncStorage is an offline UI cache. The authenticated server is authoritative.

## Development database

The default file is [`server/db.json`](../server/db.json). Collections:

| Collection           | Purpose                                            |
| -------------------- | -------------------------------------------------- |
| `users`              | Accounts, profile fields, password hashes and plan |
| `visits`             | User-owned city visits and places                  |
| `completions`        | Completed sight IDs                                |
| `wishlists`          | Saved target IDs                                   |
| `rewards`            | Unlocked rewards and challenge score values        |
| `collectionProgress` | User-specific active/completed collection progress |
| `friends`            | Symmetric user friendship links                    |
| `countries`          | Imported normalized countries                      |
| `cities`             | Imported normalized cities                         |
| `sights`             | Imported and admin-managed Top Sights              |
| `managedCollections` | Admin-managed collection definitions and places    |
| `dailyDestinations`  | Admin-managed `+` page lessons                      |
| `imageCredits`       | Source/license metadata for catalog images          |

Example completion record:

```json
{
  "id": "uuid",
  "userId": "user-uuid",
  "sightId": "eiffel-tower",
  "completedAt": "2026-08-11T08:00:00.000Z"
}
```

Example wishlist record:

```json
{
  "id": "uuid",
  "userId": "user-uuid",
  "targetId": "city-paris",
  "savedAt": "2026-08-11T08:00:00.000Z"
}
```

## Production requirements

Before production deployment:

- Replace in-memory sessions with durable, revocable tokens.
- Replace LowDB with a transactional database.
- Remove or disable JSON Server's generic fallback routes.
- Add request schemas and stricter validation for profiles and visits.
- Validate city IDs and derive country metadata server-side.
- Enforce unique email validation during profile updates.
- Add rate limiting for registration, login, and password changes.
- Use a dedicated file upload service for profile photos.
- Add password reset and email verification flows.
- Restrict CORS to approved clients where applicable.
- Add automated API, authorization, and score-calculation tests.
- Never commit production credentials or real user passwords.

## Example flow

Register:

```bash
curl -X POST http://localhost:3001/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Robb","email":"robb@example.com","password":"secret123","passwordConfirmation":"secret123"}'
```

Use the returned token:

```bash
curl http://localhost:3001/me/home \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```
