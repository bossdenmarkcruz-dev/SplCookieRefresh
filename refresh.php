<?php
/* CORS — open to all origins */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* ── Pretty page for browser GET visits ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') { ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cookie Refresh — Public Endpoint</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#fff;--surface:#f4f4f5;--surface2:#e4e4e7;--border:#d4d4d8;--text:#09090b;--text2:#3f3f46;--text3:#71717a;--text4:#a1a1aa;--accent:#000;--green:#16a34a;--green-bg:#f0fdf4;--green-border:#bbf7d0;--radius:14px;--radius-sm:10px;--radius-xs:7px}
html,body{background:var(--bg);color:var(--text);font-family:'Inter',system-ui,sans-serif;font-size:14px;-webkit-font-smoothing:antialiased}
body{display:flex;flex-direction:column;align-items:center;min-height:100vh;padding:48px 20px}
.shell{width:100%;max-width:480px}
.header{display:flex;align-items:center;gap:12px;margin-bottom:32px}
.brand-icon{width:38px;height:38px;background:#000;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.brand-icon svg{stroke:#fff}
.brand-name{font-size:15px;font-weight:900;letter-spacing:-.3px}
.badge{margin-left:auto;font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--green);background:var(--green-bg);border:1.5px solid var(--green-border);border-radius:99px;padding:3px 10px}
.card{background:var(--bg);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:12px}
.card-header{padding:14px 18px 12px;border-bottom:1px solid var(--border);font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--text4)}
.card-body{padding:18px}
.endpoint-row{display:flex;align-items:center;gap:10px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-xs);padding:12px 14px;margin-bottom:6px}
.method{font-size:11px;font-weight:900;letter-spacing:1px;text-transform:uppercase;color:#fff;background:#000;border-radius:6px;padding:3px 8px;flex-shrink:0}
.url{font-family:'JetBrains Mono',monospace;font-size:11.5px;font-weight:500;color:var(--text2);word-break:break-all}
table{width:100%;border-collapse:collapse}
tr{border-bottom:1px solid var(--border)}
tr:last-child{border-bottom:none}
td{padding:9px 4px;vertical-align:top;font-size:12px}
td:first-child{font-family:'JetBrains Mono',monospace;font-weight:600;color:var(--text);width:110px;padding-right:12px}
td:nth-child(2){font-size:10.5px;font-weight:700;color:var(--text4);width:70px}
td:last-child{color:var(--text3);font-weight:500;line-height:1.55}
.code-block{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-xs);padding:14px;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.8;color:var(--text2);overflow-x:auto;white-space:pre}
.resp-block{background:#0a0a0a;border-radius:var(--radius-xs);padding:14px;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.8;color:#d4d4d8;overflow-x:auto;white-space:pre}
.footer{text-align:center;padding-top:20px;font-size:11px;font-weight:600;color:var(--text4)}
</style>
</head>
<body>
<div class="shell">

  <div class="header">
    <div class="brand-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
        <path d="M21 3v5h-5"/>
        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
        <path d="M8 16H3v5"/>
      </svg>
    </div>
    <div class="brand-name">Cookie Refresh API</div>
    <span class="badge">Live</span>
  </div>

  <div class="card">
    <div class="card-header">Endpoint</div>
    <div class="card-body">
      <div class="endpoint-row">
        <span class="method">POST</span>
        <span class="url"><?php echo htmlspecialchars('https://'.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'],'?')); ?></span>
      </div>
      <p style="font-size:12px;color:var(--text3);font-weight:500;margin-top:10px;line-height:1.6">
        Send a POST request with your <code style="font-family:'JetBrains Mono',monospace;background:var(--surface);padding:1px 5px;border-radius:4px">.ROBLOSECURITY</code> cookie. The server refreshes it server-side and returns the new value as JSON. CORS is open — any origin can call this.
      </p>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Parameters</div>
    <div class="card-body" style="padding:0">
      <table>
        <tr>
          <td>cookie</td>
          <td style="color:#dc2626;font-weight:800;font-size:10px">REQUIRED</td>
          <td>Full <code style="font-family:'JetBrains Mono',monospace">.ROBLOSECURITY</code> value including the warning prefix.</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Example Request</div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
      <div class="code-block">curl -X POST <?php echo htmlspecialchars('https://'.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'],'?')); ?> \
  -d "cookie=_|WARNING:-DO-NOT-SHARE-THIS..."</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Response — Success</div>
    <div class="card-body">
      <div class="resp-block">{"success": true, "cookie": "_|WARNING:-DO-NOT-SHARE-THIS..."}</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Response — Failure</div>
    <div class="card-body">
      <div class="resp-block">{"success": false, "error": "Failed to fetch CSRF token"}</div>
    </div>
  </div>

  <div class="footer">For personal use only &mdash; use responsibly.</div>
</div>
</body>
</html><?php exit; }

header('Content-Type: application/json');

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cookie = $_POST['cookie'];

    function fetchSessionCSRFToken($roblosecurityCookie) {
        $ch = curl_init("https://auth.roblox.com/v2/logout");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: .ROBLOSECURITY={$roblosecurityCookie}"]);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);
        if (preg_match('/x-csrf-token: (.+)/i', $headers, $matches)) return trim($matches[1]);
        error_log("Failed to fetch CSRF token. Headers: {$headers}");
        return null;
    }

    function generateAuthTicket($roblosecurityCookie) {
        $csrfToken = fetchSessionCSRFToken($roblosecurityCookie);
        if (!$csrfToken) return "Failed to fetch CSRF token";
        $ch = curl_init("https://auth.roblox.com/v1/authentication-ticket");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-csrf-token: $csrfToken",
            "referer: https://www.roblox.com/",
            "Content-Type: application/json",
            "Cookie: .ROBLOSECURITY={$roblosecurityCookie}"
        ]);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);
        if (preg_match('/rbx-authentication-ticket: (.+)/i', $headers, $matches)) return trim($matches[1]);
        error_log("Failed to fetch auth ticket. Headers: {$headers}");
        return "Failed to fetch auth ticket";
    }

    function redeemAuthTicket($authTicket) {
        $ch = curl_init("https://auth.roblox.com/v1/authentication-ticket/redeem");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["authenticationTicket" => $authTicket]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "RBXAuthenticationNegotiation: 1"
        ]);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);
        if (preg_match('/set-cookie: .ROBLOSECURITY=(.+?);/i', $headers, $matches)) return ["success" => true, "cookie" => trim($matches[1])];
        error_log("Failed to redeem auth ticket. Headers: {$headers}");
        return ["success" => false, "error" => "Failed to redeem auth ticket"];
    }

    $authTicket = generateAuthTicket($cookie);
    if ($authTicket === "Failed to fetch auth ticket" || $authTicket === "Failed to fetch CSRF token") {
        error_log("Failed to generate auth ticket. Cookie: {$cookie}");
        echo json_encode(["success" => false, "error" => $authTicket]);
        exit();
    }
    $redeemResult = redeemAuthTicket($authTicket);
    echo json_encode($redeemResult);
    exit();
}
?>
