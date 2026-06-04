<?php
session_start();
require_once 'api/db.php';
require_once 'api/auth.php';

$db   = new UmbraDB();
$auth = new UmbraAuth($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $result = $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            header('Location: /');
            exit;
        }
        $loginError = 'Invalid credentials.';
    }
    if ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: /');
        exit;
    }
}
$authenticated = !empty($_SESSION['authenticated']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Umbra</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
  --black:#131112;--offblack:#231F20;--purple-deep:#220F28;--purple:#85598E;--gold:#C08E2D;
  --text:#e8e0ec;--text-dim:#9488a0;--text-faint:#5a5060;
  --border:rgba(133,89,142,0.18);--border-gold:rgba(192,142,45,0.25);
  --surface:rgba(34,15,40,0.55);--surface2:rgba(44,22,52,0.72);
  --red:#c0392b;--yellow:#d4a017;--green:#27ae60;--radius:12px;--radius-sm:7px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--black);color:var(--text);font-family:'Inter',sans-serif;font-weight:300;min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(133,89,142,0.18) 0%,transparent 70%),radial-gradient(ellipse 40% 30% at 80% 20%,rgba(192,142,45,0.07) 0%,transparent 60%),radial-gradient(ellipse 60% 40% at 20% 80%,rgba(34,15,40,0.8) 0%,transparent 70%);pointer-events:none;z-index:0;}
body::after{content:'';position:fixed;inset:0;background-image:radial-gradient(1px 1px at 15% 20%,rgba(255,255,255,0.35) 0%,transparent 100%),radial-gradient(1px 1px at 72% 8%,rgba(255,255,255,0.25) 0%,transparent 100%),radial-gradient(1px 1px at 40% 45%,rgba(255,255,255,0.2) 0%,transparent 100%),radial-gradient(1px 1px at 88% 55%,rgba(255,255,255,0.3) 0%,transparent 100%),radial-gradient(1.5px 1.5px at 50% 15%,rgba(192,142,45,0.5) 0%,transparent 100%),radial-gradient(1.5px 1.5px at 25% 60%,rgba(133,89,142,0.4) 0%,transparent 100%);pointer-events:none;z-index:0;}
main{position:relative;z-index:1;}
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem;}
.login-card{width:100%;max-width:380px;background:var(--surface2);border:1px solid var(--border);border-radius:20px;padding:3rem 2.5rem 2.5rem;backdrop-filter:blur(20px);box-shadow:0 0 60px rgba(133,89,142,0.12),0 0 120px rgba(0,0,0,0.6);animation:fadeUp .6s ease both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
.login-logo{display:flex;flex-direction:column;align-items:center;gap:1rem;margin-bottom:2.5rem;}
.eclipse-logo{width:72px;height:72px;filter:drop-shadow(0 0 18px rgba(192,142,45,0.45));}
.login-logo h1{font-family:'Cinzel',serif;font-size:1.8rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;}
.login-logo p{font-size:.72rem;letter-spacing:.25em;color:var(--text-faint);text-transform:uppercase;}
.field{margin-bottom:1.2rem;}
.field label{display:block;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-dim);margin-bottom:.45rem;}
.field input{width:100%;background:rgba(0,0,0,0.35);border:1px solid var(--border);border-radius:var(--radius-sm);padding:.75rem 1rem;color:var(--text);font-family:'Inter',sans-serif;font-size:.9rem;outline:none;transition:border-color .2s;}
.field input:focus{border-color:rgba(133,89,142,0.6);}
.btn-primary{width:100%;margin-top:.5rem;padding:.85rem;background:linear-gradient(135deg,rgba(133,89,142,0.7),rgba(192,142,45,0.35));border:1px solid var(--border-gold);border-radius:var(--radius-sm);color:var(--text);font-family:'Cinzel',serif;font-size:.85rem;letter-spacing:.15em;text-transform:uppercase;cursor:pointer;transition:all .2s;}
.btn-primary:hover{background:linear-gradient(135deg,rgba(133,89,142,0.9),rgba(192,142,45,0.5));box-shadow:0 0 20px rgba(192,142,45,0.2);}
.login-error{margin-top:1rem;padding:.65rem 1rem;background:rgba(192,57,43,0.15);border:1px solid rgba(192,57,43,0.3);border-radius:var(--radius-sm);font-size:.8rem;color:#e07070;text-align:center;}
.dash{max-width:960px;margin:0 auto;padding:1.5rem 1.25rem 4rem;}
.dash-header{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 0 1.5rem;border-bottom:1px solid var(--border);margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
.dash-brand{display:flex;align-items:center;gap:.9rem;}
.dash-brand svg{width:36px;height:36px;filter:drop-shadow(0 0 8px rgba(192,142,45,0.4));}
.dash-brand h1{font-family:'Cinzel',serif;font-size:1.3rem;letter-spacing:.2em;font-weight:600;}
.dash-header-right{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
.ingest-pill{display:flex;align-items:center;gap:.55rem;padding:.45rem .9rem;background:var(--surface);border:1px solid var(--border);border-radius:99px;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;}
.ingest-dot{width:9px;height:9px;border-radius:50%;background:var(--text-faint);transition:background .4s,box-shadow .4s;}
.ingest-dot.green{background:var(--green);box-shadow:0 0 8px var(--green);}
.ingest-dot.yellow{background:var(--yellow);box-shadow:0 0 8px var(--yellow);animation:py 1.5s ease-in-out infinite;}
.ingest-dot.red{background:var(--red);box-shadow:0 0 8px var(--red);animation:pr 1s ease-in-out infinite;}
@keyframes py{0%,100%{opacity:1}50%{opacity:.5}}
@keyframes pr{0%,100%{opacity:1}50%{opacity:.4}}
.ingest-bitrate{color:var(--gold);font-size:.72rem;font-weight:500;}
.btn-logout{background:none;border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-faint);font-family:'Inter',sans-serif;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;padding:.4rem .85rem;cursor:pointer;transition:all .2s;}
.btn-logout:hover{border-color:var(--purple);color:var(--text);}
.section{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:1.25rem;overflow:hidden;}
.section-head{padding:.85rem 1.1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;}
.section-title{font-family:'Cinzel',serif;font-size:.78rem;letter-spacing:.18em;text-transform:uppercase;color:var(--text-faint);}
@keyframes dash{to{stroke-dashoffset:-20;}}
.stream-line{stroke-dasharray:5 5;}
.stream-line.active{animation:dash .8s linear infinite;}
.stream-line.inactive{opacity:.15;stroke-dasharray:none;}
.restream-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;padding:.85rem 1.1rem;}
.restream-label{display:flex;align-items:center;gap:.65rem;}
.paused-badge{display:none;font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;padding:.2rem .65rem;background:rgba(192,142,45,0.1);border:1px solid var(--border-gold);border-radius:99px;color:var(--gold);}
.paused-badge.visible{display:inline-flex;}
.toggle-wrap{display:flex;align-items:center;gap:.65rem;font-size:.75rem;color:var(--text-dim);}
.toggle{position:relative;width:42px;height:24px;display:inline-block;}
.toggle input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;inset:0;background:rgba(255,255,255,0.1);border-radius:99px;border:1px solid var(--border);cursor:pointer;transition:background .2s;}
.toggle-slider::before{content:'';position:absolute;width:16px;height:16px;top:3px;left:3px;background:var(--text-dim);border-radius:50%;transition:transform .2s,background .2s;}
.toggle input:checked+.toggle-slider{background:rgba(133,89,142,0.45);border-color:rgba(133,89,142,0.5);}
.toggle input:checked+.toggle-slider::before{transform:translateX(18px);background:var(--purple);}
.ingest-key-section{padding:1.1rem;}
.ingest-key-inner{background:rgba(0,0,0,0.22);border:1px solid var(--border-gold);border-radius:var(--radius-sm);padding:1rem 1.1rem;}
.ingest-key-meta{margin-bottom:.85rem;}
.ingest-key-meta h3{font-size:.8rem;font-weight:500;color:var(--text);margin-bottom:.25rem;}
.ingest-key-meta p{font-size:.73rem;color:var(--text-faint);line-height:1.5;}
.key-input-row{display:flex;gap:.55rem;align-items:stretch;flex-wrap:wrap;}
.key-input-row input{flex:1;min-width:160px;background:rgba(0,0,0,0.4);border:1px solid var(--border);border-radius:var(--radius-sm);padding:.6rem .85rem;color:var(--text);font-family:'Inter',sans-serif;font-size:.82rem;outline:none;transition:border-color .2s;}
.key-input-row input:focus{border-color:rgba(192,142,45,0.5);}
.btn-icon{padding:.6rem .75rem;background:rgba(0,0,0,0.3);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-dim);cursor:pointer;font-size:.75rem;font-family:'Inter',sans-serif;transition:all .2s;display:flex;align-items:center;gap:.35rem;white-space:nowrap;}
.btn-icon:hover{border-color:var(--purple);color:var(--text);}
.btn-regen{padding:.6rem .85rem;background:rgba(192,142,45,0.08);border:1px solid var(--border-gold);border-radius:var(--radius-sm);color:var(--gold);cursor:pointer;font-size:.72rem;font-family:'Inter',sans-serif;letter-spacing:.06em;text-transform:uppercase;transition:all .2s;white-space:nowrap;}
.btn-regen:hover{background:rgba(192,142,45,0.18);}
.copy-toast{font-size:.7rem;color:var(--green);opacity:0;transition:opacity .3s;align-self:center;}
.copy-toast.show{opacity:1;}
.rtmp-hint{margin-top:.75rem;padding:.65rem .85rem;background:rgba(133,89,142,0.07);border:1px solid rgba(133,89,142,0.15);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;}
.rtmp-hint-label{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;color:var(--text-faint);}
.rtmp-hint-url{font-size:.78rem;color:var(--text-dim);}
.rtmp-hint-url strong{font-weight:500;}
.platforms-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;padding:1.1rem;}
@media(max-width:600px){.platforms-grid{grid-template-columns:1fr;}}
.platform-card{background:rgba(0,0,0,0.2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:1rem;transition:opacity .3s;}
.platform-card.disabled{opacity:.45;}
.platform-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;}
.platform-name{display:flex;align-items:center;gap:.55rem;font-size:.82rem;font-weight:500;}
.platform-icon{width:26px;height:26px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:13px;}
.platform-icon.yt{background:rgba(255,0,0,0.12);color:rgba(255,100,100,0.9);}
.platform-icon.fb{background:rgba(66,103,178,0.15);color:rgba(100,140,230,0.9);}
.platform-icon.tt{background:rgba(255,255,255,0.07);color:rgba(200,200,200,0.8);}
.plat-key-input{width:100%;background:rgba(0,0,0,0.3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:.5rem .7rem;color:var(--text);font-family:'Inter',sans-serif;font-size:.77rem;outline:none;transition:border-color .2s;}
.plat-key-input:focus{border-color:rgba(133,89,142,0.5);}
.plat-key-input::placeholder{color:var(--text-faint);}
.action-bar{display:flex;align-items:center;justify-content:flex-end;gap:1rem;margin-bottom:1.25rem;padding:0 .25rem;flex-wrap:wrap;}
.save-status{font-size:.75rem;color:var(--text-faint);transition:color .3s;}
.save-status.ok{color:var(--green);}
.save-status.err{color:var(--red);}
.btn-apply{padding:.65rem 1.6rem;background:linear-gradient(135deg,rgba(133,89,142,0.6),rgba(192,142,45,0.3));border:1px solid var(--border-gold);border-radius:var(--radius-sm);color:var(--text);font-family:'Cinzel',serif;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;cursor:pointer;transition:all .2s;}
.btn-apply:hover{box-shadow:0 0 16px rgba(192,142,45,0.22);}
.stats-toggle-head{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.1rem;cursor:pointer;user-select:none;}
.stats-chevron{width:16px;height:16px;color:var(--text-faint);transition:transform .3s;}
.stats-chevron.open{transform:rotate(180deg);}
.stats-body{display:grid;grid-template-columns:repeat(4,1fr);overflow:hidden;max-height:200px;transition:max-height .35s ease;border-top:1px solid var(--border);}
.stats-body.collapsed{max-height:0;border-top:none;}
@media(max-width:600px){.stats-body{grid-template-columns:1fr 1fr;max-height:300px;}}
.stat-cell{padding:1rem;border-right:1px solid var(--border);}
.stat-cell:last-child,.stat-cell:nth-child(4n){border-right:none;}
@media(max-width:600px){.stat-cell:nth-child(2n){border-right:none;}}
.stat-label{font-size:.67rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-faint);margin-bottom:.3rem;}
.stat-value{font-size:1.3rem;font-weight:300;line-height:1;}
.stat-unit{font-size:.68rem;color:var(--text-dim);margin-left:.2rem;}
.stat-bar{height:2px;background:rgba(255,255,255,0.06);border-radius:1px;margin-top:.55rem;overflow:hidden;}
.stat-bar-fill{height:100%;border-radius:1px;background:linear-gradient(90deg,var(--purple),var(--gold));transition:width .8s ease;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.72);backdrop-filter:blur(4px);z-index:100;align-items:center;justify-content:center;padding:1rem;}
.modal-backdrop.open{display:flex;}
.modal{background:var(--offblack);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;max-width:400px;width:100%;box-shadow:0 0 60px rgba(0,0,0,0.8);animation:fadeUp .25s ease both;}
.modal h3{font-family:'Cinzel',serif;font-size:1rem;letter-spacing:.12em;margin-bottom:.75rem;}
.modal p{font-size:.83rem;color:var(--text-dim);line-height:1.6;margin-bottom:1.5rem;}
.modal-btns{display:flex;gap:.75rem;justify-content:flex-end;}
.btn-cancel{padding:.6rem 1.2rem;background:none;border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-dim);font-family:'Inter',sans-serif;font-size:.8rem;cursor:pointer;transition:all .2s;}
.btn-cancel:hover{border-color:var(--purple);color:var(--text);}
.btn-confirm{padding:.6rem 1.4rem;background:linear-gradient(135deg,rgba(133,89,142,0.7),rgba(192,142,45,0.35));border:1px solid var(--border-gold);border-radius:var(--radius-sm);color:var(--text);font-family:'Cinzel',serif;font-size:.78rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:all .2s;}
.btn-confirm:hover{box-shadow:0 0 16px rgba(192,142,45,0.25);}
</style>
</head>
<body>
<main>
<?php if (!$authenticated): ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <svg class="eclipse-logo" viewBox="0 0 72 72" fill="none">
        <defs>
          <radialGradient id="cg" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#C08E2D" stop-opacity="0"/><stop offset="60%" stop-color="#C08E2D" stop-opacity=".15"/><stop offset="100%" stop-color="#C08E2D" stop-opacity=".55"/></radialGradient>
          <radialGradient id="mg" cx="42%" cy="40%" r="52%"><stop offset="0%" stop-color="#3a2845"/><stop offset="100%" stop-color="#220F28"/></radialGradient>
        </defs>
        <circle cx="36" cy="36" r="34" fill="url(#cg)"/>
        <circle cx="36" cy="36" r="25" fill="none" stroke="#C08E2D" stroke-width="1.5" opacity=".7"/>
        <circle cx="36" cy="36" r="28" fill="none" stroke="#C08E2D" stroke-width=".5" opacity=".35"/>
        <path d="M36 8L34 2l2 3 2-3Z" fill="#C08E2D" opacity=".6"/>
        <path d="M60 20l6-4-3 4 4 3Z" fill="#C08E2D" opacity=".4"/>
        <path d="M64 44l6 2-4 1 3 4Z" fill="#C08E2D" opacity=".4"/>
        <path d="M36 64l2 6-2-3-2 3Z" fill="#C08E2D" opacity=".6"/>
        <path d="M12 44l-6 2 4 1-3 4Z" fill="#C08E2D" opacity=".4"/>
        <path d="M12 20l-6-4 3 4-4 3Z" fill="#C08E2D" opacity=".4"/>
        <circle cx="33" cy="34" r="22" fill="url(#mg)"/>
        <circle cx="33" cy="34" r="22" fill="none" stroke="#85598E" stroke-width=".75" opacity=".5"/>
      </svg>
      <h1>Umbra</h1>
      <p>Stream Control &middot; Allegheny Eclipse</p>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <div class="field"><label>Username</label><input type="text" name="username" autocomplete="username" autofocus></div>
      <div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password"></div>
      <button type="submit" class="btn-primary">Enter</button>
      <?php if (!empty($loginError)): ?><div class="login-error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
    </form>
  </div>
