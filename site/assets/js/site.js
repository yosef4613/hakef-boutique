/* הכיף שבאפיה — Noir 2.0
   כללי התנועה (מהסרטון):
   1. כל אנימציה עושה עבודה אחת: מראה מוצר / מובילה את העין / מראה מה קרה.
   2. דבר אחד זז בכל מסך.
   3. המילים והכפתור במסך הראשון לא מחכים לשום אנימציה. אין שם אנימציה בכלל.
   4. יש מתג כיבוי, וברירת המחדל מכבדת את "הפחתת תנועה" במכשיר.
   בלי JavaScript כל התוכן גלוי ועובד. */
(function () {
  'use strict';

  var root = document.documentElement;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var motionOn = function () { return root.dataset.motion !== 'off'; };
  var BUSINESS_WA = '972509153197';
  var BUSINESS_MAIL = 'r0509153197@gmail.com';

  /* ---------- הגדרות המתכנן: לעדכן לפי הניסיון של העסק ---------- */
  var PLAN = {
    perGuest: { after: 3, main: 6 },   // יחידות לאורח: קינוח אחרי ארוחה / כיבוד מרכזי
    cakeFromGuests: 50,                 // מכמה אורחים ממליצים להוסיף עוגה מרכזית
    mix: [
      { label: 'טארטלטים ופאים', share: 0.30, img: 'assets/img/st-tartlets-480.webp' },
      { label: 'כוסות קינוח', share: 0.20, img: 'assets/img/st-verrines-480.webp' },
      { label: 'מוסים מעוצבים', share: 0.20, img: 'assets/img/st-mousse-480.webp' },
      { label: 'מקרונים, דונאטס וביסים', share: 0.30, img: 'assets/img/st-macarons-480.webp' }
    ]
  };

  /* ---------- מתג תנועה ---------- */
  function syncMotionButtons() {
    var on = motionOn();
    $$('[data-motion-toggle]').forEach(function (b) {
      b.setAttribute('aria-pressed', on ? 'false' : 'true');
      var l = $('.motion-toggle__label', b);
      if (l) l.textContent = on ? 'תנועה: פועלת' : 'תנועה: כבויה';
      b.setAttribute('aria-label', on ? 'כיבוי אנימציות' : 'הפעלת אנימציות');
    });
  }
  $$('[data-motion-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      root.dataset.motion = motionOn() ? 'off' : 'on';
      try { localStorage.setItem('hakef-motion', root.dataset.motion); } catch (e) {}
      syncMotionButtons();
      setupCakes();
      onScroll();
    });
  });
  syncMotionButtons();

  /* ---------- כותרת עליונה + תפריט בטלפון ---------- */
  var header = $('.site-header');
  var menuBtn = $('[data-menu-btn]');
  if (menuBtn) {
    menuBtn.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    $$('.main-nav a').forEach(function (a) {
      a.addEventListener('click', function () {
        document.body.classList.remove('nav-open');
        menuBtn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ---------- כפתורים צפים (פס בטלפון, וואטסאפ צף במחשב) ----------
     מופיעים רק אחרי שהכפתור הראשי של העמוד יצא מהמסך, כדי שבמסך הראשון תהיה פעולה בולטת אחת (כלל "כפתור אחד").
     בעמוד בלי כפתור ראשי הם מופיעים מיד. ליד המתכנן הם נעלמים, כי שם יש כבר כפתור שליחה. */
  var mobileCta = $('[data-mobile-cta]');
  var waFloat = $('.wa-float');
  var mainAction = $('.hero__action') || $('.page-head .btn') || $('.product__cta') || $('.contact-card .btn');
  var planSection = $('#plan');
  var mainGone = !mainAction, planVisible = false;
  function setFloating(el, show) {
    if (!el) return;
    el.classList.toggle('is-visible', show);
    el.setAttribute('aria-hidden', show ? 'false' : 'true');
    (el.tagName === 'A' ? [el] : $$('a', el)).forEach(function (a) { a.tabIndex = show ? 0 : -1; });
  }
  function updateFloating() {
    var show = mainGone && !planVisible;
    setFloating(mobileCta, show);
    setFloating(waFloat, show);
  }
  if ('IntersectionObserver' in window) {
    if (mainAction) new IntersectionObserver(function (e) { mainGone = !e[0].isIntersecting && e[0].boundingClientRect.top < 0; updateFloating(); }).observe(mainAction);
    if (planSection) new IntersectionObserver(function (e) { planVisible = e[0].isIntersecting; updateFloating(); }, { rootMargin: '0px 0px -30% 0px' }).observe(planSection);
  } else {
    mainGone = true;
  }
  updateFloating();

  /* ---------- 1) הבר: כרטיסים נערמים. הכרטיס שמתכסה נסוג מעט ---------- */
  var stackCards = $$('[data-stack] .stack__card');
  function updateStack() {
    if (!motionOn()) { stackCards.forEach(function (c) { c.style.removeProperty('--cover'); }); return; }
    for (var i = 0; i < stackCards.length - 1; i++) {
      var el = stackCards[i];
      var cur = el.getBoundingClientRect();
      var next = stackCards[i + 1].getBoundingClientRect();
      // רק כרטיס שכבר נעוץ למעלה מתכסה ונסוג. כך בכל רגע זז דבר אחד: הכרטיס שנכנס.
      var stuck = cur.top <= (parseFloat(getComputedStyle(el).top) || 0) + 1;
      var nextStuck = next.top <= (parseFloat(getComputedStyle(stackCards[i + 1]).top) || 0) + 1;
      // כרטיס שכבר כוסה לגמרי ננעל, כדי שבסוף הערימה לא ישתנו כמה כרטיסים יחד
      var p = !stuck ? 0 : nextStuck ? 1 : 1 - (next.top - cur.top) / Math.max(cur.height, 1);
      p = Math.min(1, Math.max(0, p));
      el.style.setProperty('--cover', p.toFixed(3));
    }
  }

  /* ---------- 2) עוגות: גלילה אנכית מזיזה רצועה אופקית (במחשב) ---------- */
  var cakes = $('[data-hscroll]');
  var track = cakes ? $('[data-track]', cakes) : null;
  var cakesDistance = 0;
  function setupCakes() {
    if (!cakes || !track) return;
    var wide = window.matchMedia('(min-width: 901px)').matches;
    if (!wide || !motionOn()) {
      cakes.classList.remove('is-pinned');
      cakes.style.height = '';
      track.style.removeProperty('--x');
      return;
    }
    cakes.classList.add('is-pinned');
    cakesDistance = Math.max(0, track.scrollWidth - window.innerWidth);
    cakes.style.height = (window.innerHeight + cakesDistance) + 'px';
  }
  function updateCakes() {
    if (!cakes || !cakes.classList.contains('is-pinned')) return;
    var r = cakes.getBoundingClientRect();
    var total = r.height - window.innerHeight;
    var p = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;
    // בעברית הרצועה נמשכת שמאלה, ולכן מזיזים אותה ימינה כדי לחשוף את ההמשך
    track.style.setProperty('--x', (p * cakesDistance).toFixed(1) + 'px');
  }

  /* ---------- 3) איך מזמינים: הקו מתמלא ומדליק את הצעד הבא ---------- */
  var steps = $('[data-steps]');
  var stepItems = steps ? $$('.step', steps) : [];
  function updateSteps() {
    if (!steps) return;
    if (!motionOn()) { steps.style.setProperty('--p', 1); stepItems.forEach(function (s) { s.classList.add('is-on'); }); return; }
    var r = steps.getBoundingClientRect();
    var vh = window.innerHeight;
    var p = (vh * 0.62 - r.top) / Math.max(r.height, 1);
    p = Math.min(1, Math.max(0, p));
    steps.style.setProperty('--p', p.toFixed(3));
    stepItems.forEach(function (s) {
      var sr = s.getBoundingClientRect();
      s.classList.toggle('is-on', sr.top + 14 < vh * 0.62);
    });
  }

  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      ticking = false;
      if (header) header.classList.toggle('is-solid', window.scrollY > 24);
      updateStack();
      updateCakes();
      updateSteps();
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', function () { setupCakes(); onScroll(); });
  window.addEventListener('load', function () { setupCakes(); onScroll(); });
  setupCakes();
  onScroll();

  /* ---------- 4) המתכנן: התוצאה מתעדכנת מיד (תנועה שמראה "מה קרה") ---------- */
  var planForm = $('#plan-form');
  var guests = $('#guests');
  var guestsOut = $('#guests-out');
  var totalEl = $('[data-total]');
  var totalSub = $('[data-total-sub]');
  var mixEl = $('[data-mix]');
  var cakeEl = $('[data-cake]');
  var shownTotal = 0, countRaf = null;
  var lastPlan = null;

  function val(name) { var el = planForm && planForm.querySelector('input[name="' + name + '"]:checked'); return el ? el.value : ''; }
  function round10(n) { return Math.max(10, Math.round(n / 10) * 10); }

  if (mixEl) {
    mixEl.innerHTML = PLAN.mix.map(function (m) {
      return '<li><img src="' + m.img + '" alt="" width="44" height="44" loading="lazy"><span class="mix__label">' + m.label + '</span><span class="mix__count">0</span><span class="mix__bar"><i></i></span></li>';
    }).join('');
  }

  function countTo(target) {
    if (!totalEl) return;
    if (countRaf) cancelAnimationFrame(countRaf);
    if (!motionOn()) { shownTotal = target; totalEl.textContent = target; return; }
    var from = shownTotal, start = null, dur = 650;
    function step(t) {
      if (!start) start = t;
      var k = Math.min(1, (t - start) / dur);
      var e = 1 - Math.pow(1 - k, 3);
      shownTotal = Math.round(from + (target - from) * e);
      totalEl.textContent = shownTotal;
      if (k < 1) countRaf = requestAnimationFrame(step);
    }
    countRaf = requestAnimationFrame(step);
  }

  function updatePlan() {
    if (!planForm) return;
    var g = parseInt(guests.value, 10) || 0;
    var role = val('role') || 'after';
    var total = round10(g * PLAN.perGuest[role]);
    if (guestsOut) guestsOut.textContent = g >= 400 ? '400+' : g;
    countTo(total);
    if (totalSub) totalSub.textContent = 'לכ־' + g + ' אורחים, ' + (role === 'main' ? 'כשהבר הוא הכיבוד המרכזי' : 'כקינוח אחרי ארוחה');
    var rows = mixEl ? $$('li', mixEl) : [];
    var maxShare = Math.max.apply(null, PLAN.mix.map(function (m) { return m.share; }));
    var parts = [];
    PLAN.mix.forEach(function (m, i) {
      var n = round10(total * m.share);
      parts.push(m.label + ': כ־' + n);
      if (rows[i]) {
        $('.mix__count', rows[i]).textContent = 'כ־' + n;
        $('.mix__bar i', rows[i]).style.setProperty('--w', Math.round(m.share / maxShare * 100) + '%');
      }
    });
    var withCake = g >= PLAN.cakeFromGuests;
    if (cakeEl) cakeEl.hidden = !withCake;
    lastPlan = {
      event: val('event'), guests: g, role: role === 'main' ? 'הכיבוד המרכזי' : 'קינוח אחרי ארוחה',
      kosher: val('kosher'), delivery: val('delivery'), total: total, parts: parts, cake: withCake
    };
  }
  if (planForm) {
    planForm.addEventListener('input', updatePlan);
    planForm.addEventListener('change', updatePlan);
    planForm.addEventListener('submit', function (e) { e.preventDefault(); });
    updatePlan();
  }

  /* ---------- קישורי "הזמנה": מסמנים מראש מה מזמינים ---------- */
  var ORDER_MAP = { bar: 'בר פטיפורים מעוצב', cake: 'עוגה מעוצבת' };
  function applyOrder(kind) {
    var box = document.querySelector('#request-form input[name="what"][value="' + ORDER_MAP[kind] + '"]');
    if (box) box.checked = true;
  }
  $$('[data-order]').forEach(function (a) {
    a.addEventListener('click', function () {
      var kind = a.getAttribute('data-order');
      applyOrder(kind);
      try { sessionStorage.setItem('hakef-order', kind); } catch (e) {}
    });
  });
  try {
    var pending = sessionStorage.getItem('hakef-order');
    if (pending && $('#request-form')) { applyOrder(pending); sessionStorage.removeItem('hakef-order'); }
  } catch (e) {}

  /* ---------- שליחת הבקשה: וואטסאפ (ובמייל כחלופה) ---------- */
  var req = $('#request-form');
  function buildMessage() {
    var f = req.elements;
    var what = $$('input[name="what"]:checked', req).map(function (i) { return i.value; }).join(', ');
    var p = lastPlan || {};
    var lines = [
      'שלום, אשמח לקבל הצעת מחיר.',
      '',
      'שם: ' + f.name.value.trim(),
      'טלפון: ' + f.phone.value.trim(),
      'סוג האירוע: ' + (p.event || ''),
      f.date.value ? 'תאריך: ' + f.date.value.split('-').reverse().join('.') : '',
      f.place.value.trim() ? 'מקום: ' + f.place.value.trim() : '',
      'מספר אורחים: כ־' + (p.guests || ''),
      what ? 'מה מזמינים: ' + what : '',
      'תפקיד הבר: ' + (p.role || '') + ' | ' + (p.kosher || '') + ' | ' + (p.delivery || ''),
      'הערכה מהמתכנן: כ־' + (p.total || '') + ' יחידות (' + (p.parts || []).join('; ') + ')' + (p.cake ? ' + עוגה מרכזית' : ''),
      f.notes.value.trim() ? 'הערות: ' + f.notes.value.trim() : ''
    ];
    return lines.filter(function (l, i) { return l !== '' || i === 1; }).join('\n');
  }
  function validPhone(v) { var d = v.replace(/[^\d+]/g, '').replace(/^\+972/, '0'); return /^0\d{8,9}$/.test(d); }
  function check(field, ok) { var w = field.closest('.field'); if (w) w.classList.toggle('is-invalid', !ok); return ok; }
  if (req) {
    var mailLink = $('[data-mail]', req);
    if (mailLink) {
      mailLink.addEventListener('click', function () {
        mailLink.href = 'mailto:' + BUSINESS_MAIL + '?subject=' + encodeURIComponent('בקשת הצעת מחיר מהאתר') + '&body=' + encodeURIComponent(buildMessage());
      });
    }
    req.addEventListener('submit', function (e) {
      e.preventDefault();
      var okName = check(req.elements.name, req.elements.name.value.trim().length > 1);
      var okPhone = check(req.elements.phone, validPhone(req.elements.phone.value));
      if (!okName || !okPhone) { (okName ? req.elements.phone : req.elements.name).focus(); return; }
      var url = 'https://wa.me/' + BUSINESS_WA + '?text=' + encodeURIComponent(buildMessage());
      var done = $('[data-done]', req);
      var fb = $('[data-wa-fallback]', req);
      if (fb) fb.href = url;
      window.open(url, '_blank', 'noopener');
      if (done) { done.hidden = false; done.focus({ preventScroll: false }); }
    });
    $$('#r-name, #r-phone').forEach(function (inp) {
      inp.addEventListener('input', function () { var w = inp.closest('.field'); if (w) w.classList.remove('is-invalid'); });
    });
  }

  /* ---------- גלריה מוגדלת ---------- */
  var lb = $('[data-lightbox]');
  var lbImg = lb ? $('[data-lightbox-img]', lb) : null;
  var gItems = $$('[data-gallery] .g-item');
  var lbIndex = 0;
  function showLb(i) {
    lbIndex = (i + gItems.length) % gItems.length;
    var item = gItems[lbIndex];
    var img = $('img', item);
    lbImg.src = item.getAttribute('data-full');
    lbImg.alt = img ? img.alt : '';
  }
  if (lb && lbImg && typeof lb.showModal === 'function') {
    gItems.forEach(function (item, i) {
      item.addEventListener('click', function () { showLb(i); lb.showModal(); });
    });
    lb.addEventListener('click', function (e) {
      var act = e.target.getAttribute && e.target.getAttribute('data-lb');
      if (act === 'close' || e.target === lb) lb.close();
      if (act === 'next') showLb(lbIndex + 1);
      if (act === 'prev') showLb(lbIndex - 1);
    });
    lb.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') showLb(lbIndex + 1);
      if (e.key === 'ArrowRight') showLb(lbIndex - 1);
    });
  }

  /* הדפסת מתכון */
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  var y = $('[data-year]');
  if (y) y.textContent = new Date().getFullYear();
})();
