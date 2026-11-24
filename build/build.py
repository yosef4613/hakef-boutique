#!/usr/bin/env python3
"""בונה את האתר הסטטי של הכיף שבאפיה.

שימוש:
  python3 build/build.py                 # תצוגה מקדימה ל-site/ (noindex)
  python3 build/build.py --production --out dist   # לאתר האמיתי: dist/ ניתן לאינדוקס, כולל .htaccess

מוצרים: build/data/products.json. דף הבית: build/pages/home.html.
"""
import html, json, os, re, sys, urllib.parse, datetime

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC_SITE = os.path.join(ROOT, 'site')            # התמונות, העיצוב והסקריפט נמצאים כאן


def asset_v(rel):
    """גרסה קצרה לפי תוכן הקובץ (assets/...), כדי שדפדפן לא ישאיר עיצוב ישן מהמטמון אחרי עדכון."""
    import hashlib
    with open(os.path.join(SRC_SITE, 'assets', rel), 'rb') as f:
        return hashlib.md5(f.read()).hexdigest()[:8]
IMG_DIR = os.path.join(SRC_SITE, 'assets', 'img')
PRODUCTION = '--production' in sys.argv
OUT = os.path.abspath(sys.argv[sys.argv.index('--out') + 1]) if '--out' in sys.argv else SRC_SITE
TODAY = datetime.date.today().isoformat()

SITE = {
    'name': 'הכיף שבאפיה',
    'url': 'https://hakef.com',
    'phone': '050-915-3197',
    'tel': '0509153197',
    'wa': '972509153197',
    'email': 'r0509153197@gmail.com',
    'area': 'ירושלים והסביבה',
}
WA_DEFAULT = 'שלום, הגעתי מהאתר של הכיף שבאפיה ואשמח לפרטים'

CATEGORIES = [
    {'slug': 'עוגות', 'name': 'עוגות מעוצבות', 'img': 'cake-glaze', 'w': 861, 'h': 861,
     'h1': 'עוגות מעוצבות בהזמנה אישית',
     'title': 'עוגות מעוצבות בהזמנה אישית בירושלים | הכיף שבאפיה',
     'meta': 'עוגות מעוצבות בהזמנה אישית בירושלים והסביבה: עוגות קומות, עוגות יום הולדת, עוגות מוס וגלייז, עם כיתוב ועיצוב לפי האירוע. כשרות מהודרת.',
     'card': 'עוגות קומות, יום הולדת, מוס וגלייז',
     'intro': ['עוגה מעוצבת היא מרכז השולחן: היא הדבר הראשון שהאורחים מצלמים, והדבר שזוכרים אחרי האירוע. כל עוגה אצלנו נאפית בהזמנה אישית, טרייה לאירוע שלכם.',
               'אפשר להתאים טעמים, צבעים, גודל וכיתוב: עוגת קומות לאירוע גדול, עוגת יום הולדת לפי הגיל והתחביב, או עוגת מוס וגלייז אלגנטית. ספרו לנו מה אתם מתכננים, ונחזור עם הצעה.']},
    {'slug': 'פטיפורים', 'name': 'פטיפורים וקינוחים אישיים', 'img': 'st-tartlets', 'w': 1600, 'h': 1066,
     'h1': 'מגשי פטיפורים וקינוחים אישיים לאירועים',
     'title': 'מגשי פטיפורים וקינוחים אישיים לאירועים בירושלים | הכיף שבאפיה',
     'meta': 'מגשי פטיפורים, פאים אישיים, כוסות קינוח ומיני מגנום ביס לאירועים בירושלים והסביבה. מגשים מוכנים או בר קינוחים מלא. הצעת מחיר בוואטסאפ.',
     'card': 'פאים אישיים, כוסות קינוח ובר לאירוע',
     'intro': ['מגשי פטיפורים וקינוחים אישיים הם הבסיס של כל בר קינוחים: טארטלטים ופאים אישיים, כוסות קינוח בשכבות, מוסים בעיצוב אישי וביסים קטנים שנאכלים בביס אחד.',
               'אפשר להזמין מגשים מוכנים במשלוח, או להרכיב מהם בר קינוחים שלם שאנחנו מקימים במקום האירוע. כל המגשים מוכנים טריים, בהזמנה מראש.']},
    {'slug': 'מארזים', 'name': 'מארזי מתוקים', 'img': 'ev-gift-boxes', 'w': 1600, 'h': 2133,
     'h1': 'מארזי מתוקים ומתנות לאורחים',
     'title': 'מארזי מתוקים ומארזים אישיים לאורחים בירושלים | הכיף שבאפיה',
     'meta': 'מארזי מתוקים מעוצבים לאירוח, לימי הולדת ולמתנה, ומארזים אישיים לאורחים בשבת חתן ובשבע ברכות. ירושלים והסביבה.',
     'card': 'מארזים מעוצבים ומתנות לאורחים',
     'intro': ['מארז מתוק הוא מתנה שנראית טוב ונגמרת מהר: מבחר קינוחים וביסים בקופסה מעוצבת, עם קישוט וכיתוב לפי הבקשה.',
               'למארחים בשבת חתן, בשבע ברכות או באירוע משפחתי, יש גם מארזים אישיים קטנים לכל אורח: שוקולדים, פיצוחים ועוגיות ארוזים עם סרט.']},
    {'slug': 'פינגר-פוד', 'name': 'מגשי אירוח ופינגר פוד', 'img': 'p-finger-tray', 'w': 1600, 'h': 1125,
     'h1': 'מגשי אירוח ופינגר פוד לאירועים',
     'title': 'מגשי אירוח ופינגר פוד לאירועים בירושלים | הכיף שבאפיה',
     'meta': 'מגשי אירוח ופינגר פוד לאירועים, לישיבות ולאירוח ביתי: לחמניות ביס, קרואסונים ממולאים ומגשי סושי. בהזמנה מראש בירושלים והסביבה.',
     'card': 'לחמניות ביס, קרואסונים ומגשי סושי',
     'intro': ['לצד הקינוחים, אפשר להזמין מגשי אירוח מוכנים להגשה: לחמניות ביס במגוון מילויים, קרואסונים ממולאים, מגשי פינגר פוד ומגשי סושי.',
               'המגשים מסודרים ומוכנים להגשה, מתאימים לאירוח ביתי, לכיבוד בעבודה, לישיבות ולאירועים. הכמויות והמילויים בתיאום מראש.']},
]
CAT_BY_SLUG = {c['slug']: c for c in CATEGORIES}

NAV = [('בר קינוחים', 'בר-קינוחים-לאירועים/'), ('עוגות', 'product-category/עוגות/'),
       ('פטיפורים', 'product-category/פטיפורים/'), ('כל המוצרים', 'shop/'),
       ('מתכונים', 'מתכונים/'), ('אודות', 'about/'), ('צור קשר', 'contact/')]

PAGES_FOR_SITEMAP = []


# ---------------------------------------------------------------- עזרים
def esc(s):
    return html.escape(s, quote=True)


def abs_url(path):
    return SITE['url'] + '/' + urllib.parse.quote(path, safe='/#')


def wa(text=WA_DEFAULT):
    return 'https://wa.me/' + SITE['wa'] + '?text=' + urllib.parse.quote(text)


def widths(key):
    ws = []
    for f in os.listdir(IMG_DIR):
        m = re.fullmatch(re.escape(key) + r'-(\d+)\.webp', f)
        if m:
            ws.append(int(m.group(1)))
    if not ws:
        raise SystemExit('חסרה תמונה: ' + key)
    return sorted(ws)


def img(root, key, w, h, alt, sizes, lazy=True, cls=''):
    ws = widths(key)
    src = f'{root}assets/img/{key}-{ws[-1] if ws[-1] <= 800 else [x for x in ws if x <= 800][-1]}.webp'
    srcset = ', '.join(f'{root}assets/img/{key}-{x}.webp {x}w' for x in ws)
    extra = ' loading="lazy"' if lazy else ' fetchpriority="high"'
    c = f' class="{cls}"' if cls else ''
    return (f'<img{c} src="{src}" srcset="{srcset}" sizes="{sizes}" width="{w}" height="{h}"'
            f'{extra} decoding="async" alt="{esc(alt)}">')


