# Nettlebed Village Club rebuild — 18 October 2026

The user confirmed the public name, approved the existing black-and-gold identity and equal priority for events and hall hire, and selected nettlebedvillageclub.co.uk. The old domain is not recoverable through the club's current contacts, so this is a fresh installation, not an old-site recovery.

## Sources

- https://www.facebook.com/NettlebedVillageClub — read through the user's Chrome tab on 18 October 2026. Club building photograph, logo, bar, snooker room, two halls, address (32 High Street), phone (01491 641617), email (thenettlebedclub@gmail.com). JPEG assets downloaded from the rendered public page, stored locally in assets/images/club-building.jpg and club-logo.jpg. Logo is a 200 px source; request an original for future print work.
- https://nettlebed-pc.gov.uk/nettlebed-village-hall-and-club/ — public club listing and postcode.
- https://www.nettlebed.org/village-club/ — building history and opening in 1913.
- Nominet RDAP: nettlebedvillageclub.co.uk returned domain not found on 18 October 2026. This is an availability signal, not a reservation. The user subsequently purchased the domain. Verified in their open Cloudflare registration page: Active, expires 18 October 2027, auto-renew enabled. Cloudflare DNS records screen shows zero records; no website hosting destination is connected yet.

## Implemented

Cream, charcoal and gold public design, existing logo and building photo, six-item navigation, events and hall hire side by side, dedicated editable hall-hire content, membership enquiries, shared editable address, and a homepage image upload in Admin > Settings. Navigation remains available with JavaScript disabled and supports Escape on mobile. Sample dates, people, membership rates and broken sample PDF are absent from new installations.

Empty event calendars link to Facebook. No invented prices, bookings, capacity guarantees, testimonials or current opening timetable. Existing draft legal/accessibility pages remain marked as drafts until the club supplies its operational details and the website is browser-tested.

## Review and launch status

Source: E:/Websites/village-community-club
Offline public preview: E:/Websites/nettlebed-club-preview-20261018/index.html
The offline preview contains rendered public HTML and public assets only. Contact form is deliberately replaced by an offline notice in that export; the PHP source retains the real enquiry flow.

Local preview database: isolated MariaDB instance bound to 127.0.0.1, port 3318. Uses only nettlebed_preview; configuration and temporary test administrator details are outside Git. Local mail is disabled. Do not deploy the local config/config.php.

Automatic approval review rejected PHP server startup without an explanatory reason. Browser policy separately rejected file URLs, so there is no browser visual or HTTP form-submission verification from this session. PHP rendering and CLI checks do not prove mobile layout or production mail delivery. Four settings/mail assertions and eighteen route/admin/sitemap rendering checks pass. Every local image and link in the offline public export resolves.

Before publication: choose hosting for the registered domain; create dedicated production database credentials; install via install.php with the new HTTPS domain; configure and test transactional email; confirm opening hours, membership terms and hall hire details; finalise privacy/cookie/accessibility pages; verify desktop/mobile, administrator uploads and form delivery; take a backup; lock/remove installer. No DNS, email or old-site changes were made.


## Expanded requirements — 18 October 2026

The user subsequently requested member logins, member-created website posts, a proper calendar with logged-in event creation, publishing website content to Facebook and Instagram, and displaying both social channels on the website. These requirements supersede the brochure-only scope. Moderation/publishing roles are awaiting the user's answer; do not treat the existing administrator/editor roles as safe member accounts, since current editors can edit all content.

Recommended flow under discussion: member submits -> publication according to agreed role -> website calendar/post -> authorised social publishing with per-channel delivery status. Month and list calendar views, recurring events, UK timezone/DST handling and calendar export are proposed. Actual Meta publishing must remain disconnected until the club authorises account access; no social posts have been sent. Instagram account handle/type is not yet verified.

HawkHost preparation: the user chose existing HawkHost. A separate addon domain now maps nettlebedvillageclub.co.uk to /home/xhashtag/public_html/nettlebedvillageclub.co.uk, with a new database xhashtag_nvclub and a dedicated database user. The docroot currently contains only a setup placeholder and an access restriction to the current connection address plus local/ACME checks. Application files have NOT been uploaded there and DNS records in Cloudflare have NOT been created. Private deployment details are in /home/xhashtag/nettlebed-deployment-20261018/deployment.json. The original villageclub.capedoctordigital.co.za installation/database are unchanged.

