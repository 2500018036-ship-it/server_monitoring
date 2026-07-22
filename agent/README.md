# Server Monitoring Agent

Manual install on the Linux VPS:

```bash
sudo mkdir -p /opt/server-monitoring-agent
sudo cp monitoring_agent.py /opt/server-monitoring-agent/
sudo chmod +x /opt/server-monitoring-agent/monitoring_agent.py
sudo cp server-monitoring-agent.env.example /etc/server-monitoring-agent.env
sudo nano /etc/server-monitoring-agent.env
sudo cp monitoring-agent.service /etc/systemd/system/monitoring-agent.service
sudo systemctl daemon-reload
sudo systemctl enable --now monitoring-agent
sudo systemctl status monitoring-agent
```

Optional dependency for richer metrics:

```bash
sudo apt update
sudo apt install -y python3-psutil
```

The agent posts core metrics on a tiered cadence:

- `SM_CORE_INTERVAL=3` for CPU, RAM, storage, and network
- `SM_SERVICE_INTERVAL=5` for service status
- `SM_HEAVY_INTERVAL=10` for processes, Docker, database, website probes, and logs

`SM_INTERVAL` remains the loop tick and should stay at `1` unless you know you need something else.

Each agent should use a unique `SM_API_KEY`. The SSH installer now provisions a per-server key automatically.

`SM_API_URL` must be reachable from the VPS. Do not use `localhost` unless the web app runs on the same server as the agent.
Use `https://...` for `SM_API_URL`; non-HTTPS endpoints should be treated as unsupported for remote agents.

Recommended admin flow:

1. Add the VPS credential in SSH Config.
2. Set `APP_BASE_URL` or `AGENT_PUBLIC_BASE_URL` to a URL reachable from the VPS.
3. Open SSH Config and click Install/Repair Agent.

The web app will upload `monitoring_agent.py`, write `/etc/server-monitoring-agent.env`, install the systemd service, and restart `monitoring-agent` automatically through SSH.
