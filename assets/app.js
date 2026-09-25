(async () => {
  const S = window.Sanchari;
  const cards = document.getElementById('routeCards');
  let data, availability = {booking_enabled:false}, direction = 'from', shuttleDirection = 'ab';
  const phases = {ab:0, ba:8}; // Campus timetable: each direction runs every 15 minutes, offset by 8 minutes.
  const pad = value => String(value).padStart(2,'0');
  const today = () => S.dayKey(Date.now());
  const istMidnight = () => new Date(today()+'T00:00:00+05:30').getTime();
  const shortClock = ts => S.clock(ts).replace(/\s*(am|pm)$/i, x => x.toUpperCase());
  const tomorrow = () => S.dayKey(istMidnight()+86400000);
  const shortWhen = ts => S.dayKey(ts)===today() ? shortClock(ts) : S.dayKey(ts)===tomorrow() ? 'Tomorrow · '+shortClock(ts) : S.date(ts,{weekday:'short'})+' · '+shortClock(ts);
  const longerWhen = ts => S.dayKey(ts)===today() ? shortClock(ts) : S.dayKey(ts)===tomorrow() ? 'Tomorrow · '+shortClock(ts) : S.when(ts);
  const minutesAway = ts => Math.max(1, Math.ceil((ts-Date.now())/60000));
  const shuttleTimes = (dir,count=6) => {
    const start=istMidnight()+phases[dir]*60000, gap=15*60000;
    const first=Math.floor((Date.now()-start)/gap)+1;
    return Array.from({length:count},(_,i)=>start+(first+i)*gap);
  };
  function renderShuttleCountdown(){
    for(const dir of ['ab','ba']){
      const next=shuttleTimes(dir,1)[0];
      const seconds=Math.max(0,Math.ceil((next-Date.now())/1000));
      const minutes=Math.floor(seconds/60),remainder=seconds%60;
      const timer=document.getElementById('eta-'+dir);
      timer.textContent=pad(minutes)+':'+pad(remainder);
      timer.setAttribute('aria-label','Next shuttle in '+minutes+' minutes '+remainder+' seconds');
    }
  }
  function renderShuttleSchedule(){
    document.getElementById('slist').innerHTML=shuttleTimes(shuttleDirection).map((ts,i)=>`<div class="srow ${i===0?'next':''}"><span class="ct">${shortClock(ts)}</span><span class="rel">${i===0?'next · ':''}${minutesAway(ts)} min</span></div>`).join('');
  }
  function renderShuttle(){renderShuttleCountdown();renderShuttleSchedule();}
  function renderDate(){document.getElementById('todayLabel').textContent=S.date(Date.now(),{weekday:'long',day:'numeric',month:'long'})+' · IST';}
  renderDate();
  document.querySelectorAll('.dir').forEach(button=>button.addEventListener('click',()=>{
    shuttleDirection=button.dataset.dir;
    document.querySelectorAll('.dir').forEach(b=>{b.classList.toggle('sel',b===button);b.setAttribute('aria-pressed',String(b===button));});
    document.querySelectorAll('.pill').forEach(b=>{b.classList.toggle('on',b.dataset.sdir===shuttleDirection);b.setAttribute('aria-pressed',String(b.dataset.sdir===shuttleDirection));});
    renderShuttle();
  }));
  document.querySelectorAll('.pill').forEach(button=>button.addEventListener('click',()=>document.querySelector(`.dir[data-dir="${button.dataset.sdir}"]`).click()));
  document.getElementById('schedBtn').addEventListener('click',e=>{
    const open=e.currentTarget.getAttribute('aria-expanded')!=='true';
    e.currentTarget.setAttribute('aria-expanded',String(open));e.currentTarget.classList.toggle('open',open);
    document.getElementById('schedPanel').classList.toggle('open',open);
    if(open)renderShuttleSchedule();
  });
  renderShuttle();setInterval(renderShuttleCountdown,1000);setInterval(renderShuttleSchedule,30000);setInterval(renderDate,60000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden){renderDate();renderShuttle();}});
  try{const response=await fetch('assets/routes.json?v=11');if(!response.ok)throw Error();data=await response.json();}
  catch{cards.innerHTML='<p class="route-notice">Schedules could not load. Please refresh or contact Transport.</p>';return;}
  try{availability=await S.api('status.php');}catch{}
  const names={patan:'Patancheru',miya:'Miyapur'};
  const statusByCard={};
  function extraHTML(id,cfg){
    if(id!=='miya')return '';
    const stops=direction==='to'?'<div class="rc-sec" style="margin-top:15px">Stops · Miyapur → IITH</div><div class="stops">'+cfg.stops.map(s=>`<div class="stop"><div class="rail"><span class="d"></span><span class="ln"></span></div><div class="si"><div><div class="nm2">${S.escape(s[0])}</div><div class="lm">${S.escape(s[1])}</div></div><div class="tm">${S.escape(s[2])}</div></div></div>`).join('')+'</div>':'<div class="rc-sec" style="margin-top:15px">From campus</div><div class="retnote">Board at <b>${S.escape(cfg.boarding_from)}</b>. The bus continues through institute stops towards Miyapur.</div>';
    return stops+'<p class="note-days">Monday–Friday only · not on institute holidays.</p>';
  }
  function renderCards(){
    const expanded=new Set([...cards.querySelectorAll('.rcard.open')].map(x=>x.dataset.bus));
    cards.innerHTML=Object.entries(data.routes).map(([id,cfg])=>{
      const route=direction==='from'?'IITH → '+cfg.name:cfg.name+' → IITH';
      // The backend only accepts a trip at least six minutes away. Show and buy that same departure.
      const up=S.departures(cfg,direction,6,Date.now()+6*60000,data.holidays);
      const first=up[0];
      const rows=up.map((ts,i)=>`<div class="srow ${i===0?'next':''}"><span class="ct">${longerWhen(ts)}</span><span class="rel">${i===0?'next · ':''}${S.dayKey(ts)===today()?minutesAway(ts)+' min away':''}</span></div>`).join('');
      return `<article class="rcard ${expanded.has(id)?'open':''}" data-bus="${id}" data-departure="${first||''}"><div class="stubcard"><div class="rc-main"><div class="rc-route">${S.escape(route)}</div><div class="rc-when"><span class="rc-at">at</span><span class="tm">${first?shortWhen(first):'No trips listed'}</span><span class="rc-rel">${first&&S.dayKey(first)===today()?minutesAway(first)+' min':''}</span></div><div class="rc-actions"><button class="rc-expand" data-expand="${id}" aria-expanded="${expanded.has(id)}" aria-controls="schedule-${id}">Schedule <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M6 9l6 6 6-6"/></svg></button><a class="rc-live" href="${S.escape(cfg.live)}" target="_blank" rel="noopener noreferrer" aria-label="Open ${S.escape(cfg.name)} external bus tracker"><span class="pulse"></span>Live</a></div></div><div class="rc-vp" aria-hidden="true"></div><div class="rc-stub"><div class="rc-farel">Fare</div><div class="rc-fare">₹${cfg.fare}</div><button class="rc-buy" data-buy="${id}" ${first&&availability.booking_enabled?'':'disabled'}>Buy</button></div></div><p class="card-status" role="status">${statusByCard[id]||''}</p><div class="rc-detail" id="schedule-${id}" ${expanded.has(id)?'':'hidden'}><div class="rc-detail-in"><div class="rc-sec">Upcoming departures</div><div class="slist">${rows}</div><div class="retnote">Board at: ${S.escape(cfg['boarding_'+direction])}</div>${extraHTML(id,cfg)}<p class="note-days">${data.confirmed?'Check for service updates with Transport.':'Please confirm schedule changes with Transport before travel.'}</p></div></div></article>`;
    }).join('');
  }
  renderCards();
  cards.addEventListener('click',async event=>{
    const expand=event.target.closest('[data-expand]');
    if(expand){const card=expand.closest('.rcard'),open=!card.classList.contains('open');card.classList.toggle('open',open);card.querySelector('.rc-detail').hidden=!open;expand.setAttribute('aria-expanded',String(open));return;}
    const buy=event.target.closest('[data-buy]');if(!buy||buy.disabled)return;
    const card=buy.closest('.rcard'),id=buy.dataset.buy,ts=Number(card.dataset.departure),epoch=Math.floor(ts/1000);
    if(!ts||ts<Date.now()+6*60000){renderCards();return;}
    buy.disabled=true;buy.dataset.busy='true';buy.textContent='Opening…';
    const status=card.querySelector('.card-status');status.textContent='Preparing secure checkout…';
    let booking=S.saved().find(x=>x.route===id&&x.direction===direction&&x.departure===epoch);
    if(!booking){booking={id:'s_'+crypto.randomUUID().replace(/-/g,''),key:Array.from(crypto.getRandomValues(new Uint8Array(24)),x=>pad(x.toString(16))).join(''),route:id,direction,departure:epoch};
      if(!S.save(booking)){status.textContent='Allow browser storage before buying so your ticket can be recovered.';buy.disabled=false;buy.textContent='Buy';delete buy.dataset.busy;return;}}
    try{
      const order=await S.api('create-order.php',{route:id,direction,departure:epoch,id:booking.id,recovery_key:booking.key});
      if(order.token){location.href='ticket.html#t='+encodeURIComponent(order.token);return;}
      const url=new URL(order.checkout_url);if(url.protocol!=='https:')throw Error('Secure checkout could not open.');
      location.assign(url.href);
    }catch(error){const link=S.recovery(booking);status.innerHTML=`${S.escape(error.message)} <a href="${S.escape(link)}">Check this booking</a> before trying again.`;buy.disabled=false;buy.textContent='Buy';delete buy.dataset.busy;}
  });
  document.querySelectorAll('#specialToggle button').forEach(button=>button.addEventListener('click',()=>{
    direction=button.dataset.tdir;
    document.querySelectorAll('#specialToggle button').forEach(b=>{b.classList.toggle('on',b===button);b.setAttribute('aria-pressed',String(b===button));});
    renderCards();
  }));
  setInterval(()=>{if(!cards.contains(document.activeElement)&&!cards.querySelector('.rcard.open'))renderCards();},60000);
})();
