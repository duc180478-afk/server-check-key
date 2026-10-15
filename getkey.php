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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F6F4EE">
<meta name="application-version" content="KEYSYS-UI-MENU-ANIMATION-2026-10-15">
<!-- KEYSYS_UI_BUILD: menu-animation-2026-10-15 -->
<title>GetKey — KEY.SYS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--paper:#F6F4EE;--card:#FCFBF7;--ink:#191B17;--soft:#4C4A42;--grid:#DEDAD0;--accent:#2B4FD8;--accent-ink:#F6F4EE;--amber:#C4780A;--line:#191B17;--good:#23734D;color-scheme:light}
@media(prefers-color-scheme:dark){:root:not([data-theme="light"]){--paper:#15160F;--card:#1C1D15;--ink:#EDEAE0;--soft:#A6A295;--grid:#2B2C22;--accent:#7D95F5;--accent-ink:#15160F;--amber:#E0A03E;--line:#EDEAE0;--good:#79C79A;color-scheme:dark}}
*{box-sizing:border-box}html{scroll-padding-top:env(safe-area-inset-top,0px)}body{margin:0;min-height:100vh;background:var(--paper);color:var(--ink);font-family:"Newsreader",serif;background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);background-size:28px 28px;cursor:auto}@media(pointer:coarse){body{cursor:auto}#cur,#curdot{display:none}}button,a,input{font:inherit}.mono{font-family:"IBM Plex Mono",monospace}#cur{position:fixed;left:50%;top:50%;width:36px;height:36px;border:2.5px solid var(--line);border-radius:50%;pointer-events:none;z-index:999;transform:translate(-50%,-50%);transition:width .22s,height .22s,background .22s,border-color .22s;will-change:transform}#curdot{position:fixed;width:7px;height:7px;background:var(--accent);border-radius:50%;pointer-events:none;z-index:999;transform:translate(-50%,-50%);transition:transform .15s}#cur.hover{width:58px;height:58px;background:color-mix(in srgb,var(--accent) 22%,transparent);border-color:var(--accent)}#curdot.hover{transform:translate(-50%,-50%) scale(0)}.ripple{position:fixed;border:1.3px solid var(--line);border-radius:50%;pointer-events:none;z-index:998;transform:translate(-50%,-50%);animation:rip .6s ease-out forwards}@keyframes rip{to{width:90px;height:90px;opacity:0}}button:focus-visible,a:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.bar{display:flex;align-items:center;justify-content:space-between;padding:18px 6vw;border-bottom:1.3px solid var(--line);position:relative}.wordmark{font:13px "IBM Plex Mono",monospace;letter-spacing:.04em}.wordmark b{color:var(--accent)}.node{font:10px/1.6 "IBM Plex Mono",monospace;color:var(--soft);text-align:right}.node i{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--good);margin-right:6px}.burger{position:relative;width:40px;height:40px;flex:0 0 40px;border:1.3px solid var(--line);background:var(--card);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:0;cursor:pointer;transition:background .2s,border-color .2s,box-shadow .22s,transform .16s;z-index:52;touch-action:manipulation}.burger span{display:block;width:16px;height:1.6px;background:var(--line);transform-origin:center;transition:transform .34s cubic-bezier(.2,.85,.25,1),opacity .2s ease,width .22s ease,background .2s ease}.burger:hover{background:color-mix(in srgb,var(--accent) 8%,var(--card));border-color:var(--accent);box-shadow:3px 3px 0 var(--accent);transform:translate(-1px,-1px)}.burger:active{transform:translate(2px,2px) scale(.97);box-shadow:none}.burger.open{background:var(--accent);border-color:var(--line);box-shadow:3px 3px 0 var(--line)}.burger.open span{background:var(--accent-ink)}.burger.open span:nth-child(1){transform:translateY(7.6px) rotate(45deg)}.burger.open span:nth-child(2){opacity:0;transform:scaleX(.2)}.burger.open span:nth-child(3){transform:translateY(-7.6px) rotate(-45deg)}.menu{position:absolute;top:calc(100% + 9px);right:6vw;min-width:232px;background:var(--card);border:1.3px solid var(--line);box-shadow:6px 6px 0 var(--line);overflow:hidden;max-height:0;opacity:0;visibility:hidden;pointer-events:none;transform:translateY(-9px) scale(.97);transform-origin:top right;transition:max-height .36s cubic-bezier(.2,.8,.2,1),opacity .22s ease,transform .32s cubic-bezier(.2,.8,.2,1),visibility 0s linear .36s;z-index:51}.menu.open{max-height:180px;opacity:1;visibility:visible;pointer-events:auto;transform:translateY(0) scale(1);transition:max-height .36s cubic-bezier(.2,.8,.2,1),opacity .22s ease,transform .32s cubic-bezier(.2,.8,.2,1),visibility 0s}.menu a{position:relative;isolation:isolate;display:flex;gap:11px;align-items:center;padding:15px 16px;text-decoration:none;color:var(--ink);font:12px "IBM Plex Mono",monospace;border-bottom:1px solid var(--grid);opacity:0;transform:translateY(-8px);overflow:hidden;transition:opacity .24s ease,transform .3s cubic-bezier(.2,.8,.2,1),background .18s,color .18s}.menu a:last-child{border-bottom:0}.menu a::before{content:"";position:absolute;z-index:-1;inset:0;background:linear-gradient(100deg,transparent, color-mix(in srgb,var(--accent) 13%,transparent),transparent);transform:translateX(-120%);transition:transform .45s ease}.menu a:hover{background:color-mix(in srgb,var(--accent) 7%,var(--card));color:var(--accent)}.menu a:hover::before{transform:translateX(120%)}.menu.open a{opacity:1;transform:translateY(0)}.menu.open a:nth-child(1){transition-delay:.05s}.menu.open a:nth-child(2){transition-delay:.12s}.menu .menu-icon{width:16px;height:16px;flex:0 0 16px;stroke:var(--accent);stroke-width:1.7;fill:none;stroke-linecap:round;stroke-linejoin:round;transition:transform .22s ease}.menu a:hover .menu-icon{transform:translateX(3px) scale(1.04)}@media(prefers-reduced-motion:reduce){.burger,.burger span,.menu,.menu a,.menu a::before{transition:none!important;animation:none!important}}
.hero{padding:8vh 6vw 5vh;border-bottom:1.3px solid var(--line)}.eyebrow{font:11px "IBM Plex Mono",monospace;letter-spacing:.08em;color:var(--soft);margin:0 0 13px}h1{font-size:clamp(52px,9vw,96px);font-weight:500;line-height:.98;letter-spacing:-.025em;margin:0}.caret{display:inline-block;width:.07em;background:var(--accent);margin-left:3px;animation:blink 1s steps(1) infinite}@keyframes blink{50%{opacity:0}}.sub{max-width:49ch;font-size:17px;color:var(--soft);line-height:1.55;margin:18px 0 0}.sub a{color:var(--accent);text-underline-offset:3px}.statusline{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:24px 0 2px;font:10px "IBM Plex Mono",monospace;color:var(--soft);letter-spacing:.035em}.statusleft{display:flex;align-items:center;gap:9px}.statusmark{width:24px;height:24px;border:1.3px solid var(--good);border-radius:50%;display:grid;place-items:center;color:var(--good);font-size:13px}.statuspill{border:1px solid var(--good);color:var(--good);padding:5px 8px;white-space:nowrap}
main{max-width:700px;margin:0 auto;padding:0 6vw 8vh}.card{margin-top:18px;background:var(--card);border:1.3px solid var(--line);box-shadow:7px 7px 0 var(--line);transition:transform .18s,box-shadow .18s}.card:hover{transform:translate(1px,1px);box-shadow:5px 5px 0 var(--line)}.card-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 24px;border-bottom:1.3px solid var(--line)}.card-head h2{font-size:22px;font-weight:500;margin:0}.badge{font:10px "IBM Plex Mono",monospace;border:1px solid var(--amber);color:var(--amber);padding:5px 9px;transform:rotate(-3deg)}.field{display:grid;grid-template-columns:120px minmax(0,1fr);gap:16px;align-items:center;padding:17px 24px;border-bottom:1px solid var(--grid)}.field label{font:11px "IBM Plex Mono",monospace;color:var(--soft)}.dd{position:relative;min-width:0}.dd-btn{width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;text-align:left;background:var(--paper);border:1.3px solid var(--line);padding:11px 13px;font-size:15px;cursor:auto;min-height:44px}.dd-label{min-width:0;overflow-wrap:anywhere}.dd-btn svg{width:11px;height:7px;flex:0 0 auto;stroke:currentColor;fill:none;stroke-width:1.5;transition:transform .25s}.dd.open .dd-btn svg{transform:rotate(180deg)}.field-typed{font-family:"IBM Plex Mono",monospace;font-size:12px}.field-caret{display:inline-block;width:1px;height:1em;background:var(--accent);margin-left:2px;vertical-align:-.12em;animation:blink 1s steps(1) infinite}.dd-list{position:absolute;left:0;right:0;top:calc(100% + 4px);background:var(--card);border:1.3px solid var(--line);box-shadow:4px 4px 0 var(--line);max-height:0;opacity:0;overflow:hidden;transition:max-height .28s,opacity .2s;z-index:30}.dd.open .dd-list{max-height:230px;opacity:1}.dd-list button{display:block;width:100%;padding:11px 13px;border:0;border-bottom:1px solid var(--grid);background:transparent;text-align:left;font-size:14px;color:var(--ink);cursor:auto}.dd-list button:last-child{border-bottom:0}.dd-list button:hover{background:var(--grid)}.stepper{display:flex;align-items:center;gap:10px}.stepper input{width:86px;padding:9px 12px;border:1.3px solid var(--line);background:var(--paper);color:var(--ink);text-align:center;font:13px "IBM Plex Mono",monospace}.fixed-tag{display:inline-block;padding:8px 9px;border:1px solid var(--grid);color:var(--soft);font:9px "IBM Plex Mono",monospace;letter-spacing:.04em}.foot{padding:20px 24px;display:flex;justify-content:space-between;align-items:center;gap:16px}.out{font:12px "IBM Plex Mono",monospace;color:var(--soft)}.out b{color:var(--ink)}.gen,.primary{position:relative;isolation:isolate;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;gap:9px;background:var(--accent);color:var(--accent-ink);border:1.3px solid var(--line);padding:13px 22px;font:12px "IBM Plex Mono",monospace;text-decoration:none;cursor:auto;transition:transform .15s,box-shadow .2s,letter-spacing .2s}.gen:before,.primary:before{content:"";position:absolute;inset:-20% auto -20% -45%;width:32%;background:rgba(255,255,255,.25);transform:skewX(-22deg);animation:sweep 3s ease-in-out infinite;z-index:-1}@keyframes sweep{0%,32%{left:-45%}70%,100%{left:125%}}.gen:hover,.primary:hover{letter-spacing:.035em;box-shadow:4px 4px 0 var(--line);transform:translate(-2px,-2px)}.gen:active,.primary:active{transform:translate(2px,2px);box-shadow:none}.gen:disabled,.primary:disabled{opacity:.55;cursor:wait}.gen:after{content:"";position:absolute;inset:-3px;border:1px solid rgba(255,255,255,.4);opacity:0;animation:glowPulse 2.2s ease-out infinite;pointer-events:none}@keyframes glowPulse{0%{opacity:0;transform:scale(.96)}35%{opacity:.7}100%{opacity:0;transform:scale(1.05)}}.result{margin-top:26px;border:1.3px solid var(--line);background:var(--card);box-shadow:6px 6px 0 var(--line);padding:18px 20px;animation:rise .35s ease both}@keyframes rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}.result.error{border-color:#a83b30}.result h3{margin:0 0 7px;font-size:21px;font-weight:500}.result p{margin:5px 0;color:var(--soft);font-size:15px;line-height:1.5}.result .primary{margin-top:14px;width:100%}.result .small{font:10px/1.7 "IBM Plex Mono",monospace;color:var(--soft);margin-top:14px}.trust{margin-top:28px;text-align:center;padding-bottom:8px}.trust p{font:10px/1.7 "IBM Plex Mono",monospace;color:var(--soft);margin:5px 0}.trust strong{color:var(--ink);font-weight:500}.toast{position:fixed;left:50%;bottom:24px;transform:translate(-50%,18px);opacity:0;pointer-events:none;background:var(--ink);color:var(--paper);padding:12px 16px;font:11px "IBM Plex Mono",monospace;z-index:1000;box-shadow:4px 4px 0 var(--accent);transition:opacity .2s,transform .2s}.toast.show{opacity:1;transform:translate(-50%,0)}
.overlay{position:fixed;inset:0;background:color-mix(in srgb,var(--ink) 55%,transparent);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:20px;z-index:200;opacity:0;pointer-events:none;transition:opacity .25s}.overlay.show{opacity:1;pointer-events:auto}.modal{width:100%;max-width:480px;max-height:min(90vh,760px);overflow:auto;background:var(--card);border:1.3px solid var(--line);box-shadow:9px 9px 0 var(--line);background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);background-size:18px 18px;transform:scale(.92);opacity:0;transition:transform .32s cubic-bezier(.2,1.2,.4,1),opacity .2s}.overlay.show .modal{transform:scale(1);opacity:1}.modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:15px 18px;border-bottom:1.3px solid var(--line);font:11px "IBM Plex Mono",monospace;color:var(--soft)}.modal-close{width:29px;height:29px;border:1.3px solid var(--line);background:var(--paper);color:var(--ink);font-size:18px;line-height:1;cursor:auto}.modal-body{padding:26px 24px 24px;text-align:center}.check{width:46px;height:46px;margin:3px auto 15px;border:1.3px solid var(--line);border-radius:50%;display:grid;place-items:center;color:var(--accent);font-size:22px}.modal-body h3{font-size:27px;font-weight:500;margin:0 0 10px}.modal-body p{font-size:14px;color:var(--soft);line-height:1.6;margin:0 0 8px}.warn{font:10px/1.7 "IBM Plex Mono",monospace!important;color:var(--amber)!important;margin-top:13px!important}.unlock{width:100%;margin-top:11px;position:relative;isolation:isolate;overflow:hidden;background:var(--accent);color:var(--accent-ink);border:1.3px solid var(--line);padding:15px 13px;font:12px "IBM Plex Mono",monospace;cursor:auto;transition:transform .15s,box-shadow .2s}.unlock:before{content:"";position:absolute;inset:-30% auto -30% -50%;width:28%;background:rgba(255,255,255,.3);transform:skewX(-22deg);animation:sweep 2.4s ease-in-out infinite;z-index:-1}.unlock:after{content:"";position:absolute;inset:0;border:1px solid rgba(255,255,255,.42);animation:glowPulse 2.2s ease-out infinite;pointer-events:none}.unlock:hover{box-shadow:5px 5px 0 var(--line);transform:translate(-2px,-2px)}.unlock:active{transform:translate(2px,2px)}.unlock:disabled{opacity:.6;cursor:wait}.foot-note{font:10px/1.7 "IBM Plex Mono",monospace!important;margin-top:14px!important}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:560px){.bar{padding:15px 20px}.node{font-size:8px}.hero{padding:6vh 20px 4vh}.hero h1{font-size:56px}.sub{font-size:16px}main{padding:0 17px 38px}.field{grid-template-columns:1fr;gap:9px;padding:15px 17px}.card-head,.foot{padding-left:17px;padding-right:17px}.foot{align-items:stretch;flex-direction:column}.gen{width:100%}.statusline{font-size:8px}.modal-body{padding:23px 17px}.modal-body h3{font-size:25px}}

