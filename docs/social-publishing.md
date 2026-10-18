# Automatic approved-post sharing

Approval records a Facebook and Instagram outbox entry inside the website transaction. Network delivery is separate. Only entries explicitly marked `auto_share: true` qualify; older manual requests and events are not sent by this worker. The approved title, plain text (including line breaks), public link, selected approved image and source hash form the snapshot. A changed, withdrawn or tampered snapshot fails closed.

## Private configuration and connection

Set these constants only in the private server configuration, after the club's accounts, consent and permissions have been verified. Never put credentials in website settings, Git, queue payloads or logs.

- `CLUB_SOCIAL_PUBLISH_ENABLED`: strict boolean `true` enables actual delivery when the CLI also receives `--deliver`; leave false until account setup is complete.
- `CLUB_META_PAGE_ID`: numeric string identifying the club Facebook Page.
- `CLUB_META_PAGE_TOKEN`: Page access token with the required current Page publishing permissions and access to the connected Instagram professional account.
- `CLUB_META_INSTAGRAM_ID`: numeric string identifying that Instagram account.
- `CLUB_META_GRAPH_VERSION`: explicit supported version string, for example `v25.0`; validate current supported version and app permissions during connection.
- `CLUB_SOCIAL_PUBLIC_MEDIA_READY`: strict boolean `true` only after public HTTPS JPEG fetching works from outside the server. Instagram also requires GD, a secret `APP_SECRET` of at least 32 characters and HTTPS `SITE_URL`.

`SITE_PREVIEW === true` blocks all actual social delivery and signed images. Do not remove the website's preview authentication to connect Meta. Launch and public media readiness need their normal authorised handover. Facebook can deliver a text-and-link post without an image; Instagram always needs a JPEG. An approved attachment is re-encoded and letterboxed into a 1080 px square. Text-only posts use a deterministic branded JPEG. Originals, contact enquiries and pending revision images are never exposed by the signed endpoint.

### Prepare now, connect when Page access arrives

Ed's Facebook admin access is still required before obtaining the club's legitimate Page token and confirming the linked Instagram professional account. The connection checker and delivery worker are ready; account setup and a real publication test remain outstanding.

Example private configuration (replace existing definitions; never define constants twice):

```php
define('CLUB_META_PAGE_ID', '');
define('CLUB_META_PAGE_TOKEN', '');
define('CLUB_META_INSTAGRAM_ID', '');
define('CLUB_META_GRAPH_VERSION', ''); // Set an explicitly supported Meta version during setup.
define('CLUB_SOCIAL_PUBLISH_ENABLED', false);
define('CLUB_SOCIAL_PUBLIC_MEDIA_READY', false);
// Retain the existing private APP_SECRET and correct public HTTPS SITE_URL.
```

Deploy the checker, worker, social module and signed image endpoint with the site. The default checker prints only configuration booleans and safe status messages; it makes no network request:

```console
php bin/club-social-check.php
php bin/club-social-check.php --check
```

Run `--check` only when the real Page ID, private Page token and Graph version have been added. This is a single read-only GET of the Page ID/name and linked `instagram_business_account{id,username}`. It works while publishing is disabled and while preview is enabled; it never sends a post or alters the outbox. It verifies the exact configured Page and Instagram IDs. If Instagram linkage is absent, it reports unavailable linkage/access rather than assuming the account is unlinked. Raw provider errors, token values, returned names and usernames are omitted from output. A successful read does **not** prove publishing permissions, app review, media fetching or successful publication.

After the connection check succeeds, verify public HTTPS signed media fetching and arrange an explicitly approved real post test. Keep the delivery switch disabled until that handover. A scheduler may already be installed safely with publishing disabled: `--deliver` still cannot publish until `CLUB_SOCIAL_PUBLISH_ENABLED === true`. Enabling it also makes all still-current approved automatic entries eligible, so review the pending queue before switching it on.

## Worker

Run from the project directory:

```console
php bin/club-social.php
php bin/club-social.php --deliver
```

The first command is a read-only dry run. The second still requires the enable flag and per-channel connection prerequisites. A scheduler should run that explicit delivery command every five minutes; it remains unable to publish while the private enable flag is false. Each run processes at most ten due entries. A short transaction locks and claims each pending/connection-required entry; processing, sent and failed entries are excluded from automatic retry. A processing entry untouched for 30 minutes moves to failed/manual attention without resending.

For deployment, confirm the server's PHP binary and project directory, then add this entry to the site's existing crontab without removing other jobs. Replace both placeholder paths with the verified absolute server paths. Keep output in a private log outside the public document root, and preserve `CLUB_SOCIAL_PUBLISH_ENABLED=false` until connection handover:

```cron
*/5 * * * * /ABSOLUTE/PHP /ABSOLUTE/CLUB/bin/club-social.php --deliver >> /ABSOLUTE/PRIVATE/club-social.log 2>&1
```

The read-only connection checker should be invoked manually when adding or replacing credentials, not scheduled as a publishing job. Deployment should verify both default CLI commands before installing the delivery schedule; neither default command sends a request to Meta.

Meta requests use the fixed HTTPS Graph host, bearer credentials in request headers, bounded connection/request timeouts and no redirects. Facebook uses Page `/photos` for an approved attachment when signed public media is ready, otherwise `/feed` for text and a link. Instagram creates `/media`, stores its creation ID in the queue payload, checks `status_code` for `FINISHED`, then calls `/media_publish`. Final remote IDs are retained in `remote_id`; an unconfirmed request becomes failed with a generic safe error.

## Recovery and verification

Do not blindly reset failed or stranded processing entries. A timeout may follow a successful external publication. Check the account and stored creation ID/remote ID first, reconcile the result, and only then decide whether an explicit manual retry is safe. Token expiration, missing permissions or an unfinished Instagram container require operator attention. Token acquisition, OAuth/app review and account linkage are deliberate setup tasks outside this worker.

Signed `/social-image.php` URLs expire after one hour by default, never more than 24 hours. They require a matching queue ID, source hash, HMAC and a still-published matching post on every fetch. Responses are uncached JPEGs. Source edits/withdrawals revoke access. Treat signed URLs as temporary publication capabilities and do not log them unnecessarily.

Local verification: `php tests/club-social-test.php` requires exactly `nettlebed_preview`, unconfigured Meta constants and GD. It uses fake connection values, a fake HTTPS site URL, an injected network transport, and a rollback transaction. It does not call Meta. Live account permissions, real remote fetching and publication remain unverified until an authorised connection test.

Public helpers: `club_social_snapshot(kind,id)`, `club_social_connection_ready(channel)`, `club_social_run(deliver,transport,limit)`, `club_social_image_url(queueId,hash,expires)`, `club_social_image_payload(id,hash,expires,signature)`, `club_social_jpeg(payload)`. Production uses the built-in cURL transport; the optional callable exists for isolated tests.

Read-only connection helpers: `club_social_configuration()` and `club_social_connection_probe(check,transport)`. Verify locally with `php tests/club-social-connection-test.php`: seven isolated processes cover missing configuration, no-network default, successful checks with publishing disabled and preview enabled, wrong Page ID, wrong Instagram linkage, absent linkage and redacted provider errors. All transports are mocked.
