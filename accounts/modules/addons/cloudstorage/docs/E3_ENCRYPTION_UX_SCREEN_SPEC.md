# e3 Backup Encryption UX Screen Specification

## Purpose

Define the exact user experience for encryption ownership, mode selection, and password lifecycle across:

1. MSP tenant settings
2. Tenant portal settings
3. Job wizard repository-security step

This specification is product and UX focused. It intentionally excludes frontend markup and backend implementation examples.

## Product Goals

- Allow customers to choose an encryption mode that matches their risk posture.
- Ensure managed mode supports safe password reset without data loss.
- Ensure strict mode clearly communicates no-admin-reset behavior.
- Keep repository access stable even when destination credentials or bucket naming changes.
- Keep tenant boundaries explicit and visible in every relevant flow.

## Roles and Permissions

- MSP Owner/Admin: can configure tenant encryption policy, switch mode, reset in managed mode, and view audit history.
- Tenant Admin: can view tenant encryption policy and perform password actions permitted by policy.
- Tenant User: read-only visibility unless explicitly granted password-change permission.

## Core UX Terms

- Encryption Scope: who owns encryption control (`Tenant` now, `User` optional future scope).
- Encryption Mode:
  - Managed: reset is available through authorized admins.
  - Strict: no admin reset; recovery requires customer-held recovery key.
- Encryption Password: dedicated crypto password, separate from WHMCS login.
- Repository Security Profile: the encryption policy and key-wrapping profile bound to repositories.

## Global UX Rules

- Never use WHMCS login password as encryption password.
- Every destructive or irreversible action requires a confirmation modal.
- All save/reset/change actions require step-up confirmation (current password for change, explicit warning for reset/mode switch).
- Show current mode and scope as persistent badges in relevant screens.
- Show "Last changed", "Changed by", and "Audit trail" metadata where available.

---

## 1) MSP Tenant Settings UX

### Page Name

Tenant Encryption Settings

### Navigation Placement

MSP Client Area -> e3 Backup -> Tenants -> [Tenant] -> Security

### Screen TS-1: Encryption Overview (default state)

#### Primary content blocks

- Current Security Profile card
- Encryption Mode card
- Password Lifecycle card
- Audit Timeline card

#### Fields

| Field | Type | Required | Editable | Notes |
|---|---|---:|---:|---|
| Tenant Name | Read-only text | Yes | No | Context only |
| Encryption Scope | Single-select | Yes | Yes | `Tenant` in v1; `User` shown as "Coming soon" if not enabled |
| Encryption Mode | Single-select | Yes | Yes | `Managed` or `Strict` |
| Recovery Email | Email input | Yes in Managed | Yes | Used for reset notifications |
| Session Unlock Duration | Single-select | No | Yes | 15m, 1h, 8h |
| Last Password Change | Read-only timestamp | No | No | Display `Never` until configured |
| Last Reset Event | Read-only timestamp | No | No | Managed only |

#### Primary buttons

- Save Profile
- Change Encryption Password
- Reset Encryption Password (Managed only)
- Download Recovery Key (Strict only)
- View Audit Log

#### Button states

- Save Profile:
  - Disabled when form has no changes.
  - Disabled when validation fails.
  - Loading state: "Saving..."
- Change Encryption Password:
  - Enabled only after initial encryption setup is complete.
- Reset Encryption Password:
  - Visible and enabled only in Managed mode.
- Download Recovery Key:
  - Visible and enabled only in Strict mode.

#### Validation

- Recovery Email:
  - Required in Managed mode.
  - Must be valid email format.
- Encryption Mode:
  - Required selection.
- Scope:
  - Required selection.

### Screen TS-2: Initial Encryption Setup Modal

#### Trigger

First time tenant opens encryption settings and no encryption profile exists.

#### Modal title

Set Tenant Encryption Password

#### Modal body copy

"This password protects your tenant encryption profile. It is separate from login credentials. Store it securely."

#### Fields

