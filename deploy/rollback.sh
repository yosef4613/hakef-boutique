#!/usr/bin/env bash
# חזרה למצב שלפני העלייה: ה-.htaccess הקודם ודף "האתר מתחדש".
# הקבצים הסטטיים נשארים בשרת אבל לא מוגשים כדף הבית (ה-.htaccess הקודם מפנה הכול לוורדפרס).
# לשחזור מלא של האתר הישן: קובץ הגיבוי wp-content/ai1wm-backups/hakef-com-20261124-153836-*.wpress
# (All-in-One WP Migration ← Backups ← Restore).
set -euo pipefail
cd "$(dirname "$0")/.."
: "${HAKEF_CREDS:?צריך להגדיר HAKEF_CREDS לקובץ ההרשאה הזמנית}"
T=deploy/tools
python3 -I $T/fb.py cp .htaccess.before-golive .htaccess
python3 -I $T/fb.py mv wp-content/rebuild-notice.php.off wp-content/mu-plugins/rebuild-notice.php
# העמודים הסטטיים (התיקייה assets נשארת, כי עמודי המתכונים משתמשים בה)
python3 -I $T/fb.py rm index.html 404.html sitemap.xml robots.txt about contact shop product product-category \
  privacy-policy-1 "בר-קינוחים-לאירועים" "הצהרת-נגישות" || true
echo "הוחזר. לנקות מטמון (hosting_cache_clear)."
