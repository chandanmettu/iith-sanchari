(async()=>{
  const S=window.Sanchari, cards=document.getElementById('routeCards'), notice=document.getElementById('bookingNotice');
  let data, direction='from', selected, availability={booking_enabled:false}, activeBooking, pending=false;
  const dialog=document.getElementById('journeyDialog'), pay=document.getElementById('confirmPay'), message=document.getElementById('checkoutMessage');
  const h=Number(S.date(Date.now(),{hour:'numeric',hour12:false}));
  document.getElementById('daypart').textContent=h<12?'Good morning, campus.':h<17?'Good afternoon, campus.':'Good evening, campus.';
  document.getElementById('todayLabel').textContent=S.date(Date.now(),{weekday:'long',day:'numeric',month:'short'})+' · IST';
  try{const response=await fetch('assets/routes.json?v=11');if(!response.ok)throw Error();data=await response.json()}catch{cards.innerHTML='<p class="notice">Bus schedules could not load. Please refresh or contact Transport.</p>';notice.hidden=true;return}
  try{availability=await S.api('status.php')}catch{availability={booking_enabled:false}}
  notice.textContent=availability.booking_enabled?'Online booking is open. Choose a departure to review your journey.':'Schedules are available. Online booking opens after payment setup and Transport confirmation.';
  notice.classList.toggle('ready',availability.booking_enabled);
  function render(){
    const expanded=new Set([...cards.querySelectorAll('[aria-expanded="true"]')].map(x=>x.dataset.expand));
    cards.innerHTML=Object.entries(data.routes).map(([id,cfg])=>{
      const up=S.departures(cfg,direction,6,Date.now(),data.holidays);const first=up[0];
      const route=direction==='from'?'IITH → '+cfg.name:cfg.name+' → IITH';
      const schedule=up.map((ts,i)=>`<div class="srow"><span class="ct">${S.when(ts)}</span><button data-review="${id}" data-departure="${ts}" aria-label="Review ${S.escape(route)} on ${S.when(ts)}">Select${i===0?' · next':''}</button></div>`).join('');
      const stops=id==='miya'&&direction==='to'?'<p class="rc-sec">Morning boarding stops</p><div class="stops">'+cfg.stops.map(s=>`<div class="stop"><div class="rail"><span class="d"></span><span class="ln"></span></div><div class="si"><div><div class="nm2">${S.escape(s[0])}</div><div class="lm">${S.escape(s[1])}</div></div><div class="tm">${S.escape(s[2])}</div></div></div>`).join('')+'</div>':'';
      return `<article class="rcard" data-bus="${id}"><div class="stubcard"><div class="rc-main"><div class="route-kicker">${cfg.weekdays?'Weekday service':'Daily service'} · approx. ${cfg.journey_mins===60?'1 hr':'1 hr 40 min'}</div><h3 class="rc-route">${S.escape(route)}</h3><div class="rc-when"><span class="rc-at">Next</span><span class="tm">${first?S.when(first):'No scheduled departures'}</span></div><div class="rc-actions"><button class="rc-expand" data-expand="${id}" aria-expanded="${expanded.has(id)}" aria-controls="schedule-${id}">Schedule <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></button><a class="rc-live" href="${S.escape(cfg.live)}" target="_blank" rel="noopener noreferrer" aria-label="Open ${cfg.name} external bus tracker">Track bus ↗</a></div></div><div class="rc-vp" aria-hidden="true"></div><div class="rc-stub"><div class="rc-farel">One way</div><div class="rc-fare">₹${cfg.fare}</div><div class="fare-sub">per passenger</div><button class="rc-buy" data-review="${id}" data-departure="${first||''}" ${first?'':'disabled'}>View trip</button></div></div><div class="rc-detail" id="schedule-${id}" ${expanded.has(id)?'':'hidden'}><div class="rc-detail-in"><p class="rc-sec">Upcoming departures · IST</p>${schedule}<p class="retnote">Board at: ${S.escape(cfg['boarding_'+direction])}</p>${stops}<p class="note-days">${cfg.weekdays?'Monday–Friday. ':''}Check holiday changes with Transport.${data.confirmed?'':' Current schedule awaits reconfirmation.'}</p></div></div></article>`;
    }).join('');
  }
  function review(id,ts){
    selected={route:id,direction,departure:Math.floor(ts/1000)};activeBooking=null;
    const cfg=data.routes[id],route=direction==='from'?'IITH → '+cfg.name:cfg.name+' → IITH';
    document.getElementById('journeySummary').innerHTML=`<div class="trip-summary"><h3>${S.escape(route)}</h3><div class="summary-row"><span>Departure</span><span>${S.when(ts)} IST</span></div><div class="summary-row"><span>Est. arrival</span><span>${S.when(ts+cfg.journey_mins*60000)}</span></div><div class="summary-row"><span>Boarding</span><span>${S.escape(cfg['boarding_'+direction])}</span></div><div class="summary-row"><span>Total fare</span><strong>₹${cfg.fare}</strong></div></div>`;
    pay.disabled=!availability.booking_enabled;pay.textContent=availability.booking_enabled?'Continue to payment · ₹'+cfg.fare:'Online booking opens soon';
    message.textContent=availability.booking_enabled?'':'You can check schedules now. Payments are not open yet.';
    document.getElementById('recoveryLink').hidden=true;dialog.showModal();
  }
  cards.addEventListener('click',e=>{const expand=e.target.closest('[data-expand]');if(expand){const panel=document.getElementById(expand.getAttribute('aria-controls'));panel.hidden=!panel.hidden;expand.setAttribute('aria-expanded',String(!panel.hidden));return}const button=e.target.closest('[data-review]');if(button)review(button.dataset.review,Number(button.dataset.departure))});
  document.querySelectorAll('#specialToggle button').forEach(btn=>btn.addEventListener('click',()=>{direction=btn.dataset.tdir;document.querySelectorAll('#specialToggle button').forEach(x=>{x.classList.toggle('on',x===btn);x.setAttribute('aria-pressed',String(x===btn))});render()}));
  pay.addEventListener('click',async()=>{
    if(pending)return;pending=true;pay.disabled=true;message.textContent='Preparing your secure checkout…';
    try{
      if(!activeBooking){activeBooking={id:'s_'+crypto.randomUUID().replace(/-/g,''),key:Array.from(crypto.getRandomValues(new Uint8Array(24)),x=>x.toString(16).padStart(2,'0')).join(''),...selected};const stored=S.save(activeBooking);if(!stored){activeBooking=null;throw Error('Allow browser storage before paying so your ticket can be recovered.');}}
      const link=document.getElementById('recoveryLink');link.href=S.recovery(activeBooking);link.textContent='Recover this booking';link.hidden=false;
      const order=await S.api('create-order.php',{...selected,id:activeBooking.id,recovery_key:activeBooking.key});
      if(order.token){location.href='ticket.html#t='+encodeURIComponent(order.token);return}
      const url=new URL(order.checkout_url);if(url.protocol!=='https:')throw Error('Could not open secure checkout. Use the recovery link.');location.assign(url.href);
    }catch(error){message.textContent=error.message+' If a payment was attempted, check this booking before paying again.';pay.textContent='Retry this booking';pay.disabled=false}finally{pending=false}
  });
  dialog.addEventListener('keydown',e=>{
    if(e.key!=='Tab')return;
    const controls=[...dialog.querySelectorAll('button:not([disabled]),a[href]')].filter(x=>!x.hidden&&x.getClientRects().length);
    const first=controls[0],last=controls[controls.length-1];
    if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}
    else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
  });
  dialog.addEventListener('cancel',e=>{if(pending)e.preventDefault()});render();
  // Refresh only between interactions; never replace a focused button or open schedule.
  setInterval(()=>{if(!dialog.open&&!cards.contains(document.activeElement)&&!cards.querySelector('[aria-expanded="true"]'))render()},60000);
})();
