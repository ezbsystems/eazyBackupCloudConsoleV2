# MS365 Tenant Seeder — Azure App Setup

The **Tenant Seeder** uses a **separate** Entra app from the read-only backup app ([AZURE_SETUP.md](AZURE_SETUP.md)). Register this app only for dev/QA tenants.

## 1. Register an application

1. [Microsoft Entra admin center](https://entra.microsoft.com/) → **App registrations** → **New registration**
2. Name: `eazyBackup MS365 Tenant Seeder`
3. Supported account types: **Accounts in this organizational directory only** (single tenant)
4. Redirect URI (Web): `{SystemURL}/admin/addonmodules.php?module=ms365backup&action=seeder_oauth_callback`  
   Use **https** if your site uses TLS (must match WHMCS **System URL** under *Setup → General Settings*).

## 2. Client secret

**Certificates & secrets** → **New client secret** → copy value for the Seeder panel **APP_SECRET**.

## 3. Application permissions (Microsoft Graph)

Grant **admin consent** after adding:

| Permission | Purpose |
|------------|---------|
| `User.Read.All` | List users |
| `Mail.ReadWrite` | Create mailbox messages |
| `Calendars.ReadWrite` | Create calendar events |
| `Contacts.ReadWrite` | Create contacts |
| `Tasks.ReadWrite.All` | Create To Do lists/tasks |
| `Files.ReadWrite.All` | Upload OneDrive and SharePoint files |
| `Sites.ReadWrite.All` | Access SharePoint document libraries |
| `Group.Read.All` | List Teams and channels |

## 4. Delegated permissions (Teams messages)

Also add under **Delegated permissions**:

| Permission | Purpose |
|------------|---------|
| `ChannelMessage.Send` | Post Teams channel messages |
| `ChatMessage.Send` | Optional group chat messages |
| `offline_access` | Refresh token for background worker |

Grant admin consent, then use **Connect seed user** in the Seeder panel (sign in as a licensed M365 user who belongs to your Teams).

## Redirect URI mismatch (AADSTS50011)

The OAuth redirect URI is built from WHMCS **System URL** + `/admin/addonmodules.php?module=ms365backup&action=seeder_oauth_callback`.

- Confirm **Setup → General Settings → System URL** is `https://…` (not `http://`) when the site is served over HTTPS.
- In Entra → app → **Authentication**, add the **exact** URI shown on the Tenant Seeder panel (scheme, host, and path must match).

## 5. Configure WHMCS

**Addons → MS365 Backup → Tenant Seeder**

1. Enter REGION, TENANT_ID, CLIENT_ID, APP_SECRET
2. **Test connection**
3. **Connect seed user** (for Teams workload)
4. Choose profile (Light / Standard / Heavy) and **Start seeding**

## Security notes

- Do **not** add write permissions to the production backup Entra app.
- Seeder credentials are stored in `ms365_seeder_config`, isolated from dashboard backup creds and customer `ms365_tenant_records`.
- Seeding is append-only; there is no bulk delete — use only on disposable dev tenants.

See also [TENANT_SEEDER.md](TENANT_SEEDER.md).
