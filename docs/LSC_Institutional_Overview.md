# LSC — Institutional Overview

**Learn · Share · Connect**  
**A focused digital collaboration space for students, lecturers and study groups**

**Prepared for institutional review**  
**Version 1.0 · 22 September 2026**

---

## Executive summary

LSC is a web-based study-group and academic collaboration platform. It gives students and lecturers one secure place to create study groups, coordinate tasks, share files, communicate, and receive relevant notifications.

LSC is designed for institutions that want a practical collaboration layer without forcing academic work into a general-purpose social network. It can be deployed on an institution-controlled domain and hosting environment, with the option to connect institutional email, SMS, and identity providers as part of implementation.

The current release provides the core collaboration experience and a security-hardened PHP/MySQL foundation. Google and Microsoft sign-in are identified as integration work for the institutional rollout rather than being represented as already-live features.

## The institutional problem

Students often coordinate academic work across disconnected channels: messaging groups, email threads, shared drives and informal task lists. This makes it harder to find the latest file, understand responsibilities, maintain continuity when group membership changes, and keep academic collaboration within an appropriate governance boundary.

Institutions need a platform that is:

- easy for students to use on desktop and mobile;
- structured enough for groups, tasks, files and communication;
- manageable by an administrator;
- deployable on an institution-approved domain;
- transparent about its data, security and integration status; and
- capable of growing into institutional single sign-on and notification workflows.

## What LSC provides

### Study groups

- Public and private groups
- Group descriptions and course context
- Join requests and approval workflows
- Member management for group creators and administrators
- Seven-day invitation links and email invitations

### Academic coordination

- Tasks with titles, descriptions, due dates and status
- Assignment of tasks to group members
- Calendar-oriented due-date views
- Activity and contribution context on profiles

### Communication and resources

- Group chat
- File attachments in chat
- Shared group file library
- Authenticated downloads restricted to eligible group members
- Recent shared resources visible from the chat area
- Supported document, image, text and archive uploads with size and content validation

### Profiles and notifications

- Student and lecturer profiles
- Institution, course and biography fields
- Optional profile photo
- Email and SMS notification preferences
- In-app notifications for group activity and invitations
- Password recovery by email when production email delivery is configured

### Administration

- Platform-level user oversight
- Account activation and deactivation
- Controlled administrator access
- Registration roles limited to Student and Lecturer; administrator access is not publicly self-selectable

### Access from different devices

- Responsive desktop and mobile interface
- Progressive Web App metadata and installable experience
- Theme preference support, including dark mode
- Public privacy, terms, offline and not-found pages

## Value to an institution

### For students

Students can find their group conversations, files and responsibilities in one place. Clear membership, task and file workflows reduce the friction of coordinating assignments and revision sessions.

### For lecturers and tutors

Lecturers can support structured group work without needing to manage every conversation manually. Course and group context makes it easier to direct students to a shared workspace.

### For academic support teams

Administrators have a defined place to manage active accounts and support collaboration across courses or cohorts.

### For IT and governance teams

LSC is a relatively small, understandable PHP/MySQL application with no Composer dependency tree. It can be deployed on institution-controlled infrastructure, reviewed from source, and integrated with approved service providers rather than requiring an opaque third-party SaaS contract.

## Security and privacy posture

Security is treated as a release requirement. The current release includes:

- prepared statements for database access;
- CSRF protection on mutating actions;
- output escaping for user-controlled content;
- password hashing with PHP's secure default and automatic rehashing when the default changes;
- single-use, expiring password-reset tokens stored as hashes;
- session expiry after 30 minutes idle or 24 hours absolute;
- HTTPS enforcement in production and secure session-cookie settings;
- Content Security Policy and additional browser security headers;
- database-backed rate limiting for authentication, registration, recovery, groups, invitations, chat, tasks and uploads;
- upload extension, size, image-header and container-signature checks;
- uploads stored outside the public document root;
- membership checks before protected file downloads; and
- randomized stored filenames rather than user-provided paths.

An institutional deployment should also use persistent storage, database backups, monitoring, a production application server, and a documented incident-response process. Security controls in software do not replace institutional governance.

## Data and compliance considerations

LSC may process names, email addresses, phone numbers, profile photos, institution and course details, group membership, chat messages, files, task activity and notification delivery records.

For a South African deployment, the institution should assess LSC against **POPIA** and designate the appropriate responsible person. Institutions serving users in the EU or UK should also assess GDPR obligations, including lawful basis, processor agreements, retention, data-subject requests and cross-border transfers.

The platform does not process payment-card data. Institutions must still define:

