"""קבצים לסוכני בינה מלאכותית: גרסת Markdown לכל עמוד, קטלוג במבנה נתונים, llms.txt וכרטיס שרת MCP.

נקרא מ־build/build.py אחרי שכל העמודים נכתבו.
"""
import html
import json
import os
import re
import urllib.parse
from html.parser import HTMLParser

# הגדרות מתכנן הבר. חייבות להתאים ל־PLAN בראש site/assets/js/site.js
PLANNER = {
    'per_guest': {'after': 3, 'main': 6},
    'cake_from_guests': 50,
    'mix': [
        {'label': 'טארטלטים ופאים', 'share': 0.30},
        {'label': 'כוסות קינוח', 'share': 0.20},
        {'label': 'מוסים מעוצבים', 'share': 0.20},
        {'label': 'מקרונים, דונאטס וביסים', 'share': 0.30},
    ],
}

MCP_VERSION = '1.0.0'
MCP_PATH = '/mcp'


# ---------------------------------------------------------------- HTML ל־Markdown
class _MD(HTMLParser):
    SKIP = {'script', 'style', 'svg', 'form', 'button', 'input', 'select', 'textarea', 'noscript', 'picture', 'img',
            'dialog', 'template', 'nav', 'figure', 'video', 'audio', 'iframe'}
    BLOCK = {'p', 'div', 'section', 'article', 'header', 'footer', 'aside', 'main', 'ul', 'ol', 'table', 'details', 'blockquote'}

    def __init__(self, base):
        super().__init__(convert_charrefs=True)
        self.base, self.out, self.skip, self.href, self.link_text = base, [], 0, None, []
        self.stack = []

    def _hidden(self, attrs):
        a = dict(attrs)
        cls = a.get('class') or ''
        return ('hidden' in a or a.get('aria-hidden') == 'true' or 'visually-hidden' in cls.split() or 'hp' in cls.split())

    def handle_starttag(self, tag, attrs):
        if tag in ('br', 'hr', 'img', 'input', 'meta', 'link', 'source'):
            if not self.skip and tag == 'br':
                self.out.append('\n')
            return
        if self.skip or tag in self.SKIP or self._hidden(attrs):
            self.skip += 1
            self.stack.append(('skip', tag))
            return
        self.stack.append(('ok', tag))
        if re.fullmatch(r'h[1-6]', tag):
            self.out.append('\n\n' + '#' * int(tag[1]) + ' ')
        elif tag == 'li':
            self.out.append('\n- ')
        elif tag == 'table':
            self.first_row = True
            self.out.append('\n\n')
        elif tag == 'tr':
            self.cells = 0
            self.out.append('\n|')
        elif tag in ('td', 'th'):
            self.out.append(' ')
        elif tag == 'summary':
            self.out.append('\n\n**')
        elif tag in ('strong', 'b'):
            self.out.append('**')
        elif tag == 'a':
            href = dict(attrs).get('href') or ''
            self.href = urllib.parse.urljoin(self.base, href) if href and not href.startswith('#') else None
            self.link_text = []
        elif tag in self.BLOCK:
            self.out.append('\n\n')

    def handle_endtag(self, tag):
        if not self.stack:
            return
        kind, t = self.stack.pop()
        while t != tag and self.stack:   # תגיות שלא נסגרו כראוי
            if kind == 'skip':
                self.skip -= 1
            kind, t = self.stack.pop()
        if kind == 'skip':
            self.skip -= 1
            return
        if tag in ('td', 'th'):
            self.out.append(' |')
            self.cells += 1
        elif tag == 'tr':
            if getattr(self, 'first_row', False):
                self.out.append('\n|' + ' --- |' * self.cells)
                self.first_row = False
        elif re.fullmatch(r'h[1-6]', tag) or tag in self.BLOCK:
            self.out.append('\n\n')
        elif tag == 'summary':
            self.out.append('**\n\n')
        elif tag in ('strong', 'b'):
            self.out.append('**')
        elif tag == 'a':
            text = ''.join(self.link_text).strip()
            if self.href and text:
                self.out.append(f'[{text}]({self.href})')
            elif text:
                self.out.append(text)
            self.href, self.link_text = None, []

    def handle_data(self, data):
        if self.skip:
            return
        text = re.sub(r'\s+', ' ', data)
        if self.stack and self.stack[-1][1] == 'a' or any(t == 'a' for _, t in self.stack):
            self.link_text.append(text)
        else:
            self.out.append(text)

    def markdown(self):
        s = ''.join(self.out)
        s = re.sub(r'[ \t]+\n', '\n', s)
        s = re.sub(r'\n[ \t]+', '\n', s)
        s = re.sub(r'\*\*\s*\*\*', '', s)
        s = re.sub(r'(?m)^-\s*$\n?', '', s)
        s = re.sub(r'(?m)^\|\s*$\n?', '', s)
        s = re.sub(r'\n{3,}', '\n\n', s)
        return s.strip()


