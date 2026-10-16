<?php
require_once __DIR__ . '/keygen_functions.php';

 $durationOptionsForJs = [];
foreach ($KEYGEN_DURATIONS as $durationKey => $durationConfig) {
    $durationOptionsForJs[] = [
        'value' => $durationKey,
        'label' => $durationConfig['label'],
        'seconds' => (int)$durationConfig['seconds'],
    ];
}

// ===== Telegram =====
 $TG_ADMIN_URL = 'https://t.me/Ducducdz';
 $TG_GROUP_URL = 'https://t.me/papaofskidisducduc';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F6F4EE">
<title>GetKey — KEY SYSTEM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --paper:#F6F4EE; --card:#FCFBF7; --ink:#191B17; --ink-soft:#4C4A42;
  --grid:#DEDAD0; --accent:#2B4FD8; --accent-ink:#F6F4EE; --amber:#C4780A;
  --line:#191B17; color-scheme:light;
  padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px);
}
@media(prefers-color-scheme:dark){
  :root:not([data-theme="light"]){
    --paper:#15160F; --card:#1C1D15; --ink:#EDEAE0; --ink-soft:#A6A295;
    --grid:#2B2C22; --accent:#7D95F5; --accent-ink:#15160F; --amber:#E0A03E; --line:#EDEAE0;
    color-scheme:dark;
  }
}
*{box-sizing:border-box;}
html{scroll-padding-top:env(safe-area-inset-top,0px);}
body{
  margin:0; background:var(--paper); color:var(--ink); font-family:"Newsreader",serif;
  background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:28px 28px; cursor:none;
}
a,button,input{font-family:inherit;}
.mono{font-family:"IBM Plex Mono",monospace;}

/* custom cursor — identical to the HTML preview: dot + ring are always visible;
   over interactive elements the ring grows and fills with accent, the dot shrinks away */
