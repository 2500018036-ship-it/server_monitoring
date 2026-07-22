# Server Monitoring Agent

Install on the Linux VPS:

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

The agent posts real metrics every 3 seconds to `SM_API_URL` with `X-API-Key`.
