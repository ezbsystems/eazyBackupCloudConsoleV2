# e3 Object Storage: Users and How to Use

This guide explains **Users** in e3 Object Storage: what they are, how they differ from the Root user, and how to create users, access keys, and buckets step by step.

---

## Introduction: What Are Users?

In e3 Object Storage, your account has one **primary identity** (the Root user) and optionally one or more **Users** (sometimes called subusers). Together they let you organize access and storage under a single account.

- **Root user** — The main account tied to your service. It has full access to its own buckets and one fixed access key pair.
- **Users** — Additional identities you create under the same account. Each user has isolated buckets and can have multiple access keys with different permissions.

All users share the same **Account ID** (shown in the client area). Billing and usage roll up to your primary account, but each user’s data and keys are separate.

---

## Root User vs. Users

| | Root user | Users (subusers) |
|---|-----------|------------------|
| **Access keys** | Exactly **one** key pair. Cannot create more; you can only rotate/replace it. | Can create **multiple** access key pairs. |
| **Key permissions** | Always **full** access (read, write, list, manage buckets). | You choose per key: **Full**, **Read/Write**, **Read**, or **Write**. |
| **Buckets** | Owns its own buckets only. | Each user owns only their own buckets. |
| **Cross-access** | Root user **cannot** access a User’s buckets. | Users **cannot** access each other’s buckets or the Root user’s buckets. |

Summary:

- **Root user**: single full-access key pair, cannot create more keys, cannot see or use a User’s buckets.
- **Users**: multiple keys per user, configurable permissions, isolated buckets. No user can see or use another user’s (or Root’s) buckets.

---

## Why Use Subusers?

- **Security** — Grant only the permissions each key needs (e.g. read-only for a backup viewer, write-only for an uploader).
- **Organization** — Separate access for different people or systems (e.g. dev, backup server, partner).
- **Billing** — All subuser usage is billed under your primary account; no separate accounts to manage.
- **Control** — You create and revoke users and keys from one place and keep full oversight.
- **Compliance** — Clear separation of identities and scoped keys makes auditing and access reviews easier.

---

## User Isolation & Security Architecture

### Complete Account Isolation

Each user operates in a strictly isolated space:

- **Isolated storage** — A user can only access their own buckets and data.
- **Scoped credentials** — An API key works only for the user who owns it.
- **Independent namespaces** — Each user’s buckets live in a separate logical namespace.
- **No cross-access** — No user can view, change, or access another user’s data (including Root vs Users).

### Access Key Scope & Limitations

- **User-specific keys** — Each API key pair belongs to one user. It only works for that user’s resources; it cannot be used to access another user’s or the Root user’s buckets.

---

## How-To: Step-by-Step

### Where to Go in the Client Area

1. Log in to the client area and open **My Services**.
2. Open your **e3 Object Storage** service.
3. Use the top navigation or the left sidebar under **e3 Object Storage**:
   - **Dashboard** — Overview and quick links  
   - **Buckets** — Create and manage buckets  
   - **Access Keys** — Create and manage API keys (for Root or for a selected user)  
   - **Users** — Create and manage Users (subusers)

The **Users** page lists all users (including the Root user), their Account ID, bucket count, storage used, and access key count.

---

### Step 1: Create a User

1. Go to **e3 Object Storage → Users**.
2. Click **+ Create User** (green button near the top right).
3. In the **Create User** modal, enter:
   - **Username** — A unique name for this user (e.g. `backup-server`, `team-member`).
   - Any other required fields (e.g. password if prompted).
4. Submit the form. The new user appears in the Users list with 0 buckets and 0 access keys.

To manage that user’s keys and buckets, click **Manage** next to their name to open their detail view.

---

### Step 2: Create Access Keys for the User

1. On the **Users** page, click **Manage** for the user you created (or the user you want to add a key for).
2. In the user detail view you’ll see:
   - **Buckets**, **Storage**, and **Access keys** summary cards.
   - An **Access keys** section with a **+ Create access key** button.
3. Click **+ Create access key**.
4. If prompted, complete **Verify password**:
   - Enter your **account password** and click **Verify**. This is required for creating keys.
5. In the **Create access key** dialog:
   - **Description** — Optional label (e.g. “backup server”, “CI/CD”) so you can tell keys apart.
   - **Permission** — Choose:
     - **Full** — Read + write + list + manage buckets.  
     - **Read/Write** — Upload + download + list objects (no bucket create/delete).  
     - **Read** — Download and list only.  
     - **Write** — Upload only.
6. Click **Create key** (or equivalent). The **secret key** is shown **once**. Copy and store it securely; you cannot view it again from the UI.

The new key appears in the Access keys table with its key hint, description, permission, and created date. You can create more keys for the same user with different descriptions and permissions.

---

### Step 3: Create a Bucket for the New User

1. Go to **e3 Object Storage → Buckets**.
2. Click **Create Bucket** (or **+ Create Bucket**).
3. In the **Create Bucket** modal:
   - **Bucket Name** — Enter a unique name for the bucket.
   - **Select Bucket Owner** — Choose the **user** you created (e.g. `demouser`) from the dropdown.  
     Do **not** select **Root user** if you want this bucket to belong to the new user.
   - Optionally set **Enable Versioning** and **Enable Object Locking** as needed.
4. Click **Submit**.

The bucket is created and owned by the selected user. Only that user’s access keys can access it; the Root user and other users cannot.

---

## Summary

| Goal | Where to go | Action |
|------|-------------|--------|
| Create a user | **Users** → **+ Create User** | Fill username (and any required fields), submit. |
| Create keys for a user | **Users** → **Manage** (user) → **Access keys** → **+ Create access key** | Verify password, set description and permission, create key and save the secret once. |
| Create a bucket for a user | **Buckets** → **Create Bucket** | Enter bucket name, **Select Bucket Owner** = the user, then submit. |

- **Root user**: one full-access key pair only; cannot access a User’s buckets.  
- **Users**: multiple keys per user, optional permissions; each user’s buckets are isolated from Root and from other users.  
- All usage is billed to your primary account; you keep full control and visibility from the client area.

---

## Screenshots (for reference)

When embedding screenshots into this KB, the following views align with the sections above:

- **Users list** — e3 Object Storage → Users: page title "Users (N users)", search, "+ Create User" button, table with Username, Account ID, Buckets, Storage, Access keys, and Manage/Locked actions. Root user appears in the list with one access key.
- **User detail (Access keys)** — After clicking Manage on a user: user name and Account ID at top, Buckets/Storage/Access keys summary cards, "Access keys" section with "+ Create access key" and table (or "No access keys yet"). Security notice: secret key shown only once.
- **Create access key** — Modal: Description (e.g. "backup server"), Permission dropdown (Full, Read/Write, Read, Write with short descriptions). Reminder that the secret is shown once.
- **Verify password** — Modal shown before creating a key: "Enter your account password to continue", Account password field, Cancel and Verify buttons.
- **Create Bucket** — Modal: Bucket Name, **Select Bucket Owner** dropdown (e.g. Root user, demouser), Enable Versioning, Enable Object Locking, Submit. Use "Select Bucket Owner" to assign the bucket to a specific user.
