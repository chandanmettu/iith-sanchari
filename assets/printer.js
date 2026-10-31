/* Boarding-pass printer. The pass feeds out of a slot in short mechanical steps,
   then can be pulled sideways (or dragged with a mouse) to tear it off.
   Purely visual: the pass reads and scans the same whether or not it is torn. */
window.SanchariPrinter = (() => {
  const calm = matchMedia('(prefers-reduced-motion: reduce)');
  const STEPS = 8, STEP_MS = 230, DELAY = 380, DROP = 38, TEAR_AT = 64; // DROP matches .is-torn .tk-feed padding
  const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
  const ease = t => t < .5 ? 2*t*t : 1 - (2 - 2*t)**2/2;
  let printer, paper, card, shade, remnant, observer, state = 'idle', fed = 0, skip = () => {}, dragged = false;
  let grip = null, loop = 0, last = 0;
  const pos = {x:0, y:0, r:0, vx:0, vy:0, vr:0}, goal = {x:0, y:0, r:0};

  // One torn edge per page: [fraction of width, depth in px]. The slot keeps the mirror image.
  const tear = (() => {
    const out = [[0, 2 + Math.random()*3]], phase = Math.random()*6;
    for (let f = 0; f < 1;) {
      f = Math.min(1, f + .012 + Math.random()*.02);
      out.push([f, clamp(3.6 + 1.8*Math.sin(f*9 + phase) + (Math.random() - .5)*3, .5, 7.5)]);
    }
    return out;
  })();

  function shapeCard() {
    const w = card.offsetWidth, h = card.offsetHeight;
    if (!w) return;
    const pts = state === 'torn' ? tear.map(([f, y]) => `${(f*100).toFixed(2)}% ${y.toFixed(1)}px`) : ['0 0', '100% 0'];
    const n = Math.max(8, Math.round(w/15)), s = w/n, r = s/2, d = Math.min(r, 6);
    pts.push(`100% ${h - d}px`);
    for (let i = n - 1; i >= 0; i--) {
      for (let k = 0; k <= 8; k++) {
        const a = Math.PI*k/8;
        pts.push(`${(s*i + r + r*Math.cos(a)).toFixed(1)}px ${(h - d + d*Math.sin(a)).toFixed(1)}px`);
      }
    }
    card.style.clipPath = `polygon(${pts.join(',')})`;
  }

  function feed(out) {
    const h = card.offsetHeight;
    fed = out;
    paper.style.transform = `translateY(${(out - h).toFixed(1)}px)`;
    shade.style.transform = `translateY(${(h - out).toFixed(1)}px)`;
  }

  function print() {
    state = 'printing';
    printer.classList.add('is-printing');
    if (calm.matches) return printed();
    feed(0);
    const t0 = performance.now();
    let fast = null, out = 0;
    skip = () => { fast = fast || {t: performance.now(), from: fed}; };
    requestAnimationFrame(function frame(now) {
      const h = card.offsetHeight;
      if (fast) {
        const p = Math.min(1, (now - fast.t)/260);
        out = fast.from + (h - fast.from)*(1 - (1 - p)**3);
      } else {
        const p = (now - t0 - DELAY)/STEP_MS, i = Math.floor(p);
        out = p < 0 ? 0 : i >= STEPS ? h : h*(i + ease(Math.min(1, (p - i)/.6)))/STEPS;
      }
      if (out >= h - .01) return printed();
      feed(out);
      requestAnimationFrame(frame);
    });
  }

  function printed() {
    state = 'attached';
    skip = () => {};
    printer.classList.remove('is-printing');
    shade.style.transform = '';
    place();
  }

  function place() {
    paper.style.transform = `translate(${pos.x.toFixed(2)}px,${pos.y.toFixed(2)}px) rotate(${pos.r.toFixed(3)}deg)`;
  }

  // A small damped spring: stiff while held, bouncier when let go.
  function step(now) {
    const dt = Math.min(1.5, last ? (now - last)/16.67 : 1), [k, d] = grip ? [.32, .55] : [.11, .32];
    let moving = !!grip;
    last = now;
    for (const a of ['x', 'y', 'r']) {
      const v = 'v' + a;
      pos[v] += ((goal[a] - pos[a])*k - pos[v]*d)*dt;
      pos[a] += pos[v]*dt;
      if (Math.abs(pos[v]) > .01 || Math.abs(goal[a] - pos[a]) > .01) moving = true;
    }
    place();
    loop = moving ? requestAnimationFrame(step) : 0;
    if (!loop) last = 0;
  }

  function kick() {
    if (calm.matches && !grip) { Object.assign(pos, goal, {vx:0, vy:0, vr:0}); return place(); }
    if (!loop) loop = requestAnimationFrame(step);
  }

  function tearOff() {
    state = 'torn';
    printer.classList.add('is-torn');
    shapeCard();
    remnant.style.clipPath = `polygon(0 0,100% 0,${tear.slice().reverse().map(([f, y]) => `${(f*100).toFixed(2)}% ${(y + 5).toFixed(1)}px`).join(',')})`;
    if (navigator.vibrate && navigator.userActivation?.hasBeenActive) navigator.vibrate(12);
  }

  function down(e) {
    dragged = false;
    if (state === 'printing') return skip();
    if (e.button > 0 || grip) return;
    grip = {id: e.pointerId, x0: e.clientX, y0: e.clientY, px: e.clientX, vx: 0, moved: false, touch: e.pointerType !== 'mouse'};
    if (!grip.touch) e.preventDefault();
  }

  function move(e) {
    if (!grip || e.pointerId !== grip.id) return;
    const dx = e.clientX - grip.x0, dy = e.clientY - grip.y0;
    if (!grip.moved) {
      if (Math.hypot(dx, dy) < 6) return;
      grip.moved = true;
      try { paper.setPointerCapture(e.pointerId); } catch {}
      printer.classList.add('is-pulling');
    }
    grip.vx = grip.vx*.6 + (e.clientX - grip.px)*.4;
    grip.px = e.clientX;
    if (state === 'attached') {
      Object.assign(goal, {x: clamp(dx*.08, -7, 7), y: clamp(dy*.1, 0, 8), r: clamp(dx*.045, -4.5, 4.5)});
      // On touch an up/down drag scrolls the page, so only a sideways pull tears.
      if (Math.abs(dx) > TEAR_AT || (!grip.touch && dy > TEAR_AT)) tearOff();
    } else {
      Object.assign(goal, {x: dx, y: DROP + dy, r: clamp(dx*.03 + grip.vx*.5, -12, 12)});
    }
    kick();
  }

  function up(e) {
    if (!grip || e.pointerId !== grip.id) return;
    dragged = grip.moved;
    grip = null;
    printer.classList.remove('is-pulling');
    Object.assign(goal, {x: 0, y: state === 'torn' ? DROP : 0, r: 0});
    kick();
  }

  function adopt(html) {
    paper.innerHTML = html;
    card = paper.querySelector('.ticket');
    shade = document.createElement('i');
    shade.className = 'tk-shade';
    shade.setAttribute('aria-hidden', 'true');
    card.append(shade);
    shapeCard();
    if (state === 'printing') feed(fed);
    observer.disconnect();
    observer.observe(card);
  }

  // First call builds the printer and prints; later refreshes swap the pass in place.
  function render(host, cardHTML, noteHTML) {
    if (printer && host.contains(printer)) {
      adopt(cardHTML);
      host.querySelector('.tk-note').outerHTML = noteHTML;
      return;
    }
    host.innerHTML = `<div class="tk-printer"><div class="tk-slot" aria-hidden="true"></div><div class="tk-feed"><i class="tk-remnant" aria-hidden="true"></i><div class="tk-paper"></div></div></div>${noteHTML}`;
    printer = host.querySelector('.tk-printer');
    paper = printer.querySelector('.tk-paper');
    remnant = printer.querySelector('.tk-remnant');
    observer = observer || new ResizeObserver(() => card && shapeCard());
    adopt(cardHTML);
    paper.addEventListener('pointerdown', down);
    paper.addEventListener('pointermove', move);
    paper.addEventListener('pointerup', up);
    paper.addEventListener('pointercancel', up);
    paper.addEventListener('click', e => { if (dragged) { e.preventDefault(); e.stopPropagation(); dragged = false; } }, true);
    print();
  }

  return {render};
})();
