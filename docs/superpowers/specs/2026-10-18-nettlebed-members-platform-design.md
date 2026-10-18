# Nettlebed Village Club: members, calendar and payments

Date: 18 October 2026  
Status: written design for user review; implementation of these expanded features has not started.

## Purpose and confirmed decisions

Build the club’s new website at nettlebedvillageclub.co.uk, hosted separately on the existing HawkHost account. Preserve the approved cream, charcoal and gold identity, existing logo and building photograph. Events and hall hire have equal prominence. The old villageclub.capedoctordigital.co.za demo remains untouched.

Users can log in, submit website posts and add calendar events. Ordinary members’ submissions require approval. Events can be public or members-only. Ed has administrator access, with a separate treasurer role for payment administration. One person can hold multiple roles.

Membership lasts twelve months, with one annual payment and a renewal reminder. The fee might be £20 or £25; neither is confirmed. The club probably uses bank transfer/EFT and may not have Stripe. Do not publish either price or enable a checkout until Ed confirms the fee and the club confirms its receiving details.

Bivetica Invoice handles the club’s membership invoices/payment records. The club website owns membership eligibility, expiry dates, website roles, posts and events. Facebook and Instagram publishing/display are required features, with live connection dependent on the club’s authorised accounts.

## Approach and alternatives

Recommended: extend the existing PHP/MySQL website and connect it to a scoped club organisation in Invoice. This preserves its editable content and shared-host deployment, while adding explicit membership and publishing permissions.

Alternative: migrate the club to a separate membership CMS. That could provide packaged calendar/membership features, but introduces a migration and another integration surface with Invoice. It does not remove the need to authorise Meta accounts. No CMS migration is proposed.

## Roles and permissions

| Role | Capabilities |
| --- | --- |
| Visitor | Read public pages, approved public posts and public events; enquire about joining/hall hire. |
| Member | Manage own profile and membership, view eligible members-only events, create/edit own drafts, submit own posts/events for approval. |
| Publisher | Review submissions; approve, reject or return for changes; manage published posts/events; choose eligible public content for social publishing. |
| Treasurer | View membership payment records and invoices, match bank transfers, record confirmation/reversal decisions and manage membership payment status. |
| Administrator | Manage accounts, assign/revoke roles, configure website/integrations and perform publisher operations. Financial reconciliation requires the Treasurer capability, which can be assigned to the same person explicitly. |

The administrator role does not provide access to other Bivetica Invoice organisations. Paying a fee never grants Publisher, Treasurer or Administrator privileges. Role changes are audited. A member cannot edit another member’s submission, change its author, promote its status or assign their own roles.

Existing admin/editor accounts are committee accounts, not member accounts. Introduce member identity/profile records and role assignments without turning the current broad editor access into member access. Retain current committee login compatibility during migration; every new member action uses a central permission check and ownership check on the server. Existing event/news records keep their original committee authorship. New member authorship uses a separately validated member reference, not a forged admin identifier.

## Registration and member experience

Provide Log in / Join links and an account area with My membership, My posts and My events. Registration verifies email, applies throttling and password-reset protections, and never grants committee roles. Administrators can invite or approve accounts. Existing members need a documented import/invitation route that does not require them to pay twice.

An unpaid/pending account can manage its profile, obtain its invoice and see payment instructions. Proposed default: only active approved members can view members-only events or submit new content. The committee can make explicit audited exceptions. This access default is a design proposal, not a confirmed club membership rule.

## Posts and moderation

Workflow: draft → submitted → approved/published, or returned for changes/rejected. A member sees their own status and reviewer feedback. A publisher can preview text and photographs before approval. Record author, reviewer, timestamps and the approved revision.

Changing an approved post/event creates a new revision for approval; it must not silently replace the approved public copy. Withdrawing/unpublishing is audited. Approval of website publication and selection for social publishing are separate actions.

## Calendar

Offer a proper month grid and an accessible chronological list. On small screens, default to the readable list with an optional month view. Visitors can switch month, return to today and open event details. Members can start an event from the calendar or My events.

Event fields: title, description, date, start/end, all-day option, venue, photograph, public/members-only visibility, optional booking link and recurrence. Retain the existing weekly/monthly recurrence options and implement actual occurrence generation, rather than storing unused recurrence labels. Changes and cancellation can affect one occurrence or the series explicitly.

Use Europe/London for event display, recurrence and daylight-saving calculations. Date-only events retain date semantics. Validate end after start; handle events crossing midnight and recurring events across clock changes. Add-to-calendar links/ICS must respect visibility: no private event in a public export, sitemap, unauthenticated API, thumbnail metadata or public calendar counts. Members-only feeds must require authenticated access or separately revocable scoped tokens.

Hall-hire enquiries remain enquiries. No room reservation, capacity promise, availability guarantee or double-booking prevention is implied by the events calendar. A booking-management system would be a separate agreed scope.

## Annual membership and Invoice

Proposed membership states: awaiting review, awaiting payment, active, expired, suspended and cancelled. Payment state and moderation/account state remain separate.

Proposed bank-transfer flow:

1. Member chooses a confirmed membership category and requests an annual invoice.
2. The website asks Invoice to create/retrieve the club’s invoice for that member and membership period, using an idempotency key.
3. The member receives the invoice, confirmed bank details and a unique reference. The website shows Awaiting payment.
4. A treasurer matches the actual bank receipt and confirms it against that invoice through the authorised workflow.
5. Invoice records the payment. A signed, scoped notification or verified status check updates the club membership once; retries cannot extend it twice.
6. The website shows the confirmed membership dates and links to the invoice/receipt.

