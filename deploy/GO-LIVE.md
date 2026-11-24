# העלייה לאוויר: hakef.com

## מצב השרת היום (מוכן לעלייה)

- **גיבוי מלא** של האתר הישן, לפני כל שינוי: `wp-content/ai1wm-backups/hakef-com-20261124-153836-ihd6zuiyj6j4.wpress` (‎1.6GB, כולל בסיס הנתונים). שחזור: All-in-One WP Migration ← Backups ← Restore.
- **וורדפרס 7.1.5** (העדכני ביותר).
- **תבנית פעילה:** "הכיף שבאפיה" (`wp-content/themes/hakef`). Astra ו־Twenty Twenty-Five נמחקו.
- **תוספים:** נשארו רק All-in-One WP Migration (גיבויים) ו־WP Mail SMTP (מיילים מהאתר).
  נמחקו: Elementor, Elementor Pro, WooCommerce, WooCommerce PayPal Payments, Contact Form 7, Flamingo, WPForms Lite, All in One SEO Pro ו־LiteSpeed Cache.
  התיקיות נמחקו ישירות, ולכן הנתונים שלהם (הזמנות ישנות, הודעות טפסים) נשארו בבסיס הנתונים ובגיבוי.
- **מתכונים:** קוד המתכונים מותקן ב־`wp-content/mu-plugins/`. חמשת המתכונים הישנים יובאו, וארבעה מהם קיבלו את התמונה המקורית.
- **`assets/`:** העיצוב, הגופנים והתמונות כבר נמצאים בשורש.
- **תיקיית בדיקה:** `/_hakef_new/` היא עותק מלא של האתר, עם noindex. שם נבדקו ה־`.htaccess` וההפניות בעברית על השרת האמיתי.
- **"האתר מתחדש":** המבקרים עדיין רואים את הדף הזה (`wp-content/mu-plugins/rebuild-notice.php`, קוד 503).

## העלייה (כ־5 דקות)

1. `python3 build/build.py --production --out dist`
2. הרשאת העלאה זמנית מ־Hostinger (`hosting_files_generate-upload-url` בחיבור של Hostinger) לקובץ JSON מחוץ למאגר, ואז:
   `HAKEF_CREDS=/path/creds.json bash deploy/golive.sh`
   הסקריפט:
   1. שומר עותק של ה־`.htaccess` הנוכחי.
   2. מעלה את האתר לשורש.
   3. מחליף את ה־`.htaccess`.
   4. מכבה את "האתר מתחדש", והקובץ נשמר בצד.
   5. מוחק את תיקיית הבדיקה.
3. ניקוי מטמון: `hosting_cache_clear` עבור hakef.com.
4. בדיקה: `node deploy/tools/verify_live.js`. כל העמודים צריכים להחזיר 200 בלי noindex, וכל ההפניות צריכות להיות "OK".

**חזרה אחורה:** `HAKEF_CREDS=... bash deploy/rollback.sh`, ואז ניקוי מטמון.

## אחרי העלייה

- Google Search Console: לשלוח את `https://hakef.com/sitemap.xml` ואת `https://hakef.com/wp-sitemap.xml`. ראו `docs/SEO.md`.
- משתמש לבעלת העסק בתפקיד "עורכת מתכונים" (משתמשים ← הוספת משתמש).
- **WP Mail SMTP מדווח שמיילים נכשלים.** צריך לבדוק את ההגדרות שלו, אחרת מיילים של איפוס סיסמה לא יגיעו.