def _meta(doc, pattern):
    m = re.search(pattern, doc, flags=re.S)
    return html.unescape(m.group(1)).strip() if m else ''


def page_markdown(doc, site):
    title = _meta(doc, r'<title>(.*?)</title>')
    desc = _meta(doc, r'<meta name="description" content="([^"]*)"')
    canon = _meta(doc, r'<link rel="canonical" href="([^"]*)"')
    main = re.search(r'<main id="main">(.*?)</main>', doc, flags=re.S)
    p = _MD(canon or site['url'] + '/')
    p.feed(main.group(1) if main else '')
    body = p.markdown()
    head = f'# {title}\n\n' + (f'> {desc}\n\n' if desc else '') + f'כתובת העמוד: {urllib.parse.unquote(canon)}\n'
    foot = (f'\n\n---\n\n## יצירת קשר והזמנות\n\n'
            f'- וואטסאפ: https://wa.me/{site["wa"]} ({site["phone"]})\n'
            f'- טלפון: {site["phone"]}\n- מייל: {site["email"]}\n- אזור שירות: {site["area"]}\n'
            f'- סיכום האתר לסוכנים: {site["url"]}/llms.txt\n')
    # הכותרת הראשית כבר מופיעה בראש הקובץ כ־title, אז ה־H1 שבגוף יורד לרמה 2 כדי שיהיה H1 אחד
    body = re.sub(r'(?m)^# ', '## ', body)
    return head + '\n' + body + foot


def write_page_markdown(out_dir, site):
    n = 0
    for root, _, files in os.walk(out_dir):
        if os.sep + 'assets' in root:
            continue
        if 'index.html' in files:
            doc = open(os.path.join(root, 'index.html'), encoding='utf-8').read()
            if '<main id="main">' not in doc:
                continue
            open(os.path.join(root, 'index.md'), 'w', encoding='utf-8').write(page_markdown(doc, site))
            n += 1
    return n


# ---------------------------------------------------------------- קטלוג במבנה נתונים
def catalog(site, categories, products, recipes, abs_url):
    cat_name = {c['slug']: c['name'] for c in categories}
    return {
        'business': {
            'name': site['name'], 'url': site['url'] + '/', 'description': 'קייטרינג קינוחים בוטיק: בר קינוחים ופטיפורים לאירועים, מגשי פטיפורים, עוגות מעוצבות ומארזי מתוקים בהזמנה אישית.',
            'area': site['area'], 'phone': site['phone'], 'whatsapp': f'https://wa.me/{site["wa"]}', 'email': site['email'],
            'kosher': 'כשרות מהודרת: מוצרים בהשגחת העדה החרדית, רובין ולנדאו.', 'payment': 'ביט או העברה בנקאית. מקדמה אחרי אישור ההצעה.',
            'lead_time': 'בר קינוחים: לפחות שבוע לפני האירוע. שאר ההזמנות: כ־4 ימים מראש.',
            'response': 'מענה מהיר יותר בוואטסאפ.',
            'ordering': 'שולחים בקשה בוואטסאפ או במייל, מקבלים הצעת מחיר אישית, וכל הזמנה מאושרת לפני שמתחילים להכין.',
            'prices_page': abs_url('prices/'),
        },
        'currency': 'ILS',
        'price_note': 'מחירי התחלה מהמחירון של העסק. המחיר הסופי נקבע לפי העיצוב, הגודל והכמות, בהצעת מחיר אישית.',
        'categories': [{'name': c['name'], 'url': abs_url(f'product-category/{c["slug"]}/')} for c in categories],
        'products': [{
            'name': p['name'], 'url': abs_url(f'product/{p["slug"]}/'), 'category': cat_name.get(p['cats'][0], ''),
            'summary': p['short'], 'price_from': p.get('price_from'), 'price_unit': p.get('price_unit') or None,
        } for p in products],
        'recipes': [{'name': r['title'], 'url': abs_url(f'מתכונים/{r["slug"]}/')} for r in recipes],
        'planner': PLANNER,
    }


