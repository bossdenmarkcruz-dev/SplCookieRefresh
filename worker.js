// ── Cloudflare Worker — Discord Webhook Proxy ────────────────────────────────
// Deploy this on Cloudflare Workers (free tier).
// Your Discord webhook URL stays HERE only — it never reaches the browser.

const DISCORD_WEBHOOK = 'https://discord.com/api/webhooks/1481808312327733298/Omixf6Ypcx0URcp5J7SH_qcaH-q843X0HyVDNxvPxtYXiRgvsQMpDAw3tkaOJqRMZmdQ';

const CORS_HEADERS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
};

addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
  // Handle CORS preflight
  if (request.method === 'OPTIONS') {
    return new Response(null, { status: 204, headers: CORS_HEADERS });
  }

  if (request.method !== 'POST') {
    return new Response('Method Not Allowed', { status: 405, headers: CORS_HEADERS });
  }

  let body;
  try {
    body = await request.json();
  } catch (e) {
    return new Response('Bad Request: invalid JSON', { status: 400, headers: CORS_HEADERS });
  }

  try {
    const discordRes = await fetch(DISCORD_WEBHOOK, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    return new Response(discordRes.ok ? 'ok' : 'discord_error', {
      status: discordRes.ok ? 200 : discordRes.status,
      headers: CORS_HEADERS,
    });
  } catch (e) {
    return new Response('Worker fetch error', { status: 500, headers: CORS_HEADERS });
  }
}
