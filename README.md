# TripToGo

A tourism guide web app. Visitors browse tourist places on a map or in a list,
open a place to read its description and reviews, and (once registered) leave
a rating + comment. An admin curates the place catalogue (add/edit/delete).

Roles: **visitor** (browse only), **registered user** (+ post reviews),
**admin** (+ manage places).

## Stack

- **Backend**: Laravel 13 (PHP ^8.3), Laravel Sanctum (token auth), MySQL
  (`backend/.env` → database `triptogo`).
- **Frontend**: Vue 3 + Vite, Vue Router, axios, Leaflet + OpenStreetMap tiles,
  Tailwind CSS v4 (via `@tailwindcss/vite`).

## Repo layout

```
backend/    Laravel API, everything under /api/*
frontend/
  src/
    api.js                 axios instance + auth-header interceptor
    composables/useAuth.js shared login state (module-level refs, not Pinia)
    router/index.js        routes + the admin route guard
    views/                 one .vue file per page
```

## Running it

Two servers, both required:

```sh
# backend — http://localhost:8000
cd backend
composer install
cp .env.example .env   # then set DB_* to a real MySQL database named triptogo
php artisan key:generate
php artisan migrate --seed
php artisan serve

# frontend — http://localhost:5173
cd frontend
npm install
npm run dev
```

`frontend/.env` must point at the API:

```
VITE_API_URL=http://localhost:8000/api
```

The seeder creates an admin login (`test@example.com` / `password`) and 10
random places, so the app has data to browse right after `migrate --seed`.

## API endpoints

All under `/api`. Errors from validation come back as `422` with
`{ errors: { field: ["message"] } }` — each field's value is an **array**,
read the message as `errors.field[0]`.

| Method | Path                    | Purpose                  | Who              |
|--------|-------------------------|---------------------------|------------------|
| POST   | `/register`             | create account            | anyone           |
| POST   | `/login`                | log in                    | anyone           |
| POST   | `/logout`               | log out                   | authenticated    |
| GET    | `/user`                 | current user              | authenticated    |
| GET    | `/places`               | list places (paginated)   | anyone           |
| GET    | `/places/{id}`          | place detail + reviews    | anyone           |
| POST   | `/places`               | create a place            | admin            |
| PUT    | `/places/{id}`          | update a place            | admin            |
| DELETE | `/places/{id}`          | delete a place            | admin            |
| GET    | `/places/{id}/reviews`  | list reviews (paginated)  | anyone           |
| POST   | `/places/{id}/reviews`  | post a review             | authenticated    |
| PUT    | `/reviews/{id}`         | edit own review           | owner            |
| DELETE | `/reviews/{id}`         | delete a review           | owner or admin   |

Two response shapes worth knowing:
- `GET /places` is a Laravel **paginator** — the rows are in `response.data.data`,
  not `response.data`.
- `GET /places/{id}` returns the place object **directly** (not paginated),
  with its `reviews` array already loaded, each review carrying a nested `user`.

`/places` accepts `search` and `category` query params, matched against `name`
and `category` respectively (see `PlaceController::index`).

## Auth flow

1. `/login` or `/register` returns `{ user, token }`.
2. The token is saved to `localStorage` and kept in `useAuth.js`'s `token` ref.
3. Every request through `src/api.js` runs one axios interceptor that reads the
   token from `localStorage` and sets `Authorization: Bearer <token>` — no
   component ever attaches this header itself.
4. `useAuth.js` declares its refs (`user`, `token`) at **module level**, outside
   the exported `useAuth()` function, so every component importing it shares the
   same state instead of getting its own copy.

Server-side: protected routes use the `auth:sanctum` middleware; admin-only
routes add the `admin` alias (`bootstrap/app.php` → `EnsureUserIsAdmin`, which
403s unless `user.is_admin`). Editing/deleting a review is checked by
`ReviewPolicy` (owner, or admin for delete).
