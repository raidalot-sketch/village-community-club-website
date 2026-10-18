# Familiar club posting

User: club members mainly use Facebook and need posting to feel familiar.

Default new-post flow: centred Create a post card, author/avatar, clear club/approval audience, a large prompted writing area, immediate photo previews, optional formatting, preview and a prominent Post button. No mandatory heading, cover or summary settings in the simple view. Keep the full Write an article workspace as a separate option. Preserve automatic photo reduction, file restrictions, draft version protection, private media and committee approval. No Facebook branding or social transmission is implied.

Implementation extends the existing editor and staging flow. `post_kind` records post/article (existing content defaults to article); quick-post titles are generated from sanitised text, with Photo update for photo-only posts. Switching an unsaved composition to the article workspace first saves it. Videos remain outside this change; the upload action is labelled Photo.

Backend composer/editor tests cover automatic titles, photo-only posts, invalid types, article compatibility, and private pending revisions. Root checks the actual hosted composer, save/reopen, preview and mode switching before final delivery. Deployment uses private backups and the existing feature branch; secrets and uploads remain excluded from Git.