/* Responsive cursor + tactile feedback */
#curpointer{position:fixed;left:0;top:0;width:28px;height:34px;z-index:1002;pointer-events:none;transform-origin:3px 2px;transition:opacity .15s ease,transform .16s ease,filter .16s ease;will-change:left,top;display:none;opacity:0}
#cur,#curdot{opacity:0}
html.has-cursor-position #cur,html.has-cursor-position #curdot,html.has-cursor-position #curpointer{opacity:1}
#curpointer svg{display:block;width:100%;height:100%;overflow:visible}
#curpointer .pointer-shape{fill:var(--accent);stroke:var(--line);stroke-width:1.65;stroke-linejoin:round}
#curpointer .pointer-glint{fill:none;stroke:var(--accent-ink);stroke-width:1.25;stroke-linecap:round;stroke-linejoin:round;opacity:.95}
#curpointer .pointer-notch{fill:none;stroke:var(--line);stroke-width:1.15;stroke-linecap:round;opacity:.8}
#cur{z-index:998}
#curdot{z-index:999}
#curdot.hover{transform:translate(-50%,-50%) scale(.82)}
html.cursor-pointer-hover #curpointer{transform:scale(1.08) rotate(-4deg);filter:drop-shadow(2px 2px 0 color-mix(in srgb,var(--accent) 28%,transparent))}
@media(pointer:fine) and (hover:hover){
  html.has-cursor-position body,html.has-cursor-position button,html.has-cursor-position a,html.has-cursor-position .dd-btn,html.has-cursor-position .dd-list button,html.has-cursor-position .burger,html.has-cursor-position .gen,html.has-cursor-position .primary,html.has-cursor-position .modal-close,html.has-cursor-position .unlock,html.has-cursor-position .copy-btn{cursor:none!important}
  #cur,#curdot,#curpointer{display:block}
}
@media(pointer:coarse),(hover:none){
  body,button,a,.dd-btn,.dd-list button,.burger,.gen,.primary,.modal-close,.unlock,.copy-btn{cursor:auto!important}
  #cur,#curdot,#curpointer{display:none!important}
  .ripple{border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 13%,transparent)}
  .gen:before,.primary:before,.unlock:before{animation:none!important;opacity:0}
  .card::after,.keybox::after{animation:none!important;opacity:0}
}
@keyframes panelScan{
  0%{transform:translateX(0) skewX(-18deg);opacity:0}
  16%{opacity:.52}
  54%{opacity:.45}
  76%,100%{transform:translateX(650%) skewX(-18deg);opacity:0}
}
@keyframes tapSweep{
  0%{transform:translateX(0) skewX(-22deg);opacity:0}
  18%{opacity:.62}
  100%{transform:translateX(520%) skewX(-22deg);opacity:0}
}
@keyframes tapRipple{
  from{width:0;height:0;opacity:.6}
  to{width:116px;height:116px;opacity:0}
}
/* Mobile still gets responsive touch feedback without a permanent cursor RAF loop */
.touch-pressed{filter:saturate(1.12)}
button.touch-pressed,a.touch-pressed,.gen.touch-pressed,.primary.touch-pressed,.unlock.touch-pressed,.copy-btn.touch-pressed,.dd-btn.touch-pressed,.burger.touch-pressed{
  transform:translate(2px,2px)!important;box-shadow:1px 1px 0 var(--line)!important;
}
.card.touch-pressed,.keybox.touch-pressed,.instructions.touch-pressed,.meta-item.touch-pressed{
  transform:translate(2px,2px)!important;box-shadow:2px 2px 0 var(--line)!important;
}
.touch-flash::after{animation:tapSweep .8s ease-out both!important}
.gen.touch-flash::before,.primary.touch-flash::before,.unlock.touch-flash::before,.copy-btn.touch-flash::before,.dd-btn.touch-flash::before{
  opacity:1!important;animation:tapSweep .7s ease-out both!important
}
.copy-btn{position:relative;isolation:isolate;overflow:hidden}
.copy-btn::before{content:"";position:absolute;inset:-20% auto -20% -40%;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);transform:skewX(-22deg);opacity:0;pointer-events:none;z-index:1}
.copy-btn>*{position:relative;z-index:2}
.copy-btn:hover::before{opacity:1;animation:tapSweep .9s ease-out both}
.copy-btn:hover{box-shadow:4px 4px 0 var(--line);transform:translate(-2px,-2px)}
.copy-btn:active{transform:translate(2px,2px);box-shadow:none}
.ripple{animation:tapRipple .68s ease-out forwards}
@media(pointer:coarse),(hover:none){
  .gen.touch-flash:before,.primary.touch-flash:before,.unlock.touch-flash:before{display:block}
}
@media(prefers-reduced-motion:reduce){
  #cur,#curdot,#curpointer{transition:none!important}
  .card::after,.keybox::after,.copy-btn::before{animation:none!important}
}