| Field | Type | Required | Notes |
|---|---|---:|---|
| New Encryption Password | Password | Yes | Must meet complexity rules |
| Confirm Encryption Password | Password | Yes | Must match |
| Recovery Mode | Radio | Yes | Managed or Strict |
| Acknowledgement Checkbox | Checkbox | Yes | Copy differs by selected mode |

#### Acknowledgement copy

- Managed: "I understand authorized admins can reset encryption password for this tenant."
- Strict: "I understand admin reset is disabled in Strict mode and recovery requires the recovery key."

#### Buttons

- Cancel
- Create Encryption Profile

#### Button states

- Create Encryption Profile disabled until all required fields pass validation.
- Loading state: "Creating..."

#### Validation rules

- Password minimum 14 characters.
- Must include at least 1 uppercase, 1 lowercase, 1 number, 1 symbol.
- Confirm password must match.
- Acknowledgement must be checked.

### Screen TS-3: Change Encryption Password Modal

#### Trigger

User clicks "Change Encryption Password."

#### Modal title

Change Encryption Password

#### Fields

| Field | Type | Required | Notes |
|---|---|---:|---|
| Current Encryption Password | Password | Yes | Required for change |
| New Encryption Password | Password | Yes | Complexity validation |
| Confirm New Encryption Password | Password | Yes | Must match |

#### Modal copy

"Changing password updates the encryption wrapping credentials. Repository access remains intact."

#### Buttons

- Cancel
- Change Password

#### Button states

- Change Password disabled until all validations pass.
- Loading state: "Updating..."

#### Validation

- Current password required.
- New password cannot equal current password.
- New password must meet complexity rules.
- Confirmation must match.

### Screen TS-4: Reset Encryption Password Modal (Managed Mode)

#### Trigger

User clicks "Reset Encryption Password."

#### Modal title

Reset Tenant Encryption Password

#### Risk level

High-risk action modal with warning style.

#### Modal copy

"You are about to reset the tenant encryption password in Managed mode. Existing repositories remain accessible, but all users must use the new password for future encryption actions."

#### Fields

| Field | Type | Required | Notes |
|---|---|---:|---|
| New Encryption Password | Password | Yes | Complexity validation |
| Confirm New Encryption Password | Password | Yes | Must match |
| Reason for Reset | Textarea | Yes | Audit requirement |
| Confirmation Phrase | Text input | Yes | Must match displayed phrase |

#### Confirmation phrase

`RESET ENCRYPTION <TENANT_NAME>`

#### Buttons

- Cancel
- Confirm Reset

#### Button states

- Confirm Reset disabled until all fields pass validation.
- Loading state: "Resetting..."

#### Validation

- New/confirm password validity.
- Reason must be at least 12 characters.
- Confirmation phrase must be exact match (case sensitive).

### Screen TS-5: Mode Switch Modal

#### Trigger

User changes mode selector and clicks Save.

#### Managed -> Strict modal copy

"Switching to Strict disables admin reset. Ensure recovery key distribution and policy acceptance are complete before continuing."

#### Strict -> Managed modal copy

"Switching to Managed enables authorized admin reset. Confirm this aligns with your security policy."

#### Required fields

| Field | Type | Required | Notes |
|---|---|---:|---|
| Policy Acknowledgement | Checkbox | Yes | Copy is mode-specific |
| Optional Change Ticket ID | Text | No | Audit metadata |

#### Buttons

- Cancel
- Confirm Mode Change

#### Validation

- Acknowledgement must be checked.

### Screen TS-6: Audit Timeline

#### Data points displayed

- Event type (setup, change, reset, mode switch, recovery key download)
- Actor (name/email/role)
- Timestamp
- Scope and mode at time of event
- Optional reason/comment

#### Empty state copy

"No encryption events recorded yet."

### MSP Tenant Settings Flow Summary

1. Open tenant security page.
2. If no profile exists, complete Initial Setup modal.
3. Select mode (`Managed` or `Strict`) and save.
4. Perform ongoing password lifecycle actions:
   - Change password (both modes)
   - Reset password (Managed only)
5. Review audit timeline after each action.

---

