(function(){
  const now = () => new Date();
  const startOfDay = () => { const d=new Date(); d.setHours(0,0,0,0); return d; };

  // interval shuttle — placeholder 15-min cadence, awaiting real transport-office timings (see BUILD_SPEC §10)
  function intervalTimes(phase, interval, count){
    const s=startOfDay(), n=now();
    const nowMins=(n - s)/60000;
    let k=Math.floor((nowMins - phase)/interval)+1;
    const out=[];
    for(let i=0;i<count;i++) out.push(new Date(s.getTime() + (phase+(k+i)*interval)*60000));
    return out;
  }
  const SHUTTLE = { ab:{phase:0}, ba:{phase:8} };

  // fixed schedules — real data (BUILD_SPEC §3)
  const PATAN = { from:['09:00','11:00','17:00','19:00','21:00','23:00'],
                  to:  ['08:00','10:00','16:00','18:00','20:00','22:00'] };
  const MIYA  = { from:['17:45'], to:['07:40'] };
  const MIYA_STOPS = [
    ['Miyapur Metro','Pillar No. 623','7:40 AM'],['Madinaguda','Opp. Green Bawarchi Hotel','7:54 AM'],
    ['Chanda Nagar','Opp. Swagath Restaurant','7:59 AM'],['BHEL Circle','BHEL Main Gate','8:05 AM'],
    ['Ashok Nagar','Near Indian Oil petrol bunk','8:07 AM'],['Beeramguda','Opp. Vijetha Super Market','8:10 AM'],
    ['D Mart','Near D Mart','8:11 AM'],['RC Puram','Near Ambedkar statue bus stop','8:13 AM'],
    ['Patancheru','Near bus stop','8:24 AM'],['Muthangi','Bus stop','8:30 AM'],['Isnapur','Isnapur X Road bus stop','8:35 AM']
  ];
  const MIYA_TERMS = ['Carry your Institute ID — checked by driver / security','Report at the boarding point 10 min before departure','Flat ₹100 per one-way, any stop · non-refundable'];
  const BUS = {
    patan:{data:PATAN,name:'Patancheru',weekdays:false,live:'https://tinyurl.com/4s6f94z8',fareFrom:5,fareTo:5},
    miya:{data:MIYA,name:'Miyapur',weekdays:true,live:'https://app.fleetx.io/live/share/v2/eJwFwcERADAEBMCKzJwgKMfjlJHas6uBuC9S4TSVga341EgfK2HX%2BnCTjg%24drArI',fareFrom:100,fareTo:100}
  };

  function parseHM(str, off){ const [h,m]=str.split(':').map(Number); const d=startOfDay(); d.setDate(d.getDate()+(off||0)); d.setHours(h,m,0,0); return d; }
  function nextFixed(list, count, weekdaysOnly){
    const n=now(); const out=[];
    for(let off=0; off<9 && out.length<count; off++){
      const probe=startOfDay(); probe.setDate(probe.getDate()+off);
      const dow=probe.getDay(); if(weekdaysOnly && (dow===0||dow===6)) continue;
      for(const s of list){ const d=parseHM(s,off); if(d>n){ out.push(d); if(out.length>=count) break; } }
    }
    return out;
  }
  const minsUntil = d => Math.round((d - now())/60000);
  const DOW=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  function fmtClock(d){ let h=d.getHours(),m=d.getMinutes(); const ap=h<12?'AM':'PM'; h=h%12||12; return h+':'+String(m).padStart(2,'0')+' '+ap; }
  function fmtWhen(d){ const n=now(); if(d.toDateString()===n.toDateString()) return 'Today, '+fmtClock(d);
    const tm=new Date(n); tm.setDate(n.getDate()+1); if(d.toDateString()===tm.toDateString()) return 'Tomorrow, '+fmtClock(d);
    return DOW[d.getDay()]+', '+fmtClock(d); }
  function fmtWhenShort(d){ const n=now(); if(d.toDateString()===n.toDateString()) return fmtClock(d);
    const tm=new Date(n); tm.setDate(n.getDate()+1); if(d.toDateString()===tm.toDateString()) return 'Tom '+fmtClock(d);
    return DOW[d.getDay()]+' '+fmtClock(d); }
  function fmtRel(mins){ if(mins<=0) return 'now'; if(mins<60) return '~'+mins+' min'; const h=Math.floor(mins/60), mm=mins%60; return '~'+h+' hr'+(mm?' '+mm+' min':''); }
  const sameDay = d => d.toDateString()===now().toDateString();

  let schedDir='ab';
  let specialDir='from';

  function extraHTML(bus, dir){
    if(bus!=='miya') return '';
    let html='';
    if(dir==='to'){
      html += '<div class="rc-sec" style="margin-top:15px">Stops · Miyapur → IITH</div><div class="stops">'+
        MIYA_STOPS.map(s=>`<div class="stop"><div class="rail"><span class="d"></span><span class="ln"></span></div><div class="si"><div><div class="nm2">${s[0]}</div><div class="lm">${s[1]}</div></div><div class="tm">${s[2]}</div></div></div>`).join('')+'</div>';
    } else {
      html += '<div class="rc-sec" style="margin-top:15px">Return · IITH → Miyapur</div><div class="retnote">Departs <b>5:45 PM</b> from <b>C-Block back-side parking</b>, through all major stops inside the Institute, then back to Miyapur.</div>';
    }
    html += '<div class="note-days">Runs Mon–Fri only · not on institute holidays.</div>';
    html += '<div class="terms"><div class="th">Good to know</div><ul>'+MIYA_TERMS.map(t=>'<li>'+t+'</li>').join('')+'</ul></div>';
    return html;
  }

  function renderHome(){
    const h=now().getHours();
    const daypartEl = document.getElementById('daypart');
    if(daypartEl) daypartEl.textContent = (h<12?'Good morning':h<17?'Good afternoon':'Good evening')+',';

    // shuttle
    ['ab','ba'].forEach(dir=>{
      const t=intervalTimes(SHUTTLE[dir].phase,15,1)[0]; const mins=minsUntil(t);
      const el=document.getElementById('eta-'+dir);
      if(el) el.innerHTML = mins<=0?'now':(mins+"<span style='font-size:10px;font-weight:800'> min</span>");
    });
    const slistEl=document.getElementById('slist');
    if(slistEl){
      const list=intervalTimes(SHUTTLE[schedDir].phase,15,6);
      slistEl.innerHTML = list.map((d,i)=>{
        const mins=minsUntil(d);
        return `<div class="srow ${i===0?'next':''}"><span class="ct">${fmtClock(d)}</span><span class="rel">${i===0?'next · ':''}${fmtRel(mins)}</span></div>`;
      }).join('');
    }

    // special route cards
    document.querySelectorAll('.rcard').forEach(card=>{
      const bus=card.dataset.bus, cfg=BUS[bus], dir=specialDir;
      card.querySelector('[data-route]').textContent = dir==='from' ? ('IITH → '+cfg.name) : (cfg.name+' → IITH');
      const up=nextFixed(cfg.data[dir], 6, cfg.weekdays);
      const first=up[0];
      const tEl=card.querySelector('[data-time]'), rEl=card.querySelector('[data-rel]');
      if(first){ const mins=minsUntil(first); tEl.textContent=fmtWhenShort(first); rEl.textContent = sameDay(first) ? fmtRel(mins) : ''; }
      else { tEl.textContent='—'; rEl.textContent=''; }
      card.querySelector('[data-upcoming]').innerHTML = up.map((d,i)=>{
        const mins=minsUntil(d);
        let rel = sameDay(d) ? fmtRel(mins) : '';
        if(i===0) rel = rel ? 'next · '+rel : 'next';
        return `<div class="srow ${i===0?'next':''}"><span class="ct">${fmtWhen(d)}</span><span class="rel">${rel}</span></div>`;
      }).join('');
      card.querySelector('[data-livelink]').setAttribute('href', card.dataset.live);
      const fareEl=card.querySelector('[data-fare]');
      if(fareEl) fareEl.textContent = '₹'+(dir==='from'?cfg.fareFrom:cfg.fareTo);
      card.querySelector('[data-extra]').innerHTML = extraHTML(bus, dir);
    });
  }

  async function startCheckout(buyBtn){
    const route = buyBtn.closest('.rcard').dataset.bus;
    const orig = buyBtn.textContent;
    buyBtn.disabled = true; buyBtn.textContent = '…';
    try {
      const orderRes = await fetch('/api/create-order.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({route})
      });
      const order = await orderRes.json();
      if(!orderRes.ok) throw new Error(order.error || 'Could not start payment');

      const rzp = new Razorpay({
        key: order.key_id, amount: order.amount, currency: order.currency, order_id: order.order_id,
        name: 'IITH Sanchari', description: order.route_name + ' bus ticket',
        theme: {color: '#e8491f'},
        handler: async function(resp){
          try {
            const vRes = await fetch('/api/verify-payment.php', {
              method: 'POST', headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({
                razorpay_order_id: resp.razorpay_order_id,
                razorpay_payment_id: resp.razorpay_payment_id,
                razorpay_signature: resp.razorpay_signature
              })
            });
            const vData = await vRes.json();
            alert(vData.verified
              ? 'Payment successful! Ticket issuance is coming next — payment ID '+vData.payment_id+'.'
              : 'Payment could not be verified. If money was deducted, contact support.');
          } catch(err){
            alert('Payment went through but verification couldn’t reach the server. Contact support with payment ID '+resp.razorpay_payment_id+'.');
          }
        }
      });
      rzp.on('payment.failed', resp=>{
        alert('Payment failed: '+(resp.error && resp.error.description ? resp.error.description : 'please try again.'));
      });
      rzp.open();
    } catch(err){
      alert(err.message || 'Could not start payment. Please try again.');
    } finally {
      buyBtn.disabled = false; buyBtn.textContent = orig;
    }
  }

  function wireHome(){
    document.querySelectorAll('.dir').forEach(row=>{
      row.addEventListener('click',()=>{
        document.querySelectorAll('.dir').forEach(r=>r.classList.remove('sel'));
        row.classList.add('sel'); schedDir=row.dataset.dir;
        document.querySelectorAll('.pill').forEach(p=>p.classList.toggle('on',p.dataset.sdir===schedDir));
        renderHome();
      });
    });
    const sb=document.getElementById('schedBtn'), sp=document.getElementById('schedPanel');
    if(sb) sb.addEventListener('click',()=>{ sb.classList.toggle('open'); sp.classList.toggle('open'); });
    document.querySelectorAll('.pill').forEach(p=>{
      p.addEventListener('click',()=>{
        document.querySelectorAll('.pill').forEach(x=>x.classList.remove('on')); p.classList.add('on'); schedDir=p.dataset.sdir;
        document.querySelectorAll('.dir').forEach(r=>r.classList.toggle('sel',r.dataset.dir===schedDir)); renderHome();
      });
    });
    document.querySelectorAll('.rcard [data-expander]').forEach(el=>{
      el.addEventListener('click',()=> el.closest('.rcard').classList.toggle('open'));
      el.addEventListener('keydown',e=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); el.closest('.rcard').classList.toggle('open'); }});
    });
    document.querySelectorAll('.rc-live').forEach(a=> a.addEventListener('click',e=> e.stopPropagation()));
    document.querySelectorAll('.rc-buy').forEach(b=> b.addEventListener('click',e=>{
      e.stopPropagation();
      startCheckout(b);
    }));
    const bell=document.querySelector('.bell');
    if(bell) bell.addEventListener('click',()=> alert('Notifications & alerts are coming soon.'));
    document.querySelectorAll('#specialToggle button').forEach(btn=>{
      btn.addEventListener('click',()=>{
        document.querySelectorAll('#specialToggle button').forEach(b=>b.classList.remove('on'));
        btn.classList.add('on'); specialDir=btn.dataset.tdir; renderHome();
      });
    });
    renderHome();
  }

  wireHome();
  setInterval(renderHome, 15000);
})();