#cur{position:fixed; width:36px; height:36px; border:2.5px solid var(--line); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); transition:width .22s cubic-bezier(.2,.8,.2,1),height .22s cubic-bezier(.2,.8,.2,1),background .22s,border-color .22s; will-change:transform;}
#curdot{position:fixed; width:7px; height:7px; background:var(--accent); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); transition:transform .15s ease, background .2s;}
#cur.hover{width:58px; height:58px; background:color-mix(in srgb, var(--accent) 22%, transparent); border-color:var(--accent); border-width:2px;}
#curdot.hover{transform:translate(-50%,-50%) scale(0);}
@media (pointer:coarse){ body{cursor:auto;} #cur,#curdot{display:none;} }
.ripple{position:fixed; border-radius:50%; border:1.3px solid var(--line); pointer-events:none; z-index:998; transform:translate(-50%,-50%); animation:rip .6s ease-out forwards;}
@keyframes rip{ to{ width:90px; height:90px; opacity:0; } }
@keyframes blink{ 50%{ opacity:0; } }
button:focus-visible, a:focus-visible, .dd-btn:focus-visible{ outline:2px solid var(--accent); outline-offset:2px; }

/* top bar — burger + admin-panel dropdown kept as the richer, accessible version */
.bar{display:flex; align-items:center; justify-content:space-between; padding:22px 6vw; border-bottom:1.3px solid var(--line); position:relative; gap:16px;}
.wordmark{font-family:"IBM Plex Mono",monospace; font-size:13px; letter-spacing:.04em;}
.wordmark b{color:var(--accent);}
.burger{position:relative; width:40px; height:40px; flex:0 0 40px; border:1.3px solid var(--line); background:var(--card); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:0; cursor:none; transition:background .2s,border-color .2s,box-shadow .22s,transform .16s;}
.burger span{display:block; width:16px; height:1.6px; background:var(--line); transform-origin:center; transition:transform .34s cubic-bezier(.2,.85,.25,1),opacity .2s ease,background .2s ease;}
.burger:hover{background:color-mix(in srgb, var(--accent) 8%, var(--card)); border-color:var(--accent); box-shadow:3px 3px 0 var(--accent); transform:translate(-1px,-1px);}
.burger:active{transform:translate(2px,2px) scale(.97); box-shadow:none;}
.burger.open{background:var(--accent); border-color:var(--line); box-shadow:3px 3px 0 var(--line);}
.burger.open span{background:var(--accent-ink);}
.burger.open span:nth-child(1){transform:translateY(7.6px) rotate(45deg);}
.burger.open span:nth-child(2){opacity:0; transform:scaleX(.2);}
.burger.open span:nth-child(3){transform:translateY(-7.6px) rotate(-45deg);}
.menu{position:absolute; top:calc(100% + 9px); right:6vw; min-width:232px; background:var(--card); border:1.3px solid var(--line); box-shadow:6px 6px 0 var(--line); overflow:hidden; max-height:0; opacity:0; visibility:hidden; pointer-events:none; transform:translateY(-9px) scale(.97); transform-origin:top right; transition:max-height .36s cubic-bezier(.2,.8,.2,1),opacity .22s ease,transform .32s cubic-bezier(.2,.8,.2,1),visibility 0s linear .36s; z-index:51;}
.menu.open{max-height:180px; opacity:1; visibility:visible; pointer-events:auto; transform:translateY(0) scale(1); transition:max-height .36s cubic-bezier(.2,.8,.2,1),opacity .22s ease,transform .32s cubic-bezier(.2,.8,.2,1),visibility 0s;}
.menu a{position:relative; isolation:isolate; display:flex; gap:11px; align-items:center; padding:15px 16px; text-decoration:none; color:var(--ink); font-family:"IBM Plex Mono",monospace; font-size:12px; border-bottom:1px solid var(--grid); opacity:0; transform:translateY(-8px); overflow:hidden; transition:opacity .24s ease,transform .3s cubic-bezier(.2,.8,.2,1),background .18s,color .18s;}
.menu a:last-child{border-bottom:0;}
.menu a::before{content:""; position:absolute; z-index:-1; inset:0; background:linear-gradient(100deg,transparent,color-mix(in srgb, var(--accent) 13%, transparent),transparent); transform:translateX(-120%); transition:transform .45s ease;}
.menu a:hover{background:color-mix(in srgb, var(--accent) 7%, var(--card)); color:var(--accent);}
.menu a:hover::before{transform:translateX(120%);}
.menu.open a{opacity:1; transform:translateY(0);}
.menu.open a:nth-child(1){transition-delay:.06s;}
.menu.open a:nth-child(2){transition-delay:.13s;}
.menu .menu-icon{width:15px; height:15px; flex:0 0 15px; stroke:var(--accent); stroke-width:1.7; fill:none; stroke-linecap:round; stroke-linejoin:round; transition:transform .22s ease;}
.menu a:hover .menu-icon{transform:translateX(3px) scale(1.04);}

/* hero — intro + mini Telegram bên trái, thẻ tải app bên phải */
.hero{display:flex; align-items:flex-start; gap:38px; padding:9vh 6vw 6vh; border-bottom:1.3px solid var(--line);}
.hero-main{flex:1 1 auto; min-width:0;}
.hero-side{flex:0 0 296px; display:flex; flex-direction:column; gap:13px; padding-top:34px;}
.side-tag{font-family:"IBM Plex Mono",monospace; font-size:11px; color:var(--ink-soft); letter-spacing:.06em; margin:0 0 1px;}
.eyebrow{font-family:"IBM Plex Mono",monospace; font-size:12.5px; color:var(--ink-soft); margin:0 0 14px;}
h1{font-size:clamp(48px,8vw,88px); font-weight:500; margin:0; line-height:.98; letter-spacing:-.01em;}
.caret{display:inline-block; width:.07em; background:var(--accent); margin-left:3px; animation:blink 1s steps(1) infinite;}
.sub{max-width:46ch; font-size:17px; color:var(--ink-soft); margin-top:18px; line-height:1.55;}
@media(max-width:860px){
  .hero{flex-direction:column; gap:26px;}
  .hero-side{flex:1 1 auto; width:100%; padding-top:0;}
}

/* thẻ tải app — nằm ngang, cả thẻ là 1 link, xoay 3D khi rê chuột */
.dl-card{
  display:flex; gap:12px; align-items:flex-start;
  background:var(--card); border:1.3px solid var(--line); box-shadow:4px 4px 0 var(--line);
  padding:12px 13px; text-decoration:none; color:var(--ink); cursor:none;
  transform-style:preserve-3d; will-change:transform;
  transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.dl-card:hover{box-shadow:6px 6px 0 var(--line); border-color:var(--accent);}
.dl-img{width:46px; height:46px; flex:0 0 46px; border:1.3px solid var(--line); background:var(--paper); object-fit:cover; display:block;}
.dl-body{min-width:0; flex:1;}
.dl-row{display:flex; justify-content:space-between; align-items:baseline; gap:8px;}
.dl-name{font-size:16px; font-weight:500; line-height:1.2;}
.dl-name em{font-style:normal; color:var(--ink-soft); font-size:12.5px;}
.dl-ver{font-family:"IBM Plex Mono",monospace; font-size:9.5px; border:1px solid var(--amber); color:var(--amber); padding:1.5px 6px; white-space:nowrap;}
.dl-desc{font-size:12px; color:var(--ink-soft); line-height:1.45; margin:4px 0 0;}
.dl-meta{font-family:"IBM Plex Mono",monospace; font-size:10px; color:var(--ink-soft); margin-top:7px; border-top:1px solid var(--grid); padding-top:7px;}

/* mini thẻ Telegram — ngay dưới intro, ~1/5 kích thước thẻ tải.
   Logo SVG chính thức của Telegram (vòng tròn gradient #2AABEE→#229ED9 + paper plane trắng),
   in nổi 3D bằng drop-shadow, xoay liên tục quanh trục dọc (rotateY). */
.tg-row{display:grid; grid-template-columns:1fr 1fr; gap:10px; max-width:330px; margin-top:24px;}
.tg-mini{
  display:flex; align-items:center; gap:8px; min-width:0;
  background:var(--card); border:1.3px solid var(--line); box-shadow:2px 2px 0 var(--line);
  padding:7px 9px; text-decoration:none; cursor:none;
  transition:transform .15s ease, box-shadow .15s ease, border-color .18s ease;
}
.tg-mini:hover{border-color:var(--accent); box-shadow:3px 3px 0 var(--accent); transform:translate(-1px,-1px);}
.tg-mini:active{transform:translate(1px,1px); box-shadow:none;}
.tg-mini-logo{
  width:20px; height:20px; flex:0 0 20px; line-height:0; display:inline-flex;
  perspective:240px;
  filter:drop-shadow(0 2px 2px rgba(0,0,0,.5)) drop-shadow(0 3px 6px rgba(34,158,217,.45));
}
.tg-mini-logo svg{width:20px; height:20px; display:block; animation:tgSpin 3.2s linear infinite; will-change:transform;}
@keyframes tgSpin{
  from{ transform:rotateY(0deg); }
  to{ transform:rotateY(360deg); }
}
.tg-mini-txt{
  font-family:"IBM Plex Mono",monospace; font-size:9.5px; letter-spacing:.02em; color:var(--ink);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}

/* layout — .field/.foot/.card-head intentionally have NO z-index so the open
   .dd-list (z-index:30) floats above the rows below it */
main{padding:0 6vw 9vh; max-width:620px; margin:0 auto;}
.card{position:relative; z-index:1; background:var(--card); border:1.3px solid var(--line); box-shadow:7px 7px 0 var(--line); margin-top:18px; transform:translate(-2px,-2px); transition:transform .18s ease, box-shadow .18s ease;}
.card:hover{transform:translate(0,0); box-shadow:4px 4px 0 var(--line);}
/* glass glint — bản sao hiệu ứng stats: dải nghiêng 20°, quét 1 lần trái→phải rồi mờ mất */
.card::before{
  content:""; position:absolute; inset:0; z-index:2; pointer-events:none;
  background-image:linear-gradient(110deg,
    transparent 47%,
    color-mix(in srgb, var(--accent) 16%, transparent) 49%,
    rgba(255,255,255,.35) 51%,
    transparent 53%);
  background-size:400% 100%;
  background-repeat:no-repeat;
  background-position:100% 0;
  opacity:0;
}
.card:hover::before{ opacity:1; animation:cardGlint .9s ease-out both; }
@keyframes cardGlint{
  from{ background-position:100% 0; opacity:1; }
  to{ background-position:0% 0; opacity:0; }
}
.card-head{display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1.3px solid var(--line);}
.card-head h2{font-size:22px; font-weight:500; margin:0;}
.badge{font-family:"IBM Plex Mono",monospace; font-size:11px; border:1px solid var(--amber); color:var(--amber); padding:4px 9px; display:inline-block; transform:rotate(-4deg);}

.field{padding:18px 24px; border-bottom:1px solid var(--grid); display:grid; grid-template-columns:120px 1fr; gap:16px; align-items:center;}
.field label{font-family:"IBM Plex Mono",monospace; font-size:12.5px; color:var(--ink-soft);}

/* custom dropdown */
.dd{position:relative; min-width:0;}
.dd-btn{width:100%; text-align:left; background:var(--paper); border:1.3px solid var(--line); padding:11px 14px; font-size:14.5px; cursor:none; display:flex; justify-content:space-between; align-items:center; gap:10px;}
.dd-label{min-width:0; flex:1;}
.dd-btn svg{width:11px; height:11px; flex:0 0 auto; transition:transform .25s ease;}
.dd.open .dd-btn svg{transform:rotate(180deg);}
.field-typed{font-family:"IBM Plex Mono",monospace;}
.field-caret{display:inline-block; width:1px; height:1em; background:var(--accent); margin-left:2px; vertical-align:-.12em; animation:blink 1s steps(1) infinite;}
.dd-list{position:absolute; left:0; right:0; top:calc(100% + 4px); background:var(--card); border:1.3px solid var(--line); box-shadow:4px 4px 0 var(--line); max-height:0; opacity:0; overflow:hidden; transition:max-height .28s ease, opacity .2s ease; z-index:30;}
.dd.open .dd-list{max-height:230px; opacity:1; overflow:auto;}
.dd-list button{display:block; width:100%; padding:10px 14px; border:0; border-bottom:1px solid var(--grid); background:transparent; text-align:left; font-size:14px; color:var(--ink); cursor:none;}
.dd-list button:last-child{border-bottom:none;}
.dd-list button:hover, .dd-list button:focus-visible{background:var(--grid);}

.stepper{display:flex; align-items:center; gap:10px; min-width:0;}
.stepper input{width:86px; min-width:0; padding:9px 12px; border:1.3px solid var(--line); background:var(--paper); color:var(--ink); text-align:center; font-family:"IBM Plex Mono",monospace; font-size:13px;}
.stepper input[readonly]{cursor:default;}
.fixed-tag{display:inline-block; padding:8px 9px; border:1px solid var(--grid); color:var(--ink-soft); font-family:"IBM Plex Mono",monospace; font-size:10px; letter-spacing:.04em; white-space:nowrap;}

.foot{padding:20px 24px; display:flex; justify-content:space-between; align-items:center; gap:16px;}
.out{font-family:"IBM Plex Mono",monospace; font-size:13px; color:var(--ink-soft);}
.out b{color:var(--ink);}
.gen{background:var(--accent); color:var(--accent-ink); border:1.3px solid var(--line); padding:13px 26px; font-family:"IBM Plex Mono",monospace; font-size:13.5px; cursor:none; position:relative; transition:transform .12s ease, letter-spacing .2s ease;}
.gen:hover{letter-spacing:.05em;}
.gen::before,.gen::after{content:''; position:absolute; width:9px; height:9px; border:1.6px solid var(--line); opacity:0; transition:opacity .2s ease, transform .2s ease;}
.gen::before{top:-5px; left:-5px; border-right:none; border-bottom:none; transform:translate(5px,5px);}
.gen::after{bottom:-5px; right:-5px; border-left:none; border-top:none; transform:translate(-5px,-5px);}
.gen:hover::before,.gen:hover::after{opacity:1; transform:translate(0,0);}
.gen:active{transform:translate(2px,2px);}
.gen:disabled{opacity:.6; cursor:wait;}

/* stats — hover lift + shine */
.stats{display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--line); border:1.3px solid var(--line); margin-top:34px;}
.stats>div{background:var(--card); padding:16px 8px; text-align:center; position:relative; overflow:hidden; transition:transform .18s ease, background .18s ease, box-shadow .18s ease;}
.stats>div::after{content:""; position:absolute; left:-45%; top:-25%; bottom:-25%; width:28%; background:linear-gradient(90deg,transparent,color-mix(in srgb, var(--accent) 16%, transparent),rgba(255,255,255,.35),transparent); transform:skewX(-20deg); opacity:0; pointer-events:none;}
.stats>div:hover{transform:translate(0,-2px); background:color-mix(in srgb, var(--card) 85%, white); box-shadow:0 3px 0 var(--accent);}
.stats>div:hover::after{opacity:1; animation:statsScan .9s ease-out both;}
@keyframes statsScan{ to{ left:130%; opacity:0; } }
.stats .num{display:block; font-family:"IBM Plex Mono",monospace; font-size:19px; font-weight:600; color:var(--accent);}
.stats .lbl{display:block; font-family:"IBM Plex Mono",monospace; font-size:10px; color:var(--ink-soft); margin-top:5px;}
@media(max-width:560px){ .stats{grid-template-columns:repeat(2,1fr);} }

.trust{margin-top:26px; text-align:center; padding-bottom:8px;}
.trust p{font-family:"IBM Plex Mono",monospace; font-size:11px; color:var(--ink-soft); margin:5px 0;}
.trust .copy{font-size:10px; opacity:.65;}

/* modal — NO backdrop-filter (perf fix) */
.overlay{position:fixed; inset:0; background:color-mix(in srgb, var(--ink) 68%, transparent); display:flex; align-items:center; justify-content:center; padding:24px; z-index:200; opacity:0; pointer-events:none; transition:opacity .25s ease;}
.overlay.show{opacity:1; pointer-events:auto;}
.modal{background:var(--card); border:1.3px solid var(--line); box-shadow:9px 9px 0 var(--line); max-width:420px; width:100%; max-height:min(90vh,760px); overflow:auto; transform:scale(.8); opacity:0; transition:transform .38s cubic-bezier(.2,1.4,.4,1), opacity .25s ease;
  background-image:linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px); background-size:18px 18px;}