## 2) Tenant Portal Settings UX

### Page Name

Encryption & Security

### Navigation Placement

Tenant Portal -> Settings -> Encryption & Security

### Screen TP-1: Security Status

#### Sections

- Current encryption policy
- Account permission level
- Allowed actions

#### Display fields

| Field | Type | Visible to | Notes |
|---|---|---|---|
| Tenant Encryption Scope | Read-only | All users | Usually `Tenant` in v1 |
| Tenant Encryption Mode | Read-only badge | All users | Managed or Strict |
| Your Permission Level | Read-only | All users | Admin or User |
| Last Password Change | Read-only | Admin, optional for User | |
| Last Reset Event | Read-only | Admin only | Managed only |

#### Action buttons by role

- Tenant Admin:
  - Change Encryption Password
  - Reset Encryption Password (Managed only)
  - Download Recovery Key (Strict only)
- Tenant User:
  - No action buttons by default, unless policy grants "Can change encryption password"

### Screen TP-2: Change Encryption Password Modal

#### Title

Change Encryption Password

#### Fields

| Field | Type | Required |
|---|---|---:|
| Current Encryption Password | Password | Yes |
| New Encryption Password | Password | Yes |
| Confirm New Encryption Password | Password | Yes |

#### Copy

"This changes encryption credentials for tenant operations. Login password is unchanged."

#### Buttons

- Cancel
- Change Password

#### Validation

Same as MSP change-password rules.

### Screen TP-3: Reset Encryption Password Modal (Managed only)

#### Visibility

Tenant Admin only when mode is Managed.

#### Fields

| Field | Type | Required |
|---|---|---:|
| New Encryption Password | Password | Yes |
| Confirm New Encryption Password | Password | Yes |
| Reason | Textarea | Yes |
| Confirmation Phrase | Text | Yes |

#### Confirmation phrase

`RESET TENANT ENCRYPTION`

#### Copy

"This reset is logged and notifies tenant security contacts."

#### Buttons

- Cancel
- Confirm Reset

### Screen TP-4: Recovery Key Panel (Strict mode)

#### Visibility

Strict mode only.

#### Content

- Recovery key status (downloaded or not)
- Last downloaded timestamp
- Reminder text: "Store offline. This key may be required for recovery."

#### Buttons

- Download Recovery Key
- I Have Stored This Securely (acknowledgement action)

#### Button states

- Download always enabled for tenant admin.
- Acknowledgement disabled until at least one download action completed in current session.

### Tenant Portal Flow Summary

1. User views encryption status and permissions.
2. Tenant Admin can change password at any time.
3. Managed mode allows reset from portal admin.
4. Strict mode provides recovery-key-centric experience instead of reset.
5. All actions appear in centralized audit timeline.

---

## 3) Job Wizard Repository-Security Step UX

### Step Name

Repository Security

### Placement in flow

After Destination selection and before Schedule/Retention.

### Purpose

Ensure every job maps to a clear repository security profile and prevent ambiguous encryption behavior.

### Screen JW-1: Repository Selection

#### Sections

- Destination lock summary
- Repository choice
- Security profile preview

#### Fields

| Field | Type | Required | Notes |
|---|---|---:|---|
| Destination Bucket | Read-only in this step | Yes | Chosen in prior step |
| Destination Root Prefix | Read-only in this step | Yes | Chosen or auto-provisioned |
| Repository Option | Radio | Yes | `Use Existing Repository` or `Create New Repository` |
| Existing Repository | Select | Required when "Use Existing" | Filter by tenant scope and destination compatibility |
| New Repository Name | Text | Required when "Create New" | Unique within tenant scope |
| Repository Ownership Scope | Read-only | Yes | Inherits tenant profile in v1 |
| Encryption Mode Preview | Read-only badge | Yes | Managed/Strict from tenant profile |

#### Button

- Continue

#### Button states

- Disabled until repository option-specific required fields are valid.

#### Validation

- Existing repository must be in same tenant scope.
- New repository name required, 3-64 chars, no leading/trailing spaces.
- If destination already bound to another repository and user chose "Create New", block and show conflict message.