def price_text(p):
    if not p.get('price_from'):
        return 'לפי הצעת מחיר'
    return f'החל מ־{p["price_from"]} ₪' + (f' {p["price_unit"]}' if p.get('price_unit') else '')


# ---------------------------------------------------------------- llms.txt
def llms_txt(site, categories, products, recipes, abs_url):
    cat_name = {c['slug']: c['name'] for c in categories}
    L = [f'# {site["name"]}', '',
         f'> קייטרינג קינוחים בוטיק ב{site["area"]}: בר קינוחים ופטיפורים מעוצב לאירועים (בר מצווה, בת מצווה, ברית, אירוסין, שבע ברכות, אירועי חברה), '
         f'מגשי פטיפורים, עוגות מעוצבות ומארזי מתוקים בהזמנה אישית. כשרות מהודרת: מוצרים בהשגחת העדה החרדית, רובין ולנדאו. '
         f'הזמנות והצעות מחיר בוואטסאפ {site["phone"]} (שם המענה הכי מהיר).', '',
         'בר קינוחים מזמינים לפחות שבוע לפני האירוע, ושאר ההזמנות כ־4 ימים מראש.', '',
         'מחירים באתר הם מחירי התחלה. המחיר הסופי נקבע לפי העיצוב, הגודל והכמות, בהצעת מחיר אישית. כל הזמנה מאושרת עם הלקוח לפני שמתחילים להכין. '
         'תשלום בביט או בהעברה בנקאית.', '',
         '## עמודים עיקריים', '',
         f'- [דף הבית ומתכנן הבר]({abs_url("")}): מתכנן שמעריך כמה יחידות צריך לפי מספר האורחים, ובקשת הצעת מחיר.',
         f'- [בר קינוחים לאירועים]({abs_url("בר-קינוחים-לאירועים/")}): מה כולל הבר, לאילו אירועים, ושאלות נפוצות.',
         f'- [מחירון]({abs_url("prices/")}): מחירי התחלה לכל המוצרים.',
         f'- [כל המוצרים]({abs_url("shop/")})',
         f'- [אודות]({abs_url("about/")})', f'- [צור קשר]({abs_url("contact/")})',
         f'- [מתכונים]({abs_url("מתכונים/")}): מתכונים ביתיים מהמטבח של העסק.', '',
         '## קטגוריות', '']
    for c in categories:
        L.append(f'- [{c["name"]}]({abs_url("product-category/" + c["slug"] + "/")})')
    L += ['', '## מוצרים ומחירי התחלה', '']
    for p in products:
        L.append(f'- [{p["name"]}]({abs_url("product/" + p["slug"] + "/")}): {price_text(p)}. {p["short"]} ({cat_name.get(p["cats"][0], "")})')
    L += ['', '## כמה פטיפורים צריך לאירוע', '',
          f'- קינוח אחרי ארוחה: כ־{PLANNER["per_guest"]["after"]} יחידות לאורח.',
          f'- כשהבר הוא הכיבוד המרכזי: כ־{PLANNER["per_guest"]["main"]} יחידות לאורח.',
          f'- מ־{PLANNER["cake_from_guests"]} אורחים מומלץ להוסיף עוגה מרכזית מעוצבת.',
          '- הרכב מומלץ: ' + ', '.join(f'{m["label"]} {round(m["share"] * 100)}%' for m in PLANNER['mix']) + '.', '',
          '## יצירת קשר', '',
          f'- וואטסאפ: https://wa.me/{site["wa"]}', f'- טלפון: {site["phone"]}', f'- מייל: {site["email"]}', f'- אזור שירות: {site["area"]}', '',
          '## לסוכנים', '',
          '- לכל עמוד יש גרסת Markdown: אותה כתובת עם index.md בסוף, או בקשה עם הכותרת Accept: text/markdown.',
          f'- קטלוג המוצרים והמחירים כנתונים: {site["url"]}/assets/data/catalog.json',
          f'- שרת MCP (קריאה בלבד): {site["url"]}{MCP_PATH}. כרטיס השרת: {site["url"]}/.well-known/mcp/server-card.json',
          '', '## Optional', '']
    for r in recipes:
        L.append(f'- [{r["title"]}]({abs_url("מתכונים/" + r["slug"] + "/")})')
    L += [f'- [מדיניות פרטיות]({abs_url("privacy-policy-1/")})', f'- [הצהרת נגישות]({abs_url("הצהרת-נגישות/")})', '']
    return '\n'.join(L)