A member clicking “I have paid” is not payment confirmation. Never activate membership from a browser redirect or client-supplied amount. Do not write directly into Invoice’s database from the club website.

Confirmed term: twelve months. Proposed term rule pending club confirmation: start on confirmed payment for a new/expired membership; an early renewal extends from the existing expiry date. Store the purchased period explicitly and handle leap-year anniversaries deterministically. No automatic bank/card charge is created.

Renewal reminders are scheduled against membership expiry with a deduplicated delivery log. Exact reminder timing remains configurable and requires club confirmation. Test deliveries go to a mail capture, not actual members. Refunds, mistaken confirmations and cancellations require audited correction and explicit membership consequences; no refund policy is invented.

If the club later connects its own Stripe account, reuse Invoice’s supplier card-payment flow for the same club-owned invoice. Money must be collected for the club, not treated as Bivetica platform subscription revenue. Card checkout stays unavailable until the connection is ready.

Read-only evidence from live Invoice at bfdbf23: supplier invoice card payments and settlement checks exist. The current /api/v1/invoices billing route raises Bivetica’s own platform invoices and is not suitable for club membership revenue. Add a tenant-scoped merchant integration for the club, with amount/currency/organisation validation, idempotent invoice creation, payment status lookup and signed payment events. Reuse the existing settlement/outbox concepts but do not claim that a club integration is already implemented or that live club payments have been tested.

## Facebook and Instagram

Only authorised Publishers/Administrators choose Share to Facebook / Share to Instagram. A member submission never posts to social accounts automatically. Each selected channel has a preview and separate queued, processing, published, failed or connection-required status. Store remote post identifiers, errors and retry state; prevent duplicate publication after timeouts. Never report success without provider confirmation.

Share public event announcements with a link back to the website. Do not promise creation of a native Facebook Event unless supported and verified for the connected account. Members-only event details cannot be shared publicly; a separate explicitly approved public announcement must omit private details.

Instagram requires an eligible professional account and authorised API access. Text-only website content needs a suitable approved image before Instagram publication. Connection tokens stay encrypted/server-side. A failure of Meta access does not block website publication or the calendar.

For display, provide a social section using official permitted embeds or an authorised feed, with a plain link fallback when unavailable. Do not scrape private groups or copy arbitrary third-party media. Load optional third-party embeds only after consent; keep the page useful if visitors decline. The club’s Instagram username and account eligibility remain unverified. No Meta app, account connection or live test post is authorised by this design alone.

## Security, operations and deployment

Enforce role/ownership/visibility checks on the server, CSRF protection, safe rich-text handling, content-checked uploads, login rate limits and revocable sessions. Protect unpublished/member-only media, rather than putting restricted uploads into globally readable directories. Avoid leaking member/payment data through public pages or logs. Publish privacy/cookie/accessibility content that reflects the final implementation and club practices.

Use separate application/database configuration on HawkHost. Keep secrets outside Git and the public document root where possible. The current remote docroot is only a setup placeholder with an access restriction; application files and new Cloudflare DNS records have not been published. Keep staging mail and social publishing disabled until explicitly connected for testing. Verify HTTPS before accepting login/payment data.

Use HawkHost cron for reminders, integration delivery/retries and reconciliation, with bounded batches, a job lock and delivery idempotency. Take file/database backups before replacing a deployed installation; verify restoration and the exact domain/docroot mapping. Release the club site independently of Invoice. Any Invoice changes need their own isolated checkout, tests and deployment review.

## Acceptance checks

- A member cannot approve content, confirm payments, assign roles or edit someone else’s drafts, including via direct requests.
- A publisher can moderate without gaining financial permissions; a treasurer can reconcile without gaining site-administration privileges.
- Ed’s administrator role and a separately assigned treasurer role work as agreed.
- Pending/rejected posts and members-only events are absent from every public surface and export.
- Member creation, moderation, post/event revisions and calendar recurrence work on desktop and mobile with keyboard access.
- Calendar occurrences behave correctly across month boundaries, midnight and UK daylight-saving changes.
- Bank-transfer membership remains pending until a verified treasurer/Invoice confirmation; duplicates and retries do not create extra invoices or extend membership twice.
- Early renewal, expiry, pending payment, reversals and reminder deduplication have meaningful integration tests.
- Social failures/expired credentials remain visible; retries do not duplicate posts; private details never enter public social payloads.
- New features are tested in staging before release; no real membership payment, email or social post is sent as an incidental test.

## Remaining facts to confirm

These are configuration/release dependencies rather than permission to invent values:

- Membership categories and fee (Ed to confirm; £20/£25 is not a published price).
- Approval process for new members and the membership start/early-renewal rule.
- Confirmed club bank details, treasurer identity and an existing/new club organisation in Invoice.
- Reminder timing, sender and membership/import details.
- Instagram username/account type and authorised Facebook/Instagram account administrator.
- Whether hall-booking management is wanted later; it is outside this calendar scope.

## Review record

The user approved the role model in chat: submissions require approval, events can be public/members-only, Ed has administrator access and a separate treasurer role is available. This written design combines that approval with the previously requested calendar, payments and social integration. Written-spec approval is still pending. Implementation planning follows that approval.