### Screen JW-2: Managed Mode Confirmation (conditional)

#### Display condition

Shown only when selected profile mode is Managed.

#### Copy

"This repository uses Managed mode. Authorized admins can reset encryption password without re-uploading backup data."

#### Fields

| Field | Type | Required | Notes |
|---|---|---:|---|
| Notify on Reset Events | Toggle | No | Default ON |
| Reset Notification Recipients | Multi-email input | Required if toggle ON | Pre-filled with tenant security contacts |

#### Buttons

- Back
- Continue

#### Validation

- If notifications enabled, at least one valid recipient required.

### Screen JW-3: Strict Mode Confirmation (conditional)

#### Display condition

Shown only when selected profile mode is Strict.

#### Copy

"This repository uses Strict mode. Admin reset is disabled. Recovery depends on your stored recovery key."

#### Fields

| Field | Type | Required |
|---|---|---:|
| Acknowledgement Checkbox | Checkbox | Yes |
| Recovery Key Status | Read-only status | Yes |

#### Acknowledgement copy

"I understand that losing encryption credentials in Strict mode may make repository access unrecoverable without recovery materials."

#### Buttons

- Back
- Continue

#### Validation

- Acknowledgement checkbox required.

### Screen JW-4: Repository Conflict Modal

#### Trigger

User attempts to continue with a bucket/prefix already bound to a different repository.

#### Title

Destination Already Bound

#### Copy

"The selected destination is already assigned to repository `<Repository Name>`. Choose one of the options below."

#### Options

- Use Existing Repository
- Go Back and Change Destination
- Cancel Job Creation

#### Buttons

- Confirm Choice
- Cancel

### Screen JW-5: Final Review Additions

Add a dedicated "Repository Security" summary block in the final review:

- Repository Name
- Repository ID (if existing)
- Encryption Scope
- Encryption Mode
- Reset Behavior
- Destination lock (bucket + root prefix)

### Job Wizard Validation Matrix

- Cannot continue without repository selection.
- Cannot create new repository with duplicate name in same tenant scope.
- Cannot create new repository on an already-bound destination root.
- Cannot bind repository across tenant boundaries.
- Strict acknowledgement required before final submit.
- Managed notification recipient validation enforced when enabled.

### Job Wizard Flow Summary

1. User chooses destination.
2. User enters Repository Security step.
3. User selects existing repo or creates new repo.
4. User confirms mode-specific behavior (Managed or Strict).
5. Wizard validates destination binding and tenant scope.
6. User reviews security summary before submitting job.

---

## Required UX Copy Library

### Managed mode helper text

"Managed mode allows authorized administrators to reset encryption password without data re-upload."

### Strict mode helper text

"Strict mode disables admin reset. Keep recovery materials offline and verified."

### Dedicated password notice

"Encryption password is separate from your login password."

### Success toasts

- "Encryption profile saved."
- "Encryption password changed."
- "Encryption password reset completed."
- "Repository security settings applied."

### Failure toasts

- "Could not save encryption profile. Review highlighted fields."
- "Current encryption password is incorrect."
- "This action is not allowed for your role."
- "Destination is already bound to a different repository."

---

## Accessibility and Usability Requirements

- All password fields support show/hide control.
- All critical actions have keyboard-focus-safe confirmation flows.
- Validation errors appear inline and at top summary area.
- Modals trap focus and return focus to triggering button on close.
- Confirmation phrases must be copyable and clearly visible.

## Analytics and Audit Requirements (UX-visible outcomes)

- Every mode change, password change/reset, and recovery key download creates an auditable event.
- User receives immediate success/failure feedback and can view the event in timeline.
- Audit timeline is filterable by event type and date range.

## Open Decisions (to finalize before implementation)

- Whether tenant users can ever change encryption password in tenant scope.
- Default unlock session duration for sensitive encryption actions.
- Whether Strict mode requires periodic recovery-key re-verification prompts.
- Whether destination root should always be auto-provisioned for new repositories.

