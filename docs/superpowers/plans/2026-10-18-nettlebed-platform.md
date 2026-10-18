# Nettlebed Members Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use subagent-driven-development or executing-plans task by task.

**Goal:** Build the approved club website with member roles, moderated posts/events, calendar, polls, private enquiries and membership payments through Invoice.
**Architecture:** Extend PHP/MySQL with isolated club-user records and capability checks; preserve existing committee admin records and existing content. Shared protected attachment delivery, scoped integration outbox and cron jobs support uploads, reminders and payment status. Keep payment/Meta external calls disabled until configured.
**Tech Stack:** PHP 8.4, MariaDB, vanilla JS/CSS, existing GD upload processing.
**Spec:** docs/superpowers/specs/2026-10-18-nettlebed-members-platform-design.md

## Global Constraints
- Approved cream/charcoal/gold visual references; mobile stacked controls.
- Three attachments total; phone photo originals up to 30 MB, automatic resizing/compression, previews; PDF/Word/Excel documents retained privately.
- Members submit; publishers approve. Members-only event data never reaches public feeds/social outputs.
- Annual membership, twelve months, renewal reminders; fee and bank details unconfirmed, therefore no fabricated prices or enabled payment checkout.
- All contact notifications default to thenettlebedclub@gmail.com. No incidental real messages/social posts/payment transactions.
- User explicitly requested immediate build without more design questions; implement reversible defaults and record them.

## Review Focus
- Role escalation and private media access via direct requests.
- Calendar recurrence/DST and per-occurrence cancellation.
- Duplicate votes and payment callbacks under concurrency.
- Large/HEIC uploads, failed preparation and misleading success states.
- Integration timeouts without duplicate social publication or lost enquiries.

## Task 1: Foundation and migrations (root)
Files: database/club-platform.sql, includes/club.php, includes/bootstrap.php, tests/platform-test.php.
Interfaces: club_current_user(): ?array; club_can(string): bool; club_require_login(): array; club_require_capability(string): array; club_is_active_member(?int): bool; club_flash(string,string): void using existing flash helpers; club_media_url(int): string.
- [ ] Write failing CLI schema/role checks; run to confirm failure.
- [ ] Create idempotent schema and module loader, preserve legacy tables.
- [ ] Run migrations and role checks; commit.

## Task 2: Member identity and membership UI (identity worker)
Files: includes/member-auth.php, login.php, register.php, logout.php, account.php, members/profile.php, members/membership.php, admin/club-users.php, assets/js/member-auth.js, tests/member-auth-test.php.
- [ ] Test role/ownership/login failure, suspended account and expired membership boundaries.
- [ ] Implement password hashing, CSRF, rate limits, account/profile, scoped role management and fees-unconfigured state.
- [ ] Verify PHP lint and CLI tests; commit only owned files.

## Task 3: Community content and calendar (community worker)
Files: includes/community.php, calendar.php, events.php, event-detail.php, post.php, members/posts.php, members/post-edit.php, members/event-edit.php, admin/submissions.php, assets/js/club-calendar.js, tests/community-test.php.
- [ ] Test public/member visibility, ownership, moderation and recurrence around DST.
- [ ] Implement moderated posts/event revisions, responsive month/list calendar, ICS and event forms.
- [ ] Verify ownership/visibility tests and lint; commit only owned files.

## Task 4: Member polls (poll worker)
Files: includes/polls.php, polls.php, poll.php, members/poll-edit.php, admin/club-polls.php, tests/polls-test.php.
- [ ] Test eligibility, unique vote/change-vote, closing boundaries and hidden results.
- [ ] Implement moderated polls with locked choices after voting opens and private aggregates.
- [ ] Verify tests and lint; commit only owned files.

## Task 5: Attachments, enquiries and approved styling (root)
Files: includes/club-uploads.php, media.php, contact.php, admin/enquiries.php, assets/js/club-uploads.js, assets/css/club-platform.css, includes/header.php, includes/nav.php, tests/uploads-test.php.
- [ ] Test MIME/size/image transformation and protected attachments.
- [ ] Implement client preparation and safe server re-encoding, three-file submission, private enquiry storage and captured notifications.
- [ ] Match approved public/member/contact/membership/calendar layouts; verify render, direct-request access and mobile paths.

## Task 6: Membership/Invoice and social delivery (root, then available worker)
Files: includes/club-membership.php, includes/club-integrations.php, payment-callback.php, cron.php, admin/club-settings.php; separate Invoice merchant adapter checkout if needed.
- [ ] Test idempotent requests/callback signatures and fee-unconfigured refusal.
- [ ] Implement bank-transfer invoice workflow, signed scoped Invoice adapter/status, membership term changes/reminders, configurable Meta connection/outbox and social-feed fallbacks.
- [ ] Verify all live outputs remain disabled until authorised accounts/details are supplied.

## Task 7: Deployment and acceptance (root)
- [ ] Fix inherited invalid .htaccess directory directive and protect private folders.
- [ ] Back up source/database, deploy separate preview with HTTPS, secrets outside Git and robots noindex.
- [ ] Browser-test desktop/mobile, representative member/admin flows and attachment privacy.
- [ ] Commit and push verified source. Report working preview and concrete external setup dependencies.

Execution: parallel workers own disjoint modules; root coordinates shared schema, integration, uploads, visual system and deployment. No further preference interview is required.

## Execution result, 18 October 2026

- [x] Foundation, roles, moderated content/calendar, polls, private contact uploads/inbox and approved visual styling implemented and committed.
- [x] Scoped Invoice merchant adapter and website membership/payment-status integration implemented, tested and pushed on their respective feature branches. Server-to-server bearer authentication is used; no unauthenticated browser payment callback can activate membership.
- [x] Phone-photo resizing and private PDF/DOCX/XLSX storage implemented and regression checked.
- [x] Source and database backed up; isolated private HTTPS preview deployed with noindex and connection restrictions.
- [x] Live HTTPS login, public/private routes, CSRF, contact/image processing and media authorisation verified. Actual homepage/contact screenshots captured.
- [x] Both repositories pushed. Handover records live setup dependencies.
- [ ] Final fee, bank instructions, club Invoice organisation/client, authorised email/Meta connections and final-domain launch.
- [ ] Real-phone camera/HEIC and narrow-screen acceptance. Browser extension panel blocked further interactive browser testing; remaining server acceptance checks passed directly over HTTPS.

The external account dependencies are visible disconnected states. Automatic Facebook/Instagram transmission is not implemented as a live connection; approved public-content requests are captured for that connection. No incidental real-world emails, social posts or payments were sent during verification.