def jsonld(obj):
    return '<script type="application/ld+json">' + json.dumps(obj, ensure_ascii=False) + '</script>'


# בונה האתר: מופיע בתחתית כל עמוד ובנתונים שגוגל קורא
BUILDER = {'name': 'יוסף יהודה גורדון', 'url': 'https://yosef4613.github.io/yyg-site/'}

BUSINESS = {
    '@context': 'https://schema.org', '@type': 'Bakery', '@id': SITE['url'] + '/#business',
    'name': SITE['name'],
    'description': 'קייטרינג קינוחים בוטיק: בר קינוחים ופטיפורים, מגשי פטיפורים, עוגות מעוצבות ומארזי מתוקים בהזמנה אישית לאירועים.',
    'url': SITE['url'] + '/', 'telephone': '+972-50-915-3197', 'email': SITE['email'],
    'logo': SITE['url'] + '/assets/img/logo-240.png',
    'image': [SITE['url'] + '/assets/img/og-image.jpg', SITE['url'] + '/assets/img/ev-bar-full-1200.webp'],
    'address': {'@type': 'PostalAddress', 'addressLocality': 'ירושלים', 'addressCountry': 'IL'},
    'areaServed': [{'@type': 'City', 'name': 'ירושלים'}, {'@type': 'AdministrativeArea', 'name': 'אזור ירושלים'}],
    'servesCuisine': ['קינוחים', 'פטיפורים', 'עוגות'],
    'paymentAccepted': 'ביט, העברה בנקאית',
    'contactPoint': {'@type': 'ContactPoint', 'telephone': '+972-50-915-3197', 'contactType': 'הזמנות', 'availableLanguage': 'Hebrew'},
}


def breadcrumbs(root, items):
    """items: [(שם, נתיב או None)]"""
    parts = [f'<a href="{root}">דף הבית</a>']
    for name, path in items:
        parts.append(f'<a href="{root}{path}">{esc(name)}</a>' if path is not None else f'<span aria-current="page">{esc(name)}</span>')
    nav = '<nav class="crumbs container" aria-label="פירורי לחם">' + ' <span aria-hidden="true">/</span> '.join(parts) + '</nav>'
    ld = {'@context': 'https://schema.org', '@type': 'BreadcrumbList', 'itemListElement': []}
    seq = [('דף הבית', '')] + [(n, p) for n, p in items]
    for i, (n, p) in enumerate(seq, 1):
        el = {'@type': 'ListItem', 'position': i, 'name': n}
        if p is not None:
            el['item'] = abs_url(p)
        ld['itemListElement'].append(el)
    return nav, ld


WA_ICON = '<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.1.7a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3Z"/></svg>'


# הסקריפט היחיד שמוטמע בעמודים: העדפת תנועה, לפני הציור הראשון כדי שלא יהיה הבהוב.
# מדיניות התוכן (CSP) מאשרת אותו לפי טביעת אצבע שמחושבת ממנו, כך ששינוי בו מעדכן גם את המדיניות.
MOTION_SCRIPT = """
  /* העדפת תנועה: לפני הציור הראשון, כדי שלא יהיה הבהוב */
  (function () {
    var pref = null;
    try { pref = localStorage.getItem('hakef-motion'); } catch (e) {}
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.documentElement.dataset.motion = pref ? pref : (reduce ? 'off' : 'on');
    document.documentElement.classList.add('js');
  })();
"""


def csp_hash(script):
    import hashlib, base64
    return "'sha256-" + base64.b64encode(hashlib.sha256(script.encode('utf-8')).digest()).decode() + "'"


# ---------------------------------------------------------------- שלד עמוד
def layout(path, title, desc, body, extra_ld=(), og_img='og-image.jpg', preload='',
           root_override=None, head_override=None, write=True):
    depth = len([p for p in path.split('/') if p])
    root = root_override if root_override is not None else '../' * depth
    canonical = abs_url(path)
    if write:
        PAGES_FOR_SITEMAP.append(canonical)
    robots = '' if PRODUCTION else '<meta name="robots" content="noindex">\n'
    lds = '\n'.join(jsonld(x) for x in extra_ld)
    nav_items = '\n'.join(
        f'        <li><a href="{root}{p}"{" aria-current=\"page\"" if p == path else ""}>{n}</a></li>' for n, p in NAV)
    og_abs = SITE['url'] + '/assets/img/' + urllib.parse.quote(og_img)
    plan = f'{root}#plan' if (depth or root_override) else '#plan'
    cat_links = '\n'.join(f'          <li><a href="{root}product-category/{c["slug"]}/">{c["name"]}</a></li>' for c in CATEGORIES)
    meta_block = f'''<title>{esc(title)}</title>
<meta name="description" content="{esc(desc)}">
{robots}<link rel="canonical" href="{canonical}">
<meta property="og:type" content="website">
<meta property="og:locale" content="he_IL">
<meta property="og:site_name" content="{SITE['name']}">
<meta property="og:title" content="{esc(title)}">
<meta property="og:description" content="{esc(desc)}">
<meta property="og:url" content="{canonical}">
<meta property="og:image" content="{og_abs}">
<meta name="twitter:card" content="summary_large_image">'''
    if head_override is not None:
        meta_block, lds = head_override, ''
    doc = f'''<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#141210">
{meta_block}
<link rel="icon" href="{root}assets/img/logo-120.png">
<link rel="apple-touch-icon" href="{root}assets/img/logo-240.png">
<link rel="preload" href="{root}assets/fonts/Playpen-hebrew.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{root}assets/fonts/Fredoka-hebrew.woff2" as="font" type="font/woff2" crossorigin>
{preload}<link rel="stylesheet" href="{root}assets/css/fonts.css?v={asset_v('css/fonts.css')}">
<link rel="stylesheet" href="{root}assets/css/site.css?v={asset_v('css/site.css')}">
<script>{MOTION_SCRIPT}</script>
{lds}
</head>
<body>
<a class="skip-link" href="#main">דילוג לתוכן</a>

<header class="site-header" id="top">
  <div class="container site-header__row">
    <a class="brand" href="{root or './'}" aria-label="{SITE['name']} — דף הבית">
      <img class="brand__logo" src="{root}assets/img/logo-light-120.webp" srcset="{root}assets/img/logo-light-140.webp 2x" width="133" height="120" alt="">
      <span class="brand__text"><small>קייטרינג קינוחים<br>בוטיק</small></span>
    </a>
    <nav class="main-nav" aria-label="ניווט ראשי">
      <ul id="nav-list">
{nav_items}
      </ul>
    </nav>
    <div class="site-header__tools">
      <button class="motion-toggle" type="button" aria-pressed="false" data-motion-toggle>
        <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M4 12c2.5-5 5.5-5 8 0s5.5 5 8 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <span class="motion-toggle__label">תנועה: פועלת</span>
      </button>
      <a class="icon-link" href="{wa()}" target="_blank" rel="noopener" aria-label="שליחת הודעה בוואטסאפ" data-wa>{WA_ICON}</a>
      <button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav-list" data-menu-btn>
        <span class="visually-hidden">תפריט</span>
        <span class="menu-btn__bars" aria-hidden="true"></span>
      </button>
    </div>
  </div>
</header>

<main id="main">
{body}
</main>

<footer class="site-footer" id="contact-footer">
  <div class="container footer__grid">
    <div class="footer__brand">
      <img class="footer__logo" src="{root}assets/img/logo-light-200.webp" srcset="{root}assets/img/logo-light-480.webp 2x" width="222" height="200" alt="הלוגו של הכיף שבאפיה: הסוד לאפיה מוצלחת" loading="lazy">
      <p>קייטרינג קינוחים בוטיק: בר קינוחים ופטיפורים, מגשי פטיפורים, עוגות מעוצבות ומארזי מתוקים בהזמנה אישית. {SITE['area']}.</p>
    </div>
    <div>
      <h2 class="footer__title">דברו איתנו</h2>
      <ul class="footer__list">
        <li><a class="text-link text-link--wa" href="{wa()}" target="_blank" rel="noopener" data-wa>וואטסאפ: <span dir="ltr">{SITE['phone']}</span></a></li>
        <li><a class="text-link" href="tel:{SITE['tel']}">חיוג: <span dir="ltr">{SITE['phone']}</span></a></li>
        <li><a class="text-link" href="mailto:{SITE['email']}">{SITE['email']}</a></li>
      </ul>
      <button class="motion-toggle motion-toggle--footer" type="button" aria-pressed="false" data-motion-toggle>
        <span class="motion-toggle__label">תנועה: פועלת</span>
      </button>
    </div>
    <div>
      <h2 class="footer__title">הקטלוג</h2>
      <ul class="footer__list footer__list--plain">
          <li><a href="{root}בר-קינוחים-לאירועים/">בר קינוחים לאירועים</a></li>
{cat_links}
          <li><a href="{root}shop/">כל המוצרים</a></li>
      </ul>
    </div>
    <div>
      <h2 class="footer__title">מידע</h2>
      <ul class="footer__list footer__list--plain">
        <li><a href="{root}מתכונים/">מתכונים</a></li>
        <li><a href="{root}about/">אודות</a></li>
        <li><a href="{root}contact/">צור קשר</a></li>
        <li><a href="{root}הצהרת-נגישות/">הצהרת נגישות</a></li>
        <li><a href="{root}privacy-policy-1/">מדיניות פרטיות</a></li>
      </ul>
    </div>
  </div>
  <div class="container footer__bottom">
    <p>© <span data-year>2026</span> {SITE['name']} · {SITE['area']}</p>
    <p>צילומי סטודיו: Esther Andibo</p>
    <p class="footer__built">עיצוב ובניית האתר: <a class="footer__credit" href="{BUILDER['url']}" target="_blank" rel="noopener">{BUILDER['name']}</a></p>
  </div>
</footer>

<a class="wa-float" href="{wa()}" target="_blank" rel="noopener" aria-label="שליחת הודעה בוואטסאפ" data-wa>{WA_ICON}<span>וואטסאפ</span></a>

<div class="mobile-cta" data-mobile-cta aria-hidden="true">
  <a class="btn btn--primary" href="{plan}" data-order="bar" tabindex="-1">קבלו הצעת מחיר <span aria-hidden="true">←</span></a>
  <a class="icon-link icon-link--box" href="{wa()}" target="_blank" rel="noopener" aria-label="וואטסאפ" tabindex="-1" data-wa>{WA_ICON}</a>
</div>

<script src="{root}assets/js/site.js?v={asset_v('js/site.js')}" defer></script>
</body>
</html>
'''
    if not write:
        return doc
    out = os.path.join(OUT, path, 'index.html') if path else os.path.join(OUT, 'index.html')
    os.makedirs(os.path.dirname(out), exist_ok=True)
    with open(out, 'w', encoding='utf-8') as f:
        f.write(doc)