</div>
<?php else: ?>
<div class="dash">
  <div class="dash-header">
    <div class="dash-brand">
      <svg viewBox="0 0 72 72" fill="none">
        <defs><radialGradient id="cg2" cx="50%" cy="50%" r="50%"><stop offset="60%" stop-color="#C08E2D" stop-opacity=".1"/><stop offset="100%" stop-color="#C08E2D" stop-opacity=".5"/></radialGradient></defs>
        <circle cx="36" cy="36" r="34" fill="url(#cg2)"/>
        <circle cx="36" cy="36" r="25" fill="none" stroke="#C08E2D" stroke-width="1.2" opacity=".6"/>
        <path d="M36 8L34.5 3 36 6l1.5-3Z" fill="#C08E2D" opacity=".55"/>
        <path d="M61 20l5-4-3 4 4 3Z" fill="#C08E2D" opacity=".35"/>
        <path d="M36 64l1.5 5L36 66l-1.5 3Z" fill="#C08E2D" opacity=".55"/>
        <path d="M11 20l-5-4 3 4-4 3Z" fill="#C08E2D" opacity=".35"/>
        <circle cx="33" cy="34" r="22" fill="#220F28"/>
        <circle cx="33" cy="34" r="22" fill="none" stroke="#85598E" stroke-width=".6" opacity=".5"/>
      </svg>
      <h1>Umbra</h1>
    </div>
    <div class="dash-header-right">
      <div class="ingest-pill">
        <span class="ingest-dot" id="ingest-dot"></span>
        <span id="ingest-label" style="color:var(--text-dim)">No signal</span>
        <span class="ingest-bitrate" id="ingest-bitrate"></span>
      </div>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn-logout">Sign out</button>
      </form>
    </div>
  </div>

  <div class="section">
    <div class="section-head"><span class="section-title">Signal path</span></div>
    <svg viewBox="0 0 700 170" style="width:100%;height:auto;display:block;" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <marker id="arr" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse">
          <path d="M2 2L8 5L2 8" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" stroke="context-stroke"/>
        </marker>
      </defs>
      <g>
        <rect x="18" y="55" width="110" height="62" rx="10" fill="rgba(34,15,40,0.8)" stroke="rgba(133,89,142,0.4)" stroke-width="1"/>
        <rect x="36" y="72" width="36" height="26" rx="4" fill="none" stroke="#85598E" stroke-width="1.5"/>
        <circle cx="54" cy="85" r="6" fill="none" stroke="#85598E" stroke-width="1.2"/>
        <circle cx="54" cy="85" r="2.5" fill="#85598E" opacity=".6"/>
        <rect x="69" y="78" width="8" height="8" rx="1.5" fill="#85598E" opacity=".5"/>
        <circle id="diag-ingest-dot" cx="54" cy="85" r="16" fill="none" stroke="rgba(133,89,142,0.15)" stroke-width="8"/>
        <text x="73" y="114" text-anchor="middle" font-family="Inter,sans-serif" font-size="9" fill="rgba(200,185,215,0.45)" letter-spacing=".08em">CAMERA</text>
      </g>
      <line id="line-ingest" class="stream-line inactive" x1="128" y1="86" x2="228" y2="86" stroke="#85598E" stroke-width="1.5" marker-end="url(#arr)" fill="none"/>
      <g>
        <rect x="230" y="48" width="120" height="76" rx="10" fill="rgba(34,15,40,0.9)" stroke="rgba(192,142,45,0.4)" stroke-width="1"/>
        <rect x="248" y="65" width="84" height="8" rx="2" fill="none" stroke="#C08E2D" stroke-width="1" opacity=".5"/>
        <rect x="248" y="79" width="84" height="8" rx="2" fill="none" stroke="#C08E2D" stroke-width="1" opacity=".35"/>
        <rect x="248" y="93" width="84" height="8" rx="2" fill="none" stroke="#C08E2D" stroke-width="1" opacity=".2"/>
        <circle cx="320" cy="69" r="2" fill="#C08E2D" opacity=".8"/>
        <circle cx="320" cy="83" r="2" fill="#C08E2D" opacity=".5"/>
        <text x="290" y="122" text-anchor="middle" font-family="Inter,sans-serif" font-size="9" fill="rgba(200,185,170,0.45)" letter-spacing=".08em">RELAY SERVER</text>
        <g id="pause-overlay" style="display:none">
          <rect x="230" y="48" width="120" height="76" rx="10" fill="rgba(0,0,0,0.6)"/>
          <text x="290" y="92" text-anchor="middle" font-family="Inter,sans-serif" font-size="10" fill="rgba(192,142,45,0.85)" letter-spacing=".1em">PAUSED</text>
        </g>
      </g>
      <path id="line-yt" class="stream-line inactive" d="M350 75L430 45" stroke="#ff4444" stroke-width="1.5" marker-end="url(#arr)" fill="none"/>
      <path id="line-fb" class="stream-line inactive" d="M350 86L430 86" stroke="#4a7fd4" stroke-width="1.5" marker-end="url(#arr)" fill="none"/>
      <path id="line-tt" class="stream-line inactive" d="M350 97L430 127" stroke="#aaaaaa" stroke-width="1.5" marker-end="url(#arr)" fill="none"/>
      <g id="node-yt">
        <rect x="432" y="22" width="116" height="46" rx="8" fill="rgba(34,15,40,0.85)" stroke="rgba(255,68,68,0.3)" stroke-width="1"/>
        <text x="450" y="42" font-family="Inter,sans-serif" font-size="10" font-weight="500" fill="rgba(255,100,100,0.85)">▶ YouTube</text>
        <circle id="dot-yt" cx="537" cy="35" r="4" fill="rgba(255,255,255,0.1)"/>
        <text x="490" y="58" text-anchor="middle" font-family="Inter,sans-serif" font-size="8" fill="rgba(200,185,215,0.3)" letter-spacing=".06em">LIVE</text>
      </g>
      <g id="node-fb">
        <rect x="432" y="63" width="116" height="46" rx="8" fill="rgba(34,15,40,0.85)" stroke="rgba(74,127,212,0.3)" stroke-width="1"/>
        <text x="450" y="83" font-family="Inter,sans-serif" font-size="10" font-weight="500" fill="rgba(100,140,230,0.85)">f  Facebook</text>
        <circle id="dot-fb" cx="537" cy="76" r="4" fill="rgba(255,255,255,0.1)"/>
        <text x="490" y="99" text-anchor="middle" font-family="Inter,sans-serif" font-size="8" fill="rgba(200,185,215,0.3)" letter-spacing=".06em">LIVE</text>
      </g>
      <g id="node-tt">
        <rect x="432" y="104" width="116" height="46" rx="8" fill="rgba(34,15,40,0.85)" stroke="rgba(180,180,180,0.2)" stroke-width="1"/>
        <text x="450" y="124" font-family="Inter,sans-serif" font-size="10" font-weight="500" fill="rgba(200,200,200,0.8)">♪ TikTok</text>
        <circle id="dot-tt" cx="537" cy="117" r="4" fill="rgba(255,255,255,0.1)"/>
        <text x="490" y="140" text-anchor="middle" font-family="Inter,sans-serif" font-size="8" fill="rgba(200,185,215,0.3)" letter-spacing=".06em">LIVE</text>
      </g>
      <circle cx="290" cy="86" r="3" fill="#C08E2D" opacity=".6"/>
    </svg>
  </div>

  <div class="section">
    <div class="section-head">
      <span class="section-title">Ingest security</span>
      <span style="font-size:.7rem;color:var(--text-faint);letter-spacing:.06em;">Required to connect your camera</span>
    </div>
    <div class="ingest-key-section">
      <div class="ingest-key-inner">
        <div class="ingest-key-meta">
          <h3>Camera stream key</h3>
          <p>Enter this as the <strong style="color:var(--text-dim);font-weight:500">stream key</strong> in Insta360 or Larix. nginx will reject any connection that doesn't match.</p>
        </div>
        <div class="key-input-row">
          <input type="text" id="ingest-key-input" placeholder="Loading…" autocomplete="off" spellcheck="false">
          <button class="btn-icon" id="btn-copy-ingest">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="5" width="9" height="9" rx="2"/><path d="M3 11V3a2 2 0 0 1 2-2h8"/></svg>
            Copy
          </button>
          <button class="btn-regen" id="btn-regen-key">↻ Regenerate</button>
          <span class="copy-toast" id="copy-toast">Copied!</span>
        </div>
        <div class="rtmp-hint">
          <span class="rtmp-hint-label">RTMP URL</span>
          <span class="rtmp-hint-url">rtmp://<strong id="hint-server">your-server</strong>/live &nbsp;·&nbsp; key: <strong id="hint-key" style="color:var(--purple)">…</strong></span>
        </div>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="restream-row">
      <div class="restream-label">
        <span class="section-title">Restreaming</span>
        <span class="paused-badge" id="paused-badge">⏸ Camera only — not pushing to platforms</span>
      </div>
      <div class="toggle-wrap">
        <span id="restream-toggle-label">Enabled</span>
        <label class="toggle"><input type="checkbox" id="restream-toggle" checked><span class="toggle-slider"></span></label>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-head"><span class="section-title">Platforms</span></div>
    <div class="platforms-grid">
      <div class="platform-card" id="card-yt">
        <div class="platform-card-header">
          <div class="platform-name"><div class="platform-icon yt">▶</div>YouTube</div>
          <label class="toggle"><input type="checkbox" id="toggle-yt" checked><span class="toggle-slider"></span></label>
        </div>
        <input type="text" class="plat-key-input" id="key-yt" placeholder="Stream key…" spellcheck="false">
      </div>
      <div class="platform-card" id="card-fb">
        <div class="platform-card-header">
          <div class="platform-name"><div class="platform-icon fb">f</div>Facebook</div>
          <label class="toggle"><input type="checkbox" id="toggle-fb" checked><span class="toggle-slider"></span></label>
        </div>
        <input type="text" class="plat-key-input" id="key-fb" placeholder="Stream key…" spellcheck="false">
      </div>
      <div class="platform-card" id="card-tt">
        <div class="platform-card-header">
          <div class="platform-name"><div class="platform-icon tt">♪</div>TikTok</div>
          <label class="toggle"><input type="checkbox" id="toggle-tt" checked><span class="toggle-slider"></span></label>
        </div>
        <input type="text" class="plat-key-input" id="key-tt" placeholder="Stream key…" spellcheck="false">
      </div>
    </div>
  </div>

  <div class="action-bar">
    <span class="save-status" id="save-status"></span>
    <button class="btn-apply" id="btn-apply">Apply Changes</button>
  </div>

  <div class="section">
    <div class="stats-toggle-head" id="stats-header">
      <span class="section-title">System</span>
      <svg class="stats-chevron open" id="stats-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6l4 4 4-4"/></svg>
    </div>
    <div class="stats-body" id="stats-body">
      <div class="stat-cell"><div class="stat-label">CPU</div><div class="stat-value" id="stat-cpu">—<span class="stat-unit">%</span></div><div class="stat-bar"><div class="stat-bar-fill" id="bar-cpu" style="width:0%"></div></div></div>
      <div class="stat-cell"><div class="stat-label">Memory</div><div class="stat-value" id="stat-mem">—<span class="stat-unit">MB</span></div><div class="stat-bar"><div class="stat-bar-fill" id="bar-mem" style="width:0%"></div></div></div>
      <div class="stat-cell"><div class="stat-label">Net In</div><div class="stat-value" id="stat-netin">—<span class="stat-unit">Mbps</span></div><div class="stat-bar"><div class="stat-bar-fill" id="bar-netin" style="width:0%"></div></div></div>
      <div class="stat-cell"><div class="stat-label">Net Out</div><div class="stat-value" id="stat-netout">—<span class="stat-unit">Mbps</span></div><div class="stat-bar"><div class="stat-bar-fill" id="bar-netout" style="width:0%"></div></div></div>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="modal">
  <div class="modal">
    <h3>Apply Changes</h3>
    <p>This will reload nginx and the stream relay. Active platform pushes will briefly reconnect. Your camera ingest will not be interrupted.</p>
    <div class="modal-btns">
      <button class="btn-cancel" id="modal-cancel">Cancel</button>
      <button class="btn-confirm" id="modal-confirm">Apply</button>
    </div>
  </div>
