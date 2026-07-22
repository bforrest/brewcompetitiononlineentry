# BCOEM App Health Dashboard — Design

## Goal

Give the Grafana instance that already ships in `docker-compose.yml` a starter
dashboard, auto-provisioned on `docker compose up` (no manual "import JSON"
step), that lets us compare the performance of legacy pages against
modernized Slim routes and drill into individual request traces — without
requiring a new `/metrics` app endpoint (that's tracked separately as a
future enhancement, not in scope here).

## Background

Tracing is already flowing end-to-end: `TracingMiddleware` opens one root
span per request named `"{METHOD} {path}"`, `SpanEnrichmentMiddleware` tags
that span with `http.route` (the matched Slim route name) and
`bcoem.section` (the `?section=` query param, when present). Tempo's
metrics-generator (`docker/tempo.yml`) turns every span into
`span-metrics` and `service-graph` series and remote-writes them into
Prometheus, so both raw traces (via Tempo) and aggregated rate/latency
series (via Prometheus) are already available as Grafana data sources
(`docker/grafana-datasources.yml`).

The key fact driving the panel design: in `src/Kernel/app.php`, every legacy
page (`/index.php`, `/`, the SEF catch-all) is registered under the *same*
route name, `"section"`. Every modernized route gets its own distinct name
(`registration.form`, `registration.create`, `entry.list`,
`export.download`, etc.). Grouping by `http_route` therefore naturally
produces exactly the comparison we want: one bucket for "legacy monolith
traffic" versus many granular buckets for "modernized traffic" — no extra
instrumentation needed.

## Architecture

Mirrors the existing datasource-provisioning pattern exactly:

- `docker/grafana-dashboards/bcoem-app-health.json` — the dashboard's JSON
  model (panels, queries, layout).
- `docker/grafana-dashboards-provider.yml` — a Grafana dashboard-provisioning
  config (the "file provider" type) pointing at that directory.
- `docker-compose.yml` — two new read-only mounts on the `grafana` service:
  - `docker/grafana-dashboards-provider.yml` → `/etc/grafana/provisioning/dashboards/provider.yml`
  - `docker/grafana-dashboards/` → `/var/lib/grafana/dashboards`

No changes to `web`, `tempo`, or `prometheus` services are needed — all data
these panels use is already being collected.

## Components

The dashboard has three rows, five panels total.

**Row 1 — Traffic Overview**

- *Request rate by route* (time series). Prometheus query:
  `sum by (http_route) (rate(traces_spanmetrics_calls_total{span_kind="SPAN_KIND_SERVER"}[$__rate_interval]))`.
  One series per named modern route, one combined `section` series for all
  legacy traffic.
- *Latency p50/p95 by route* (time series). Prometheus query:
  `histogram_quantile(0.95, sum by (le, http_route) (rate(traces_spanmetrics_latency_bucket{span_kind="SPAN_KIND_SERVER"}[$__rate_interval])))`
  (and the p50 equivalent). This is the direct legacy-vs-modern speed
  comparison the user asked for.

**Row 2 — Database Load**

- *DB call volume* (time series). Prometheus query:
  `sum by (span_name) (rate(traces_spanmetrics_calls_total{span_name=~"mysqli.*"}[$__rate_interval]))`.
  A spike in connection/query rate relative to the request rate in Row 1 is
  the N+1 signal already found empirically on the admin participants page
  (35 connections / 222 queries for one page load).
- *Service Graph* (Node Graph panel), backed by
  `traces_service_graph_request_total` / `..._failed_total` /
  `..._server_seconds_*`, showing the `bcoem-web → db` edge with call volume
  and error rate.

**Row 3 — Trace Explorer**

- *Trace list* (Tempo search table panel), TraceQL query
  `{span.http.route=~"$route"}`, backed by a dashboard variable `route`
  (Prometheus label-values query on `http_route` from
  `traces_spanmetrics_calls_total`, default `.*` / "All"). Selecting a row
  opens Tempo's built-in flame-graph trace view. This is a generalized,
  filterable version of the manual TraceQL lookups already done by hand for
  the registration and participants-list flows — usable for any route going
  forward.

## Data Flow

1. A request hits `web` (Apache/PHP) → OTel auto-instrumentation emits spans
   for the root request and every `mysqli_query`/`mysqli::__construct` call
   → exported via OTLP to `tempo:4318`.
2. Tempo stores the raw trace (queryable via TraceQL, Row 3) and its
   metrics-generator derives `span-metrics` and `service-graph` series from
   every span, remote-writing them to `prometheus:9090`.
3. Grafana's Prometheus datasource (already provisioned) serves Rows 1–2;
   Grafana's Tempo datasource (already provisioned) serves Row 3.
4. The dashboard JSON itself is loaded by Grafana's file-based dashboard
   provisioner at container startup — no database state, no manual import,
   consistent with how the two datasources already provision themselves.

Default time range: last 1 hour. Default refresh: 30s (reasonable for local
dev without hammering the browser).

## Error Handling / Empty States

- On a fresh `docker compose up` with zero traffic, all five panels
  legitimately show "No data" — this is correct, not a bug, since no spans
  have been generated yet. No special-casing needed; Grafana's default
  empty-state rendering is sufficient.
- If Tempo's metrics-generator or the Prometheus remote-write receiver is
  ever misconfigured (regressing the fixes already made to
  `docker/tempo.yml` / `docker-compose.yml`), Rows 1–2 go empty while Row 3
  (raw Tempo search) keeps working, since it queries Tempo directly rather
  than through the Prometheus pipeline. That split is a deliberate resilience
  property of the design, not accidental.

## Testing / Verification Plan

1. `docker compose up` — confirm Grafana starts with no provisioning errors
   in `docker compose logs grafana`, and the "BCOEM App Health" dashboard
   appears in the dashboard list without a manual import.
2. Re-run the two flows already exercised manually this session (register a
   new entrant via `POST /register`; load the admin participants list via
   `?section=admin&go=participants`) and confirm:
   - Row 1 shows a `registration.create` (or `section`, for the admin page)
     series appear with nonzero rate.
   - Row 2's DB call volume panel shows a spike coinciding with the
     participants-page load (the known N+1 pattern).
   - Row 3's trace list, filtered to the relevant route, returns the
     specific trace and opens its flame graph.
3. Confirm the dashboard survives `docker compose down && docker compose up`
   (provisioning is file-based, not dependent on Grafana's SQLite state
   persisting).

## Out of Scope

- A `/metrics` Prometheus exporter endpoint in the PHP app (tracked
  separately as a future enhancement per explicit prior instruction).
- Alerting rules / notification channels.
- Per-route dedicated panels beyond the generic filterable trace list (an
  explicit earlier decision — one generic list over N hardcoded panels).