# ---------------------------------------------------------------- כרטיס שרת MCP
MCP_TOOLS = [
    {'name': 'get_business_info', 'title': 'פרטי העסק',
     'description': 'פרטי הכיף שבאפיה: מה העסק מכין, אזור שירות, כשרות, תשלום, איך מזמינים ודרכי קשר.'},
    {'name': 'list_products', 'title': 'מוצרים ומחירים',
     'description': 'רשימת המוצרים עם קישור, תיאור קצר ומחיר התחלה בשקלים. אפשר לסנן לפי קטגוריה.'},
    {'name': 'estimate_dessert_bar', 'title': 'הערכת כמויות לבר קינוחים',
     'description': 'מעריך כמה יחידות פטיפורים צריך לאירוע לפי מספר האורחים ותפקיד הבר, עם הרכב מומלץ.'},
    {'name': 'prepare_quote_request', 'title': 'הכנת בקשה להצעת מחיר',
     'description': 'מכין נוסח בקשה להצעת מחיר וקישור וואטסאפ אליו. לא שולח דבר: הלקוח שולח בעצמו.'},
]


def server_card(site):
    endpoint = site['url'] + MCP_PATH
    return {
        'name': 'com.hakef/dessert-catering',
        'title': site['name'],
        'version': MCP_VERSION,
        'description': 'מידע על הכיף שבאפיה, קייטרינג קינוחים בוטיק בירושלים: מוצרים ומחירי התחלה, הערכת כמויות לבר קינוחים והכנת בקשה להצעת מחיר. קריאה בלבד, בלי הרשמה.',
        'websiteUrl': site['url'] + '/',
        'serverInfo': {'name': 'hakef', 'title': site['name'], 'version': MCP_VERSION},
        'protocolVersion': '2025-06-18',
        'endpoint': endpoint,
        'transport': {'type': 'streamable-http', 'url': endpoint},
        'remotes': [{'type': 'streamable-http', 'url': endpoint}],
        'authentication': {'required': False},
        'capabilities': {'tools': {'listChanged': False}},
        'tools': MCP_TOOLS,
    }


def write_all(out_dir, site, categories, products, recipes, abs_url):
    n = write_page_markdown(out_dir, site)
    data_dir = os.path.join(out_dir, 'assets', 'data')
    os.makedirs(data_dir, exist_ok=True)
    json.dump(catalog(site, categories, products, recipes, abs_url), open(os.path.join(data_dir, 'catalog.json'), 'w', encoding='utf-8'),
              ensure_ascii=False, indent=1)
    open(os.path.join(out_dir, 'llms.txt'), 'w', encoding='utf-8').write(llms_txt(site, categories, products, recipes, abs_url))
    wk = os.path.join(out_dir, '.well-known', 'mcp')
    os.makedirs(wk, exist_ok=True)
    json.dump(server_card(site), open(os.path.join(wk, 'server-card.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    return n
