#!/usr/bin/env bash
# העלאת האתר החדש ל-hakef.com. הפיך: ראו deploy/rollback.sh.
#
# לפני ההרצה:
#   1. python3 build/build.py --production --out dist
#   2. הרשאת העלאה זמנית מ-Hostinger (hosting_files_generate-upload-url) בקובץ JSON,
#      והנתיב אליו במשתנה HAKEF_CREDS. לא לשמור אותו במאגר.
# אחרי ההרצה: לנקות את המטמון (hosting_cache_clear) ולהריץ deploy/tools/verify_live.js.
set -euo pipefail
cd "$(dirname "$0")/.."
: "${HAKEF_CREDS:?צריך להגדיר HAKEF_CREDS לקובץ ההרשאה הזמנית}"
T=deploy/tools
TMP=$(mktemp -d)

[ -f dist/index.html ] && grep -q 'noindex' dist/index.html && { echo "dist נבנה בלי --production (יש noindex). עצירה."; exit 1; }

echo "1/5 גיבוי ה-.htaccess הנוכחי"
python3 -I $T/fb.py cp .htaccess .htaccess.before-golive

echo "2/5 העלאת קובצי האתר לשורש"
python3 -I $T/manifest.py golive "$TMP/m.json"
python3 -I $T/upload.py "$HAKEF_CREDS" "$TMP/m.json"

echo "3/5 העלאת ה-.htaccess החדש"
printf '[{"local": "%s", "remote": ".htaccess"}]' "$PWD/dist/.htaccess" > "$TMP/h.json"
python3 -I $T/upload.py "$HAKEF_CREDS" "$TMP/h.json"

echo "4/5 כיבוי דף 'האתר מתחדש' (הקובץ נשמר בצד ואפשר להחזיר)"
python3 -I $T/fb.py mv wp-content/mu-plugins/rebuild-notice.php wp-content/rebuild-notice.php.off

echo "5/5 הסרת תיקיית הבדיקה"
python3 -I $T/fb.py rm _hakef_new || true

rm -rf "$TMP"
echo "בוצע. עכשיו: ניקוי מטמון (hosting_cache_clear) ובדיקה: node deploy/tools/verify_live.js"
