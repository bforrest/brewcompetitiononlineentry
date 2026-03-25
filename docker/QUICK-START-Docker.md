Usage:

Scenario	Command
Existing install (normal)	docker compose up
Fresh install (setup wizard)	docker compose -f docker-compose.yml -f docker-compose.install.yml up
Wipe DB + fresh install	docker compose down -v then the install command above
The base docker-compose.yml defaults to FALSE (safe), so a plain docker compose up never accidentally exposes the setup wizard.