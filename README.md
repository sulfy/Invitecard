# സ്നേഹ വിരുന്ന് · Sneha Virunnu — Web Invitation

An interactive, light-themed, bilingual (Malayalam / English) web invitation
for a family gathering (**സ്നേഹ വിരുന്ന്**) at **Muthuvattissery B2** on
**Saturday, July 11, 2026, 11:00 AM – 2:00 PM**, hosted by
**M A Basheer & Family**.

The whole site is a single self-contained file: [`index.html`](index.html).

## Features

- ✉️ Animated "Open Invitation" intro with floating petals
- 🌐 Malayalam ⇄ English toggle (remembers your choice)
- ⏳ Live countdown to the event
- 📅 "Add to Google Calendar" + downloadable `.ics` file
- 🗺 Embedded map, "Open in Google Maps" and "Get Directions" buttons
- 📞 One-tap Call / WhatsApp / SMS buttons for the hosts
- 📤 Native share button (WhatsApp fallback)
- 📱 Fully responsive, respects reduced-motion settings

## Hosting

### GitHub Pages (automatic)

`.github/workflows/deploy-pages.yml` deploys `index.html` to GitHub Pages on
every push. Live at: **https://sulfy.github.io/Invitecard/**

### weonegroup.com via FTP (needs one-time setup)

`.github/workflows/deploy-ftp.yml` uploads the site to the GoDaddy hosting.
Because this repository is **public**, the FTP credentials must be stored as
encrypted secrets — never in the code:

1. Go to **Settings → Secrets and variables → Actions → New repository secret**
2. Add `FTP_USERNAME` and `FTP_PASSWORD`
3. Run **Actions → "Deploy to weonegroup.com (FTP)" → Run workflow**
   (set *server_dir* if the site must go into a subfolder, e.g. `./public_html/invite/`)

### Custom domain on GitHub Pages (optional)

To serve the invitation at e.g. `invite.weonegroup.com`, add a DNS **CNAME**
record for `invite` pointing to `sulfy.github.io`, then set the custom domain
in the repo's **Settings → Pages**.