.overlay.show .modal{transform:scale(1); opacity:1;}
.modal-head{display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1.3px solid var(--line);}
.modal-head span{font-family:"IBM Plex Mono",monospace; font-size:12.5px; color:var(--ink-soft);}
.modal-close{width:28px; height:28px; border:1.3px solid var(--line); background:var(--paper); cursor:none; font-size:14px; line-height:1;}
.modal-body{padding:30px 28px; text-align:center;}
.check{width:46px; height:46px; margin:0 auto 16px; border:1.3px solid var(--line); border-radius:50%; display:flex; align-items:center; justify-content:center;}
.check svg{width:20px; height:20px; stroke:var(--accent); stroke-width:2.4;}
.modal-body h3{font-size:24px; font-weight:500; margin:0 0 10px;}
.modal-body p{font-size:14px; color:var(--ink-soft); line-height:1.6; margin:0 0 4px;}
.modal-body .warn{font-family:"IBM Plex Mono",monospace; font-size:11.5px; color:var(--amber); margin-top:14px;}
/* unlock — compositor-only animations (transform/opacity) */
.unlock{
  width:100%; margin-top:20px; position:relative; isolation:isolate; overflow:hidden;
  background:var(--accent); color:var(--accent-ink); border:1.3px solid var(--line);
  padding:15px; font-family:"IBM Plex Mono",monospace; font-size:13.5px; cursor:none;
  transition:transform .16s ease, box-shadow .16s ease, filter .2s ease;
}
.unlock span{position:relative; z-index:2;}
.unlock::before{
  content:""; position:absolute; z-index:1; pointer-events:none; top:-35%; bottom:-35%; left:0; width:38%;
  transform:skewX(-22deg) translateX(-180%);
  background:linear-gradient(90deg,transparent,color-mix(in srgb,var(--accent-ink) 30%,transparent),transparent);
  animation:unlockSweep 2.8s ease-in-out infinite; will-change:transform;
}
.unlock::after{
  content:""; position:absolute; z-index:1; pointer-events:none; inset:4px;
  border:1px solid color-mix(in srgb,var(--accent-ink) 34%,transparent);
  opacity:.5; animation:unlockFrame 2.4s ease-in-out infinite;
}
.unlock:hover{transform:translateY(-2px); filter:brightness(1.06); box-shadow:0 5px 0 var(--line);}
.unlock:active{transform:translate(2px,2px); box-shadow:none;}
.unlock:disabled{opacity:.55; cursor:default;}
.unlock:disabled::before,.unlock:disabled::after{animation:none;}
@keyframes unlockSweep{
  0%,18%{ transform:skewX(-22deg) translateX(-180%); opacity:0; }
  30%{ opacity:1; }
  72%{ transform:skewX(-22deg) translateX(340%); opacity:1; }
  73%,100%{ transform:skewX(-22deg) translateX(340%); opacity:0; }
}
@keyframes unlockFrame{ 0%,100%{opacity:.28; transform:scale(1);} 50%{opacity:.8; transform:scale(1.04);} }
.ready-caret{height:1em; vertical-align:-.08em; margin-left:1px;}
.foot-note{font-family:"IBM Plex Mono",monospace; font-size:11px; color:var(--ink-soft); margin-top:14px;}

