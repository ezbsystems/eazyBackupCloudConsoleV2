# Proxmox LXC fleet for ms365-backup-worker

Whale-scale workers stream Microsoft Graph content into Kopia via a virtual overlay. **Local disk is not sized to tenant TB** — provision modest containers and scale **out** (more workers + sharding), not **up**.

## Default fleet container sizing

| Resource | Default | Notes |
|----------|---------|-------|
| RAM | **8192 MB** (8 GB) | Matches `ram_budget_mib: 6144` in template config |
| CPU | **4 cores** | Matches `max_cpu_cores: 3` admission headroom |
| rootfs | **32 GB** | Matches `disk_budget_mib: 24576`; Kopia cache + run metadata only |

**Comfort tier** (busier single node): 16 GB RAM, 8 cores, 64 GB disk — raise `ram_budget_mib` / `disk_budget_mib` / `max_concurrent_runs` in `config.yaml` to match.

**Dev / smoke**: 4096 MB RAM, 2 cores, 16 GB disk — lower budgets accordingly.

---

## Step 1 — WHMCS server prep (`192.168.92.79`)

Run on the WHMCS host before building the template.

### 1.1 Confirm whale-scale code and binary

```bash
ls /var/www/eazybackup.ca/ms365-backup-worker/deploy/proxmox/config.yaml.template
ls /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/storage/worker-releases/0.1.11/ms365-backup-worker
```

Build **0.1.11+** via **Addons → MS365 Backup → Worker Fleet → Builds** if missing.

### 1.2 Addon settings

**Setup → Addon Modules → MS365 Backup → Save** (applies whale-scale defaults):

- Engine mode: `kopia`
- Worker lease seconds: `7200`
- Sharding enabled: yes
- Proxmox API URL: `https://192.168.92.195:8006/api2/json`
- Proxmox node: *(hostname of Proxmox host where the golden template lives)*
- LXC template VMID: `9010`
- Proxmox API token: dedicated user with fleet role (see **API token permissions** below)

### 1.2a Proxmox API token permissions

Fleet scale-up calls `POST /nodes/{source}/lxc/{template}/clone`. The token must have:

```text
/vms/9010          → VM.Clone
/storage/local-lvm → Datastore.Allocate, Datastore.AllocateSpace  (on each node)
/vms               → VM.Allocate, VM.Config, VM.PowerMgmt, VM.Monitor  (propagate)
```

Example (CLI on Proxmox):

```bash
pveum role add MS365Fleet -privs "VM.Clone,VM.Allocate,VM.Config,VM.PowerMgmt,VM.Monitor,Datastore.Allocate,Datastore.AllocateSpace"
pveum aclmod /vms/9010 -user whmcs-fleet@pve -role MS365Fleet
pveum aclmod /storage/local-lvm -user whmcs-fleet@pve -role MS365Fleet
pveum aclmod /vms -user whmcs-fleet@pve -role MS365Fleet
pveum user token add whmcs-fleet@pve whmcs-fleet -privsep 0
```

For multi-node clusters, repeat storage ACLs per node or use `/storage` with propagate. Cross-node clone from `proxmox_node` to another host uses the `target` parameter — both nodes need storage allocate rights.

Per-node local templates: set WHMCS `proxmox_template_vmid_map` JSON, e.g. `{"yow-pve-r630-01":9010,"yow-pve-r640-01":9010}`.

### 1.3 Crons

```bash
crontab -e
```

```cron
* * * * * php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_worker_build_runner.php >> /var/log/ms365_build_runner.log 2>&1
*/2 * * * * php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_worker_fleet.php >> /var/log/ms365_fleet.log 2>&1
0 3 * * 0 php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_kopia_maintenance.php >> /var/log/ms365_kopia_maint.log 2>&1
```

### 1.4 Copy artifacts to Proxmox

```bash
export MS365_WORKER_TOKEN='your-fleet-token-from-addon-settings'

BINARY=/var/www/eazybackup.ca/accounts/modules/addons/ms365backup/storage/worker-releases/0.1.11/ms365-backup-worker

tar czf /tmp/ms365-backup-worker-src.tar.gz -C /var/www/eazybackup.ca ms365-backup-worker \
  --exclude='ms365-backup-worker/.git'

scp /tmp/ms365-backup-worker-src.tar.gz root@192.168.92.195:/tmp/
scp "$BINARY" root@192.168.92.195:/tmp/ms365-backup-worker
```

Use your WHMCS API base (LAN example):

```bash
export MS365_WORKER_API_BASE='http://192.168.92.79/modules/addons/cloudstorage/api'
```

---

## Step 2 — Create builder container on Proxmox (`192.168.92.195`)

SSH to the Proxmox host as root.

```bash
export TEMPLATE_VMID=9010
export STORAGE=local-lvm
export BRIDGE=vmbr0
export VZTEMPLATE=local:vztmpl/debian-12-standard_12.12-1_amd64.tar.zst
export MS365_WORKER_TOKEN='your-fleet-token'
export MS365_WORKER_API_BASE='http://192.168.92.79/modules/addons/cloudstorage/api'
```

Download the OS template if needed:

```bash
pveam update
pveam download local debian-12-standard_12.12-1_amd64.tar.zst
```

Remove any old VMID 9010 builder (skip if 9010 does not exist):

```bash
pct stop 9010 2>/dev/null || true
pct destroy 9010 2>/dev/null || true
```

Create the builder CT (**8 GB RAM, 4 cores, 32 GB disk**):