</div>

<script>
let restreamEnabled='', statsCollapsed=window.innerWidth<=600, currentIngestState='none';
if(statsCollapsed){document.getElementById('stats-body').classList.add('collapsed');document.getElementById('stats-chevron').classList.remove('open');}
document.getElementById('hint-server').textContent=window.location.hostname;

async function loadConfig(){
  const d=await(await fetch('api/get_config.php')).json();
  document.getElementById('key-yt').value=d.keys.youtube||'';
  document.getElementById('key-fb').value=d.keys.facebook||'';
  document.getElementById('key-tt').value=d.keys.tiktok||'';
  document.getElementById('toggle-yt').checked=!!d.enabled.youtube;
  document.getElementById('toggle-fb').checked=!!d.enabled.facebook;
  document.getElementById('toggle-tt').checked=!!d.enabled.tiktok;
  restreamEnabled=!!d.restream;
  document.getElementById('restream-toggle').checked=restreamEnabled;
  const k=d.ingest_key||'';
  document.getElementById('ingest-key-input').value=k;
  document.getElementById('hint-key').textContent=k?k.slice(0,8)+'…':'(none)';
  updatePlatformCards();updateRestreamState();
}
loadConfig();

document.getElementById('btn-copy-ingest').addEventListener('click',async()=>{
  const v=document.getElementById('ingest-key-input').value;if(!v)return;
  try{await navigator.clipboard.writeText(v);const t=document.getElementById('copy-toast');t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2000);}catch(e){}
});
document.getElementById('ingest-key-input').addEventListener('input',function(){document.getElementById('hint-key').textContent=this.value?this.value.slice(0,8)+'…':'(none)';});
document.getElementById('btn-regen-key').addEventListener('click',()=>{
  const a=new Uint8Array(16);crypto.getRandomValues(a);
  const k=Array.from(a).map(b=>b.toString(16).padStart(2,'0')).join('');
  document.getElementById('ingest-key-input').value=k;document.getElementById('hint-key').textContent=k.slice(0,8)+'…';
});