.toast{position:fixed; left:50%; bottom:24px; transform:translate(-50%,18px); opacity:0; pointer-events:none; background:var(--ink); color:var(--paper); padding:12px 16px; font-family:"IBM Plex Mono",monospace; font-size:12px; z-index:1000; box-shadow:4px 4px 0 var(--accent); transition:opacity .2s, transform .2s; max-width:calc(100vw - 32px); text-align:center;}
.toast.show{opacity:1; transform:translate(-50%,0);}

@media(max-width:560px){
  .bar{padding:16px 20px;} .hero{padding:6vh 20px 4vh;} .hero h1{font-size:54px;} .sub{font-size:16px;}
  main{padding:0 17px 40px;} .card-head,.foot{padding-left:16px; padding-right:16px;}
  .field{grid-template-columns:1fr; gap:9px; padding:15px 16px;} .foot{align-items:stretch; flex-direction:column;} .gen{width:100%;}
  .modal-body{padding:24px 18px;} .modal-body h3{font-size:22px;}
}
</style>
</head>
<body>
<div id="cur" aria-hidden="true"></div><div id="curdot" aria-hidden="true"></div>

<header class="bar">
  <div class="wordmark"><b>KEY</b>.SYSTEM — MDUCDZ</div>
  <button class="burger" id="burger" type="button" aria-label="Mở menu" aria-controls="menu" aria-expanded="false"><span></span><span></span><span></span></button>
  <nav class="menu" id="menu" aria-label="Menu chính" aria-hidden="true">
    <a href="getkey">
      <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke-width="1.6" aria-hidden="true"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
      Lấy key
    </a>
    <a href="admin/index">
      <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke-width="1.6" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Admin panel
    </a>
  </nav>
