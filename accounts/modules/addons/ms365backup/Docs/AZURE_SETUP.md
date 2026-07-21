# Microsoft 365 Backup — Azure App Setup

This addon uses **application permissions** (client credentials flow). Admin consent is required.

## 1. Register an application

1. Open [Microsoft Entra admin center](https://entra.microsoft.com/) → **App registrations** → **New registration**.
2. Name: e.g. `eazyBackup MS365 Dev Backup`.
3. Supported account types: **Accounts in this organizational directory only**.
4. No redirect URI required for client credentials.

## 2. Create a client secret

1. **Certificates & secrets** → **New client secret**.
2. Copy the **Value** immediately (this is `APP_SECRET` in the addon).

## 3. Application permissions (Microsoft Graph)

Under **API permissions** → **Add a permission** → **Microsoft Graph** → **Application permissions**:

| Permission | Purpose |
|------------|---------|
| `User.Read.All` | List users |
| `Mail.Read` | Backup mailbox messages (including per-folder delta sync) |
| `Calendars.Read` | Backup calendar events (series masters + exceptions) |
| `Contacts.Read` | Backup contact folders and contacts (delta per folder) |
| `Tasks.Read.All` | Backup Microsoft To Do lists and tasks (delta per list) |
| `Sites.Read.All` | List SharePoint sites; also covers **site permission/member metering** (`/sites/{id}/permissions`) — see note below |
| `Group.Read.All` | List Microsoft Teams and M365 groups |
| `Files.Read.All` | OneDrive drive metadata (`/users/{id}/drive`) for resource inventory |
| `ChannelMessage.Read.All` | Teams channel messages and replies (Phase 2E) |
| `TeamMember.Read.All` | Team members (`/teams/{id}/members`) — Protected Object metering |
| `GroupMember.Read.All` | M365 group members (`/groups/{id}/members`) — Protected Object metering |
| `Channel.ReadBasic.All` | Channel tabs (`/teams/{id}/channels/{id}/tabs`) — optional; tabs skipped if denied |
| `Notes.Read.All` | OneNote notebooks, sections, pages (Phase 2F) |

**SharePoint site member metering (2026-07-21):** billing resolves SharePoint site members/permission principals for Protected Objects billing via `GET /sites/{id}/permissions`, cached on the site resource the same way Team/Group members are cached. This uses the **existing `Sites.Read.All`** application permission already required for SharePoint site inventory/backup — **no additional Graph permission is required**. If a future tenant is found where `Sites.Read.All` alone does not return `grantedToV2`/`grantedToIdentitiesV2` on the permissions endpoint, document the exact additional permission name here before shipping further changes.

Click **Grant admin consent for {tenant}**.

## 12. Restore write permissions (Phase 5)

Restore requires **write** application permissions in addition to read scopes. Re-grant admin consent after adding these:

| Permission | Purpose |
|------------|---------|
| `Mail.ReadWrite` | Restore mailbox messages |
| `Calendars.ReadWrite` | Restore calendar events (without attendee notifications) |
| `Contacts.ReadWrite` | Restore contacts |
| `Tasks.ReadWrite` | Restore To Do tasks |
| `Files.ReadWrite.All` | Restore OneDrive / SharePoint file content |
| `Sites.ReadWrite.All` | Restore SharePoint lists and site content |
| `Group.ReadWrite.All` | Restore group mail/calendar where applicable |

The e3 restore wizard surfaces `needs_reconnect` when write scopes are missing (same pattern as inventory refresh).

- Uses the same `Mail.Read` and `Calendars.Read` permissions against `groups/{groupId}/mailFolders` and `groups/{groupId}/calendars`.
- Group mailboxes require the group to be **mail-enabled**; otherwise Graph returns skippable errors.
- Group calendar backup follows the same rules as users (no `calendarView`; partition fallback).

## 10. Microsoft Planner (Phase 2F)

- Plans: `GET /groups/{groupId}/planner/plans`, buckets and tasks under `planner/`.
- Requires mail-enabled group with Planner; uses existing `Group.Read.All` and `Tasks.Read.All`.

## 11. OneNote (Phase 2F)

- `GET /users|groups|sites/{id}/onenote/notebooks` and sections/pages export.
- Requires `Notes.Read.All` (application).

**Note:** Re-grant consent after adding `ChannelMessage.Read.All`. Team channel enumeration uses `/teams/{id}/channels` (`Team.ReadBasic.All` may help in some tenants). Channel/site relationship resolution uses `/teams/.../channels/.../filesFolder` and `groups/{id}/sites/root` where available.

## 4. Configure the WHMCS addon

**Addons → MS365 Backup → Dashboard**

| Field | Value |
|-------|--------|
| REGION | `GlobalPublicCloud` (or your cloud) |
| CLIENT_ID | Application (client) ID |
| TENANT_ID | Directory (tenant) ID |
| APP_SECRET | Client secret value |

Click **Test connection**.

## 5. Mail incremental (delta)

- First sync per folder uses `GET /users/{id}/mailFolders/{folderId}/messages/delta` (full export + delta token).
- Subsequent runs resume from stored `@odata.deltaLink` under `{user}/mail/messages/{folderId}/delta_state.json`.
- Removed messages are recorded as `{id}.removed.json` tombstones (original JSON retained).

## 6. OneDrive backup

- Uses `GET /drives/{driveId}/root/delta` for metadata sync (initial + incremental).
- File bytes: `GET /drives/{driveId}/items/{itemId}/content` streamed to `{tenant}/drives/{driveId}/content/`.
- Requires `Files.Read.All` and selecting **OneDrive** scope plus a `user_onedrive` inventory row.

## 7. SharePoint site backup (files + lists)

- **Files:** `GET /sites/{siteId}/drives` then per library `GET /drives/{driveId}/root/delta` + `…/items/{itemId}/content`.
- **Lists:** `GET /sites/{siteId}/lists` then per list `GET /sites/{siteId}/lists/{listId}/items/delta`.
- Requires `Sites.Read.All` and `Files.Read.All`; enable **SharePoint files** and/or **SharePoint lists** scope and select a site (or Team/channel that dedupes to a site).
- Storage under `{tenant}/sites/{safeSiteId}/`:
  - `drives.json`, `drives/{driveId}/items/`, `content/`, `delta_state.json`
  - `lists/lists.json`, `lists/{listId}/items/`, `delta_state.json`
- Site IDs are composite (`hostname,siteId,webId`); URL-encoded per segment in Graph paths.

## 8. Teams backup (metadata + messages)

- **Metadata:** `GET /teams/{groupId}`, `/members`, `groups/{groupId}/owners`, `/channels`, `/channels/{id}/tabs`.
- **Messages:** `GET /teams/{groupId}/channels/{channelId}/messages/delta` (full history on first run) + `…/messages/{messageId}/replies` per top-level message.
- Physical jobs: `team:{groupId}` (all channels) or `channel:{groupId}:{channelId}` (single channel).
- SharePoint **files** for a Team still use Phase 2D (`site:{siteId}`) — not duplicated in Teams engine.
- Storage under `{tenant}/teams/{groupId}/`:
  - `team.json`, `members.json`, `owners.json`, `channels.json`
  - `channels/{channelId}/messages/{messageId}.json`, `…/replies/{replyId}.json`, `delta_state.json`

## 9. Contacts and To Do

- Contacts: `contactFolders`, `contactFolders/{id}/contacts`, and `contacts/delta` per folder.
- Tasks: Microsoft To Do via `/users/{id}/todo/lists` and `lists/{id}/tasks/delta` (not Outlook mailbox tasks).
- Storage: `{tenant}/users/{id}/contacts/` and `{tenant}/users/{id}/todo/`.

## 10. Calendar backup notes

- Uses `GET /users/{id}/calendars/{calendarId}/events` (not `calendarView` or `calendarView/delta`).
- Sends `Prefer: IdType="ImmutableId"` on calendar requests.
- Stores `seriesMaster`, `singleInstance`, and `exception` events; skips `occurrence` rows to avoid recurring-series explosion.
- Pagination is logged per page. Safety limits abort if Graph returns a looping or endless `@odata.nextLink` (see [Graph SDK issue #3070](https://github.com/microsoftgraph/msgraph-sdk-dotnet/issues/3070)).

## 11. Local storage

Backups are written to:

```
/var/www/eazybackup/ms365/{tenant_id}/
```

Ensure the PHP/web user can write to this directory (created on addon activation).
