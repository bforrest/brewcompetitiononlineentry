# BCOEM App Health Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "BCOEM App Health" Grafana dashboard that is auto-provisioned on `docker compose up`, comparing legacy vs. modernized route traffic/latency, DB call volume, the service graph, and a filterable trace list — using data already flowing into Tempo/Prometheus.

**Architecture:** Two new files under `docker/` (a dashboard-provisioning config and the dashboard JSON model itself), one small addition to the existing `docker/grafana-datasources.yml` (pinning stable datasource UIDs so the dashboard JSON can reference them), and two new read-only volume mounts on the `grafana` service in `docker-compose.yml` — the exact same pattern already used for datasource provisioning.

**Tech Stack:** Grafana file-based provisioning (dashboards + datasources), Grafana dashboard JSON schema v39, PromQL (Prometheus datasource), TraceQL (Tempo datasource), Docker Compose.

## Global Constraints

- Dashboard must load automatically on `docker compose up` — no manual "import JSON" step (per spec).
- No changes to `web`, `tempo`, or `prometheus` services — all required data is already being collected (per spec).
- Out of scope: `/metrics` PHP exporter endpoint, alerting rules, per-route dedicated panels (per spec's "Out of Scope" section).
- Default dashboard time range: last 1 hour. Default refresh: 30s (per spec).
- Follow the existing provisioning pattern exactly: `docker/grafana-datasources.yml` mounted read-only into `/etc/grafana/provisioning/...` is the template for how the new files must be wired up.

---

### Task 1: Pin stable datasource UIDs and wire Tempo's service-graph link

The dashboard JSON (Task 3) needs to reference Prometheus and Tempo by a fixed UID. Today's `docker/grafana-datasources.yml` doesn't set explicit UIDs, so Grafana would assign random ones — any dashboard JSON referencing a datasource by UID would break. The Service Graph panel additionally needs Tempo's datasource config to know which Prometheus datasource holds the service-graph metrics (`jsonData.serviceMap.datasourceUid`), which isn't set today either.

**Files:**
- Modify: `docker/grafana-datasources.yml`

**Interfaces:**
- Produces: two datasources with fixed UIDs `prometheus` and `tempo`, consumed by every panel target in Task 3's dashboard JSON via `"datasource": {"type": "...", "uid": "prometheus"|"tempo"}`.

- [ ] **Step 1: Add explicit `uid` fields and the `serviceMap` link**

Edit `docker/grafana-datasources.yml` to match:

```yaml
apiVersion: 1

datasources:
  - name: Tempo
    type: tempo
    uid: tempo
    access: proxy
    orgId: 1
    url: http://tempo:3200
    jsonData:
      nodeGraph:
        enabled: true
      lokiSearch:
        enabled: true
      serviceMap:
        datasourceUid: prometheus
    isDefault: true

  - name: Prometheus
    type: prometheus
    uid: prometheus
    access: proxy
    orgId: 1
    url: http://prometheus:9090
    jsonData:
      timeInterval: 15s
    isDefault: false
```

(Only additions are the two `uid:` lines and the `serviceMap:` block — nothing else changes.)

- [ ] **Step 2: Validate YAML syntax**

Run: `python3 -c "import yaml; yaml.safe_load(open('docker/grafana-datasources.yml'))" && echo "valid yaml"`
Expected: `valid yaml`

- [ ] **Step 3: Restart Grafana and confirm the UIDs took effect**

Run: `docker compose up -d grafana && sleep 3 && curl -s -u admin:admin http://localhost:3000/api/datasources | python3 -m json.tool`
Expected: JSON array containing one object with `"uid": "tempo"` and one with `"uid": "prometheus"`.

- [ ] **Step 4: Commit**

```bash
git add docker/grafana-datasources.yml
git commit -m "feat: pin Grafana datasource UIDs and link Tempo service graph to Prometheus"
```

---

### Task 2: Add the dashboard file-provisioning config

Grafana needs a provisioning config telling it to load dashboard JSON files from a directory — this is the dashboard equivalent of `grafana-datasources.yml`, but there is no existing one to modify; it's new.

**Files:**
- Create: `docker/grafana-dashboards-provider.yml`

**Interfaces:**
- Produces: a file-provider watching `/var/lib/grafana/dashboards` inside the Grafana container. Consumed by Task 4 (docker-compose mount of `docker/grafana-dashboards/` to that same path) and by Task 3 (the JSON file that must live in `docker/grafana-dashboards/`).

- [ ] **Step 1: Write the provider config**

Create `docker/grafana-dashboards-provider.yml`:

```yaml
apiVersion: 1

providers:
  - name: BCOEM
    orgId: 1
    folder: ''
    type: file
    disableDeletion: false
    updateIntervalSeconds: 30
    allowUiUpdates: true
    options:
      path: /var/lib/grafana/dashboards
```

- [ ] **Step 2: Validate YAML syntax**

Run: `python3 -c "import yaml; yaml.safe_load(open('docker/grafana-dashboards-provider.yml'))" && echo "valid yaml"`
Expected: `valid yaml`

- [ ] **Step 3: Commit**

```bash
git add docker/grafana-dashboards-provider.yml
git commit -m "feat: add Grafana dashboard file-provisioning config"
```

---

### Task 3: Write the "BCOEM App Health" dashboard JSON

The dashboard itself: 3 rows / 5 panels, plus a `$route` template variable used by the trace-list panel, as specified in the design doc.

**Files:**
- Create: `docker/grafana-dashboards/bcoem-app-health.json`

**Interfaces:**
- Consumes: datasource UIDs `prometheus` and `tempo` (produced by Task 1).
- Produces: a dashboard with `uid: "bcoem-app-health"`, consumed by Task 5's verification steps (`GET /api/dashboards/uid/bcoem-app-health`).

- [ ] **Step 1: Write the dashboard JSON**

Create `docker/grafana-dashboards/bcoem-app-health.json`:

```json
{
  "uid": "bcoem-app-health",
  "title": "BCOEM App Health",
  "schemaVersion": 39,
  "version": 1,
  "editable": true,
  "timezone": "browser",
  "tags": ["bcoem", "observability"],
  "time": { "from": "now-1h", "to": "now" },
  "refresh": "30s",
  "templating": {
    "list": [
      {
        "name": "route",
        "type": "query",
        "label": "Route",
        "datasource": { "type": "prometheus", "uid": "prometheus" },
        "query": {
          "query": "label_values(traces_spanmetrics_calls_total{span_kind=\"SPAN_KIND_SERVER\"}, http_route)",
          "refId": "StandardVariableQuery"
        },
        "refresh": 2,
        "includeAll": true,
        "multi": false,
        "allValue": ".*",
        "current": { "selected": true, "text": "All", "value": "$__all" }
      }
    ]
  },
  "panels": [
    {
      "id": 1,
      "type": "timeseries",
      "title": "Request rate by route",
      "description": "Legacy pages all share the route name \"section\"; modernized routes (registration.create, entry.list, etc.) get their own series.",
      "gridPos": { "x": 0, "y": 0, "w": 12, "h": 8 },
      "datasource": { "type": "prometheus", "uid": "prometheus" },
      "fieldConfig": { "defaults": { "unit": "reqps" }, "overrides": [] },
      "targets": [
        {
          "refId": "A",
          "datasource": { "type": "prometheus", "uid": "prometheus" },
          "expr": "sum by (http_route) (rate(traces_spanmetrics_calls_total{span_kind=\"SPAN_KIND_SERVER\"}[$__rate_interval]))",
          "legendFormat": "{{http_route}}"
        }
      ]
    },
    {
      "id": 2,
      "type": "timeseries",
      "title": "Latency p50/p95 by route",
      "description": "Direct legacy-vs-modern speed comparison: one blended \"section\" series versus per-route modern series.",
      "gridPos": { "x": 12, "y": 0, "w": 12, "h": 8 },
      "datasource": { "type": "prometheus", "uid": "prometheus" },
      "fieldConfig": { "defaults": { "unit": "s" }, "overrides": [] },
      "targets": [
        {
          "refId": "A",
          "datasource": { "type": "prometheus", "uid": "prometheus" },
          "expr": "histogram_quantile(0.50, sum by (le, http_route) (rate(traces_spanmetrics_latency_bucket{span_kind=\"SPAN_KIND_SERVER\"}[$__rate_interval])))",
          "legendFormat": "p50 {{http_route}}"
        },
        {
          "refId": "B",
          "datasource": { "type": "prometheus", "uid": "prometheus" },
          "expr": "histogram_quantile(0.95, sum by (le, http_route) (rate(traces_spanmetrics_latency_bucket{span_kind=\"SPAN_KIND_SERVER\"}[$__rate_interval])))",
          "legendFormat": "p95 {{http_route}}"
        }
      ]
    },
    {
      "id": 3,
      "type": "timeseries",
      "title": "DB call volume",
      "description": "Spikes relative to request rate (panel above) are the N+1 signal found on the admin participants page (35 connections / 222 queries for one page load).",
      "gridPos": { "x": 0, "y": 8, "w": 12, "h": 8 },
      "datasource": { "type": "prometheus", "uid": "prometheus" },
      "fieldConfig": { "defaults": { "unit": "reqps" }, "overrides": [] },
      "targets": [
        {
          "refId": "A",
          "datasource": { "type": "prometheus", "uid": "prometheus" },
          "expr": "sum by (span_name) (rate(traces_spanmetrics_calls_total{span_name=~\"mysqli.*\"}[$__rate_interval]))",
          "legendFormat": "{{span_name}}"
        }
      ]
    },
    {
      "id": 4,
      "type": "nodeGraph",
      "title": "Service Graph",
      "gridPos": { "x": 12, "y": 8, "w": 12, "h": 8 },
      "datasource": { "type": "tempo", "uid": "tempo" },
      "targets": [
        {
          "refId": "A",
          "datasource": { "type": "tempo", "uid": "tempo" },
          "queryType": "serviceMap"
        }
      ]
    },
    {
      "id": 5,
      "type": "table",
      "title": "Trace list",
      "description": "Filter by route below, click a row to open its flame graph.",
      "gridPos": { "x": 0, "y": 16, "w": 24, "h": 8 },
      "datasource": { "type": "tempo", "uid": "tempo" },
      "targets": [
        {
          "refId": "A",
          "datasource": { "type": "tempo", "uid": "tempo" },
          "queryType": "traceql",
          "query": "{span.http.route=~\"$route\"}",
          "tableType": "traces",
          "limit": 20
        }
      ]
    }
  ]
}
```

- [ ] **Step 2: Validate JSON syntax**

Run: `python3 -m json.tool docker/grafana-dashboards/bcoem-app-health.json > /dev/null && echo "valid json"`
Expected: `valid json`

- [ ] **Step 3: Commit**

```bash
git add docker/grafana-dashboards/bcoem-app-health.json
git commit -m "feat: add BCOEM App Health dashboard JSON model"
```

---

### Task 4: Mount the dashboard files into the Grafana container

Wire Tasks 2 and 3's files into the running container, mirroring the existing `grafana-datasources.yml` mount.

**Files:**
- Modify: `docker-compose.yml:81-83` (the `grafana` service's `volumes:` block)

**Interfaces:**
- Consumes: `docker/grafana-dashboards-provider.yml` (Task 2) and `docker/grafana-dashboards/` (Task 3).

- [ ] **Step 1: Add the two mounts**

In `docker-compose.yml`, change the `grafana` service's `volumes:` block from:

```yaml
    volumes:
      - grafana_data:/var/lib/grafana
      - ./docker/grafana-datasources.yml:/etc/grafana/provisioning/datasources/datasources.yml:ro
```

to:

```yaml
    volumes:
      - grafana_data:/var/lib/grafana
      - ./docker/grafana-datasources.yml:/etc/grafana/provisioning/datasources/datasources.yml:ro
      - ./docker/grafana-dashboards-provider.yml:/etc/grafana/provisioning/dashboards/provider.yml:ro
      - ./docker/grafana-dashboards:/var/lib/grafana/dashboards:ro
```

- [ ] **Step 2: Validate compose file syntax**

Run: `docker compose config -q && echo "valid compose file"`
Expected: `valid compose file`

- [ ] **Step 3: Commit**

```bash
git add docker-compose.yml
git commit -m "feat: mount BCOEM App Health dashboard provisioning into Grafana"
```

---

### Task 5: End-to-end verification

Bring the whole stack up from a clean state, confirm the dashboard provisions automatically, generate real traffic through the two flows already validated manually earlier (register an entrant; load the admin participants list), and confirm each panel actually has data.

**Files:** None (verification only).

**Interfaces:** None — this task consumes the fully wired-up stack from Tasks 1–4 and produces no new files.

- [ ] **Step 1: Full stack restart**

Run: `docker compose down && docker compose up -d`
Expected: all services (`web`, `tempo`, `prometheus`, `grafana`, `db`) report `Started`/`Healthy` in `docker compose ps` within ~30s.

- [ ] **Step 2: Confirm the dashboard provisioned without manual import**

Run: `sleep 5 && curl -s -u admin:admin http://localhost:3000/api/dashboards/uid/bcoem-app-health | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['dashboard']['title'])"`
Expected: `BCOEM App Health`

If this 404s, run `docker compose logs grafana | tail -50` and check for a provisioning error (e.g., a JSON syntax mistake missed in Task 3 Step 2) before proceeding.

- [ ] **Step 3: Generate traffic — register a new entrant**

```bash
curl -s -c /tmp/reg_cookies.txt -o /dev/null -w "%{http_code}\n" \
  -e "http://localhost:8080/index.php" \
  --data-urlencode "user_name=dashboard-verify-$(date +%s)@example.com" \
  --data-urlencode "password=DashVerify123!" \
  --data-urlencode "userQuestion=1" \
  --data-urlencode "userQuestionAnswer=hops" \
  --data-urlencode "brewerFirstName=Dash" \
  --data-urlencode "brewerLastName=Verify" \
  --data-urlencode "brewerAddress=1 Test St" \
  --data-urlencode "brewerCity=Testville" \
  --data-urlencode "brewerZip=75001" \
  --data-urlencode "brewerCountry=US" \
  --data-urlencode "brewerPhone1=555-555-0100" \
  http://localhost:8080/register
```
Expected: `302` (redirect to `/entries/my` on success).

- [ ] **Step 4: Generate traffic — admin participants list**

```bash
curl -s -c /tmp/admin_cookies.txt -o /dev/null -w "%{http_code}\n" \
  -e "http://localhost:8080/index.php" \
  --data-urlencode "loginUsername=user.baseline@brewingcompetitions.com" \
  --data-urlencode "loginPassword=bcoem" \
  --data-urlencode "action=login" \
  "http://localhost:8080/includes/process.inc.php?section=login"

curl -s -b /tmp/admin_cookies.txt -o /dev/null -w "%{http_code}\n" \
  "http://localhost:8080/index.php?section=admin&go=participants"
```
Expected: both requests return `200` or `302` (not `4xx`/`5xx`).

- [ ] **Step 5: Confirm span-metrics reached Prometheus**

Run: `sleep 20 && curl -s 'http://localhost:9090/api/v1/query?query=sum(rate(traces_spanmetrics_calls_total%7Bspan_kind%3D%22SPAN_KIND_SERVER%22%7D%5B5m%5D))' | python3 -m json.tool`
Expected: `"result"` array is non-empty (at least one series with a nonzero value). The 20s sleep accounts for Tempo's metrics-generator flush interval plus Prometheus's scrape/remote-write lag.

- [ ] **Step 6: Confirm the DB call volume panel has data**

Run: `curl -s 'http://localhost:9090/api/v1/query?query=sum(rate(traces_spanmetrics_calls_total%7Bspan_name%3D~%22mysqli.%2A%22%7D%5B5m%5D))' | python3 -m json.tool`
Expected: `"result"` array is non-empty, confirming the participants-list N+1 query load is visible.

- [ ] **Step 7: Confirm the trace-list panel returns real traces**

Run: `curl -s -G 'http://localhost:3200/api/search' --data-urlencode 'q={span.http.route="registration.create"}' | python3 -m json.tool | head -20`
Expected: `"traces"` array contains at least one entry (the registration request from Step 3).

- [ ] **Step 8: Visual confirmation**

Open `http://localhost:3000/d/bcoem-app-health` in a browser (Grafana login: `admin`/`admin`). Confirm all 5 panels render without a "Panel plugin error" or persistent "No data" (some legitimate "No data" is fine for series with zero traffic, e.g. if only 2 routes were exercised). Pay particular attention to:
- The Service Graph panel (Panel 4) — Tempo/Prometheus service-graph JSON schemas are the most likely to need a small adjustment; if it errors, check the panel's query inspector output against `jsonData.serviceMap.datasourceUid` in `docker/grafana-datasources.yml` (Task 1).
- The Trace list panel (Panel 5) — if it errors, check the query inspector's raw response against the `queryType`/`tableType` fields on Panel 5's target in `docker/grafana-dashboards/bcoem-app-health.json` (Task 3); adjust and re-save if Grafana's version expects different field names.

If either panel needs adjustment, edit the relevant file, re-run Task 3 Step 2 or Task 1 Step 2's validation, `docker compose restart grafana`, and repeat this step.

- [ ] **Step 9: Clean up temp files and commit any adjustments**

```bash
rm -f /tmp/reg_cookies.txt /tmp/admin_cookies.txt
git status
```

If Step 8 required edits, stage and commit them:

```bash
git add docker/grafana-dashboards/bcoem-app-health.json docker/grafana-datasources.yml
git commit -m "fix: adjust dashboard panel queries after end-to-end verification"
```

If no edits were needed, no commit is required for this task.

---

## Self-Review Notes

- **Spec coverage:** All 3 rows / 5 panels from the spec are implemented (Task 3). The provisioning mechanism matches the spec's architecture section exactly (Tasks 2 and 4). The spec's default time range (1h) and refresh (30s) are set in Task 3 Step 1. The spec's error-handling/empty-state expectations are exercised in Task 5 Steps 5-8. Out-of-scope items (`/metrics` exporter, alerting, per-route panels) are not touched anywhere in this plan.
- **Placeholder scan:** No TBDs; every step has literal file content or an exact command with expected output.
- **Type/name consistency:** Datasource UIDs `prometheus`/`tempo` (Task 1) match every `datasource.uid` reference in Task 3's JSON. The dashboard `uid: "bcoem-app-health"` (Task 3) matches the API path used in Task 5 Step 2. The provider's `options.path: /var/lib/grafana/dashboards` (Task 2) matches the mount target in Task 4 Step 1.
