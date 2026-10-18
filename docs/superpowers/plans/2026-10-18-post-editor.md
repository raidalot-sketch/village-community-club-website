# Proper club article editor

User request: replace the basic text box with substantial blog/post authoring. Existing authorisation, approval, private media and photo reduction remain binding. User has requested implementation and previously directed us to build without more design interviews.

Implementation:
1. Add a self-hosted Quill visual editor: headings, bold/italic/underline, lists, quotes, links, undo/redo, inline photographs, alt text/captions, live preview, word count and draft/save status. Keep accessible plain-text fallback.
2. Add authenticated, CSRF-protected staging for photo uploads so photos can be positioned before the article is saved. Retain three attachments and 30 MB original-photo limits. Resize before upload and re-encode on the server.
3. Sanitise article HTML server-side, restrict inline media to the author's current article/staging context, keep drafts/revisions private, preserve formatting on reopen and avoid duplicate image galleries below inline photos.
4. Provide optional cover photo and article summary, draft saving and existing submission/approval. A pending edit must not replace an approved post before review.
5. Run security/round-trip tests, inspect actual editor/preview, back up and deploy only this improvement to the private hosted preview, commit/push.

Ownership: backend worker owns editor helpers, migration, media endpoint, community integration and public article render; root owns editor page, JS/CSS, vendor assets and deployment. Independent reviewer checks final behaviour and security.

Ruling: use Quill 2.0.3, self-hosted, without paid API keys or third-party editor requests. Existing graph tools are unavailable; structural exploration uses source reads/rg fallback.
