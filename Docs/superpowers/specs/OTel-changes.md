## Swapped to Tempo + Prometheus + Grafana:

### Changes made:

- Replaced Jaeger with Tempo (port 4318) — receives same OTLP/http/protobuf exports
- Added Prometheus to scrape metrics from the app's /metrics endpoint
- Added Grafana as the unified dashboard with pre-configured datasources
- Updated vhost.conf to point OTEL_EXPORTER_OTLP_ENDPOINT to tempo:4318
- Created three config files in