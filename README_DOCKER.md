# Docker Setup

1. Copy `.env.example` to `.env`, then adjust passwords and URLs.
2. Start the stack:

```bash
docker compose up -d --build
```

3. Open:

```text
http://localhost:8080/
```

Default seeded admin from `database/server_monitoring.sql`:

```text
username: admin
email: admin@servermonitoring.local
```

Change the default password after first login.

The app container uses Apache + PHP 8.2. MariaDB data, uploaded files, and application logs are stored in Docker volumes.
