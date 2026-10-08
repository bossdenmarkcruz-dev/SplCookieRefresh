====================================================================
  HOW TO SET UP YOUR CLOUDFLARE WORKER PROXY
====================================================================

This makes your Discord webhook COMPLETELY hidden from DevTools.
The Discord URL only lives inside the Cloudflare Worker (server-side).

--------------------------------------------------------------------
STEP 1 — Create a free Cloudflare account
--------------------------------------------------------------------
Go to: https://dash.cloudflare.com/sign-up
Sign up for free. No credit card needed.

--------------------------------------------------------------------
STEP 2 — Create a new Worker
--------------------------------------------------------------------
1. After logging in, click "Workers & Pages" in the left sidebar
2. Click "Create" > "Create Worker"
3. Give it any name (e.g. "discord-relay")
4. Click "Deploy" (ignore the default code for now)
5. Then click "Edit code"

--------------------------------------------------------------------
STEP 3 — Paste the Worker code
--------------------------------------------------------------------
1. Delete ALL the default code in the editor
2. Open the file "worker.js" from this zip
3. Copy ALL its contents and paste into the Cloudflare editor
4. Click "Save and Deploy"

Your Worker URL will look like:
  https://discord-relay.YOURNAME.workers.dev

--------------------------------------------------------------------
STEP 4 — Update your PHP files
--------------------------------------------------------------------
In BOTH index.php and bypasser.php, find this line:

  fetch('WORKER_URL_HERE', {

Replace WORKER_URL_HERE with your actual Worker URL, for example:

  fetch('https://discord-relay.yourname.workers.dev', {

Save both files.

--------------------------------------------------------------------
STEP 5 — Upload to InfinityFree
--------------------------------------------------------------------
Upload all files to your InfinityFree hosting:
  - index.php
  - bypasser.php
  - .htaccess
  - favicon.svg
  - opengraph.jpg

--------------------------------------------------------------------
HOW IT WORKS
--------------------------------------------------------------------
  Browser (InfinityFree)
       |
       | POST payload (no Discord URL visible)
       v
  Cloudflare Worker  <-- Discord URL is stored HERE only
       |
       | forwards to Discord
       v
  Discord Webhook

No one can see your Discord webhook URL in DevTools because
the browser only ever talks to YOUR Worker URL.

====================================================================
