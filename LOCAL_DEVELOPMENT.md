# Local development

Use the development Compose file when working locally:

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --no-build
```

The development configuration bind-mounts the repository into the Apache
container. PHP, HTML, CSS, JavaScript, and configuration changes therefore
appear at `http://localhost:8080` without rebuilding the app image. Use this
command for normal local development; do not use the production Compose file
alone, because it serves code baked into the image.

If the required images are not already available, build them once with:

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Stop the local stack with:

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
```

Do not use `docker-compose.dev.yml` on EC2 or Jhub. The production workflow
continues to use only `docker-compose.yml` and the baked Docker image.