```bash
pct create $TEMPLATE_VMID $VZTEMPLATE \
  --hostname ms365-template \
  --memory 8192 \
  --cores 4 \
  --rootfs ${STORAGE}:32 \
  --net0 name=eth0,bridge=$BRIDGE,ip=dhcp \
  --unprivileged 1 \
  --onboot 0

pct start $TEMPLATE_VMID
sleep 20
pct exec $TEMPLATE_VMID -- ping -c2 192.168.92.79
```

---

## Step 3 — Install worker inside the builder CT

Still on the Proxmox host:

```bash
pct push $TEMPLATE_VMID /tmp/ms365-backup-worker-src.tar.gz /tmp/ms365-backup-worker-src.tar.gz
pct push $TEMPLATE_VMID /tmp/ms365-backup-worker /tmp/ms365-backup-worker

pct exec $TEMPLATE_VMID -- bash -c '
  mkdir -p /opt/ms365-backup-worker
  tar xzf /tmp/ms365-backup-worker-src.tar.gz -C /opt --strip-components=0
'

pct exec $TEMPLATE_VMID -- bash -c "
  export MS365_WORKER_TOKEN='${MS365_WORKER_TOKEN}'
  export MS365_WORKER_API_BASE='${MS365_WORKER_API_BASE}'
  export STRIP_SOURCE=1
  bash /opt/ms365-backup-worker/deploy/proxmox/template-setup.sh
"
```

Verify:

```bash
pct exec $TEMPLATE_VMID -- bash -c '
  ls -la /var/lib/ms365-backup-worker/bin/ms365-backup-worker
  grep -E "ram_budget|disk_budget|max_concurrent" /etc/ms365-backup-worker/config.yaml
  ls -la /etc/systemd/system/ms365-backup-worker.service.d/environment.conf
  test ! -d /opt/ms365-backup-worker && echo "source stripped OK"
  systemctl is-enabled ms365-backup-worker
'
```

Expected config highlights:

- `max_concurrent_runs: 4`
- `ram_budget_mib: 6144`
- `disk_budget_mib: 24576`

---

## Step 4 — Convert to Proxmox template

On the Proxmox host:

```bash
pct stop $TEMPLATE_VMID
pct template $TEMPLATE_VMID
pct list | grep 9010
```

VMID **9010** is now the golden template. Set **Proxmox LXC template VMID** = `9010` in WHMCS addon settings.

---

## Step 5 — Clone workers and deploy binary

### Option A — Manual two-node fleet

```bash
for pair in "9011 ms365-worker-01" "9012 ms365-worker-02"; do
  set -- $pair
  pct clone 9010 $1 --hostname $2 --full 1 --storage local-lvm
  pct set $1 --env PROXMOX_VMID=$1
  pct start $1
done
```

Optional fixed hostnames in config (only for manual nodes):

```bash
pct exec 9011 -- sed -i 's/^  hostname: .*/  hostname: "ms365-worker-01"/' /etc/ms365-backup-worker/config.yaml
pct exec 9012 -- sed -i 's/^  hostname: .*/  hostname: "ms365-worker-02"/' /etc/ms365-backup-worker/config.yaml
```

### Option B — Autoscale

Ensure fleet cron runs; `ProxmoxProvisioner` clones 9010 when queue depth requires nodes.

### Deploy whale-scale binary

On WHMCS: **Worker Fleet → Deployments** → deploy release **0.1.11** (rolling). Clones self-update via heartbeat — no `pct push` for routine releases.

### Verify registration

```bash
# On WHMCS — list registered worker nodes
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/ms365_worker_nodes.php

# Print worker API token (for template / env setup)
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/ms365_worker_token.php
```

Or **Worker Fleet → Nodes** in the admin UI.

---

## What the template includes

| Path | Purpose |
|------|---------|
| `/var/lib/ms365-backup-worker/bin/ms365-backup-worker` | Worker binary (self-update target) |
| `/var/lib/ms365-backup-worker/runs` | Run metadata / checkpoints (not full backup staging) |
| `/var/lib/ms365-backup-worker/kopia` | Kopia repo config cache |
| `/etc/ms365-backup-worker/config.yaml` | Budget-tuned settings (`hostname` / `node_id` empty) |
| `.../ms365-backup-worker.service.d/environment.conf` | `MS365_WORKER_TOKEN`, `MS365_WORKER_API_BASE` |

---

## Autoscale post-clone

`ProxmoxProvisioner::cloneWorkerLxc()` after clone:

1. `PUT .../lxc/{vmid}/config` with `hostname=ms365-worker-<hash>` (schema-valid keys only; the LXC config REST API does **not** accept `env` on PVE 8+)
2. Inserts a WHMCS `registering` row with `proxmox_vmid` **before** start (so the worker can adopt it on first register)
3. Starts the container

The worker does **not** need `PROXMOX_VMID` in the container environment for autoscale: it registers with the Proxmox hostname and WHMCS matches the pre-created `registering` row (`WorkerNodeRepository::adoptProvisioningRow`).

Leave `hostname` empty in the template so autoscale clones adopt the Proxmox hostname.

**Manual clones** may still use `pct set --env PROXMOX_VMID=<vmid>` (CLI-only on some nodes) or set `proxmox_vmid` in config.yaml.

---

## Scaling larger containers

If you clone with **16 GB / 64 GB** CTs, update `config.yaml` on those nodes (or bake a separate template):

```yaml
worker:
  max_concurrent_runs: 6
  ram_budget_mib: 12288
  disk_budget_mib: 51200
  max_cpu_cores: 6
```

**Always keep budget fields ≤ actual CT resources.**

---

## Legacy install path migration

Workers created before `/var/lib/ms365-backup-worker/bin` need a one-time migration — see `migrate-worker-install-path.sh`.
