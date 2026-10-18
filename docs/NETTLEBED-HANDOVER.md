# Nettlebed Village Club build

The approved cream, charcoal and gold design is implemented as a PHP/MariaDB website. The working branch is `feature/nettlebed-members-platform` in `raidalot-sketch/village-community-club-website`.

## Working features

- Separate website accounts, committee approval, email verification/reset tokens and explicit member, publisher, treasurer and administrator roles.
- Moderated news and event submissions, protected revisions, public/member event visibility, month/list calendar, recurring events, cancellation and ICS export.
- Member polls with one changeable vote per member until closing, aggregate results after closing and no public ballot identities.
- Contact categories, a private committee inbox, scoped handlers and recoverable resolved status.
- Three attachments per contribution or enquiry. Original files up to 30 MB. JPEG/PNG/WebP and browser-converted HEIC photographs become optimised images, maximum 2,000 pixels on the longest edge. Standard PDF/DOCX/XLSX files retain their contents. Originals are not retained after image processing. Files live outside the public website and pass authorisation before delivery.
- Annual membership invoice requests, explicit treasurer bank confirmation, Invoice payment-status checks, idempotent activation and twelve calendar month terms. Renewals extend a current term. Reminders use a deduplicated outbox.
- Social profile links, consented Facebook display and publisher-only social preparation. Private events and polls cannot enter the social queue.

## Details still required for activation

The annual membership fee is deliberately blank pending Ed's confirmation. Club bank instructions, a dedicated club organisation/client in Invoice, mail delivery and authorised Meta accounts also need configuration. The Invoice adapter is implemented separately on `feature/nettlebed-membership-merchant-adapter` in `raidalot-sketch/bivetica-invoice`; it has not been deployed to live Invoice.

Facebook/Instagram automatic transmission remains disabled and labelled connection required. Instagram display needs an authorised feed connection. No payments, emails or social posts were sent by build tests. Draft policy content needs club review before public launch.

## Hosting and operations

Private HTTPS preview: `https://nettlebedvillageclub.4x4hashtag.co.za`. It is restricted to the current development connection and marked noindex. The original demo is preserved. The purchased final domain is `nettlebedvillageclub.co.uk`; final DNS launch has not been performed.

The new site uses its own database and private storage on HawkHost. Configuration and generated access details are outside the public folder, excluded from Git. Host-side deployment backups are outside the website. Do not commit credentials, enquiry files, uploaded images or database exports.

The maintenance entry point is `php bin/club-maintenance.php`. Email sending additionally requires an enabled mail configuration and explicit `--deliver`; ambiguous or failed sends need review rather than automatic repeat transmission.

## Validation

CLI suites cover identity/roles, membership settlement and retries, moderation/recurrence/DST/ICS, ballots, private enquiries, image/document validation, and integration consent/outbox rules. Database tests use the isolated local preview database and roll their fixtures back. Browser checks use the HTTPS preview; phone camera/HEIC testing on real devices remains a launch check.

Hosted acceptance checks passed on 18 October 2026: eight public HTTPS screens, six authenticated committee/member screens, a real preview-account login, CSRF rejection, blocked configuration/database/Git/test paths, contact submission with server-processed WebP attachment, authorised media delivery and anonymous denial. The labelled test enquiry remains in the private preview inbox. Chrome rendered the homepage and contact form; an extension panel interrupted further browser automation, so the remaining route/authentication checks used direct HTTPS requests. Narrow-screen behaviour and real-device HEIC handling still require device acceptance.

Both website and Invoice adapter branches are pushed to GitHub. The adapter has 26 passing Laravel tests with 190 assertions; website suites include 19 identity, 31 membership, 30 poll, 27 integration and nine upload checks, plus community and enquiry scenarios. The later credit/refund of a paid invoice requires committee review; it does not automatically revoke club membership.

## Article editor upgrade

Member posts now use a self-hosted visual editor with headings, emphasis, lists, quotations, links, inline photographs, captions/alternative descriptions, cover selection, summary and article preview. Drafts save manually and automatically; published/submitted articles do not silently autosubmit revisions. Version checks reject stale tabs. Committee-returned revisions reopen with their text, photos and feedback. Photo removal is reversible in storage and published originals stay online until approval.

Apply `database/club-editor.sql` after `database/club-platform.sql` when installing/updating. It adds the summary/cover fields and author-bound upload staging sessions. Staging sessions expire after seven days; files remain outside the public folder. Three-file and 30 MB original-photo limits continue to apply. A private unpublished editor demonstration is available to publishers; no sample article was published and no social messages were sent.
