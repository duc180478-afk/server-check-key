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
