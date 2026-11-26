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
      // התווית לקורא מסך מתחילה בטקסט שרואים על הכפתור, ואחריו ההסבר
      b.setAttribute('aria-label', on ? 'תנועה: פועלת. לחיצה מכבה את האנימציות' : 'תנועה: כבויה. לחיצה מפעילה את האנימציות');
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
  function closeMenu(focusBtn) {
    document.body.classList.remove('nav-open');
    menuBtn.setAttribute('aria-expanded', 'false');
    if (focusBtn) menuBtn.focus();
  }
  function menuItems() { return $$('#nav-list a, #nav-list button'); }
  if (menuBtn) {
    menuBtn.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { var first = menuItems()[0]; if (first) first.focus(); }
    });
    $$('.main-nav a').forEach(function (a) {
      a.addEventListener('click', function () { closeMenu(false); });
    });
    document.addEventListener('keydown', function (e) {
      if (!document.body.classList.contains('nav-open')) return;
      if (e.key === 'Escape') { closeMenu(true); return; }
      if (e.key !== 'Tab') return;
      // הפוקוס מסתובב בין כפתור התפריט לקישורים שבו, ולא יוצא לעמוד שמתחת
      var items = [menuBtn].concat(menuItems());
      var i = items.indexOf(document.activeElement);
      var next = e.shiftKey ? (i <= 0 ? items.length - 1 : i - 1) : (i === -1 || i === items.length - 1 ? 0 : i + 1);
      e.preventDefault();
      items[next].focus();
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
  /* דף הבית: הלוגו הגדול במסך הראשון. הלוגו הקטן בכותרת מופיע רק כשהגדול כבר לא נראה, כדי שלא יהיו שניים צמודים */
  var heroLogo = $('.hero__logo');
  if (heroLogo && header && 'IntersectionObserver' in window) {
    header.classList.add('has-hero-logo');
    new IntersectionObserver(function (e) { header.classList.toggle('show-logo', !e[0].isIntersecting); },
      { rootMargin: '-' + (header.offsetHeight || 80) + 'px 0px 0px 0px' }).observe(heroLogo);
  }
  if (!mainAction) {
    var checkScrolled = function () { mainGone = window.scrollY > window.innerHeight * 0.8; updateFloating(); };
    window.addEventListener('scroll', checkScrolled, { passive: true });
    mainGone = false;
    checkScrolled();
  }
  if ('IntersectionObserver' in window) {
    if (mainAction) new IntersectionObserver(function (e) { mainGone = !e[0].isIntersecting && e[0].boundingClientRect.top < 0; updateFloating(); }).observe(mainAction);
    if (planSection) new IntersectionObserver(function (e) { planVisible = e[0].isIntersecting; updateFloating(); }, { rootMargin: '0px 0px -30% 0px' }).observe(planSection);
  } else if (mainAction) {
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
  var planTouched = false;
  var planStatus = $('[data-plan-status]');
  var statusTimer = null;
  var planBar = $('[data-plan-bar]');

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
    if (planBar) {
      $('[data-bar-total]', planBar).textContent = total;
      $('[data-bar-guests]', planBar).textContent = g >= 400 ? '400+' : g;
      $('[data-bar-cake]', planBar).hidden = !withCake;
    }
    if (planStatus) {
      clearTimeout(statusTimer);
      statusTimer = setTimeout(function () {
        planStatus.textContent = 'ההמלצה: כ־' + total + ' יחידות לכ־' + g + ' אורחים' + (withCake ? ', ועוגה מרכזית' : '') + '.';
      }, 700);
    }
  }
  if (planForm) {
    var touch = function () { planTouched = true; };
    planForm.addEventListener('input', touch);
    planForm.addEventListener('change', touch);
    planForm.addEventListener('input', updatePlan);
    planForm.addEventListener('change', updatePlan);
    planForm.addEventListener('submit', function (e) { e.preventDefault(); });
    updatePlan();
  }

  if (planBar && planForm && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (e) {
      var show = e[0].isIntersecting && window.matchMedia('(max-width: 900px)').matches;
      planBar.classList.toggle('is-visible', show);
      planBar.setAttribute('aria-hidden', show ? 'false' : 'true');
      $('a', planBar).tabIndex = show ? 0 : -1;
    }, { rootMargin: '-35% 0px -20% 0px' }).observe(planForm);
  }

  /* ---------- קישורי "הזמנה": מסמנים מראש מה מזמינים ---------- */
  var ORDER_MAP = { bar: 'בר פטיפורים מעוצב', cake: 'עוגה מעוצבת' };
  function applyOrder(kind) {
    var box = document.querySelector('#request-form input[name="what"][value="' + ORDER_MAP[kind] + '"]');
    if (box) box.checked = true;
    if (kind === 'cake') {
      var bar = document.querySelector('#request-form input[name="what"][value="' + ORDER_MAP.bar + '"]');
      if (bar) bar.checked = false;
    }
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
      f.phone.value.trim() ? 'טלפון: ' + f.phone.value.trim() : '',
      p.event ? 'סוג האירוע: ' + p.event : '',
      f.date.value ? 'תאריך: ' + f.date.value.split('-').reverse().join('.') : '',
      f.place.value.trim() ? 'מקום: ' + f.place.value.trim() : '',
      what ? 'מה מזמינים: ' + what : '',
      planTouched ? 'מספר אורחים: כ־' + (p.guests || '') : '',
      planTouched ? 'תפקיד הבר: ' + (p.role || '') + ' | ' + (p.kosher || '') + ' | ' + (p.delivery || '') : '',
      planTouched ? 'הערכה מהמתכנן: כ־' + (p.total || '') + ' יחידות (' + (p.parts || []).join('; ') + ')' + (p.cake ? ' + עוגה מרכזית' : '') : '',
      f.notes.value.trim() ? 'הערות: ' + f.notes.value.trim() : ''
    ];
    return lines.filter(function (l, i) { return l !== '' || i === 1; }).join('\n');
  }
  function validPhone(v) {
    var d = v.replace(/[^\d+]/g, '');
    if (/^\+972/.test(d)) d = '0' + d.slice(4);
    return /^0\d{8,9}$/.test(d) || /^(\+|00)\d{8,15}$/.test(d);   // ישראלי, או מספר מחו"ל עם קידומת מדינה
  }
  function check(field, ok) {
    var w = field.closest('.field');
    if (w) w.classList.toggle('is-invalid', !ok);
    if (ok) field.removeAttribute('aria-invalid'); else field.setAttribute('aria-invalid', 'true');
    return ok;
  }
  if (req) {
    /* שליחה במייל: ישר לתיבה של העסק דרך השרת, בלי וואטסאפ ובלי תוכנת מייל */
    var mailBtn = $('[data-send-mail]', req);
    var mailStatus = $('[data-mail-status]', req);
    var phoneErr = $('#r-phone-err');
    var phoneErrText = phoneErr ? phoneErr.textContent : '';
    var shownAt = Date.now();
    function link(href, text) { var a = document.createElement('a'); a.className = 'text-link'; a.href = href; a.textContent = text; return a; }
    if (mailBtn) {
      mailBtn.addEventListener('click', function () {
        var okName = check(req.elements.name, req.elements.name.value.trim().length > 1);
        var phoneVal = req.elements.phone.value.trim();
        if (phoneErr) phoneErr.textContent = phoneVal ? phoneErrText : 'כדי לשלוח במייל צריך טלפון, כדי שנחזור אליכם';
        var okPhone = check(req.elements.phone, !!phoneVal && validPhone(phoneVal));
        if (!okName || !okPhone) { (okName ? req.elements.phone : req.elements.name).focus(); return; }
        mailBtn.disabled = true;
        if (mailStatus) mailStatus.textContent = 'שולחים…';
        fetch('/wp-json/hakef/v1/request', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name: req.elements.name.value.trim(), phone: phoneVal, message: buildMessage(), page: location.href, website: req.elements.website ? req.elements.website.value : '', t: Date.now() - shownAt })
        }).then(function (r) {
          return r.json().catch(function () { return {}; }).then(function (res) { if (!r.ok || !res.ok) throw new Error(res.error || r.status); });
        }).then(function () {
          if (mailStatus) mailStatus.textContent = '';
          var md = $('[data-mail-done]', req);
          if (md) { md.hidden = false; md.focus({ preventScroll: false }); }
        }).catch(function () {
          if (!mailStatus) return;
          mailStatus.textContent = '';
          mailStatus.append('לא הצלחנו לשלוח מכאן. אפשר ', link('mailto:' + BUSINESS_MAIL + '?subject=' + encodeURIComponent('בקשת הצעת מחיר מהאתר') + '&body=' + encodeURIComponent(buildMessage()), 'לשלוח מתוכנת המייל'), ' או להתקשר ל־', link('tel:0509153197', '050-915-3197'), '.');
        }).then(function () { mailBtn.disabled = false; });
      });
    }
    req.addEventListener('submit', function (e) {
      e.preventDefault();
      if (phoneErr) phoneErr.textContent = phoneErrText;
      var okName = check(req.elements.name, req.elements.name.value.trim().length > 1);
      var phoneVal = req.elements.phone.value.trim();
      var okPhone = check(req.elements.phone, !phoneVal || validPhone(phoneVal));
      if (!okName || !okPhone) { (okName ? req.elements.phone : req.elements.name).focus(); return; }
      var url = 'https://wa.me/' + BUSINESS_WA + '?text=' + encodeURIComponent(buildMessage());
      var done = $('[data-done]', req);
      var fb = $('[data-wa-fallback]', req);
      if (fb) fb.href = url;
      window.open(url, '_blank', 'noopener');
      if (done) { done.hidden = false; done.focus({ preventScroll: false }); }
    });
    $$('#r-name, #r-phone').forEach(function (inp) {
      inp.addEventListener('input', function () { var w = inp.closest('.field'); if (w) w.classList.remove('is-invalid'); inp.removeAttribute('aria-invalid'); });
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

  /* ---------- תגובות ורשימת תפוצה ----------
     הסקריפט מסמן שהטופס מולא בדפדפן אמיתי (תוכנות ספאם בדרך כלל לא מריצות אותו),
     ובודק לפני שליחה שהתגובה בעברית ובלי קישורים, כדי שלא יאבד מה שנכתב. */
  $$('form[data-human]').forEach(function (f) {
    var mark = function () { if (f.elements.hakef_ts && f.elements.hakef_js) f.elements.hakef_js.value = f.elements.hakef_ts.value; };
    f.addEventListener('input', mark);
    f.addEventListener('submit', function (e) {
      mark();
      var t = f.elements.comment;
      if (!t) return;
      var v = t.value.trim();
      var ok = /[֐-׿]/.test(v) && !/https?:\/\/|www\./i.test(v);
      if (!check(t, ok)) { e.preventDefault(); t.focus(); }
    });
    if (f.elements.comment) {
      f.elements.comment.addEventListener('input', function () { var w = f.elements.comment.closest('.field'); if (w) w.classList.remove('is-invalid'); f.elements.comment.removeAttribute('aria-invalid'); });
    }
  });

  /* הדפסת מתכון */
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  /* ---------- כניסה עם Google, ואזור ניהול למנהלים ----------
     הבדיקה מול השרת נעשית רק כשיש עוגיית hakef_user, כלומר רק אצל מי שהתחבר. */
  (function () {
    var here = location.pathname + location.search.replace(/([?&])login=[^&]*&?/, '$1').replace(/[?&]$/, '');
    $$('[data-login]').forEach(function (a) { a.href = '/wp-json/hakef/v1/google/start?r=' + encodeURIComponent(here); });
    var msg = { ok: 'התחברתם בהצלחה.', cancel: 'הכניסה בוטלה.', error: 'לא הצלחנו להתחבר עם Google. נסו שוב.', expired: 'עבר יותר מדי זמן. נסו להתחבר שוב.', off: 'הכניסה עם Google עוד לא פעילה.' };
    var m = location.search.match(/[?&]login=([a-z]+)/);
    if (m && msg[m[1]]) {
      var note = document.createElement('p');
      note.className = 'login-note'; note.setAttribute('role', 'status'); note.textContent = msg[m[1]];
      document.body.appendChild(note);
      setTimeout(function () { note.remove(); }, 5000);
      try { history.replaceState(null, '', here + location.hash); } catch (e) {}
    }
    if (!/(?:^|;\s*)hakef_user=1/.test(document.cookie)) return;
    fetch('/wp-admin/admin-ajax.php?action=hakef_me', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (me) {
        if (!me || !me.logged_in) return;
        $$('[data-login]').forEach(function (a) {
          a.href = me.logout;
          if (a.closest('.nav-login')) {
            a.textContent = 'יציאה (' + me.first + ')';
          } else {
            // בכותרת: "שלום, [שם]" ולידו "יציאה" קטן
            var who = document.createElement('span'), nm = document.createElement('strong');
            who.className = 'header-user'; who.title = me.name;
            nm.textContent = me.first;
            who.appendChild(document.createTextNode('שלום, ')); who.appendChild(nm);
            a.parentNode.insertBefore(who, a);
            a.textContent = 'יציאה';
            a.classList.add('is-out');
          }
          a.title = 'מחוברים בתור ' + me.name;
          a.removeAttribute('data-login');
        });
        var hdr = document.querySelector('.site-header');
        if (hdr) hdr.classList.add('is-signed');
        if (me.manager) $$('[data-admin]').forEach(function (a) { a.href = me.admin_url; a.hidden = false; });
        var rn = document.getElementById('r-name');
        if (rn && !rn.value) rn.value = me.name;
      })
      .catch(function () {});
  })();

  /* ---------- כלים לסוכני בינה מלאכותית בדפדפן (WebMCP) ----------
     סוכן שפועל בתוך הדפדפן יכול לקבל מהעמוד מחירים, הערכת כמויות ונוסח בקשה.
     שום כלי לא שולח הודעה: הלקוח שולח בעצמו. */
  (function () {
    var mc = navigator.modelContext;
    if (!mc) return;
    var asText = function (o) { return { content: [{ type: 'text', text: typeof o === 'string' ? o : JSON.stringify(o) }] }; };
    var catalogP = null;
    var getCatalog = function () {
      if (!catalogP) catalogP = fetch('/assets/data/catalog.json').then(function (r) { return r.json(); });
      return catalogP;
    };
    var tools = [
      {
        name: 'get_prices',
        description: 'מחירי ההתחלה של המוצרים של הכיף שבאפיה (עוגות מעוצבות, פטיפורים, מארזים ומגשי אירוח) בשקלים, עם קישור לכל מוצר.',
        inputSchema: { type: 'object', properties: { category: { type: 'string', description: 'סינון לפי קטגוריה, למשל עוגות או מארזים. לא חובה.' } } },
        annotations: { readOnlyHint: true },
        execute: function (input) {
          return getCatalog().then(function (c) {
            var cat = input && input.category ? String(input.category) : '';
            var list = c.products.filter(function (p) { return !cat || (p.category + ' ' + p.name).indexOf(cat) !== -1; });
            return asText({ currency: c.currency, note: c.price_note, prices_page: c.business.prices_page, products: list });
          });
        }
      },
      {
        name: 'estimate_dessert_bar',
        description: 'מעריך כמה יחידות פטיפורים וקינוחים צריך לאירוע לפי מספר האורחים ותפקיד הבר, עם הרכב מומלץ. הערכה ראשונית בלבד.',
        inputSchema: { type: 'object', properties: {
          guests: { type: 'integer', minimum: 10, maximum: 1000, description: 'מספר האורחים.' },
          role: { type: 'string', enum: ['after', 'main'], description: 'after: קינוח אחרי ארוחה. main: הבר הוא הכיבוד המרכזי.' }
        }, required: ['guests'] },
        annotations: { readOnlyHint: true },
        execute: function (input) {
          var g = Math.max(10, Math.min(1000, Math.round(Number(input && input.guests) || 0)));
          var role = input && input.role === 'main' ? 'main' : 'after';
          var total = round10(g * PLAN.perGuest[role]);
          return asText({
            guests: g, role: role === 'main' ? 'הכיבוד המרכזי באירוע' : 'קינוח אחרי ארוחה', total_units: total,
            mix: PLAN.mix.map(function (m) { return { item: m.label, units: round10(total * m.share) }; }),
            add_cake: g >= PLAN.cakeFromGuests,
            note: 'הערכה ראשונית. ההרכב, הטעמים והמחיר נקבעים בהצעת מחיר אישית.'
          });
        }
      },
      {
        name: 'prepare_quote_request',
        description: 'מכין נוסח בקשה להצעת מחיר וקישור וואטסאפ לעסק. לא שולח דבר: הלקוח פותח את הקישור ולוחץ שליחה בעצמו.',
        inputSchema: { type: 'object', properties: {
          name: { type: 'string' }, event_type: { type: 'string' }, guests: { type: 'integer' },
          date: { type: 'string' }, location: { type: 'string' }, items: { type: 'string' }, notes: { type: 'string' }
        } },
        annotations: { readOnlyHint: true },
        execute: function (input) {
          var labels = { name: 'שם', event_type: 'סוג האירוע', guests: 'מספר אורחים', date: 'תאריך', location: 'מקום', items: 'מה מזמינים', notes: 'הערות' };
          var lines = ['שלום, אשמח לקבל הצעת מחיר.', ''];
          Object.keys(labels).forEach(function (k) { if (input && input[k] !== undefined && String(input[k]).trim()) lines.push(labels[k] + ': ' + String(input[k]).trim().slice(0, 300)); });
          var msg = lines.join('\n');
          return asText({ message: msg, whatsapp_url: 'https://wa.me/' + BUSINESS_WA + '?text=' + encodeURIComponent(msg), note: 'לא נשלח דבר. הלקוח צריך לפתוח את הקישור וללחוץ שליחה.' });
        }
      }
    ];
    try {
      if (typeof mc.registerTool === 'function') tools.forEach(function (t) { mc.registerTool(t); });
      else if (typeof mc.provideContext === 'function') mc.provideContext({ tools: tools });
    } catch (e) {}
  })();

  var y = $('[data-year]');
  if (y) y.textContent = new Date().getFullYear();
})();