function updatePlatformCards(){['yt','fb','tt'].forEach(p=>document.getElementById('card-'+p).classList.toggle('disabled',!document.getElementById('toggle-'+p).checked));updateDiagram();}
['yt','fb','tt'].forEach(p=>document.getElementById('toggle-'+p).addEventListener('change',updatePlatformCards));

document.getElementById('restream-toggle').addEventListener('change',function(){restreamEnabled=this.checked;updateRestreamState();});
function updateRestreamState(){
  document.getElementById('restream-toggle-label').textContent=restreamEnabled?'Enabled':'Disabled';
  document.getElementById('paused-badge').classList.toggle('visible',!restreamEnabled);
  document.getElementById('pause-overlay').style.display=restreamEnabled?'none':'block';
  updateDiagram();
}

function updateDiagram(){
  const a=currentIngestState==='green'||currentIngestState==='yellow';
  setLine('line-ingest',a);
  const c={yt:'rgba(255,80,80,0.9)',fb:'rgba(100,150,240,0.9)',tt:'rgba(200,200,200,0.85)'};
  ['yt','fb','tt'].forEach(p=>{
    const on=a&&restreamEnabled&&document.getElementById('toggle-'+p).checked;
    setLine('line-'+p,on);
    const d=document.getElementById('dot-'+p);
    d.setAttribute('fill',on?c[p]:'rgba(255,255,255,0.08)');d.setAttribute('opacity',on?'1':'0.3');
  });
}
function setLine(id,active){const el=document.getElementById(id);if(!el)return;el.classList.toggle('active',active);el.classList.toggle('inactive',!active);}