# ---------------------------------------------------------------- רכיבים
def category_cards(root):
    cards = []
    for c in CATEGORIES:
        cards.append(f'''<a class="cat-card" href="{root}product-category/{c["slug"]}/">
          {img(root, c["img"], c["w"], c["h"], "", "(max-width: 760px) 92vw, 290px")}
          <span class="cat-card__body"><span class="cat-card__name">{c["name"]}</span><span class="cat-card__sub">{c["card"]}</span></span>
        </a>''')
    return '<div class="cat-grid">\n        ' + '\n        '.join(cards) + '\n      </div>'


def product_card(root, p, heading='h3'):
    return f'''<article class="p-card">
          <a href="{root}product/{p["slug"]}/">
            {img(root, p["img"], p["imgW"], p["imgH"], p["alt"], "(max-width: 760px) 46vw, 290px")}
            <{heading} class="p-card__name">{esc(p["name"])}</{heading}>
            <p class="p-card__text">{esc(p["short"])}</p>
          </a>
        </article>'''


def product_grid(root, items, heading='h3'):
    return '<div class="p-grid">\n        ' + '\n        '.join(product_card(root, p, heading) for p in items) + '\n      </div>'


def page_band(root, back, crumbs, head_html):
    """ראש עמוד בשלוש שכבות: צילום אמיתי ברקע, הטקסט באמצע, הכפתור בחזית."""
    key, w, h = back
    return (f'<div class="page-band">\n  <figure class="page-band__back" aria-hidden="true">'
            f'{img(root, key, w, h, "", "100vw", lazy=False)}</figure>\n  {crumbs}\n{head_html}\n  </div>')


def cta_band(root, title, text, label='לתכנון הבר והצעת מחיר', href=None):
    href = href or f'{root}#plan'
    return f'''<section class="cta-band">
    <div class="container cta-band__inner">
      <div><h2>{title}</h2><p>{text}</p></div>
      <a class="btn btn--primary" href="{href}" data-order="bar">{label} <span aria-hidden="true">←</span></a>
    </div>
  </section>'''


def faq_block(items, heading='שאלות נפוצות'):
    det = '\n'.join(f'        <details><summary>{esc(q)}</summary><p>{esc(a)}</p></details>' for q, a in items)
    ld = {'@context': 'https://schema.org', '@type': 'FAQPage',
          'mainEntity': [{'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': a}} for q, a in items]}
    block = f'''<section class="faq section" aria-labelledby="faq-title">
    <div class="container faq__grid">
      <header class="section-head"><p class="eyebrow">שאלות נפוצות</p><h2 id="faq-title">{heading}</h2></header>
      <div class="faq__list">
{det}
      </div>
    </div>
  </section>'''
    return block, ld


FAQ_GENERAL = [
    ('כמה זמן מראש צריך להזמין?', 'זה תלוי בגודל ההזמנה. לאירועים גדולים כדאי לפנות כמה שיותר מוקדם. צריכים משהו דחוף? לפעמים אפשר, בתוספת תשלום ובכפוף לזמינות.'),
    ('יש מינימום הזמנה?', 'כן. המינימום משתנה לפי סוג המוצר, ונפרט אותו בהצעת המחיר.'),
    ('לאן אתם מגיעים?', 'לירושלים והסביבה. אפשר לקבל מגשים מוכנים במשלוח, או הקמה מלאה של בר קינוחים במקום האירוע.'),
    ('מה אפשר להתאים אישית?', 'כמעט הכול: טעמים, צבעים ועיצוב, כיתוב או שם על המוצרים, סוגי הקינוחים בכל מגש, וגודל המגש או הבר.'),
    ('כמה עולה בר קינוחים?', 'המחיר תלוי במספר האורחים, בהרכב הקינוחים ובעיצוב. במתכנן הבר תקבלו הערכה של הכמות, ואנחנו נחזור אליכם עם הצעת מחיר מדויקת ובלי התחייבות.'),
    ('איך משלמים?', 'בביט או בהעברה בנקאית. אחרי שמאשרים את ההצעה משלמים מקדמה, ואת היתרה לפי מה שסיכמנו.'),
    ('מה עם כשרות?', 'כל המוצרים בכשרות מהודרת. לשאלות על ההשגחה, דברו איתנו ונשמח לפרט.'),
]


# ---------------------------------------------------------------- עמודים
def build_home(products):
    body = open(os.path.join(ROOT, 'build', 'pages', 'home.html'), encoding='utf-8').read()
    body = body.replace('{{CATEGORY_CARDS}}', category_cards(''))
    body = body.replace('href="https://wa.me/972509153197"', f'href="{wa()}"')
    preload = ('<link rel="preload" as="image" href="assets/img/hero-petitfours-tall-800.webp" media="(max-width: 760px)">\n'
               '<link rel="preload" as="image" imagesrcset="assets/img/hero-petitfours-800.webp 800w, assets/img/hero-petitfours-1200.webp 1200w, assets/img/hero-petitfours-1600.webp 1600w" imagesizes="100vw" media="(min-width: 761px)">\n')
    faq_ld = {'@context': 'https://schema.org', '@type': 'FAQPage',
              'mainEntity': [{'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': a}} for q, a in FAQ_GENERAL]}
    website = {'@context': 'https://schema.org', '@type': 'WebSite', 'name': SITE['name'], 'url': SITE['url'] + '/', 'inLanguage': 'he-IL',
               'creator': {'@type': 'Person', 'name': BUILDER['name'], 'url': BUILDER['url']}}
    layout('', 'בר קינוחים, פטיפורים ועוגות מעוצבות בירושלים | הכיף שבאפיה',
           'בר קינוחים ופטיפורים מעוצב, מגשי פטיפורים ועוגות מעוצבות בהזמנה אישית לבר מצווה, בת מצווה, ברית ושבע ברכות בירושלים והסביבה. כשרות מהודרת. הצעת מחיר בוואטסאפ.',
           body, extra_ld=[BUSINESS, website, faq_ld], preload=preload)


