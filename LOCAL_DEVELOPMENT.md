# Local development

Use the development Compose file when working locally:

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

The development configuration bind-mounts the repository into the Apache
container. PHP, HTML, CSS, JavaScript, and configuration changes therefore
appear at `http://localhost:8080` without rebuilding the app image.

The first start may build the meeting-server image if it is not already
available. Rebuild only when changing a Dockerfile, Composer dependencies, or
other files copied into an image:

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Stop the local stack with:

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
```

Do not use `docker-compose.dev.yml` on EC2 or Jhub. The production workflow
continues to use only `docker-compose.yml` and the baked Docker image.