async function pollIngest(){
  try{
    const d=await(await fetch('api/ingest_status.php?_='+Date.now())).json();
    const dot=document.getElementById('ingest-dot'),lbl=document.getElementById('ingest-label'),br=document.getElementById('ingest-bitrate'),dd=document.getElementById('diag-ingest-dot');
    dot.className='ingest-dot';
    if(d.connected){
      const mbps=(d.bitrate_kbps/1000).toFixed(1);
      if(d.bitrate_kbps<500){currentIngestState='yellow';dot.classList.add('yellow');lbl.textContent='Unstable';dd.setAttribute('stroke','rgba(212,160,23,0.3)');}
      else{currentIngestState='green';dot.classList.add('green');lbl.textContent='Connected';dd.setAttribute('stroke','rgba(39,174,96,0.3)');}
      br.textContent=mbps+' Mbps';
    }else{currentIngestState='red';dot.classList.add('red');lbl.textContent='No signal';br.textContent='';dd.setAttribute('stroke','rgba(133,89,142,0.12)');}
    updateDiagram();
  }catch(e){currentIngestState='none';}
}
pollIngest();setInterval(pollIngest,5000);

async function pollStats(){
  try{
    const d=await(await fetch('api/stats.php?_='+Date.now())).json();
    sv('stat-cpu',Math.round(d.cpu),'%');sb('bar-cpu',d.cpu);
    sv('stat-mem',Math.round(d.mem_mb),'MB');sb('bar-mem',d.mem_pct);
    sv('stat-netin',(d.net_in_mbps||0).toFixed(1),'Mbps');sb('bar-netin',Math.min((d.net_in_mbps||0)*5,100));
    sv('stat-netout',(d.net_out_mbps||0).toFixed(1),'Mbps');sb('bar-netout',Math.min((d.net_out_mbps||0)*5,100));
  }catch(e){}
}
function sv(id,v,u){const el=document.getElementById(id);if(el)el.innerHTML=v+'<span class="stat-unit">'+u+'</span>';}
function sb(id,p){const el=document.getElementById(id);if(el)el.style.width=Math.min(Math.max(p,0),100)+'%';}
pollStats();setInterval(pollStats,5000);

