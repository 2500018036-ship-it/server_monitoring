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

The agent posts real metrics every 1 second to `SM_API_URL` with `X-API-Key`.

`SM_API_URL` must be reachable from the VPS. Do not use `localhost` unless the web app runs on the same server as the agent.

Recommended admin flow:

1. Add the VPS credential in SSH Config.
2. Set `APP_BASE_URL` or `AGENT_PUBLIC_BASE_URL` to a URL reachable from the VPS.
3. Open SSH Config and click Install/Repair Agent.

The web app will upload `monitoring_agent.py`, write `/etc/server-monitoring-agent.env`, install the systemd service, and restart `monitoring-agent` automatically through SSH.
