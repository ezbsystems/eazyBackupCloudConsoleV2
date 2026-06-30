Context: @accounts/modules/addons/ms365backup/Docs/Prompts/ms365_product_agent_prompt.md 

We are working on a WHMCS development server and we are continuing development on a new ms365 Backup product. 

Context: 
We recently completed a major redeisgn of this product, the Tenant-Owner Redesign (claim unit: tenant batch). @accounts/modules/addons/ms365backup/Docs/MS365_TENANT_OWNER_REDESIGN.md 

Following that redesign, we implement some worker disk management improvements: 

Worker disk management (0.3.35):
Kopia cache eviction at batch end and under disk pressure; startup kopia/cache/ sweep
Real statfs-based disk admission instead of paper budgets only
Disk monitor with flush + graceful pause of new children below flush watermark
Age-based orphan run-dir GC; 60GB fleet template tuning
Unit tests for disk GC and pool eviction

All development process tracked in: 
@accounts/modules/addons/ms365backup/Docs/PROGRESS.md 


To help with diagnostics: 
There are Proxmox SSH keys  available that can be used for the WHMCS server SSH into the Proxmox hypervisor host as root. 

There are two ways WHMCS talks to containers:

Proxmox API (proxmox_api_url + API token) — preferred; uses LXC exec API
SSH fallback (proxmox_ssh_target + private key) — when the API path fails

For debugging, you can use the SSH path like this:

ssh -i /var/www/.ssh/ms365_proxmox_ed25519 root@192.168.92.195 \
  "pct exec 9000 -- journalctl -u ms365-backup-worker --since '60 min ago' --no-pager | tail -40"

## Where the keys live
File	Purpose
/var/www/.ssh/ms365_proxmox_ed25519
Private key (ed25519) — used for SSH
/var/www/.ssh/ms365_proxmox_ed25519.pub
Public key — must be in root@proxmox-host’s authorized_keys
/var/www/.ssh/known_hosts

## Examples: Replace 9000 with the worker’s proxmox_vmid:


SSHK=/var/www/.ssh/ms365_proxmox_ed25519
PVE=root@192.168.92.195
VMID=9000
# Last hour of worker journal
sudo -u www-data ssh -i $SSHK -o IdentitiesOnly=yes -o StrictHostKeyChecking=no $PVE \
  "pct exec $VMID -- journalctl -u ms365-backup-worker --since '60 min ago' --no-pager | tail -50"
# Filter by batch/run
sudo -u www-data ssh -i $SSHK -o IdentitiesOnly=yes $PVE \
  "pct exec $VMID -- journalctl -u ms365-backup-worker --since '2 hours ago' --no-pager | grep 66aaa73d"
# Disk / service status inside container
sudo -u www-data ssh -i $SSHK -o IdentitiesOnly=yes $PVE \
  "pct exec $VMID -- bash -c 'df -h /; systemctl status ms365-backup-worker --no-pager'"