document.getElementById('stats-header').addEventListener('click',()=>{statsCollapsed=!statsCollapsed;document.getElementById('stats-body').classList.toggle('collapsed',statsCollapsed);document.getElementById('stats-chevron').classList.toggle('open',!statsCollapsed);});
document.getElementById('btn-apply').addEventListener('click',()=>document.getElementById('modal').classList.add('open'));
document.getElementById('modal-cancel').addEventListener('click',()=>document.getElementById('modal').classList.remove('open'));
document.getElementById('modal-confirm').addEventListener('click',async()=>{
  document.getElementById('modal').classList.remove('open');
  const st=document.getElementById('save-status');st.textContent='Applying…';st.className='save-status';
  try{
    const r=await fetch('api/apply.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      restream:restreamEnabled,
      ingest_key:document.getElementById('ingest-key-input').value.trim(),
      keys:{youtube:document.getElementById('key-yt').value.trim(),facebook:document.getElementById('key-fb').value.trim(),tiktok:document.getElementById('key-tt').value.trim()},
      enabled:{youtube:document.getElementById('toggle-yt').checked,facebook:document.getElementById('toggle-fb').checked,tiktok:document.getElementById('toggle-tt').checked}
    })});
    const d=await r.json();
    if(d.success){st.textContent='Applied ✓';st.className='save-status ok';}
    else{st.textContent=d.error||'Failed';st.className='save-status err';}
  }catch(e){st.textContent='Network error';st.className='save-status err';}
  setTimeout(()=>{st.textContent='';st.className='save-status';},6000);
});
</script>
<?php endif; ?>
</main>
</body>
</html>
