# Bi-Tech Illuminate — Senior Laravel Hiring Challenge

This repository contains my solution to the Bi-Tech Illuminate hiring challenge, built as a Laravel Zero CLI application.

---

## Stages Completed

### Stage 0 — Read the Source
The app was throwing a generic "Something Went Wrong" error. I traced the error path through `IlluminateCommand` → `DatabaseConnector::verify()` and read the source code, which is exactly what the challenge intended. The flag was hidden in a docblock inside `DatabaseConnector.php`.

### Stage 1 — Framework Internals
Identified the Laravel trait used to delegate method calls to another object as `ForwardsCalls`, fetched the file from the Laravel GitHub repository at the exact tag `v10.14.0`, and submitted its md5 hash.

### Stage 2 — Beyond the Tunnel (`illuminate:fetch-flag`)
- Fetches SSH private key and database credentials from the API
- Writes the key to a secure temp file (`chmod 0600`)
- Opens an SSH tunnel via `proc_open` forwarding the remote `localhost:5432` to a local port
- Connects to PostgreSQL through the tunnel using PDO
- Queries `gis_data.config` and extracts the flag
- Cleans up the key file and tunnel process in a `finally` block

### Stage 3 — Centroid of Chaos (`illuminate:import-gis` + `illuminate:find-donut`)

#### Import (`illuminate:import-gis`)
- Opens an SSH tunnel to the remote PostgreSQL instance
- Runs local SQLite migrations via `Artisan::call('migrate:fresh')`
- Imports all rows from `gis_data.neighborhoods` and `gis_data.incidents` into local SQLite
- Parses PostgreSQL `point` and `polygon` types into flat columns

#### Custom Eloquent Relation (`App\Relations\DonutRelation`)
Built a custom relation class extending `Illuminate\Database\Eloquent\Relations\Relation` — not `hasMany`, `belongsTo`, or any built-in type. It implements all required abstract methods: `addConstraints`, `addEagerConstraints`, `initRelation`, `match`, and `getResults`.

The relation:
- Computes the centroid of a neighborhood from its bounding box vertices
- Applies the **Haversine formula** in raw SQL to calculate real-world km distances
- Filters incidents to a donut (annulus) between an inner and outer radius
- Orders results by distance ascending

Used from the `Neighborhood` model as:
```php
public function incidents(float $innerKm = 0.5, float $outerKm = 2.0): DonutRelation
{
    return new DonutRelation($this, $innerKm, $outerKm);
}
```

#### Find Donut (`illuminate:find-donut`)
- Loads the target neighborhood from local SQLite
- Calls the custom `DonutRelation` to get all incidents in the 0.5–2.0 km donut
- Displays a table with ID, distance, code, and severity
- Concatenates the `incident.code` from each row (ordered by distance) to reveal the flag

---

## Commands

```bash
# Configure your token
illuminate --token=<your-token>

# Stage 2: fetch flag from remote DB via SSH tunnel
illuminate illuminate:fetch-flag

# Stage 3: import remote GIS data into local SQLite
illuminate illuminate:import-gis

# Stage 3: run the donut query and reveal the flag
illuminate illuminate:find-donut [neighborhood] [--inner=0.5] [--outer=2.0]

# Final: submit repo and CV
illuminate illuminate:submit-repo <repo-url> <cv.pdf>
```

---

## Key Technical Decisions

- **SSH tunnel via `proc_open`** — used instead of phpseclib because phpseclib3's `SSH2` has no direct TCP forwarding method. `proc_open` with the system `ssh` binary is reliable and produces clean, readable code.
- **Haversine in raw SQL** — distance calculation runs entirely in the database layer, keeping the relation efficient and avoiding loading all rows into PHP.
- **SQLite BETWEEN quirk** — SQLite compares bound float parameters as text when using `?` placeholders with `BETWEEN`. The inner/outer radius values are cast to `float` and interpolated directly into the SQL string to work around this.
- **Constructor property order** — the `Relation` parent constructor calls `addConstraints()` immediately, so the `$neighborhood` property must be assigned before `parent::__construct()` is called. The property is declared explicitly (not promoted) to satisfy PHP 8.2's deprecation of dynamic properties.

---

## Setup

```bash
composer install
php illuminate --token=<your-token>
php illuminate illuminate:import-gis
php illuminate illuminate:find-donut
```

Requires: PHP 8.2+, `ext-pdo_sqlite`, `ext-pdo_pgsql`, system `ssh` binary.
