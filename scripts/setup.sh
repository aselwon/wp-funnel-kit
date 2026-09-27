#!/usr/bin/env sh
set -eu
docker compose up -d
attempt=0
until docker compose run --rm cli core version >/dev/null 2>&1; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 30 ]; then echo 'WordPress did not become ready. Check docker compose logs.' >&2; exit 1; fi
  sleep 3
done
if ! docker compose run --rm cli core is-installed; then
  docker compose run --rm cli core install --url=http://localhost:8080 --title='FunnelKit Demo' --admin_user=demo --admin_password=local-demo-change-me --admin_email=demo@example.test --skip-email
fi
docker compose run --rm cli plugin activate funnelkit-lite
if ! docker compose run --rm cli post list --post_type=page --name=funnel-demo --field=ID | grep -q '[0-9]'; then
  docker compose run --rm cli post create --post_type=page --post_status=publish --post_title='Funnel Demo' --post_name=funnel-demo --post_content='[funnelkit id="1"]'
fi
docker compose run --rm cli post list --post_type=page --name=funnel-demo --fields=ID,url
