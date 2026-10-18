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

## Worker

Run from the project directory:

```console
php bin/club-social.php
php bin/club-social.php --deliver
```

The first command is a read-only dry run. The second still requires the enable flag and per-channel connection prerequisites. A scheduler should run that explicit delivery command every five minutes once launch has been authorised; no scheduler is installed by this change. Each run processes at most ten due entries. A short transaction locks and claims each pending/connection-required entry; processing, sent and failed entries are excluded from automatic retry. A processing entry untouched for 30 minutes moves to failed/manual attention without resending.

Meta requests use the fixed HTTPS Graph host, bearer credentials in request headers, bounded connection/request timeouts and no redirects. Facebook uses Page `/photos` for an approved attachment when signed public media is ready, otherwise `/feed` for text and a link. Instagram creates `/media`, stores its creation ID in the queue payload, checks `status_code` for `FINISHED`, then calls `/media_publish`. Final remote IDs are retained in `remote_id`; an unconfirmed request becomes failed with a generic safe error.

## Recovery and verification

Do not blindly reset failed or stranded processing entries. A timeout may follow a successful external publication. Check the account and stored creation ID/remote ID first, reconcile the result, and only then decide whether an explicit manual retry is safe. Token expiration, missing permissions or an unfinished Instagram container require operator attention. Token acquisition, OAuth/app review and account linkage are deliberate setup tasks outside this worker.

Signed `/social-image.php` URLs expire after one hour by default, never more than 24 hours. They require a matching queue ID, source hash, HMAC and a still-published matching post on every fetch. Responses are uncached JPEGs. Source edits/withdrawals revoke access. Treat signed URLs as temporary publication capabilities and do not log them unnecessarily.

Local verification: `php tests/club-social-test.php` requires exactly `nettlebed_preview`, unconfigured Meta constants and GD. It uses fake connection values, a fake HTTPS site URL, an injected network transport, and a rollback transaction. It does not call Meta. Live account permissions, real remote fetching and publication remain unverified until an authorised connection test.

Public helpers: `club_social_snapshot(kind,id)`, `club_social_connection_ready(channel)`, `club_social_run(deliver,transport,limit)`, `club_social_image_url(queueId,hash,expires)`, `club_social_image_payload(id,hash,expires,signature)`, `club_social_jpeg(payload)`. Production uses the built-in cURL transport; the optional callable exists for isolated tests.
