/* Shared, build-free helpers. All clocks use the campus timezone. */
window.Sanchari = (() => {
  const tz = 'Asia/Kolkata';
  const date = (value, options={}) => new Intl.DateTimeFormat('en-IN', {timeZone:tz,...options}).format(new Date(value));
  const dayKey = value => date(value,{year:'numeric',month:'2-digit',day:'2-digit'}).split('/').reverse().join('-');
  const clock = value => date(value,{hour:'numeric',minute:'2-digit',hour12:true});
  const when = value => date(value,{weekday:'short',day:'numeric',month:'short'})+' · '+clock(value);
  const escape = value => String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function departures(cfg, direction, count=6, now=Date.now(), holidays=[]) {
    const out=[];const today=dayKey(now);
    for(let off=0;off<15&&out.length<count;off++){
      const anchor=new Date(today+'T00:00:00+05:30').getTime()+off*86400000;
      const key=dayKey(anchor);const day=date(anchor,{weekday:'short'});
      if(holidays.includes(key))continue;
      const weekend=['Sat','Sun'].includes(day);
      const times=cfg.timetable?cfg.timetable[weekend?'weekend':'weekday']?.[direction]||[]:weekend&&cfg.weekdays?[]:cfg[direction]||[];
      for(const hm of times){const ts=new Date(key+'T'+hm+':00+05:30').getTime();if(ts>now){out.push(ts);if(out.length===count)break;}}
    }return out;
  }
  function boarding(cfg,direction,departure){
    const weekend=departure&&['Sat','Sun'].includes(date(departure,{weekday:'short'}));
    return weekend&&cfg['boarding_'+direction+'_weekend']||cfg['boarding_'+direction];
  }
  async function api(path, body){
    const r=await fetch('/api/'+path,{method:body?'POST':'GET',headers:body?{'Content-Type':'application/json'}:{},body:body?JSON.stringify(body):undefined,cache:'no-store',signal:AbortSignal.timeout(25000)});
    let d;try{d=await r.json()}catch{throw new Error('The service is temporarily unavailable. Please try again.');}
    if(!r.ok){const e=new Error(d.error||'Could not complete this request.');e.status=r.status;throw e;}return d;
  }
  const storageKey='sanchari.bookings.v1';
  function saved(){try{const v=JSON.parse(localStorage.getItem(storageKey)||'[]');return Array.isArray(v)?v.filter(x=>x&&typeof x.id==='string'&&typeof x.key==='string'):[]}catch{return []}}
  function save(item){const all=saved().filter(x=>x.id!==item.id);all.unshift(item);try{localStorage.setItem(storageKey,JSON.stringify(all.slice(0,30)));return true}catch{return false}}
  function recovery(item){return 'payment.html#id='+encodeURIComponent(item.id)+'&key='+encodeURIComponent(item.key)}
  return {date,dayKey,clock,when,escape,departures,boarding,api,saved,save,recovery};
})();
