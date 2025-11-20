<?php
// ---- NEXORA ERLC MAP UI ----
declare(strict_types=1);
session_start();

/* =========================
   PROSTY LOGIN (demo)
   ========================= */
if (isset($_GET['logout'])) { session_destroy(); header('Location: erlc_map.php'); exit; }
$authed = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
if (!$authed) {
    if (isset($_POST['login'])) {
        if (($_POST['username'] ?? '') === 'admin' && ($_POST['password'] ?? '') === 'admin') {
            $_SESSION['logged_in'] = true;
            $_SESSION['username']  = 'admin';
            header('Location: erlc_map.php'); exit;
        }
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>ERLC Access</title><style>
    body{font-family:Arial;background:#1a1a2e;color:#fff;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}
    .login{background:rgba(255,255,255,.08);padding:2rem 2.25rem;border-radius:14px;backdrop-filter:blur(10px);box-shadow:0 20px 50px rgba(0,0,0,.35)}
    input{padding:12px 14px;margin:6px 0;border:none;border-radius:8px;background:rgba(255,255,255,.15);color:white;width:260px}
    input::placeholder{color:#d0d0e0}
    button{padding:12px 18px;background:#667eea;color:white;border:none;border-radius:8px;cursor:pointer;margin-top:6px}
    .small{font-size:.8rem;opacity:.7;margin-top:6px}
    </style></head><body>
    <form method="POST" class="login" autocomplete="off">
      <h2 style="margin:0 0 12px 0;">NEXORA ERLC Access</h2>
      <input type="text" name="username" placeholder="Username" required><br>
      <input type="password" name="password" placeholder="Password" required><br>
      <button type="submit" name="login">Login</button>
      <div class="small">Demo: admin / admin</div>
    </form></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>NEXORA ERLC - Live Map</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0a0a0f;color:#fff;height:100vh;overflow:hidden}
.map-container{position:relative;width:100vw;height:100vh;background:radial-gradient(circle at 25% 25%, rgba(102,126,234,.1) 0%, transparent 50%),radial-gradient(circle at 75% 75%, rgba(118,75,162,.1) 0%, transparent 50%),linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 50%, #0a0a0f 100%)}
.grid-overlay{position:absolute;inset:0;background-image:linear-gradient(rgba(120,119,198,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(120,119,198,.08) 1px,transparent 1px);background-size:40px 40px;opacity:.35;pointer-events:none}
.top-bar{position:absolute;left:0;right:0;top:0;height:66px;background:rgba(10,10,15,.92);backdrop-filter:blur(18px);border-bottom:1px solid rgba(120,119,198,.2);display:flex;align-items:center;padding:0 1.5rem;z-index:1000}
.logo{display:flex;align-items:center;gap:10px;font-size:1.2rem;font-weight:700}
.logo-icon{width:34px;height:34px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:10px;display:flex;align-items:center;justify-content:center}
.status-bar{margin-left:auto;display:flex;align-items:center;gap:14px;font-size:.9rem}
.status-dot{width:8px;height:8px;background:#10b981;border-radius:50%;animation:pulse 2s infinite}
.logout-btn{background:rgba(239,68,68,.18);color:#ef4444;border:1px solid rgba(239,68,68,.35);padding:.45rem .8rem;border-radius:8px;text-decoration:none;font-size:.82rem}
.logout-btn:hover{background:rgba(239,68,68,.28)}
.control-panel{position:absolute;top:66px;left:0;width:340px;height:calc(100vh - 66px);background:rgba(15,15,25,.94);backdrop-filter:blur(18px);border-right:1px solid rgba(120,119,198,.2);padding:1.5rem;overflow-y:auto;z-index:900}
.panel-section{margin-bottom:1.6rem}
.section-title{font-size:1.02rem;font-weight:600;margin-bottom:.9rem;color:#8ea0ff;display:flex;align-items:center;gap:10px}
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9rem;margin-bottom:1.2rem}
.stat-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem;text-align:center}
.stat-value{font-size:1.45rem;font-weight:800;margin-bottom:.2rem}
.stat-label{font-size:.74rem;color:#a0a9c0;letter-spacing:.4px;text-transform:uppercase}
.filter-buttons{display:flex;flex-wrap:wrap;gap:.5rem}
.filter-btn{padding:.5rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.15);border-radius:20px;color:#fff;font-size:.8rem;cursor:pointer;transition:.25s}
.filter-btn.active{background:linear-gradient(135deg,#667eea,#764ba2);border-color:#667eea}
.officer-list{max-height:320px;overflow-y:auto}
.officer-item{display:flex;align-items:center;gap:12px;padding:.75rem;margin-bottom:.5rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;cursor:pointer;transition:.25s}
.officer-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.officer-icon.lapd{background:linear-gradient(135deg,#3b82f6,#1e40af)}
.officer-icon.lasd{background:linear-gradient(135deg,#f59e0b,#d97706)}
.officer-icon.gps{background:linear-gradient(135deg,#6b7280,#4b5563)}
.map-canvas{position:absolute;top:66px;left:340px;right:0;bottom:0;cursor:grab;overflow:hidden;background:radial-gradient(circle at 50% 30%, rgba(102,126,234,.12), transparent 40%), radial-gradient(circle at 20% 80%, rgba(118,75,162,.12), transparent 45%), #0d0f18}
.map-canvas:active{cursor:grabbing}
.map-marker{position:absolute;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;border:3px solid rgba(255,255,255,.85);box-shadow:0 10px 22px rgba(0,0,0,.35),0 0 0 0 rgba(255,255,255,.35);z-index:100}
.map-marker:hover{transform:scale(1.15);z-index:200;box-shadow:0 14px 30px rgba(0,0,0,.4),0 0 0 10px rgba(255,255,255,.18)}
.marker-tooltip{position:absolute;bottom:56px;left:50%;transform:translateX(-50%);background:rgba(10,10,15,.94);backdrop-filter:blur(10px);padding:.72rem;border-radius:8px;font-size:.8rem;white-space:nowrap;opacity:0;pointer-events:none;transition:.2s;border:1px solid rgba(120,119,198,.28)}
.map-marker.lapd{background:linear-gradient(135deg,#3b82f6,#1e40af)}
.map-marker.lasd{background:linear-gradient(135deg,#f59e0b,#d97706)}
.map-marker.gps{background:linear-gradient(135deg,#6b7280,#4b5563)}
.map-controls{position:absolute;bottom:1.6rem;right:1.6rem;display:flex;flex-direction:column;gap:.55rem;z-index:800}
.control-btn{width:50px;height:50px;background:rgba(15,15,25,.94);backdrop-filter:blur(18px);border:1px solid rgba(120,119,198,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;color:#fff}
.connection-status{position:absolute;top:1.2rem;right:1.2rem;background:rgba(15,15,25,.94);backdrop-filter:blur(18px);border:1px solid rgba(120,119,198,.22);border-radius:12px;padding:1rem;z-index:800;text-align:right}
.loading{position:absolute;inset:0;background:rgba(10,10,15,.95);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2000}
.spinner{width:52px;height:52px;border:4px solid rgba(120,119,198,.22);border-top:4px solid #667eea;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:1rem}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.12)}}
@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="map-container">
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <div style="font-size:1.08rem;margin-bottom:.4rem;">Connecting to ERLC...</div>
        <div style="font-size:.92rem;color:#a0a9c0;">Initializing NEXORA system</div>
    </div>

    <div class="grid-overlay"></div>

    <div class="top-bar">
        <div class="logo">
            <div class="logo-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2 2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7L12 2z"/></svg>
            </div>
            NEXORA ERLC Live Map
        </div>
        <div class="status-bar">
            <div style="display:flex;align-items:center;gap:8px;">
                <div class="status-dot" id="connDot"></div>
                <span id="connectionText">Connected</span>
            </div>
            <span>|</span>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'user', ENT_QUOTES); ?></span>
            <a href="?logout=1" class="logout-btn" onclick="return confirm('Logout?')">Logout</a>
        </div>
    </div>

    <div class="control-panel" id="panel">
        <div class="panel-section">
            <div class="section-title">Live Statistics</div>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value" id="totalOfficers">0</div><div class="stat-label">Active</div></div>
                <div class="stat-card"><div class="stat-value" id="lapdCount">0</div><div class="stat-label">LAPD</div></div>
                <div class="stat-card"><div class="stat-value" id="lasdCount">0</div><div class="stat-label">Sheriff</div></div>
                <div class="stat-card"><div class="stat-value" id="gpsCount">0</div><div class="stat-label">GPS</div></div>
            </div>
        </div>
        <div class="panel-section">
            <div class="section-title">Filters</div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="lapd">LAPD</button>
                <button class="filter-btn" data-filter="lasd">Sheriff</button>
                <button class="filter-btn" data-filter="gps">GPS</button>
            </div>
        </div>
        <div class="panel-section">
            <div class="section-title">Active Officers</div>
            <div class="officer-list" id="officerList"></div>
        </div>
    </div>

    <div class="map-canvas" id="mapCanvas"></div>

    <div class="map-controls">
        <button class="control-btn" onclick="zoomIn()"  title="Zoom In">+</button>
        <button class="control-btn" onclick="zoomOut()" title="Zoom Out">−</button>
        <button class="control-btn" onclick="centerMap()" title="Center">◎</button>
    </div>

    <div class="connection-status">
        <div style="font-weight:600;margin-bottom:.25rem;">ERLC Status</div>
        <div id="connTextSmall" style="font-size:.84rem;color:#10b981;">Connected</div>
        <div style="font-size:.72rem;color:#a0a9c0;margin-top:.25rem;">Last update: <span id="lastUpdate">--:--</span></div>
    </div>
</div>

<script>
class ERLCMap {
  constructor() {
    this.officers = new Map();
    this.markers  = new Map();
    this.mapCanvas = document.getElementById('mapCanvas');
    this.currentFilter = 'all';
    this.zoom = 1; this.panX = 0; this.panY = 0;
    this.isDragging = false; this.lastPanPoint = {x:0,y:0};
    this.worldBounds = { minX:-5000, maxX:5000, minZ:-5000, maxZ:5000 };
    this._apiURL = null; // cache poprawnego URL
    this.init();
  }

  // KANDYDACI ŚCIEŻEK API na podstawie window.location (bez PHP)
  buildApiCandidates() {
    // katalog bieżącej strony, np. /panel/tablet/
    const here = window.location.pathname.replace(/[^\/]*$/, '');
    // Spróbujemy: ten sam katalog, podkatalog api/, katalog wyżej, root
    const candidates = [
      here + 'erlc_api.php',
      here + 'api/erlc_api.php',
      here.replace(/[^\/]+\/$/, '') + 'erlc_api.php', // ../erlc_api.php
      '/erlc_api.php'
    ];
    const clean = u => u.replace(/\/{2,}/g,'/').replace(':/','://');
    return candidates.map(p => new URL(clean(p), window.location.origin).toString());
  }

  async resolveApiURL() {
    if (this._apiURL) return this._apiURL;
    const paths = this.buildApiCandidates();
    for (const url of paths) {
      try {
        const r = await fetch(url + (url.includes('?') ? '&' : '?') + 'ping=1', { cache:'no-store' });
        if (r.ok) { this._apiURL = url; return url; }
      } catch(_) { /* próbuj dalej */ }
    }
    // Fallback – pierwszy kandydat; jeśli nie działa, UI pokaże błąd
    this._apiURL = paths[0];
    return this._apiURL;
  }

  init(){
    document.querySelectorAll('.filter-btn').forEach(btn=>{
      btn.addEventListener('click',(e)=>{
        document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
        e.target.classList.add('active');
        this.currentFilter = e.target.dataset.filter;
        this.applyFilters();
      });
    });
    this.mapCanvas.addEventListener('wheel',e=>{
      e.preventDefault();
      const d = e.deltaY>0 ? -0.12 : 0.12;
      this.zoom = Math.max(.5, Math.min(3, this.zoom + d));
      this.updateMapTransform();
    });
    this.mapCanvas.addEventListener('mousedown',e=>{
      this.isDragging = true; this.lastPanPoint = {x:e.clientX,y:e.clientY};
    });
    document.addEventListener('mousemove',e=>{
      if(!this.isDragging) return;
      const dx = e.clientX - this.lastPanPoint.x;
      const dy = e.clientY - this.lastPanPoint.y;
      this.panX += dx; this.panY += dy;
      this.lastPanPoint = {x:e.clientX,y:e.clientY};
      this.updateMapTransform();
    });
    document.addEventListener('mouseup',()=>{ this.isDragging=false; });

    this.fetchOfficers(); setInterval(()=>this.fetchOfficers(), 3000);
    setTimeout(()=>document.getElementById('loading').style.display='none', 600);
  }

  updateMapTransform(){
    this.mapCanvas.style.transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`;
  }

  async fetchOfficers(){
    try{
      const apiUrl = await this.resolveApiURL();
      const res = await fetch(apiUrl, { cache:'no-store', headers:{'X-Requested-With':'fetch'} });
      const text = await res.text();

      let data = null;
      try { data = JSON.parse(text); }
      catch(e){
        console.error('API not JSON. Status:', res.status, 'Body:', text);
        // jeśli 404 – spróbuj zmienić URL (jednorazowo)
        if (res.status === 404) {
          this._apiURL = null;
          const altUrl = await this.resolveApiURL();
          const res2 = await fetch(altUrl, { cache:'no-store', headers:{'X-Requested-With':'fetch'} });
          const t2 = await res2.text();
          try { data = JSON.parse(t2); } catch(e2){
            this.setConnectionUI(false, 'Invalid JSON/404'); this.updateOfficers([]); return;
          }
          if (!res2.ok || !data?.success) {
            this.setConnectionUI(false, data?.error || ('HTTP '+res2.status)); this.updateOfficers([]); return;
          }
          this.setConnectionUI(true); this.updateOfficers(data.officers||[]); this.updateLastUpdate(); return;
        }
        this.setConnectionUI(false, res.status===404?'404 Not Found':'Invalid JSON');
        this.updateOfficers([]); return;
      }

      if (!res.ok || !data?.success) {
        const msg = data?.error || ('HTTP ' + res.status);
        this.setConnectionUI(false, msg); this.updateOfficers([]); return;
      }

      this.setConnectionUI(true);
      this.updateOfficers(data.officers||[]); this.updateLastUpdate();

    } catch (err) {
      console.error('API Error:', err);
      this.setConnectionUI(false, 'Network error'); this.updateOfficers([]);
    }
  }

  setConnectionUI(ok,msg){
    const dot=document.getElementById('connDot'), big=document.getElementById('connectionText'), small=document.getElementById('connTextSmall');
    if(ok){ dot.style.background='#10b981'; big.textContent='Connected'; small.textContent='Connected'; small.style.color='#10b981'; }
    else  { dot.style.background='#ef4444'; big.textContent='Disconnected'; small.textContent='Disconnected'+(msg?` (${msg})`:''); small.style.color='#ef4444'; }
  }

  updateOfficers(list){
    this.officers.clear();
    list.forEach(o=>{ this.officers.set(o.id,o); this.updateMarker(o); });
    this.markers.forEach((m,id)=>{ if(!this.officers.has(id)){ m.remove(); this.markers.delete(id);} });
    this.updateStats(); this.updateOfficerList(); this.applyFilters();
  }

  updateMarker(o){
    let m=this.markers.get(o.id);
    if(!m){ m=this.createMarker(o); this.mapCanvas.appendChild(m); this.markers.set(o.id,m); }
    this.positionMarker(m,o); this.updateMarkerTooltip(m,o);
  }

  createMarker(o){
    const m=document.createElement('div'); m.className=`map-marker ${o.faction.toLowerCase()}`;
    const tip=document.createElement('div'); tip.className='marker-tooltip'; m.appendChild(tip);
    m.addEventListener('click',()=>this.focusMarker(o));
    return m;
  }

  positionMarker(m,o){
    const W=this.mapCanvas.clientWidth, H=this.mapCanvas.clientHeight, b=this.worldBounds;
    const nx=( (o.position?.x??0) - b.minX )/(b.maxX-b.minX);
    const nz=( (o.position?.z??0) - b.minZ )/(b.maxZ-b.minZ);
    const x=nx*W, y=(1-nz)*H;
    m.style.left=`${x-22}px`; m.style.top=`${y-22}px`;
  }

  updateMarkerTooltip(m,o){
    m.querySelector('.marker-tooltip').innerHTML =
      `<div style="font-weight:700;">${o.name}</div>
       <div style="font-size:.74rem;color:#a0a9c0;">${o.badge_number}</div>
       <div style="font-size:.72rem;color:#8ea0ff;">${o.faction}</div>`;
  }

  updateStats(){
    const s={total:0,lapd:0,lasd:0,gps:0};
    this.officers.forEach(o=>{ s.total++; const f=(o.faction||'').toLowerCase(); if(f==='lapd')s.lapd++; else if(f==='lasd')s.lasd++; else s.gps++; });
    document.getElementById('totalOfficers').textContent=s.total;
    document.getElementById('lapdCount').textContent=s.lapd;
    document.getElementById('lasdCount').textContent=s.lasd;
    document.getElementById('gpsCount').textContent=s.gps;
  }

  updateOfficerList(){
    const list=document.getElementById('officerList'); list.innerHTML='';
    this.officers.forEach(o=>{
      const el=document.createElement('div'); el.className='officer-item'; el.dataset.faction=o.faction.toLowerCase();
      el.innerHTML = `<div class="officer-icon ${o.faction.toLowerCase()}"></div>
                      <div class="officer-info"><div class="officer-name">${o.name}</div>
                      <div class="officer-details">${o.badge_number} • ${o.faction}</div></div>`;
      el.addEventListener('click',()=>this.focusMarker(o)); list.appendChild(el);
    });
  }

  applyFilters(){
    document.querySelectorAll('.officer-item').forEach(el=>{
      const f=el.dataset.faction; el.style.display=(this.currentFilter==='all'||this.currentFilter===f)?'flex':'none';
    });
    this.markers.forEach((m,id)=>{
      const o=this.officers.get(id), f=o.faction.toLowerCase();
      m.style.display=(this.currentFilter==='all'||this.currentFilter===f)?'flex':'none';
    });
  }

  focusMarker(o){
    const m=this.markers.get(o.id); if(!m) return;
    const r=m.getBoundingClientRect(), mr=this.mapCanvas.getBoundingClientRect();
    this.panX = mr.width/2  - (r.left - mr.left) - r.width/2;
    this.panY = mr.height/2 - (r.top  - mr.top ) - r.height/2;
    this.updateMapTransform(); m.style.transform='scale(1.45)'; setTimeout(()=>m.style.transform='',900);
  }

  updateLastUpdate(){ document.getElementById('lastUpdate').textContent=new Date().toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'}); }
}

function zoomIn(){ app.zoom=Math.min(3, app.zoom+.2); app.updateMapTransform(); }
function zoomOut(){ app.zoom=Math.max(.5, app.zoom-.2); app.updateMapTransform(); }
function centerMap(){ app.panX=0; app.panY=0; app.zoom=1; app.updateMapTransform(); }

let app; document.addEventListener('DOMContentLoaded',()=>{ app=new ERLCMap(); setInterval(()=>app.updateLastUpdate(), 1000*30); });
</script>
</body>
</html>
