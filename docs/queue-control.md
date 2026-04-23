# Queue Control — Usage Guide

Operations for killing, purging, forgetting, and clearing jobs and metrics. Available via **UI**, **Artisan commands**, and **HTTP API**.

---

## UI Locations

| Operation | Page | Where |
|---|---|---|
| Purge Stuck Jobs | Dashboard `/jobs-monitor` | Warning banner at top (only visible when jobs are in `processing` status) — "Purge stuck jobs" button shows a preview count before confirming |
| Forget Job | Dashboard → All Jobs table | Kebab menu (⋮) on each non-failed job row → "Delete record" |
| Clear Queue | Workers `/jobs-monitor/workers` | Queue list table — trash icon button on each queue row |
| Clear Metrics | Settings → General `/jobs-monitor/settings/general` | **Danger Zone** section → "Clear metrics" button (baselines + anomalies only) |
| Clear Job Records | Settings → General `/jobs-monitor/settings/general` | **Danger Zone** section → period dropdown (`24h / 7d / 30d / all`) + "Clear" button (also clears metrics) |

---

## 1. Purge Stuck Jobs

Finds jobs stuck in `processing` state longer than a threshold and marks them `failed`. Useful after a worker crash where the monitoring record was never updated.

> **Note:** This only changes monitoring records — it cannot kill a running PHP process.

### Artisan

```bash
# Purge stuck jobs using default threshold (queue retry_after + 60s)
php artisan jobs-monitor:purge-stuck

# Purge jobs stuck longer than 300 seconds
php artisan jobs-monitor:purge-stuck --older-than=300

# Preview count without making changes
php artisan jobs-monitor:purge-stuck --dry-run
php artisan jobs-monitor:purge-stuck --older-than=300 --dry-run
```

Default threshold: `queue.connections.<default>.retry_after` + 60 seconds (falls back to 150s if not configured).

### API

**Preview first (recommended):**

```
GET /jobs-monitor/jobs/purge-stuck/preview?older_than=300
GET /jobs-monitor/api/jobs/purge-stuck/preview?older_than=300
```

```json
{
  "data": {
    "count": 7,
    "older_than_seconds": 300
  }
}
```

**Then purge:**

```
POST /jobs-monitor/jobs/purge-stuck
POST /jobs-monitor/api/jobs/purge-stuck
```

**Request body:**

| Field        | Type | Required | Default | Description                            |
|--------------|------|----------|---------|----------------------------------------|
| `older_than` | int  | No       | `150`   | Seconds threshold to consider job stuck |

**Response:**

```json
{
  "data": { "purged": 7 },
  "message": "Marked 7 stuck job(s) as failed."
}
```

---

## 2. Forget a Single Job

Deletes all monitoring records for one specific job UUID.

### Artisan

```bash
php artisan jobs-monitor:forget "550e8400-e29b-41d4-a716-446655440000"
```

### API

```
POST /jobs-monitor/jobs/{uuid}/forget
POST /jobs-monitor/api/jobs/{uuid}/forget
```

No request body needed. UUID is in the URL path.

**Response:**

```json
{
  "data": { "deleted": 1 },
  "message": "Job records deleted."
}
```

Returns `404` if no records found for the UUID.

---

## 3. Clear a Queue

Clears all pending jobs from a Horizon queue by running `php artisan horizon:clear --queue=queueName`.

> **Note:** Requires Laravel Horizon to be installed. Returns an error if Horizon is not available.

### Artisan

```bash
php artisan jobs-monitor:clear-queue default
```

### API

```
POST /jobs-monitor/queue/clear
POST /jobs-monitor/api/queue/clear
```

**Request body:**

| Field   | Type   | Required | Description                           |
|---------|--------|----------|---------------------------------------|
| `queue` | string | Yes      | Queue name (e.g. `default`, `emails`) |

**Response (success):**

```json
{
  "data": {
    "horizon_cleared": true,
    "queue": "default"
  },
  "message": "Horizon queue \"default\" cleared."
}
```

---

## 4. Clear Metrics

Deletes duration baselines and anomaly records. Optionally also deletes job records for a given time period.

Baselines can be rebuilt afterwards by running:
```bash
php artisan jobs-monitor:refresh-duration-baselines
```

### Artisan

```bash
# Clear baselines and anomalies only
php artisan jobs-monitor:clear-metrics

# Clear baselines, anomalies, and job records older than 7 days
php artisan jobs-monitor:clear-metrics --jobs=7d

# Available --jobs values: 24h, 7d, 30d, all

# Preview without changes
php artisan jobs-monitor:clear-metrics --jobs=30d --dry-run
```

### API

```
POST /jobs-monitor/settings/metrics/clear    ← web route
POST /jobs-monitor/api/metrics/clear         ← API route
```

**Request body:**

| Field    | Type   | Required | Description                                              |
|----------|--------|----------|----------------------------------------------------------|
| `period` | string | No       | Also delete job records: `24h`, `7d`, `30d`, `all`. Omit to skip job deletion. |

**Response:**

```json
{
  "data": {
    "baselines_deleted": 38,
    "anomalies_deleted": 12,
    "jobs_deleted": 0
  },
  "message": "Metrics cleared."
}
```

---

## Route Prefix

All routes are registered under the package's configured prefix (default: `jobs-monitor`).

| Route group | Base URL                  |
|-------------|---------------------------|
| Web         | `/jobs-monitor/...`       |
| API         | `/jobs-monitor/api/...`   |

If the host app changed the prefix in `config/jobs-monitor.php`, substitute accordingly.

---

## Named Routes Reference

| Action               | Web route name                          | API route name                              |
|----------------------|-----------------------------------------|---------------------------------------------|
| Purge stuck (preview)| `jobs-monitor.jobs.purge-stuck.preview` | `jobs-monitor.api.jobs.purge-stuck.preview` |
| Purge stuck          | `jobs-monitor.jobs.purge-stuck`         | `jobs-monitor.api.jobs.purge-stuck`         |
| Forget job           | `jobs-monitor.jobs.forget`              | `jobs-monitor.api.jobs.forget`              |
| Clear queue          | `jobs-monitor.queue.clear`              | `jobs-monitor.api.queue.clear`              |
| Clear metrics        | `jobs-monitor.settings.metrics.clear`   | `jobs-monitor.api.metrics.clear`            |
