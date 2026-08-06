# Pre-Launch Checklist

Everything below is placeholder content, included so the site is fully
functional and demonstrable immediately after installation. Replace or
remove each item before the site goes live for real visitors.

## Content to replace

- [ ] **Club name & village name** — Admin > Settings (currently "The Village
      Community Club" / "Little Meadow Village")
- [ ] **Logo** — Admin > Settings (currently a placeholder circle graphic)
- [ ] **Hero image on the homepage** — currently a placeholder SVG; add a real
      photo (edit `index.php`'s hero section or extend Admin > Settings with
      an uploaded hero image if you'd like it admin-editable)
- [ ] **Home page hero heading/subheading/welcome message/membership text** —
      Admin > Settings
- [ ] **About page content** — Admin > Page Content > About
- [ ] **Membership page content and rates** — Admin > Page Content > Membership
- [ ] **Facilities/Activities page content** — Admin > Page Content > Facilities
- [ ] **Committee intro text** — Admin > Page Content > Committee
- [ ] **Committee member names, roles, photos and biographies** — Admin > Committee
      (three placeholder entries are included)
- [ ] **Sample news articles** — Admin > News (three `[SAMPLE]` articles are
      included — edit or delete them)
- [ ] **Sample events** — Admin > Events (three `[SAMPLE]` events are included)
- [ ] **Sample newsletter** — Admin > Newsletters (replace the placeholder PDF
      with a real newsletter)
- [ ] **Sample gallery album** — Admin > Gallery (replace with real photos;
      remember to add alt text to every photo)
- [ ] **Contact details, address, opening hours, social media links** —
      Admin > Settings
- [ ] **Google Maps embed** — Admin > Settings > Address & Map (get an embed
      URL from Google Maps: Share > Embed a map)

## Legal content — must be reviewed by the club

- [ ] **Privacy Policy** — Admin > Page Content > Privacy Policy. The
      included text is a starting-point draft only, not legal advice.
- [ ] **Cookie Policy** — Admin > Page Content > Cookie Policy. Same caveat.
- [ ] **Accessibility Statement** — Admin > Page Content > Accessibility
      Statement. Review and adjust to reflect what's actually true of your
      finished site.

If in doubt about the legal wording for a UK/EU-facing club website, a
qualified advisor (or your local council/CVS association, which often
provides free guidance to community groups) should review these before
launch.

## Technical checklist

- [ ] Delete `install.php` from the server (see README section 14)
- [ ] Confirm `config/config.php` has `APP_ENV` set to `'production'`
- [ ] Confirm `FORCE_HTTPS` is `true` once your SSL certificate is active
- [ ] Set a real, random `APP_SECRET` if you configured `config.php` by hand
      instead of using the installer
- [ ] Test the contact form end-to-end and confirm the email arrives
- [ ] Test file uploads (news image, event image, newsletter PDF, gallery
      photos) end-to-end
- [ ] Create your first real backup (README section 12)
- [ ] Check the site on a phone and tablet, not just a desktop browser
- [ ] Ask someone unfamiliar with the site to try the contact form and find
      an event — a quick real-world test catches issues automated checks miss