@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}}

/* Preview parity + stronger visible UI effects */
.dd-list{z-index:120;border-color:var(--line);box-shadow:5px 5px 0 var(--line)}
.dd.open .dd-list{max-height:240px;opacity:1;overflow:auto;overscroll-behavior:contain}
.dd-list button{min-height:42px;transition:background .16s ease,transform .16s ease,padding-left .16s ease}
.dd-list button:hover,.dd-list button:focus-visible{background:var(--grid);padding-left:18px}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;background:var(--line);border:1.3px solid var(--line);margin-top:30px;box-shadow:5px 5px 0 var(--line);animation:rise .42s ease both}
.stats>div{background:var(--card);padding:16px 8px 13px;text-align:center;position:relative;overflow:hidden;min-width:0;transition:transform .18s ease,background .18s ease,box-shadow .18s ease}
.stats>div::after{content:"";position:absolute;left:-45%;top:-25%;bottom:-25%;width:28%;background:linear-gradient(90deg,transparent,rgba(43,79,216,.13),rgba(255,255,255,.35),transparent);transform:skewX(-20deg);opacity:0;pointer-events:none}
.stats>div:hover{transform:translate(0,-2px);background:#fffefa;box-shadow:0 3px 0 var(--accent)}
.stats>div:hover::after{opacity:1;animation:statsScan .9s ease-out both}
@keyframes statsScan{to{left:130%;opacity:0}}
.stats>div.touch-flash::after{opacity:1;animation:statsScan .8s ease-out both!important}
.stats>div.touch-pressed{transform:translate(2px,2px)!important;box-shadow:2px 2px 0 var(--line)!important}
.stats .num{display:block;font:600 21px "IBM Plex Mono",monospace;color:var(--accent);letter-spacing:-.03em}
.stats .lbl{display:block;font:9px/1.5 "IBM Plex Mono",monospace;color:var(--soft);margin-top:6px;letter-spacing:.025em}
.stats .desc{display:block;font:9px/1.5 "IBM Plex Mono",monospace;color:var(--soft);opacity:.82;margin-top:5px}
@media(max-width:560px){.stats{grid-template-columns:repeat(2,minmax(0,1fr));margin-top:22px}.stats>div{padding:14px 8px 12px}.stats .num{font-size:19px}.stats .lbl{font-size:9px}}
@media(prefers-reduced-motion:reduce){.stats,.stats>div,.dd-list button{animation:none!important;transition:none!important}}

</style>
</head>
<body class="getkey-page">
<div id="cur" aria-hidden="true"></div>
<div id="curdot" aria-hidden="true"></div>
<div id="curpointer" aria-hidden="true">
  <svg viewBox="0 0 28 34" xmlns="http://www.w3.org/2000/svg" focusable="false">
    <path class="pointer-shape" d="M3 2.4 3 25.7 9.6 19.2 15 31.2 19.7 29.1 14.3 17.4 24.2 17.4Z"/>
    <path class="pointer-glint" d="M6.2 7.1 6.2 18.3 9.3 15.3"/>
    <path class="pointer-notch" d="M14.3 17.4 19.7 29.1"/>
  </svg>
</div>
<header class="bar">
  <div class="wordmark"><b>KEY</b>.SYS — NODE VN-01</div>
  <div style="display:flex;align-items:center;gap:18px"><div class="node"><i></i>LICENSE<br>DELIVERY</div><button class="burger" id="burger" type="button" aria-label="Mở menu" aria-controls="menu" aria-expanded="false"><span></span><span></span><span></span></button></div>
  <nav class="menu" id="menu" aria-label="Menu chính" aria-hidden="true"><a href="getkey"><svg class="menu-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7M21 3 10 14"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg><span>Lấy key</span></a><a href="admin/index"><svg class="menu-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 20 7v5c0 5-3.4 8-8 9-4.6-1-8-4-8-9V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg><span>Admin panel</span></a></nav>
</header>
<section class="hero">
  <p class="eyebrow">HỆ THỐNG CẤP LICENSE MIỄN PHÍ / V1.0</p>
  <h1 id="type"><span class="caret">&nbsp;</span></h1>
  <p class="sub">Tạo license cho ứng dụng của bạn. Mỗi key được gắn với một thiết bị. Hoàn tất các bước vượt link tương ứng với thời hạn đã chọn để nhận key.</p>
</section>
<main>
  <div class="statusline"><div class="statusleft"><span class="statusmark">✓</span><span>KEYGEN SERVICE / OPERATIONAL</span></div><span class="statuspill">FREE ACCESS</span></div>
  <section class="card">
    <div class="card-head"><h2>Tạo license</h2><span class="badge">FREE PLAN</span></div>
    <form id="licenseForm" autocomplete="off">
      <input type="hidden" name="game" id="gameValue" value="">
      <input type="hidden" name="duration" id="durationValue" value="1d">
      <div class="field">
        <label for="appChoice">ỨNG DỤNG / APP</label>
        <div class="dd" id="ddApp">
          <button class="dd-btn" id="appChoice" type="button" aria-haspopup="listbox" aria-expanded="false"><span class="dd-label" id="appLabel" data-text="Chọn ứng dụng">Chọn ứng dụng</span><svg viewBox="0 0 10 6"><path d="M1 1l4 4 4-4"/></svg></button>
          <div class="dd-list" role="listbox">
            <?php foreach ($KEYGEN_APPS as $gKey => $g): ?>
              <button type="button" role="option" data-v="<?= htmlspecialchars($gKey, ENT_QUOTES, 'UTF-8') ?>" data-label="<?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="field">
        <label>SỐ THIẾT BỊ</label>
        <div class="stepper"><input id="devCount" value="1" readonly aria-readonly="true" aria-label="Số thiết bị cố định là 1"><span class="fixed-tag">CỐ ĐỊNH</span></div>
      </div>
      <div class="field">
        <label for="durationChoice">THỜI HẠN</label>
        <div class="dd" id="ddDays">
          <button class="dd-btn" id="durationChoice" type="button" aria-haspopup="listbox" aria-expanded="false"><span class="dd-label" id="durationLabel" data-text="1 ngày">1 ngày</span><svg viewBox="0 0 10 6"><path d="M1 1l4 4 4-4"/></svg></button>
          <div class="dd-list" role="listbox" id="durationList"></div>
        </div>
      </div>
      <div class="field">
        <label>GÓI LICENSE</label><div class="stepper"><span class="mono">FREE</span><span class="fixed-tag">1 KEY / 1 DEVICE</span></div>
      </div>
      <div class="foot"><span class="out">MODE: <b>FREE KEYGEN</b></span><button class="gen" id="generateBtn" type="submit">Tạo key ↗</button></div>
    </form>
  </section>
  <div id="resultArea" aria-live="polite"></div>
  <section class="stats" aria-label="Thông số hệ thống KEY.SYS">
    <div><span class="num">03</span><span class="lbl">GÓI THỜI HẠN</span><span class="desc">12h · 1 ngày · 2 ngày</span></div>
    <div><span class="num">01</span><span class="lbl">THIẾT BỊ / KEY</span><span class="desc">Giới hạn một thiết bị</span></div>
    <div><span class="num">45s</span><span class="lbl">CHỜ TỐI THIỂU</span><span class="desc">Cho mỗi lượt vượt link</span></div>
    <div><span class="num">02</span><span class="lbl">DỊCH VỤ LINK</span><span class="desc">Link4m + Layma.net</span></div>
  </section>
  <div class="trust"><p>SSL READY — SESSION TICKET — ONE DEVICE PER KEY</p><p><strong>KEY.SYS</strong> · Hệ thống cấp license tự động · © 2026</p></div>
</main>
<div class="overlay" id="overlay" aria-hidden="true">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="readyTitle">
    <div class="modal-head"><span id="modalApp">YÊU CẦU KEY — CLAIM PORTAL</span><button class="modal-close" id="modalClose" type="button" aria-label="Đóng">×</button></div>
    <div class="modal-body">
      <div class="check">✓</div>
      <h3 id="readyTitle">Sẵn sàng</h3>
      <p id="readyMessage">Bấm nút bên dưới để bắt đầu vượt link lấy key.</p>
      <button class="unlock" id="unlockBtn" type="button">Vượt Link4m để lấy key ↗</button>
      <p class="warn" id="readyWarning">Link4m cần tối thiểu 45 giây. Khi quay lại, hệ thống sẽ kiểm tra bước tiếp theo.</p>
      <p class="foot-note" id="readyFoot">Không đóng tab sau khi bắt đầu vượt link. Không chia sẻ key đã tạo.</p>
    </div>
  </section>
</div>
<div class="toast" id="toast"></div>
<script>
const durationChoices = <?= json_encode($durationOptionsForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const form=document.getElementById('licenseForm');
const generateBtn=document.getElementById('generateBtn');
const resultArea=document.getElementById('resultArea');
const overlay=document.getElementById('overlay');
const unlockBtn=document.getElementById('unlockBtn');
const menu=document.getElementById('menu');
let pendingUnlockUrl='';
let activeTicket=false;

// Custom cursor + click ripple.
// Custom cursor: only animate on devices with a real hover-capable pointer.
const cur=document.getElementById('cur'),dot=document.getElementById('curdot'),pointerEl=document.getElementById('curpointer');
const cursorMedia=window.matchMedia('(pointer:fine) and (hover:hover)');
const reducedMotionMedia=window.matchMedia('(prefers-reduced-motion: reduce)');
const touchMedia=window.matchMedia('(pointer:coarse), (hover:none)');
let mx=innerWidth/2,my=innerHeight/2,rx=mx,ry=my,cursorRaf=0,cursorRunning=false;
function cursorLoop(){if(!cursorRunning)return;rx+=(mx-rx)*.22;ry+=(my-ry)*.22;cur.style.left=rx+'px';cur.style.top=ry+'px';cursorRaf=requestAnimationFrame(cursorLoop)}
function startCursorLoop(){if(!cursorMedia.matches||reducedMotionMedia.matches||document.hidden||cursorRunning||!document.documentElement.classList.contains('has-cursor-position'))return;cursorRunning=true;cursorLoop()}
function stopCursorLoop(){cursorRunning=false;if(cursorRaf)cancelAnimationFrame(cursorRaf);cursorRaf=0}
function syncCursorMode(){if(cursorMedia.matches){document.documentElement.classList.add('has-custom-cursor')}else{document.documentElement.classList.remove('has-custom-cursor','cursor-pointer-hover','has-cursor-position');stopCursorLoop()}}
window.addEventListener('mousemove',e=>{if(!cursorMedia.matches)return;mx=e.clientX;my=e.clientY;document.documentElement.classList.add('has-cursor-position');dot.style.left=mx+'px';dot.style.top=my+'px';pointerEl.style.left=(mx-3)+'px';pointerEl.style.top=(my-2)+'px';if(reducedMotionMedia.matches){rx=mx;ry=my;cur.style.left=mx+'px';cur.style.top=my+'px'}else startCursorLoop()},{passive:true});
document.addEventListener('mouseleave',()=>{document.documentElement.classList.remove('has-cursor-position','cursor-pointer-hover');cur.classList.remove('hover');dot.classList.remove('hover');stopCursorLoop()});
document.addEventListener('visibilitychange',()=>document.hidden?stopCursorLoop():startCursorLoop());
if(cursorMedia.addEventListener)cursorMedia.addEventListener('change',syncCursorMode);else if(cursorMedia.addListener)cursorMedia.addListener(syncCursorMode);
if(reducedMotionMedia.addEventListener)reducedMotionMedia.addEventListener('change',()=>{if(reducedMotionMedia.matches)stopCursorLoop();else startCursorLoop()});
syncCursorMode();
function bindHover(el){if(!el||el.dataset.hoverBound)return;el.dataset.hoverBound='1';el.addEventListener('mouseenter',()=>{if(!cursorMedia.matches)return;cur.classList.add('hover');dot.classList.add('hover');document.documentElement.classList.add('cursor-pointer-hover')});el.addEventListener('mouseleave',()=>{cur.classList.remove('hover');dot.classList.remove('hover');document.documentElement.classList.remove('cursor-pointer-hover')})}
document.querySelectorAll('button,a,.dd-list button').forEach(bindHover);
// Click/tap ripples work on desktop and touchscreens.
document.addEventListener('click',e=>{const r=document.createElement('span');r.className='ripple';r.style.left=e.clientX+'px';r.style.top=e.clientY+'px';r.style.width='0';r.style.height='0';document.body.appendChild(r);setTimeout(()=>r.remove(),720)},{passive:true});
// Mobile gets short scan/press feedback on interaction, not an always-running cursor animation.
function timedClass(el,name,duration){if(!el)return;el.classList.add(name);const key='_'+name+'Timer';if(el[key])clearTimeout(el[key]);el[key]=setTimeout(()=>el.classList.remove(name),duration)}
document.addEventListener('pointerdown',e=>{if(!(touchMedia.matches||e.pointerType==='touch'||e.pointerType==='pen'))return;const target=e.target.closest('button,a,.card,.keybox,.instructions,.meta-item,.dd-btn,.stats>div');if(!target)return;timedClass(target,'touch-flash',900);if(target.matches('button,a,.card,.keybox,.instructions,.meta-item'))timedClass(target,'touch-pressed',230);const card=target.closest('.card');if(card)timedClass(card,'touch-flash',900);const keybox=target.closest('.keybox');if(keybox)timedClass(keybox,'touch-flash',900)},{passive:true});
// Main headline typewriter.
const headline=document.getElementById('type');const mainWord='GetKey';let headIndex=0,headDeleting=false;
(function typeHeadline(){headline.innerHTML=mainWord.slice(0,headIndex)+'<span class="caret">&nbsp;</span>';let wait=headDeleting?65:105;if(!headDeleting&&headIndex===mainWord.length){wait=1300;headDeleting=true}else if(headDeleting&&headIndex===0){wait=400;headDeleting=false}else{headIndex+=headDeleting?-1:1}setTimeout(typeHeadline,wait)})();

// Fast typing loop used for the two custom dropdown values.
function startFieldTyping(label,text){if(!label)return;clearTimeout(label._typingTimer);label.dataset.text=text;let i=0,deleting=false;label.innerHTML='<span class="field-typed"></span><span class="field-caret" aria-hidden="true"></span>';const typed=label.querySelector('.field-typed');function tick(){typed.textContent=text.slice(0,i);let wait;if(!deleting&&i===text.length){wait=300;deleting=true}else if(deleting&&i===0){wait=120;deleting=false}else{wait=deleting?18:28;i+=deleting?-1:1}label._typingTimer=setTimeout(tick,wait)}label._typingTimer=setTimeout(tick,25)}
const appDD=document.getElementById('ddApp'),appLabel=document.getElementById('appLabel'),gameValue=document.getElementById('gameValue');
const durationDD=document.getElementById('ddDays'),durationLabel=document.getElementById('durationLabel'),durationValue=document.getElementById('durationValue'),durationList=document.getElementById('durationList');
startFieldTyping(appLabel,'Chọn ứng dụng');startFieldTyping(durationLabel,'1 ngày');
let selectedDuration='1d';
function renderDurationOptions(){durationList.innerHTML='';durationChoices.slice().sort((a,b)=>a.seconds-b.seconds).filter(opt=>opt.value!==selectedDuration).forEach(opt=>{const button=document.createElement('button');button.type='button';button.setAttribute('role','option');button.dataset.v=opt.value;button.textContent=opt.label;button.addEventListener('click',()=>{selectedDuration=opt.value;durationValue.value=selectedDuration;startFieldTyping(durationLabel,opt.label);durationDD.classList.remove('open');document.getElementById('durationChoice').setAttribute('aria-expanded','false');renderDurationOptions();});durationList.appendChild(button);bindHover(button)})}
renderDurationOptions();
function closeDropdowns(){document.querySelectorAll('.dd.open').forEach(dd=>{dd.classList.remove('open');const b=dd.querySelector('.dd-btn');if(b)b.setAttribute('aria-expanded','false')})}
document.querySelectorAll('.dd').forEach(dd=>{const btn=dd.querySelector('.dd-btn');btn.addEventListener('click',e=>{e.stopPropagation();const was=dd.classList.contains('open');closeDropdowns();if(!was){dd.classList.add('open');btn.setAttribute('aria-expanded','true')}})});
appDD.querySelectorAll('[data-v]').forEach(opt=>opt.addEventListener('click',()=>{gameValue.value=opt.dataset.v;startFieldTyping(appLabel,opt.dataset.label);appDD.classList.remove('open');document.getElementById('appChoice').setAttribute('aria-expanded','false')}));
document.addEventListener('click',closeDropdowns);

const burger=document.getElementById('burger');
function setMenuOpen(open){burger.classList.toggle('open',open);menu.classList.toggle('open',open);burger.setAttribute('aria-expanded',open?'true':'false');menu.setAttribute('aria-hidden',open?'false':'true');}
burger.addEventListener('click',e=>{e.stopPropagation();setMenuOpen(!menu.classList.contains('open'));});
document.addEventListener('click',e=>{if(!burger.contains(e.target)&&!menu.contains(e.target))setMenuOpen(false)});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu.classList.contains('open')){setMenuOpen(false);burger.focus()}});
menu.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>setMenuOpen(false)));
function showToast(message){const t=document.getElementById('toast');t.textContent=message;t.classList.add('show');clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>t.classList.remove('show'),2800)}
function closeModal(){overlay.classList.remove('show');overlay.setAttribute('aria-hidden','true')}
document.getElementById('modalClose').addEventListener('click',closeModal);
overlay.addEventListener('click',e=>{if(e.target===overlay)closeModal()});
function showError(message){resultArea.innerHTML='';const box=document.createElement('section');box.className='result error';const h=document.createElement('h3');h.textContent='Không thể tạo yêu cầu';const p=document.createElement('p');p.textContent=message;box.append(h,p);resultArea.appendChild(box)}
form.addEventListener('submit',async e=>{e.preventDefault();if(!gameValue.value){showError('Vui lòng chọn ứng dụng trước khi tạo key.');return}generateBtn.disabled=true;generateBtn.textContent='Đang xử lý…';resultArea.innerHTML='';try{const fd=new FormData(form);fd.set('action','create');const response=await fetch('getkey_action.php',{method:'POST',body:fd,credentials:'same-origin'});const data=await response.json();if(!response.ok||data.status!=='success'){showError(data.message||'Có lỗi xảy ra. Vui lòng thử lại.');return}pendingUnlockUrl=data.unlockUrl;activeTicket=true;document.getElementById('modalApp').textContent='YÊU CẦU KEY — '+(data.appLabel||'CLAIM PORTAL').toUpperCase();document.getElementById('readyMessage').textContent='Ứng dụng: '+data.appLabel+'. Thời hạn: '+data.duration+'. Hoàn tất Link4m và các lượt Layma nếu được yêu cầu để nhận key.';document.getElementById('readyWarning').textContent='Link4m và mỗi lượt Layma.net cần tối thiểu 45 giây trước khi quay lại. Hệ thống sẽ kiểm tra từng bước của vé.';const laymaCount=Number(data.laymaPasses||0);document.getElementById('readyFoot').textContent=laymaCount===0?'Gói 12h: chỉ cần hoàn tất 1 lượt Link4m.':'Sau Link4m, bạn cần hoàn tất thêm '+laymaCount+' lượt Layma.net (mỗi lượt tối thiểu 45 giây). Còn '+(data.remaining??'?')+' lượt lấy key trong giờ này.';unlockBtn.disabled=false;unlockBtn.textContent='Vượt Link4m để lấy key ↗';overlay.classList.add('show');overlay.setAttribute('aria-hidden','false');}catch(error){showError('Lỗi kết nối server. Vui lòng thử lại.')}finally{generateBtn.disabled=false;generateBtn.textContent='Tạo key ↗'}});
unlockBtn.addEventListener('click',async()=>{if(!pendingUnlockUrl)return;unlockBtn.disabled=true;unlockBtn.textContent='Đang chuyển tới Link4m…';try{const fd=new FormData();fd.set('action','begin_link4m');const response=await fetch('getkey_action.php',{method:'POST',body:fd,credentials:'same-origin'});const data=await response.json();if(!response.ok||data.status!=='success'){showToast(data.message||'Không thể bắt đầu Link4m.');unlockBtn.disabled=false;unlockBtn.textContent='Thử lại Link4m ↗';return}window.location.assign(pendingUnlockUrl)}catch(error){showToast('Không thể kết nối server. Vui lòng thử lại.');unlockBtn.disabled=false;unlockBtn.textContent='Thử lại Link4m ↗'}});
</script>
</body>
</html>
