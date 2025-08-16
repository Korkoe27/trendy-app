Deploying to Render using Docker

This repository contains a Laravel app. The provided `Dockerfile` builds frontend assets, installs PHP dependencies, and produces a runtime image that starts the Laravel server on port 10000.

Quick steps to build and run locally (PowerShell):

```powershell
# build the image
docker build -t trendy-app:latest .

# run the container (map port 10000)
docker run --rm -e APP_KEY="base64:YOURKEYHERE" -p 10000:10000 trendy-app:latest
```

Recommended Render setup

1. Push this repo to GitHub/GitLab.
2. In Render, create a new Web Service.
   - Environment: Docker
   - Branch: main (or your branch)
   - Build command: leave empty (Render will build using the Dockerfile)
   - Start command: leave empty (Dockerfile ENTRYPOINT handles it)
3. Set these environment variables in the Render service settings:
   - APP_ENV=production
   - APP_KEY (create a key locally with `php artisan key:generate --show` and paste it)
   - APP_URL (e.g. https://your-service.onrender.com)
   - DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD (point to a managed DB or Render Postgres)
   - Optional: RUN_MIGRATIONS=true to run `php artisan migrate --force` at startup

Notes and next steps

- The Dockerfile uses `php artisan serve` for simplicity. For production, consider switching the final image to `php-fpm` + Nginx for better performance.
- If you rely on queues, cron jobs, or websockets, create additional services on Render (e.g., a Background Worker) and point them at the same image.
- You may prefer to add a `render.yaml` to declaratively configure the service; I can add one if you want.

If you'd like, I can also:
- Add a `render.yaml` with a Web Service + optional Postgres service.
- Replace the `php artisan serve` pattern with `php-fpm` + `nginx` in the Dockerfile.
- Add automatic migrations with a healthcheck wrapper.