cPanel CLI: API2 cpapi2 fails because its cpanel executable is unavailable in the jailed shell. The installed Cpanel/API/AddonDomain.pm exposes AddonDomain::addaddondomain through UAPI; that worked successfully. Follow installed API source rather than the older public support article which says no UAPI equivalent exists.

The new member/calendar/social work changes data ownership and publishing permissions, so architectural design and a written implementation plan are required before implementing it. The already approved branding and domain choice remain settled.


## Membership payments through Bivetica Invoice

User requirement: take membership payments on the club website using invoice.bivetica.com. User confirmed one annual payment with a renewal reminder, not automatic recurring collection.

Read-only live source verification: hawkhost-mv1:/var/www/invoice, HEAD bfdbf23 on 18 October 2026. The local apps/invoice copy lacks some current Stripe Connect code, so it is not authoritative for this integration.

Verified live code: StripeInvoicePay starts Checkout for an invoice's balance in mode=payment, uses the supplier organisation's connected Stripe account, verifies paid status/account/amount/currency, and settles the invoice under database locks. StripeInvoiceController provides hosted payment/start/return routes. Existing billing_invoice_requests consumers can receive invoice.paid outbox deliveries, but the current BillingController/BillingService raiseInvoice API raises Bivetica platform invoices, not a club's own merchant invoices. Do not call that API for club membership revenue.

Proposed integration: club owns its organisation/customer/invoice records in Invoice and its connected merchant account; the club website owns member profiles, membership terms/dates and access permissions. A scoped merchant integration creates an invoice idempotently per member and membership period, returns its hosted payment URL, and confirms the matching paid invoice via a signed callback/status check before activating membership. A browser success redirect alone never activates membership. Repeated provider notifications must not extend membership twice. Renewal reminders run from the club's membership expiry dates; renewals produce a new annual payment request. Future refunds/cancellations must have an explicit club decision and a reliable status update; do not infer cancellation policy.

Not verified: an existing Invoice organisation/Stripe connection for this club, live payment acceptance, membership prices/categories, whether years run from joining or a fixed annual renewal date, reminder timing and membership approval rules. No production Invoice changes or transactions have been made.


## Role model confirmed

The user approved member submission moderation, public/members-only event visibility, Ed as administrator and a separate treasurer role. The full written design is docs/superpowers/specs/2026-10-18-nettlebed-members-platform-design.md and awaits written-spec review. Fee remains unconfirmed; annual membership term is twelve months, with a renewal reminder and no automatic charge.


## Members-only polls requested

The user added polls for club members. The written design now includes moderated member poll submissions, eligible-member voting with server-side duplicate prevention, closing dates and aggregate-only results. Single-choice voting, changing a vote before closing and revealing results after closing are proposed defaults, not separately confirmed club rules. No poll functionality has been implemented yet.


## Poll behaviour confirmed

The user confirmed vote changes until closing and results only after closing. They had no preference on single versus multiple choice; single-choice polls are the recommended initial implementation. The design now explicitly hides aggregate results until closing. Remaining written-design review is pending.


## Contact-form requirements confirmed

The user approved notifications to thenettlebedclub@gmail.com, category routing to different committee members, and file attachments. The design now defines private enquiry storage, scoped handler access, protected attachments and saved-message behaviour when email delivery fails. Specific category recipients are still unknown and must fall back to the confirmed club Gmail address until configured. The existing form has not yet been extended with these additions.


## Photo-size correction confirmed

The user rejected the proposed 5 MB photo cap. Accept original phone photographs up to 30 MB and automatically resize/compress them using the established Motors/Invoice approach. The design now includes client preparation, server validation/re-encoding, orientation/HEIC handling, privacy-preserving storage and per-site hosting-limit checks. The club upload code still has its original 5 MB cap; no claim is made that this feature is implemented. PDF handling and original-retention policy remain separate.


## Attachment behaviour confirmed

The user confirmed keeping only optimised photos after successful processing, three attachments total per contact message and photo previews before submission. PDFs are explicitly accepted and remain intact; additional document formats and the PDF/document size limit still need confirmation. These decisions are reflected in the written design; they do not mean the new upload implementation is complete.