def build_shop(products):
    root = '../'
    crumbs, crumbs_ld = breadcrumbs(root, [('כל המוצרים', None)])
    sections = []
    for c in CATEGORIES:
        items = [p for p in products if p['cats'][0] == c['slug']]
        sections.append(f'''<section class="shop-cat" aria-labelledby="s-{esc(c["slug"])}">
      <div class="shop-cat__head"><h2 id="s-{esc(c["slug"])}">{c["name"]}</h2><a class="text-link" href="{root}product-category/{c["slug"]}/">לעמוד {c["name"]} ←</a></div>
      {product_grid(root, items)}
    </section>''')
    head = '''  <header class="page-head container">
    <p class="eyebrow">הקטלוג</p>
    <h1>כל המוצרים: בר קינוחים, פטיפורים, עוגות מעוצבות ומארזים</h1>
    <p class="lead">כל מה שאפשר להזמין לאירוע, בהזמנה אישית ובטריות מלאה. בוחרים מה מתאים, שולחים הודעה, ומקבלים הצעת מחיר.</p>
  </header>'''
    body = f'''{page_band(root, ("hero-petitfours", 1600, 1066), crumbs, head)}
  <div class="container shop overlap">
    {"".join(sections)}
  </div>
  {cta_band(root, "לא בטוחים מה לבחור?", "ספרו לנו על האירוע, ונרכיב איתכם את ההזמנה המתאימה.")}'''
    itemlist = {'@context': 'https://schema.org', '@type': 'ItemList', 'name': 'כל המוצרים',
                'itemListElement': [{'@type': 'ListItem', 'position': i, 'url': abs_url(f'product/{p["slug"]}/'), 'name': p['name']} for i, p in enumerate(products, 1)]}
    layout('shop/', 'כל המוצרים | בר קינוחים, פטיפורים ועוגות מעוצבות | הכיף שבאפיה',
           'הקטלוג המלא של הכיף שבאפיה: בר קינוחים לאירוע, מגשי פטיפורים, פאים אישיים, עוגות מעוצבות, מארזי מתוקים ומגשי אירוח. ירושלים והסביבה.',
           body, extra_ld=[crumbs_ld, itemlist])


def build_category(c, products):
    path = f'product-category/{c["slug"]}/'
    root = '../../'
    items = [p for p in products if c['slug'] in p['cats']]
    crumbs, crumbs_ld = breadcrumbs(root, [('כל המוצרים', 'shop/'), (c['name'], None)])
    others = ' '.join(f'<a class="chip-link" href="{root}product-category/{o["slug"]}/">{o["name"]}</a>' for o in CATEGORIES if o is not c)
    intro = ''.join(f'<p>{esc(t)}</p>' for t in c['intro'])
    faq, faq_ld = faq_block([FAQ_GENERAL[0], FAQ_GENERAL[3], FAQ_GENERAL[2], FAQ_GENERAL[6]], 'מה חשוב לדעת לפני שמזמינים')
    head = f'''  <header class="page-head container">
    <p class="eyebrow">{c["name"]} · {SITE["area"]}</p>
    <h1>{c["h1"]}</h1>
    <div class="page-head__intro">{intro}</div>
    <a class="btn btn--wa" href="{wa("שלום, אשמח לקבל הצעת מחיר על " + c["name"])}" target="_blank" rel="noopener" data-wa>{WA_ICON} לקבלת הצעת מחיר בוואטסאפ</a>
  </header>'''
    body = f'''{page_band(root, (c["img"], c["w"], c["h"]), crumbs, head)}
  <section class="container section--tight overlap" aria-label="{c["name"]}">
    {product_grid(root, items, 'h2')}
    <p class="also">עוד בקטלוג: {others}</p>
  </section>
  {faq}
  {cta_band(root, "מתכננים אירוע?", "בנו בר קינוחים שלם במתכנן, וקבלו הצעת מחיר אישית.")}'''
    itemlist = {'@context': 'https://schema.org', '@type': 'ItemList', 'name': c['h1'],
                'itemListElement': [{'@type': 'ListItem', 'position': i, 'url': abs_url(f'product/{p["slug"]}/'), 'name': p['name']} for i, p in enumerate(items, 1)]}
    layout(path, c['title'], c['meta'], body, extra_ld=[crumbs_ld, itemlist, faq_ld], og_img=f'{c["img"]}-800.webp')


def build_product(p, products):
    path = f'product/{p["slug"]}/'
    root = '../../'
    cat = CAT_BY_SLUG[p['cats'][0]]
    crumbs, crumbs_ld = breadcrumbs(root, [(cat['name'], f'product-category/{cat["slug"]}/'), (p['name'], None)])
    desc_html = ''.join(f'<p>{esc(t)}</p>' for t in p['desc'])
    related = [x for x in products if x is not p and cat['slug'] in x['cats']][:4]
    if len(related) < 4:
        related += [x for x in products if x is not p and x not in related and x.get('featured')][:4 - len(related)]
    msg = f'שלום, אשמח לקבל הצעת מחיר על: {p["name"]}'
    body = f'''{crumbs}
  <section class="product container">
    <figure class="product__media">
      {img(root, p["img"], p["imgW"], p["imgH"], p["alt"], "(max-width: 900px) 92vw, 560px", lazy=False)}
    </figure>
    <div class="product__info">
      <p class="eyebrow">{cat["name"]}</p>
      <h1>{esc(p["name"])}</h1>
      <p class="lead">{esc(p["short"])}</p>
      <div class="product__cta">
        <a class="btn btn--wa" href="{wa(msg)}" target="_blank" rel="noopener" data-wa>{WA_ICON} לקבלת הצעת מחיר בוואטסאפ</a>
        <p class="micro">או <a class="text-link" href="{root}#plan">תכננו בר קינוחים שלם במתכנן</a></p>
      </div>
      <div class="product__desc">{desc_html}</div>
      <ul class="ticks" aria-label="פרטים">
        <li>בהזמנה אישית</li><li>כשרות מהודרת</li><li>{SITE["area"]}</li>
      </ul>
      <h2 class="product__sub">מה אפשר להתאים</h2>
      <ul class="custom-list"><li>טעמים</li><li>צבעים ועיצוב</li><li>כיתוב או שם</li><li>כמות וגודל</li></ul>
    </div>
  </section>
  <section class="related container section--tight" aria-labelledby="rel-title">
    <h2 id="rel-title" class="related__title">עוד מ{cat["name"]}</h2>
    {product_grid(root, related)}
  </section>'''
    # בלי נתונים מסוג "מוצר": גוגל דורש בהם מחיר או ביקורות, והמחיר כאן נקבע בהצעה אישית.
    # כותרת קצרה מספיק כדי שגוגל לא יקטע אותה (עד כ־62 תווים)
    title = next(t for t in (f'{p["name"]} | {cat["name"]} בירושלים | הכיף שבאפיה',
                              f'{p["name"]} בירושלים | הכיף שבאפיה',
                              f'{p["name"]} | הכיף שבאפיה', p['name']) if len(t) <= 62)
    layout(path, title,
           f'{p["short"]} בהזמנה אישית, {SITE["area"]}. הצעת מחיר בוואטסאפ.', body,
           extra_ld=[crumbs_ld], og_img=f'{p["img"]}-800.webp')