</header>

<section class="hero">
  <div class="hero-main">
    <p class="eyebrow">Hệ thống cấp license MDUCDZ</p>
    <h1 id="type"><span class="caret">&nbsp;</span></h1>
    <p class="sub">Mỗi key chỉ gắn với 1 thiết bị, không chia sẻ key đã tạo cho bất kỳ ai. Gặp lỗi khi tạo key hoặc muốn nhận key free mỗi ngày — bấm thẻ Telegram bên dưới.</p>

    <!-- mini thẻ Telegram — logo chính thức quay trục dọc -->
    <div class="tg-row">
      <a class="tg-mini" href="<?= htmlspecialchars($TG_ADMIN_URL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Telegram Admin">
        <span class="tg-mini-logo" aria-hidden="true">
          <svg viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="tgLogoA" x1="120" y1="0" x2="120" y2="240" gradientUnits="userSpaceOnUse">
                <stop stop-color="#2AABEE"/><stop offset="1" stop-color="#229ED9"/>
              </linearGradient>
            </defs>
            <circle cx="120" cy="120" r="120" fill="url(#tgLogoA)"/>
            <path fill="#ffffff" fill-rule="evenodd" clip-rule="evenodd" d="M181.79 75.2791L158.222 167.655C156.444 174.555 152.477 176.284 146.933 173.171L117.44 151.398L103.217 165.096C101.645 166.668 100.32 168 97.442 168L99.444 138.075L154.539 88.4177C156.936 86.2752 154.033 85.1295 150.856 87.272L83.1776 130.012L54.1066 120.921C47.2739 118.785 47.2092 114.102 55.5202 110.989L173.156 65.7891C178.738 63.7152 183.631 67.1164 181.79 75.2791Z"/>
          </svg>
        </span>
        <span class="tg-mini-txt">Telegram Admin</span>
      </a>
      <a class="tg-mini" href="<?= htmlspecialchars($TG_GROUP_URL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Group Share Mod">
        <span class="tg-mini-logo" aria-hidden="true">
          <svg viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="tgLogoB" x1="120" y1="0" x2="120" y2="240" gradientUnits="userSpaceOnUse">
                <stop stop-color="#2AABEE"/><stop offset="1" stop-color="#229ED9"/>
              </linearGradient>
            </defs>
            <circle cx="120" cy="120" r="120" fill="url(#tgLogoB)"/>
            <path fill="#ffffff" fill-rule="evenodd" clip-rule="evenodd" d="M181.79 75.2791L158.222 167.655C156.444 174.555 152.477 176.284 146.933 173.171L117.44 151.398L103.217 165.096C101.645 166.668 100.32 168 97.442 168L99.444 138.075L154.539 88.4177C156.936 86.2752 154.033 85.1295 150.856 87.272L83.1776 130.012L54.1066 120.921C47.2739 118.785 47.2092 114.102 55.5202 110.989L173.156 65.7891C178.738 63.7152 183.631 67.1164 181.79 75.2791Z"/>
          </svg>
        </span>
        <span class="tg-mini-txt">Group Share Mod</span>
      </a>
    </div>
  </div>

  <!-- thẻ tải app — render từ $KEYGEN_APPS, cả thẻ là 1 link, xoay 3D khi rê chuột -->
  <div class="hero-side" id="downloads">
    <p class="side-tag">TẢI ỨNG DỤNG — DOWNLOAD</p>
    <?php
    $appIndex = 0;
    foreach ($KEYGEN_APPS as $gKey => $g):
      $appIndex++;
      $appLabel  = $g['label'];
      $dlUrl     = $g['downloadUrl'] ?? '#';
      $version   = $g['version'] ?? '';
      $desc      = $g['desc'] ?? '';
      $size      = $g['size'] ?? '';
      $platform  = $g['platform'] ?? '';
      $updated   = $g['updated'] ?? '';
      $metaParts = array_filter([$size, $platform, $updated], fn($p) => $p !== '');
      $meta      = implode(' · ', $metaParts);
      $nameParts = preg_split('/\s*—\s*|\s+-\s+/u', $appLabel, 2);
      $nameMain  = trim($nameParts[0]);
      $nameSub   = isset($nameParts[1]) ? trim($nameParts[1]) : '';
      if (!empty($g['icon'])) {
        $imgTag = '<img class="dl-img" src="' . htmlspecialchars($g['icon'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') . '">';
      } else {
        $initials = '';
        foreach (preg_split('/\s+/u', trim($appLabel)) as $w) {
          if ($w === '') continue;
          $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
          if (mb_strlen($initials, 'UTF-8') >= 2) break;
        }
        $phSvg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'><rect width='48' height='48' fill='#DEDAD0'/><text x='24' y='31' font-family='monospace' font-size='18' font-weight='bold' text-anchor='middle' fill='#2B4FD8'>{$initials}</text></svg>";
        $imgTag = '<img class="dl-img" src="data:image/svg+xml,' . rawurlencode($phSvg) . '" alt="' . htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') . '">';
      }
    ?>
    <a class="dl-card" href="<?= htmlspecialchars($dlUrl, ENT_QUOTES, 'UTF-8') ?>">
      <?= $imgTag ?>
      <div class="dl-body">
        <div class="dl-row">
          <span class="dl-name"><?= htmlspecialchars($nameMain, ENT_QUOTES, 'UTF-8') ?><?= $nameSub !== '' ? ' <em>— ' . htmlspecialchars($nameSub, ENT_QUOTES, 'UTF-8') . '</em>' : '' ?></span>
          <?= $version !== '' ? '<span class="dl-ver">' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '</span>' : '' ?>
        </div>
        <?= $desc !== '' ? '<p class="dl-desc">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>' : '' ?>
        <?= $meta !== '' ? '<div class="dl-meta">' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</div>' : '' ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<main>
  <section class="card">
    <div class="card-head"><h2>Tạo license</h2><span class="badge">FREE</span></div>
    <form id="licenseForm" autocomplete="off">
      <input type="hidden" name="game" id="gameValue" value="">
      <input type="hidden" name="duration" id="durationValue" value="1d">

      <div class="field">
        <label for="appChoice">Ứng dụng</label>
        <div class="dd" id="ddApp">
          <button class="dd-btn" id="appChoice" type="button" aria-haspopup="listbox" aria-expanded="false">
            <span class="dd-label" id="appLabel" data-text="Chọn ứng dụng">Chọn ứng dụng</span>
            <svg viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M1 1l4 4 4-4"/></svg>
          </button>
          <div class="dd-list" role="listbox">
            <?php foreach ($KEYGEN_APPS as $gKey => $g): ?>
              <button type="button" role="option" data-v="<?= htmlspecialchars($gKey, ENT_QUOTES, 'UTF-8') ?>" data-label="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="field">
        <label>Số thiết bị</label>
        <div class="stepper">
          <input id="devCount" class="mono" value="1" readonly aria-readonly="true" aria-label="Số thiết bị cố định: 1">
          <span class="fixed-tag">CỐ ĐỊNH</span>
        </div>
      </div>

      <div class="field">
        <label for="durationChoice">Thời hạn</label>
        <div class="dd" id="ddDays">
          <button class="dd-btn" id="durationChoice" type="button" aria-haspopup="listbox" aria-expanded="false">
            <span class="dd-label" id="durationLabel" data-text="1 ngày">1 ngày</span>
            <svg viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M1 1l4 4 4-4"/></svg>
          </button>
          <div class="dd-list" role="listbox" id="durationList"></div>
        </div>
      </div>

      <div class="foot">
        <span class="out">Gói: <b>FREE</b></span>
        <button class="gen" id="generateBtn" type="submit">Tạo key</button>
      </div>
    </form>
  </section>

  <div class="stats">
    <div><span class="num">374</span><span class="lbl">Key đã tạo</span></div>
    <div><span class="num">4.6/5</span><span class="lbl">Đánh giá sao</span></div>
    <div><span class="num">1547</span><span class="lbl">Lượt truy cập</span></div>
    <div><span class="num">99.9%</span><span class="lbl">Uptime</span></div>
  </div>

  <div class="trust">
    <p>Xác thực SSL &nbsp;—&nbsp; Dữ liệu mã hoá &nbsp;—&nbsp; Không lưu log key</p>
    <p class="copy">© 2026 KEY.SYS — Hệ thống cấp license tự động. Bảo lưu mọi quyền.</p>
  </div>
</main>

<div class="overlay" id="overlay" aria-hidden="true">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="readyTitle">
    <div class="modal-head"><span id="modalApp">Yêu cầu key — CLAIM PORTAL</span><button class="modal-close" id="modalClose" type="button" aria-label="Đóng">×</button></div>
    <div class="modal-body">
      <div class="check"><svg viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6"/></svg></div>
      <h3 id="readyTitle">Sẵn sàng</h3>
      <p id="readyMessage">Bấm nút bên dưới để bắt đầu vượt link lấy key.</p>
      <p class="warn" id="readyWarning">Quá trình xác minh mất khoảng 45 giây — đến nhanh hơn hệ thống sẽ từ chối cấp key.</p>
      <button class="unlock" id="unlockBtn" type="button" disabled><span>Vượt link để lấy key</span></button>
      <p class="foot-note" id="readyFoot">Không đóng tab sau khi bắt đầu vượt link. Không chia sẻ key đã tạo.</p>
    </div>
  </section>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
const durationChoices = <?= json_encode($durationOptionsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

// custom cursor — dot snaps, ring trails
const cur=document.getElementById('cur'), dot=document.getElementById('curdot');
let mx=innerWidth/2, my=innerHeight/2, rx=mx, ry=my;
window.addEventListener('mousemove',e=>{
  mx=e.clientX; my=e.clientY;
  dot.style.left=mx+'px'; dot.style.top=my+'px';
});
(function loop(){
  rx+=(mx-rx)*0.22; ry+=(my-ry)*0.22;
  cur.style.left=rx+'px'; cur.style.top=ry+'px';
  requestAnimationFrame(loop);
})();
function bindHover(el){
  el.addEventListener('mouseenter',()=>{ cur.classList.add('hover'); dot.classList.add('hover'); });
  el.addEventListener('mouseleave',()=>{ cur.classList.remove('hover'); dot.classList.remove('hover'); });
}
document.querySelectorAll('a,button').forEach(bindHover);

// tilt 3D trên thẻ tải app — transform-only, không lag
document.querySelectorAll('.dl-card').forEach(card=>{
  card.addEventListener('mousemove',e=>{
    const r=card.getBoundingClientRect();
    const px=(e.clientX-r.left)/r.width-.5, py=(e.clientY-r.top)/r.height-.5;
    card.style.transform='perspective(700px) rotateY('+(px*9).toFixed(2)+'deg) rotateX('+(-py*7).toFixed(2)+'deg) translateY(-2px)';
  });
  card.addEventListener('mouseleave',()=>{ card.style.transform=''; });
});

// click ripple
document.addEventListener('click',e=>{
  const r=document.createElement('div'); r.className='ripple';
  r.style.left=e.clientX+'px'; r.style.top=e.clientY+'px';
  document.body.appendChild(r); setTimeout(()=>r.remove(),600);
});

// headline typewriter — spans built once, textContent only
const headTxt='GetKey'; const typeEl=document.getElementById('type');
typeEl.innerHTML='<span class="ht-txt"></span><span class="caret" aria-hidden="true">&nbsp;</span>';
const headTxtEl=typeEl.querySelector('.ht-txt');
let ti=0, tDel=false;
(function typeHead(){
  headTxtEl.textContent=headTxt.slice(0,ti);
  let wait=tDel?65:110;
  if(!tDel && ti===headTxt.length){ wait=1500; tDel=true; }
  else if(tDel && ti===0){ wait=500; tDel=false; }
  else{ ti+=tDel?-1:1; }
  setTimeout(typeHead,wait);
})();

// burger + admin-panel dropdown
const burger=document.getElementById('burger'), menu=document.getElementById('menu');
function setMenuOpen(open){
  burger.classList.toggle('open',open); menu.classList.toggle('open',open);
  burger.setAttribute('aria-expanded', open?'true':'false'); menu.setAttribute('aria-hidden', open?'false':'true');
}
burger.addEventListener('click', e=>{ e.stopPropagation(); setMenuOpen(!menu.classList.contains('open')); });
document.addEventListener('click', e=>{ if(!burger.contains(e.target) && !menu.contains(e.target)) setMenuOpen(false); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape' && menu.classList.contains('open')){ setMenuOpen(false); burger.focus(); } });
menu.querySelectorAll('a').forEach(link=>link.addEventListener('click', ()=>setMenuOpen(false)));

// typewriter for the two dropdown values
function startFieldTyping(label, text){
  if(!label) return;
  clearTimeout(label._typingTimer);
  label.dataset.text=text;
  let i=0, deleting=false;
  label.innerHTML='<span class="field-typed"></span><span class="field-caret" aria-hidden="true"></span>';
  const typed=label.querySelector('.field-typed');
  function tick(){
    typed.textContent=text.slice(0,i);
    let wait;
    if(!deleting && i===text.length){ wait=550; deleting=true; }
    else if(deleting && i===0){ wait=220; deleting=false; }
    else { wait=deleting?28:42; i+=deleting?-1:1; }
    label._typingTimer=setTimeout(tick,wait);
  }
  label._typingTimer=setTimeout(tick,35);
}

// app dropdown (from $KEYGEN_APPS)
const appDD=document.getElementById('ddApp'), appLabel=document.getElementById('appLabel'), gameValue=document.getElementById('gameValue');
startFieldTyping(appLabel,'Chọn ứng dụng');
appDD.querySelectorAll('[data-v]').forEach(opt=>{
  opt.addEventListener('click',()=>{
    gameValue.value=opt.dataset.v;
    startFieldTyping(appLabel, opt.dataset.label);
    appDD.classList.remove('open');
    document.getElementById('appChoice').setAttribute('aria-expanded','false');
  });
});

// duration dropdown (from $KEYGEN_DURATIONS, sorted, current hidden from its own list)
const durationDD=document.getElementById('ddDays'), durationLabel=document.getElementById('durationLabel'),
      durationValue=document.getElementById('durationValue'), durationList=document.getElementById('durationList');
startFieldTyping(durationLabel,'1 ngày');
let selectedDuration = durationValue.value || '1d';
function renderDurationOptions(){
  durationList.innerHTML='';
  durationChoices.slice().sort((a,b)=>a.seconds-b.seconds).filter(d=>d.value!==selectedDuration).forEach(opt=>{
    const b=document.createElement('button');
    b.type='button'; b.setAttribute('role','option'); b.dataset.v=opt.value; b.textContent=opt.label;
    b.addEventListener('click',()=>{
      selectedDuration=opt.value; durationValue.value=selectedDuration;
      startFieldTyping(durationLabel, opt.label);
      durationDD.classList.remove('open');
      document.getElementById('durationChoice').setAttribute('aria-expanded','false');
      renderDurationOptions();
    });
    bindHover(b);
    durationList.appendChild(b);
  });
}
renderDurationOptions();

function closeDropdowns(){
  document.querySelectorAll('.dd.open').forEach(dd=>{
    dd.classList.remove('open');
    const b=dd.querySelector('.dd-btn'); if(b) b.setAttribute('aria-expanded','false');
  });
}
document.querySelectorAll('.dd').forEach(dd=>{
  const btn=dd.querySelector('.dd-btn');
  btn.addEventListener('click', e=>{
    e.stopPropagation();
    const was=dd.classList.contains('open');
    closeDropdowns();
    if(!was){ dd.classList.add('open'); btn.setAttribute('aria-expanded','true'); }
  });
});
document.addEventListener('click', closeDropdowns);

// device count — hard-locked at 1
const devInput=document.getElementById('devCount');
devInput.value='1'; devInput.readOnly=true;

// toast
function showToast(message){
  const t=document.getElementById('toast');
  t.textContent=message; t.classList.add('show');
  clearTimeout(window.toastTimer);
  window.toastTimer=setTimeout(()=>t.classList.remove('show'),2800);
}

// modal: "Sẵn sàng" title types in on open
const overlay=document.getElementById('overlay'), unlockBtn=document.getElementById('unlockBtn');
const readyTitle=document.getElementById('readyTitle'); const readyText='Sẵn sàng';
let readyIndex=0, readyDeleting=false, readyTimer=null, readyTxtEl=null;
function stopReadyTyping(reset=true){
  clearTimeout(readyTimer); readyTimer=null;
  if(reset){ readyIndex=0; readyDeleting=false; readyTxtEl=null; readyTitle.textContent=readyText; }
}
function typeReadyTitle(){
  if(!overlay.classList.contains('show')){ readyTimer=null; return; }
  if(readyTxtEl) readyTxtEl.textContent=readyText.slice(0,readyIndex);
  let wait=readyDeleting?65:110;
  if(!readyDeleting && readyIndex===readyText.length){ wait=1400; readyDeleting=true; }
  else if(readyDeleting && readyIndex===0){ wait=500; readyDeleting=false; }
  else{ readyIndex+=readyDeleting?-1:1; }
  readyTimer=setTimeout(typeReadyTitle,wait);
}
function startReadyTyping(){
  stopReadyTyping(false); readyIndex=0; readyDeleting=false;
  readyTitle.innerHTML='<span class="rt-txt"></span><span class="caret ready-caret" aria-hidden="true">&nbsp;</span>';
  readyTxtEl=readyTitle.querySelector('.rt-txt');
  typeReadyTitle();
}
function openModal(){ overlay.classList.add('show'); overlay.setAttribute('aria-hidden','false'); startReadyTyping(); }
function closeModal(){ overlay.classList.remove('show'); overlay.setAttribute('aria-hidden','true'); stopReadyTyping(); }
document.getElementById('modalClose').addEventListener('click', closeModal);
overlay.addEventListener('click', e=>{ if(e.target===overlay) closeModal(); });

// form submit -> create ticket -> open modal with real ticket data
const form=document.getElementById('licenseForm'), generateBtn=document.getElementById('generateBtn');
let pendingUnlockUrl='';
form.addEventListener('submit', async e=>{
  e.preventDefault();
  if(!gameValue.value){ showToast('Vui lòng chọn ứng dụng trước khi tạo key.'); return; }
  generateBtn.disabled=true; generateBtn.textContent='Đang xử lý…';
  try{
    const fd=new FormData(form); fd.set('action','create');
    const res=await fetch('getkey_action.php',{method:'POST',body:fd,credentials:'same-origin'});
    const data=await res.json();
    if(!res.ok || data.status!=='success'){ showToast(data.message || 'Có lỗi xảy ra. Vui lòng thử lại.'); return; }

    pendingUnlockUrl=data.unlockUrl;
    document.getElementById('modalApp').textContent='Yêu cầu key — '+(data.appLabel||'CLAIM PORTAL');
    document.getElementById('readyMessage').textContent='Ứng dụng: '+data.appLabel+'. Thời hạn: '+data.duration+'.';
    document.getElementById('readyWarning').textContent='Link4m và mỗi lượt Layma.net cần tối thiểu 45 giây trước khi quay lại — đến nhanh hơn hệ thống sẽ từ chối cấp key.';
    const laymaCount=Number(data.laymaPasses||0);
    document.getElementById('readyFoot').textContent = laymaCount===0
      ? 'Chỉ cần hoàn tất 1 lượt Link4m. Còn '+(data.remaining??'?')+' lượt lấy key trong giờ này.'
      : 'Sau Link4m, hoàn tất thêm '+laymaCount+' lượt Layma.net. Còn '+(data.remaining??'?')+' lượt lấy key trong giờ này.';
    unlockBtn.disabled=false; unlockBtn.querySelector('span').textContent='Vượt link để lấy key';
    openModal();
  }catch(err){
    showToast('Lỗi kết nối server. Vui lòng thử lại.');
  }finally{
    generateBtn.disabled=false; generateBtn.textContent='Tạo key';
  }
});

unlockBtn.addEventListener('click', async()=>{
  if(!pendingUnlockUrl) return;
  unlockBtn.disabled=true; unlockBtn.querySelector('span').textContent='Đang chuyển hướng…';
  try{
    const fd=new FormData(); fd.set('action','begin_link4m');
    const res=await fetch('getkey_action.php',{method:'POST',body:fd,credentials:'same-origin'});
    const data=await res.json();
    if(!res.ok || data.status!=='success'){
      showToast(data.message || 'Không thể bắt đầu vượt link.');
      unlockBtn.disabled=false; unlockBtn.querySelector('span').textContent='Thử lại';
      return;
    }
    window.location.assign(pendingUnlockUrl);
  }catch(err){
    showToast('Không thể kết nối server. Vui lòng thử lại.');
    unlockBtn.disabled=false; unlockBtn.querySelector('span').textContent='Thử lại';
  }
});
</script>
</body>
</html>
