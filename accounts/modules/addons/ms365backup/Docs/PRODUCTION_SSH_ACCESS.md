# Production WHMCS SSH access (dev → prod)

Development WHMCS (`dev.eazybackup.ca`) and production WHMCS (`192.168.92.75`) are separate hosts. Most fleet operations from dev use the **remote fleet API** (`FleetRemoteClient` → `fleet_remote.php` with `ms365_fleet_deploy_shared_secret`). For full-shell debugging on production — browse binary permissions, PHP/CLI scripts, logs, MySQL, filesystem — use **SSH from the dev server**.

---

## Key location (dev server only)

| Item | Value |
|------|--------|
| **Private key** | `/root/.ssh/whmcs_prod_root` |
| **SSH target** | `root@192.168.92.75` |
| **Prod hostname** | `whmcs-production-75` |
| **Prod WHMCS root** | `/var/www/eazybackup.ca/accounts` |
| **Prod site root** | `/var/www/eazybackup.ca` |

The private key is installed for **root on the development WHMCS host** only. It is **not** in git and must not be copied into the repository or committed.

Permissions should remain `600` on the private key:

```bash
chmod 600 /root/.ssh/whmcs_prod_root
```

---

## When to use SSH vs fleet remote API

| Need | Prefer |
|------|--------|
| Fleet summary, nodes, deploy, release upsert | Dev UI or `FleetRemoteClient` (no SSH) |
| Browse binary install, `chown`, diag scripts | **SSH** (runs as root on prod) |
| `deploy-production.sh`, git pull on prod | **SSH** |
| Worker journals inside LXC | Proxmox SSH key — see `Prompts/DEBUG_PROMPT.md` |
| Read prod `ms365_worker_fleet_audit` | Either remote API `fleet_audit` or SSH + MySQL/PHP |

---

## Basic usage

From the **development server** (as root):

```bash
PROD_KEY=/root/.ssh/whmcs_prod_root
PROD=root@192.168.92.75

# Interactive shell
ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD"

# One-off command
ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" 'hostname; php -v'
```

First connection may require accepting the host key (`StrictHostKeyChecking=accept-new`).

---

## Common debugging commands

### Browse binary (restore wizard runs this on prod WHMCS)

```bash
PROD_KEY=/root/.ssh/whmcs_prod_root
PROD=root@192.168.92.75
ACC=/var/www/eazybackup.ca/accounts/modules/addons/ms365backup

ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" \
  "php $ACC/bin/ms365_browse_binary_diag.php"

ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" \
  "chown -R www-data:www-data /var/www/eazybackup.ca/ms365-backup-worker && php $ACC/bin/ms365_install_browse_binary.php"
```

### Production health check (post-deploy)

```bash
ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" \
  "php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/ms365_prod_health_check.php"
```

### Deploy PHP/custom code on production

Run on prod (after git pull in the deploy clone):

```bash
ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" \
  "bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh"
```

### Fleet smoke on production

```bash
ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" \
  "php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/ms365_fleet_smoke.php"
```

### Release sync cron (prod pull from dev)

```bash
ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" \
  "php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_worker_release_sync.php"
```

### Tail WHMCS activity / module logs

Paths vary by setup; typical locations:

```bash
ssh -i "$PROD_KEY" -o IdentitiesOnly=yes "$PROD" \
  "tail -50 /var/www/eazybackup.ca/accounts/storage/logs/activity.log"
```

---

## Agent / Cursor instructions

If you are a **debugging or implementation agent** with shell access on the **dev** host:

1. You **may** use `/root/.ssh/whmcs_prod_root` to run read-only or fix-up commands on production when the task requires prod state (browse binary, deploy verification, prod-only settings).
2. Prefer **read-only** investigation first (`diag`, `health_check`, `fleet_summary` via remote API).
3. For writes on prod (deploy, `chown`, binary sync), confirm the change matches the user’s intent; do not force-push git or run destructive commands unless explicitly requested.
4. Do **not** exfiltrate or log private key material. Reference the path only in internal ops docs.
5. `www-data` on dev cannot use this key (root-only path); use `sudo ssh -i /root/.ssh/whmcs_prod_root ...` if executing from a www-data context.

---

## Related documentation

| Document | Topic |
|----------|--------|
| `MS365_WORKER_FLEET.md` | Dual fleet, prod URL, release sync, browse binary |
| `ARCHITECTURE_BOUNDARIES.md` | Dev vs prod WHMCS roles |
| `Prompts/DEBUG_PROMPT.md` | Proxmox / worker LXC SSH (`ms365_proxmox_ed25519`) |
| `Prompts/ms365_product_agent_prompt.md` | Product agent startup + doc map |
| `bin/deploy-production.sh` | Safe prod deploy + browse binary post-checks |

---

## Security notes

- Root SSH to production is powerful: limit to operators and automated agents on the trusted dev host.
- Rotate or revoke the key pair if the dev server is compromised or an operator leaves.
- The public half must be in `root@192.168.92.75:~/.ssh/authorized_keys` (managed on prod; not stored in this repo).