def build_bar_page(products):
    path = 'בר-קינוחים-לאירועים/'
    root = '../'
    crumbs, crumbs_ld = breadcrumbs(root, [('בר קינוחים לאירועים', None)])
    stations = [('st-tartlets', 1600, 1066, 'טארטלטים ופאים אישיים', 'בצק פריך ומילויים עשירים: פיסטוק־לימון, פירות טריים, שוקולד ומרנג קלוי.'),
                ('st-verrines', 1500, 1000, 'כוסות קינוח', 'שכבות של קרם, פירות וקראנץ\' בכוסות אישיות. נוח לאורחים ויפה על השולחן.'),
                ('st-mousse', 1600, 1200, 'מוסים בעיצוב אישי', 'כיפות מוס בצבעים שמתאימים לאירוע, עם גימור שנראה מרחוק.'),
                ('st-macarons', 1600, 1066, 'מקרונים וביסים קטנים', 'מוסיפים צבע וגובה לבר, בהתאמה לפלטת האירוע.'),
                ('st-donuts', 1600, 1200, 'דונאטס ומגנום ביס', 'הפינה שהילדים מחכים לה: מיני דונאטס מצופים ומגנום ביס במילוי עוגה.'),
                ('cake-glaze', 861, 861, 'עוגה מרכזית', 'עוגה מעוצבת שמשלימה את הבר, עם כיתוב או שם לפי הבקשה.')]
    st_html = '\n'.join(f'''<li class="mini">{img(root, k, w, h, t, "(max-width: 760px) 30vw, 160px")}<div><h3>{t}</h3><p>{esc(d)}</p></div></li>''' for k, w, h, t, d in stations)
    events = [
        ('bar-mitzvah', 'בר קינוחים לבר מצווה ולבת מצווה', 'st-mousse', 1600, 1200,
         ['בבר מצווה ובבת מצווה הבר הוא לרוב הכיבוד המרכזי אחרי הסעודה או בקבלת הפנים, ולכן כדאי לתכנן אותו בכמות נדיבה.',
          'אפשר לבנות את הבר בצבעים שבעל השמחה או בעלת השמחה בחרו, להוסיף כיתוב עם השם, ולשלב עוגה מרכזית מעוצבת. כדאי לפנות כמה שיותר מוקדם, במיוחד לאירועים גדולים.']),
        ('brit', 'בר מתוקים לברית ולשלום זכר', 'ev-cups', 1600, 1200,
         ['ברית ושלום זכר מגיעים לפעמים בהתראה קצרה. בשבילם יש מגשי פטיפורים וקינוחים אישיים שמגיעים מוכנים להגשה, או בר קטן ואלגנטי שמוקם במקום.',
          'צריכים משהו דחוף? לפעמים אפשר, בתוספת תשלום ובכפוף לזמינות. שלחו הודעה ונבדוק מיד.']),
        ('sheva-brachot', 'שולחן קינוחים לאירוסין ולשבע ברכות', 'ev-gift-boxes', 1600, 2133,
         ['באירוסין ובשבע ברכות מחפשים קינוחים שנראים חגיגיים ומגיעים מוכנים: מגשי פטיפורים, כוסות קינוח ומארזי מתוקים.',
          'לשבת חתן ולשבע ברכות יש גם מארזים אישיים קטנים לכל אורח: שוקולדים, פיצוחים ועוגיות ארוזים עם סרט.']),
        ('corporate', 'בר קינוחים לאירועי חברה', 'ev-platter-wide', 1600, 1200,
         ['לכנסים, לישיבות ולימי גיבוש: מגשי פטיפורים, מגשי אירוח ופינגר פוד, ובר קינוחים מסודר שמכבד את האורחים.',
          'אפשר להזמין מגשים מוכנים במשלוח או הקמה מלאה במקום, לפי לוח הזמנים של האירוע.']),
    ]
    ev_html = '\n'.join(f'''<section class="event-block" id="{eid}" aria-labelledby="{eid}-t">
      <figure>{img(root, k, w, h, t, "(max-width: 900px) 92vw, 480px")}</figure>
      <div><h2 id="{eid}-t">{t}</h2>{"".join(f"<p>{esc(x)}</p>" for x in txt)}
        <a class="text-link text-link--wa" href="{wa("שלום, אשמח להצעת מחיר ל" + t)}" target="_blank" rel="noopener" data-wa>לשאלה בוואטסאפ ←</a></div>
    </section>''' for eid, t, k, w, h, txt in events)
    faq_items = [
        ('מה ההבדל בין בר קינוחים, בר מתוקים ושולחן קינוחים?', 'בפועל מדובר באותו רעיון: שולחן מעוצב שמרכז את הקינוחים של האירוע במקום אחד. אצלנו הוא כולל פטיפורים, קינוחים אישיים ועוגה מרכזית, ומותאם לצבעים ולטעמים שלכם.'),
        ('כמה פטיפורים צריך לבר קינוחים?', 'במתכנן שלנו ההערכה הראשונית היא כ־3 יחידות לאורח כשהבר מוגש כקינוח אחרי ארוחה, וכ־6 יחידות לאורח כשהבר הוא הכיבוד המרכזי. את הכמות הסופית נדייק יחד איתכם.'),
        FAQ_GENERAL[4], FAQ_GENERAL[0], FAQ_GENERAL[2], FAQ_GENERAL[6],
    ]
    faq, faq_ld = faq_block(faq_items, 'שאלות על בר קינוחים')
    body = f'''{crumbs}
  <header class="page-head page-head--split container">
    <div>
      <p class="eyebrow">בר קינוחים · בר מתוקים · שולחן קינוחים</p>
      <h1>בר קינוחים ופטיפורים לאירועים בירושלים</h1>
      <p class="lead">בר הקינוחים הוא מה שהאורחים זוכרים מהאירוע. אנחנו מתכננים, אופים ומקימים בר מעוצב בהתאמה לצבעים, לטעמים ולכמות האורחים, בירושלים והסביבה.</p>
      <a class="btn btn--primary" href="{root}#plan" data-order="bar">תכננו את הבר וקבלו הצעת מחיר <span aria-hidden="true">←</span></a>
    </div>
    <figure class="page-head__media">{img(root, "ev-bar-full", 1600, 1200, "בר קינוחים מלא באירוע: מעמדים לבנים, פרחים ומגוון פטיפורים", "(max-width: 900px) 92vw, 560px", lazy=False)}</figure>
  </header>
  <section class="container section--tight" aria-labelledby="inc-title">
    <h2 id="inc-title" class="block-title">מה כולל בר קינוחים?</h2>
    <ul class="mini-list">
{st_html}
    </ul>
  </section>
  <div class="container events-list">
{ev_html}
  </div>
  {faq}
  {cta_band(root, "בואו נתכנן את הבר שלכם", "ענו על כמה שאלות קצרות במתכנן, וקבלו הרכב מומלץ והצעת מחיר אישית.")}'''
    service = {'@context': 'https://schema.org', '@type': 'Service', 'serviceType': 'בר קינוחים ופטיפורים לאירועים',
               'provider': {'@id': SITE['url'] + '/#business'}, 'areaServed': {'@type': 'City', 'name': 'ירושלים'},
               'description': 'תכנון, הכנה והקמה של בר קינוחים ופטיפורים מעוצב לבר מצווה, בת מצווה, ברית, אירוסין, שבע ברכות ואירועי חברה.'}
    layout(path, 'בר קינוחים לאירועים ובר מצווה בירושלים | הכיף שבאפיה',
           'בר קינוחים ופטיפורים מעוצב לבר מצווה, בת מצווה, ברית, אירוסין ושבע ברכות בירושלים והסביבה. מה כולל הבר, כמה פטיפורים צריך, ואיך מזמינים.',
           body, extra_ld=[crumbs_ld, service, faq_ld], og_img='ev-bar-full-1200.webp')