- the purpose and lawful basis for processing;
- retention and deletion periods;
- who may create groups and invite users;
- acceptable-use and academic-integrity rules;
- procedures for account suspension, access requests and erasure; and
- safeguarding requirements where users may be under 18.

The privacy and terms pages in the application provide a technical starting point, not legal advice or a substitute for the institution's policies.

## Email, SMS and sign-in integrations

### Email

LSC has an outbound notification queue and can use Resend for production email notifications such as invitations. Password-reset delivery also requires a configured production mail transport. A scheduled worker should process the queue at least every minute.

### SMS

LSC has an Africa's Talking integration for queued SMS notifications. Users can store a phone number in international format and choose SMS notifications. Provider credentials must be stored as server-side environment variables and the queue worker must be scheduled.

SMS-based login codes or two-factor authentication are not part of the current release; they can be added as a separately tested security feature.

### Google and Microsoft sign-in

Google and Microsoft OpenID Connect sign-in are suitable for an institutional rollout, but they require implementation and provider registration before being advertised as available. The recommended design is to:

1. retain ordinary email/password login;
2. add secure Google and Microsoft sign-in buttons;
3. store provider identities separately from local users;
4. validate state, nonce, redirect URI and verified provider claims;
5. support secure linking and unlinking from the user profile; and
6. preserve the Student/Lecturer onboarding and administrator controls.

Microsoft Entra ID is the natural option when an institution wants Microsoft 365 or tenant-restricted access. Google Workspace can be configured for an approved institutional domain. Provider secrets must remain in environment variables and callback URLs must use HTTPS.

## Deployment model

A typical institutional deployment consists of:

- an HTTPS domain, such as `lsc.example.ac.za`;
- a PHP 8.2+ application server with the `public` directory as document root;
- MySQL 8 or MariaDB 10.4+;
- persistent storage for uploads;
- scheduled notification-queue processing;
- encrypted environment variables for database, mail, SMS and identity-provider credentials;
- daily database backups and a tested restore procedure; and
- institution-approved monitoring, logging and support ownership.

LSC can be deployed on Railway or equivalent infrastructure, or on institution-managed hosting. The deployment should not use PHP's built-in development server for a public production service.

## Suggested implementation plan

### Phase 1 — institutional discovery

Confirm the institution's target cohorts, domain, data-residency requirements, administrator roles, retention policy, support model and acceptable-use rules.

### Phase 2 — production foundation

Provision the domain, HTTPS, database, persistent file storage, backups, monitoring, email provider and SMS provider. Apply the institution's privacy and terms content.

### Phase 3 — identity integration

Implement and test Google Workspace and/or Microsoft Entra ID sign-in. Decide whether access is open to verified provider accounts or restricted to approved institutional domains or tenants.

### Phase 4 — pilot

Run a controlled pilot with selected students, lecturers and support staff. Test invitation flows, file access, account recovery, notification delivery, moderation and account deactivation.

### Phase 5 — rollout and review

Train administrators, publish support contacts, monitor adoption and incidents, review privacy controls, and schedule regular security and dependency reviews.

## Institution decisions required

Before implementation, the institution should decide:

- Which students, lecturers or departments may use LSC?
- Should access be restricted to a Google Workspace domain, a Microsoft tenant, or both?
- Is local password login retained as a fallback?
- Which roles may create groups, invite users and moderate content?
- Which notifications are appropriate: email, SMS, both, or neither?
- How long should profiles, files, chat and inactive accounts be retained?
- Where must data and backups be hosted?
- Who is the operational owner for support, privacy requests and incidents?

## Current release status

| Capability | Status |
|---|---|
| Study groups, membership and join requests | Available |
| Tasks, due dates and assignment | Available |
| Group chat and file sharing | Available |
| Secure file download and upload validation | Available |
| Student and lecturer registration | Available |
| Administrator account management | Available |
| Password login and recovery flow | Available; production mail transport required |
| Resend email notifications | Available; provider and queue worker required |
| Africa's Talking SMS notifications | Available; provider and queue worker required |
| Google OpenID Connect login | Integration required |
| Microsoft Entra OpenID Connect login | Integration required |
| SMS login OTP / two-factor authentication | Optional future enhancement |

## Recommended next step

Begin with a technical and governance discovery session, then establish a staging deployment for the institution. Use the staging environment to validate identity-provider configuration, notification delivery, data-retention rules, accessibility, mobile usability and administrative workflows before a wider pilot.

**LSC is intended to make academic collaboration simpler, more structured and more accountable — while leaving the institution in control of its deployment, data and integration choices.**

---

### Contact

**Institution / project owner:** ______________________________  
**Email:** ______________________________  
**Website or staging URL:** ______________________________  
**Review date:** ______________________________