def simple_page(path, title, desc, h1, eyebrow, inner, crumbs_name, back=None, intro='', overlap=False):
    """intro: משפט פתיחה בתוך הרצועה העליונה. overlap: התוכן עולה לתוך שולי התמונה (שבירת הקצה)."""
    root = '../' * len([x for x in path.split('/') if x])
    crumbs, crumbs_ld = breadcrumbs(root, [(crumbs_name, None)])
    intro_html = f'\n    <p class="lead">{intro}</p>' if intro else ''
    head = f'''  <header class="page-head container">
    <p class="eyebrow">{eyebrow}</p>
    <h1>{h1}</h1>{intro_html}
  </header>'''
    top = page_band(root, back, crumbs, head) if back else crumbs + '\n' + head
    body = f'''{top}
  <div class="container prose{' overlap' if overlap else ''}">
{inner.replace("{root}", root)}
  </div>'''
    layout(path, title, desc, body, extra_ld=[crumbs_ld])


def build_static_pages():
    simple_page('about/', 'אודות הכיף שבאפיה | קייטרינג קינוחים בוטיק בירושלים',
                'הכיף שבאפיה: קייטרינג קינוחים בוטיק בירושלים והסביבה. בר קינוחים ופטיפורים, עוגות מעוצבות ומארזי מתוקים, טריים ובהזמנה אישית.',
                'הכיף שבאפיה: קייטרינג קינוחים בוטיק בירושלים', 'אודות', '''
    <p class="lead">הכיף שבאפיה התחיל כחנות לחומרי גלם ולציוד אפייה, עם מתכונים וסדנאות לאופים בבית. עם השנים הבקשות לפטיפורים ולעוגות לאירועים הפכו לעיקר, והיום אנחנו קייטרינג קינוחים בוטיק.</p>
    <p>מאחורי העסק עומדת רחלי דיוויס. אנחנו מכינים בר קינוחים ופטיפורים, מגשי פטיפורים וקינוחים אישיים, עוגות מעוצבות ומארזי מתוקים לשמחות ולאירועים בירושלים והסביבה.</p>
    <h2>מה חשוב לנו</h2>
    <ul class="custom-list custom-list--wide">
      <li>כל הזמנה מוכנה טרייה, רק אחרי שהוזמנה.</li>
      <li>כל הזמנה מאושרת איתכם אישית לפני שמתחילים להכין.</li>
      <li>התאמה אישית: טעמים, צבעים, עיצוב וכיתוב.</li>
      <li>כשרות מהודרת.</li>
    </ul>
    <p><a class="btn btn--primary" href="{root}#plan" data-order="bar">לתכנון הבר והצעת מחיר <span aria-hidden="true">←</span></a></p>''', 'אודות',
                back=('ev-platter-wide', 1600, 1200))

    simple_page('contact/', 'צור קשר | הזמנות והצעות מחיר בוואטסאפ | הכיף שבאפיה',
                'להזמנת בר קינוחים, מגשי פטיפורים ועוגות מעוצבות: וואטסאפ 050-915-3197. ירושלים והסביבה.',
                'צרו קשר: הזמנות והצעות מחיר', 'צור קשר', f'''
    <div class="contact-cards">
      <div class="contact-card"><h2>וואטסאפ</h2><p dir="ltr" class="contact-card__num">{SITE["phone"]}</p>
        <a class="btn btn--wa" href="{wa()}" target="_blank" rel="noopener" data-wa>{WA_ICON} שליחת הודעה בוואטסאפ</a></div>
      <div class="contact-card"><h2>טלפון</h2><p><a class="text-link" href="tel:{SITE["tel"]}" dir="ltr">{SITE["phone"]}</a></p>
        <h2>מייל</h2><p><a class="text-link" href="mailto:{SITE["email"]}">{SITE["email"]}</a></p></div>
      <div class="contact-card"><h2>אזור שירות</h2><p>{SITE["area"]}. משלוח מגשים או הקמת בר קינוחים במקום האירוע.</p>
        <p><a class="text-link" href="{{root}}#plan">רוצים הערכה מהירה? מתכנן הבר ←</a></p></div>
    </div>''', 'צור קשר', back=('ev-bar-full', 1600, 1200),
                intro='הדרך הכי מהירה להזמין היא וואטסאפ. שלחו לנו מה אתם מתכננים, ונחזור אליכם עם הצעה אישית.', overlap=True)

    simple_page('הצהרת-נגישות/', 'הצהרת נגישות | הכיף שבאפיה',
                'הצהרת הנגישות של אתר הכיף שבאפיה: התאמות הנגישות באתר ודרכי פנייה בנושא נגישות.',
                'הצהרת נגישות', 'נגישות', f'''
    <p>אנחנו רוצים שכל אחד ואחת יוכלו להשתמש באתר בנוחות. האתר נבנה בהתאם להנחיות התקן הישראלי ת"י 5568, המבוסס על הנחיות WCAG 2.0 ברמה AA.</p>
    <h2>מה עשינו באתר</h2>
    <ul class="custom-list custom-list--wide">
      <li>ניווט מלא במקלדת, כולל קישור "דילוג לתוכן" ומסגרת פוקוס בולטת.</li>
      <li>ניגודיות גבוהה בין טקסט לרקע.</li>
      <li>טקסט חלופי לתמונות, וכותרות במבנה היררכי.</li>
      <li>תוויות לכל שדות הטופס, והודעות שגיאה ברורות.</li>
      <li>מתג "תנועה" שמכבה את כל האנימציות. האתר מכבד גם את הגדרת "הפחתת תנועה" במכשיר.</li>
      <li>תצוגה מותאמת לטלפון, לטאבלט ולמחשב, ותמיכה בהגדלת טקסט בדפדפן.</li>
    </ul>
    <h2>נתקלתם בבעיה?</h2>
    <p>אם משהו באתר לא נגיש לכם, נשמח לשמוע ולתקן: וואטסאפ או טלפון <a class="text-link" href="tel:{SITE["tel"]}" dir="ltr">{SITE["phone"]}</a>, או מייל <a class="text-link" href="mailto:{SITE["email"]}">{SITE["email"]}</a>.</p>
    <p class="micro">עדכון אחרון: {TODAY}</p>''', 'הצהרת נגישות')

    simple_page('privacy-policy-1/', 'מדיניות פרטיות | הכיף שבאפיה',
                'מדיניות הפרטיות של אתר הכיף שבאפיה: אילו פרטים נאספים, למה, איפה הם נשמרים ואיך פונים אלינו בכל שאלה.',
                'מדיניות פרטיות', 'פרטיות', f'''
    <p>האתר לא שומר פרטים אישיים בשרת. כשאתם שולחים בקשה להצעת מחיר, הפרטים שמילאתם נפתחים כהודעה באפליקציית וואטסאפ או המייל שלכם, ונשלחים אלינו רק כשאתם לוחצים "שליחה".</p>
    <p>אנחנו משתמשים בפרטים האלה רק כדי לחזור אליכם בעניין ההזמנה, ולא מעבירים אותם לאף גורם אחר.</p>
    <p>האתר שומר בדפדפן שלכם העדפה אחת בלבד: האם להפעיל אנימציות. היא נשארת במכשיר שלכם ואינה נשלחת אלינו.</p>
    <p>לשאלות: <a class="text-link" href="mailto:{SITE["email"]}">{SITE["email"]}</a>.</p>''', 'מדיניות פרטיות')


def build_404():
    """404 לאתר האמיתי: נתיבים מוחלטים מהשורש."""
    doc = f'''<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>העמוד לא נמצא | {SITE["name"]}</title>
<meta name="robots" content="noindex"><link rel="stylesheet" href="/assets/css/fonts.css?v={asset_v('css/fonts.css')}"><link rel="stylesheet" href="/assets/css/site.css?v={asset_v('css/site.css')}"></head>
<body><main class="container prose" style="padding-block:120px"><p class="eyebrow">404</p><h1>העמוד הזה לא נמצא</h1>
<p class="lead">אולי הוא עבר מקום. אפשר להמשיך מכאן:</p>
<p><a class="btn btn--primary" href="/">לדף הבית <span aria-hidden="true">←</span></a></p>
<ul class="custom-list custom-list--wide"><li><a class="text-link" href="/בר-קינוחים-לאירועים/">בר קינוחים לאירועים</a></li><li><a class="text-link" href="/shop/">כל המוצרים</a></li><li><a class="text-link text-link--wa" href="{wa()}">וואטסאפ: {SITE["phone"]}</a></li></ul>
</main></body></html>'''
    open(os.path.join(OUT, '404.html'), 'w', encoding='utf-8').write(doc)


def build_sitemap_robots():
    urls = '\n'.join(f'  <url><loc>{u}</loc><lastmod>{TODAY}</lastmod></url>' for u in PAGES_FOR_SITEMAP)
    open(os.path.join(OUT, 'sitemap.xml'), 'w', encoding='utf-8').write(
        f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n{urls}\n</urlset>\n')
    open(os.path.join(OUT, 'robots.txt'), 'w', encoding='utf-8').write(
        f'User-agent: *\nAllow: /\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: {SITE["url"]}/sitemap.xml\nSitemap: {SITE["url"]}/wp-sitemap.xml\n')


def build_htaccess():
    """הפניות 301 מהכתובות הישנות (וורדפרס) לחדשות, לשימור הדירוג בגוגל."""
    raw_products = ['מיזרה-צבעוני-200-גרם', 'שמבלות', 'קליק-מטאלי', 'סוכר-נצנצ-לקישוט', 'פניני-קראנצ-חלבי', 'גליליות-מיני',
                    'קרם-פסק-זמן', 'קרם-נוגט', 'קרם-וניל', 'קרם-קינדר-פרווה', 'מארז-קישוטים-לילדים', 'נשיקות-ממותגות']
    recipes = json.load(open(os.path.join(ROOT, 'build', 'data', 'recipes.json'), encoding='utf-8'))
    rules = [
        ('product-category/(כללי|שוקולד|קרמים-ומליות|קישוטים-אכילים)/?.*', 'shop/'),
        ('product-category/עוגות/(עוגות-בהזמנה|עוגות-מוס)/?', 'product-category/עוגות/'),
        ('product-category/פטיפורים/(קינוחים-אישיים|פאים-אישיים)/?', 'product-category/פטיפורים/'),
        ('product-tag/.*', 'shop/'),
        ('recipes/?', 'מתכונים/'),
        ('שוקלד-קרנץ/?', 'מתכונים/'),
        ('(מדיניות-החזרים|cart|checkout|my-account)/?.*', 'contact/'),
        ('(category|tag|author|20[0-9][0-9])/.*', ''),
    ] + [(f'{o}/?', f'מתכונים/{r["slug"]}/') for r in recipes for o in r['old']] \
      + [(f'product/{s}/?', 'shop/') for s in raw_products]
    lines = ['# הכיף שבאפיה: נוצר אוטומטית על ידי build/build.py',
             '# האתר עצמו הוא קבצים סטטיים. וורדפרס נשאר רק לממשק הניהול ולעמודי המתכונים.',
             'Options -MultiViews', 'DirectoryIndex index.html index.php',
             'ErrorDocument 404 /404.html', 'RewriteEngine On', 'RewriteBase /',
             '# HTTPS ובלי www', 'RewriteCond %{HTTPS} !=on [OR]', 'RewriteCond %{HTTP_HOST} ^www\\. [NC]',
             'RewriteRule ^(.*)$ https://hakef.com/$1 [R=301,L]',
             '# אבטחה: חסימת xmlrpc', 'RewriteRule ^xmlrpc\\.php$ - [F,L]',
             '# כתובות ישנות']
    for src, dst in rules:
        lines.append(f'RewriteRule ^{src}$ /{urllib.parse.quote(dst)} [R=301,L,NE]')  # יעד מקודד, כדי שכותרת Location תהיה ASCII תקני
    lines += ['# וורדפרס: כל מה שאינו קובץ או תיקייה קיימים (המתכונים, ממשק הניהול)',
              'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
              'RewriteRule ^index\\.php$ - [L]', 'RewriteCond %{REQUEST_FILENAME} !-f', 'RewriteCond %{REQUEST_FILENAME} !-d',
              'RewriteRule . /index.php [L]',
              '', '<IfModule mod_expires.c>', '  ExpiresActive On',
              '  ExpiresByType image/webp "access plus 1 year"', '  ExpiresByType image/jpeg "access plus 1 year"', '  ExpiresByType image/png "access plus 1 year"',
              '  ExpiresByType font/woff2 "access plus 1 year"',
              '  ExpiresByType text/css "access plus 1 year"', '  ExpiresByType application/javascript "access plus 1 year"', '  ExpiresByType text/javascript "access plus 1 year"',
              '  ExpiresByType text/html "access plus 0 seconds"', '</IfModule>',
              '<IfModule mod_deflate.c>', '  AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml application/xml', '</IfModule>',
              '<IfModule mod_headers.c>', '  Header always set X-Content-Type-Options "nosniff"',
              '  Header always set Referrer-Policy "strict-origin-when-cross-origin"',
              '  # הדפדפן מתחבר תמיד בצורה מוצפנת (שנה). בלי תתי־דומיינים, כי שרתי המייל לא בהכרח מוצפנים',
              '  Header always set Strict-Transport-Security "max-age=31536000"',
              '  # אתרים זרים לא יכולים להציג את האתר בתוך מסגרת',
              '  Header always set X-Frame-Options "SAMEORIGIN"',
              '  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"',
              '  # מדיניות תוכן לעמודים הסטטיים: רק קבצים מהאתר עצמו, והסקריפט המוטמע מאושר לפי טביעת אצבע',
              '  <FilesMatch "\\.html$">',
              f'    Header set Content-Security-Policy "default-src \'self\'; script-src \'self\' {csp_hash(MOTION_SCRIPT)}; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\'; object-src \'none\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'self\'; upgrade-insecure-requests"',
              '  </FilesMatch>',
              '</IfModule>', '']
    os.makedirs(os.path.join(ROOT, 'deploy'), exist_ok=True)
    open(os.path.join(ROOT, 'deploy', '.htaccess'), 'w', encoding='utf-8').write('\n'.join(lines))



# ---------------------------------------------------------------- תבנית וורדפרס (מתכונים)
def build_wp_theme():
    """כותרת ותחתית לתבנית הוורדפרס, מאותו שלד של שאר האתר (נתיבים מוחלטים מהשורש)."""
    doc = layout('', '', '', '%%BODY%%', root_override='/', head_override='<?php hakef_head_meta(); ?>', write=False)
    doc = doc.replace('</head>', '<?php wp_head(); ?>\n</head>', 1)
    doc = doc.replace('<body>', '<body <?php body_class(); ?>>\n<?php wp_body_open(); ?>', 1)
    doc = doc.replace('</body>', '<?php wp_footer(); ?>\n</body>', 1)
    top, bottom = doc.split('%%BODY%%')
    theme = os.path.join(ROOT, 'wp', 'themes', 'hakef')
    note = '<?php /* נוצר אוטומטית על ידי build/build.py. לא לערוך ידנית. */ defined( \'ABSPATH\' ) || exit; ?>\n'
    open(os.path.join(theme, 'header.php'), 'w', encoding='utf-8').write(note + top)
    open(os.path.join(theme, 'footer.php'), 'w', encoding='utf-8').write(note + bottom)
    seed = os.path.join(ROOT, 'wp', 'mu-plugins', 'hakef-seed')
    os.makedirs(seed, exist_ok=True)
    import shutil
    shutil.copy(os.path.join(ROOT, 'build', 'data', 'recipes.json'), os.path.join(seed, 'recipes.json'))


# ---------------------------------------------------------------- מתכונים בתצוגה המקדימה (באתר האמיתי וורדפרס מציג אותם)
def parse_lines(text):
    out = []
    for line in (text or '').splitlines():
        line = re.sub(r'^\s*(?:[-*•·]|\d+[.)])\s*', '', line).strip()
        if not line:
            continue
        if re.search(r'[:：]\s*$', line):
            out.append(('heading', line.rstrip(':： \t')))
        else:
            out.append(('item', line))
    return out


def recipe_cover(title):
    return (f'<div class="recipe-cover" aria-hidden="true"><span class="recipe-cover__brand">{SITE["name"]} · מתכון</span>'
            f'<span class="recipe-cover__title">{esc(title)}</span></div>')


def recipe_visual(root, r, sizes, lazy=True):
    """תמונת המתכון, או כריכה מעוצבת כשאין תמונה (כמו hakef_recipe_visual בתבנית הוורדפרס)."""
    if r.get('img'):
        return img(root, r['img'], r['imgW'], r['imgH'], r.get('alt', r['title']), sizes, lazy=lazy)
    return recipe_cover(r['title'])


def recipe_card(root, r, heading='h2'):
    meta = f'<span class="r-card__meta">{esc(r["time"])}</span>' if r.get('time') else ''
    return (f'<article class="r-card"><a href="{root}מתכונים/{r["slug"]}/">{recipe_visual(root, r, "(max-width: 760px) 92vw, 380px")}'
            f'<{heading} class="r-card__name">{esc(r["title"])}</{heading}><p class="r-card__text">{esc(r["summary"])}</p>{meta}</a></article>')


def cta_recipes(root):
    return (f'<section class="cta-band"><div class="container cta-band__inner"><div><h2>אין זמן לאפות?</h2>'
            f'<p>בר קינוחים, מגשי פטיפורים ועוגות מעוצבות לאירוע שלכם, טריים ובהזמנה אישית. ירושלים והסביבה.</p></div>'
            f'<a class="btn btn--primary" href="{root}#plan" data-order="bar">לתכנון הבר והצעת מחיר <span aria-hidden="true">←</span></a></div></section>')


def build_recipes_preview():
    recipes = json.load(open(os.path.join(ROOT, 'build', 'data', 'recipes.json'), encoding='utf-8'))
    root = '../'
    crumbs, crumbs_ld = breadcrumbs(root, [('מתכונים', None)])
    cards = '\n      '.join(recipe_card(root, r) for r in recipes)
    head = f'''  <header class="page-head container">
    <p class="eyebrow">מתכונים</p>
    <h1>מתכונים מהמטבח של {SITE["name"]}</h1>
    <p class="lead">מתכונים ביתיים לעוגות, עוגיות וקינוחים, צעד אחר צעד. ואם אין זמן לאפות, אנחנו כאן.</p>
  </header>'''
    body = f"""{page_band(root, ("rec-coffee-nougat", 1600, 1196), crumbs, head)}
  <section class="container section--tight overlap" aria-label="רשימת המתכונים">
    <div class="r-grid">
      {cards}
    </div>
  </section>
  {cta_recipes(root)}"""
    layout('מתכונים/', f'מתכונים לעוגות, עוגיות וקינוחים | {SITE["name"]}',
           'מתכונים ביתיים לעוגות, עוגיות וקינוחים מהמטבח של הכיף שבאפיה: מצרכים ואופן הכנה, צעד אחר צעד.',
           body, extra_ld=[crumbs_ld])
    for r in recipes:
        path = f'מתכונים/{r["slug"]}/'
        root = '../../'
        crumbs, crumbs_ld = breadcrumbs(root, [('מתכונים', 'מתכונים/'), (r['title'], None)])
        ings, steps = parse_lines(r['ingredients']), parse_lines(r['steps'])
        n_ings = sum(1 for t, _ in ings if t == 'item')
        ing_html, open_ = '', False
        for t, x in ings:
            if t == 'heading':
                ing_html += ('</ul>' if open_ else '') + f'<h3>{esc(x)}</h3><ul class="checklist">'
                open_ = True
                continue
            if not open_:
                ing_html += '<ul class="checklist">'
                open_ = True
            ing_html += f'<li><label><input type="checkbox"><span>{esc(x)}</span></label></li>'
        ing_html += '</ul>' if open_ else ''
        st_html, open_, n = '', False, 0
        for t, x in steps:
            if t == 'heading':
                st_html += ('</ol>' if open_ else '') + f'<h3>{esc(x)}</h3><ol class="steps-list" start="{n + 1}">'
                open_ = True
                continue
            if not open_:
                st_html += '<ol class="steps-list">'
                open_ = True
            n += 1
            st_html += f'<li><span class="steps-num" aria-hidden="true">{n}</span>{esc(x)}</li>'
        st_html += '</ol>' if open_ else ''
        facts = ''.join(f'<li><span>{lbl}</span><strong>{esc(v)}</strong></li>' for lbl, v in
                        (('זמן הכנה', r.get('time')), ('כמות', r.get('yield')), ('מצרכים', str(n_ings) if n_ings else '')) if v)
        tip = f'<aside class="recipe__tip"><strong>טיפ מהמטבח</strong><p>{esc(r["tip"])}</p></aside>' if r.get('tip') else ''
        more = '\n'.join([recipe_card(root, o, 'h3') for o in recipes if o is not r][:3])
        body = f"""{crumbs}
  <article class="recipe container">
    <header class="recipe__head">
      <p class="eyebrow">מתכון מהמטבח של {SITE["name"]}</p>
      <h1>{esc(r["title"])}</h1>
      <p class="lead">{esc(r["summary"])}</p>
      <ul class="recipe__facts">{facts}</ul>
      <button class="text-link recipe__print" type="button" data-print>הדפסת המתכון</button>
    </header>
    <figure class="recipe__media">{recipe_visual(root, r, "(max-width: 900px) 92vw, 620px", lazy=False)}</figure>
    <div class="recipe__body">
      <section class="recipe__ingredients" aria-labelledby="ing-t"><h2 id="ing-t">מצרכים</h2>{ing_html}</section>
      <section class="recipe__steps" aria-labelledby="steps-t"><h2 id="steps-t">אופן ההכנה</h2>{st_html}</section>
    </div>
    {tip}
  </article>
  {cta_recipes(root)}
  <section class="container section--tight" aria-labelledby="more-t"><h2 id="more-t" class="related__title">עוד מתכונים</h2><div class="r-grid">{more}</div></section>"""
        rec_ld = {'@context': 'https://schema.org', '@type': 'Recipe', 'name': r['title'], 'description': r['summary'],
                  'author': {'@type': 'Organization', 'name': SITE['name']}, 'recipeCategory': 'קינוחים', 'inLanguage': 'he',
                  'recipeIngredient': [x for t, x in ings if t == 'item'],
                  'recipeInstructions': [{'@type': 'HowToStep', 'text': x} for t, x in steps if t == 'item']}
        if r.get('yield'):
            rec_ld['recipeYield'] = r['yield']
        if r.get('img'):
            rec_ld['image'] = [SITE['url'] + '/assets/img/' + urllib.parse.quote(f'{r["img"]}-{widths(r["img"])[-1]}.webp')]
        layout(path, f'{r["title"]} – מתכון | {SITE["name"]}', (r['summary'] + ' מצרכים ואופן הכנה, צעד אחר צעד.')[:160],
               body, extra_ld=[crumbs_ld, rec_ld], og_img=f'{r["img"]}-800.webp' if r.get('img') else 'og-image.jpg')

def main():
    products = json.load(open(os.path.join(ROOT, 'build', 'data', 'products.json'), encoding='utf-8'))
    build_home(products)
    build_shop(products)
    for c in CATEGORIES:
        build_category(c, products)
    for p in products:
        build_product(p, products)
    build_bar_page(products)
    build_static_pages()
    build_404()
    if not PRODUCTION:
        build_recipes_preview()   # באתר האמיתי וורדפרס מציג את המתכונים
    build_sitemap_robots()
    build_htaccess()
    build_wp_theme()
    if OUT != SRC_SITE:
        import shutil
        shutil.copytree(os.path.join(SRC_SITE, 'assets'), os.path.join(OUT, 'assets'), dirs_exist_ok=True)
        shutil.copy(os.path.join(ROOT, 'deploy', '.htaccess'), os.path.join(OUT, '.htaccess'))
        # IndexNow: קובץ המפתח בשורש האתר מאשר שאנחנו בעלי האתר, כדי לשלוח כתובות לבינג ולמנועים נוספים
        key = open(os.path.join(ROOT, 'build', 'data', 'indexnow-key.txt')).read().strip()
        with open(os.path.join(OUT, key + '.txt'), 'w') as f:
            f.write(key)
    print(f'נבנו {len(PAGES_FOR_SITEMAP)} עמודים ל-{OUT}', '(production)' if PRODUCTION else '(תצוגה מקדימה, noindex)')


if __name__ == '__main__':
    main()
