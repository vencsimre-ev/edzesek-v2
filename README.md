# Edzések v2

Mászóedzések, résztvevők, részvételek, bérletalkalmak és manuálisan
rögzített befizetések kezelésére készülő webalkalmazás.

A regisztráció, email-megerősítés és admin jóváhagyás elkészült.
Az edzéskezelési funkciók később készülnek el.

## Jelenlegi állapot

- Laravel 13 projekt létrehozva.
- Helyi SQLite-adatbázis és az alapmigrációk működnek.
- Frontend függőségek telepítve, a build ellenőrizve.
- Az alkalmazás helyben elindul.
- Git-verziókövetés és GitHub-kapcsolat beállítva.
- Bootstrap 5 bekötve Vite-on keresztül.
- Közös Blade layout és magyar Bootstrap auth-felületek.
- Laravel Fortify: regisztráció, belépés, Remember me, POST kijelentkezés,
  email-megerősítés és újraküldés, elfelejtett jelszó és visszaállítás.
- Admin jóváhagyási lista, jogosultságvédelem és emailes értesítések.
- Interaktív adminlétrehozó parancs és auth feature tesztek.

## Technológiák

- Laravel 13
- PHP 8.3+
- SQLite
- Node.js 24 LTS és npm
- Vite
- Blade
- Bootstrap 5
- Laravel Fortify
- Mailpit a helyi emailteszteléshez

Tervezett kiegészítések:

- Google-belépés Laravel Socialite használatával

## Helyi telepítés

Szükséges: PHP 8.3+, Composer 2, Node.js 24 LTS, npm és Git.

A szükséges PHP-bővítmények között legyen SQLite-támogatás
(`pdo_sqlite` és `sqlite3`), mbstring, XML, cURL és ZIP.

```bash
git clone https://github.com/vencsimre-ev/edzesek-v2.git
cd edzesek-v2

composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

npm ci
npm run build
php artisan serve
```

Az alkalmazás címe: http://127.0.0.1:8000

Meglévő telepítés frissítésekor:

```bash
composer install
php artisan migrate
php artisan config:clear
npm ci
npm run build
```

A migráció a felhasználókhoz szerepet és jóváhagyási adatokat ad hozzá.
A meglévő fiókok alapértelmezetten `student` és `pending` állapotot kapnak;
a meglévő email-megerősítésük megmarad. A helyi adatbázison ne futtass
`migrate:fresh` parancsot.

A fenti parancsok új klónozásra vonatkoznak. Meglévő telepítés
frissítésekor ne írd felül a `.env` fájlt vagy az adatbázist.

## Frontend fejlesztés

Egy terminálban indítsd el a Laravel fejlesztői szerverét:

```bash
php artisan serve
```

Egy másik terminálban indítsd el a Vite fejlesztői szerverét:

```bash
npm run dev
```

A frontend fájlok külön builddel is előállíthatók:

```bash
npm run build
```

## Ellenőrzések

```bash
php artisan test
npm audit
npm run build
```

## Függőségi javítás

A `concurrently` jelenleg a sérülékeny `shell-quote` 1.9.0 verzióját
kéri. Egy célzott npm override a javított 1.11.0 verziót használja
a GHSA-pqg4-j6r4-53mv sérülékenység kezelésére.

A `concurrently` frissítésekor ellenőrizni kell az override
szükségességét. Eltávolítható, ha a függőség nélküle is javított
verzióra oldódik fel.

## Mailpit és helyi konfiguráció

A Laravel a hoston fut, és a már futó Mailpit SMTP-szerverét használja.
Nem szükséges második konténert indítani. A `.env.example` helyi példái:

```dotenv
APP_URL=http://127.0.0.1:8000
APP_LOCALE=hu
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
```

Mailpit webfelület: http://127.0.0.1:8025. A küldés szinkron, az auth-
és jóváhagyási levelekhez nem kell queue worker. A böngészőben is a
`127.0.0.1:8000` címet használd, hogy a session és az aláírt linkek
azonos hosthoz tartozzanak. Környezetváltozó módosítása után:

```bash
php artisan config:clear
```

Helyi HTTP alatt a Secure cookie alapértelmezetten kikapcsolt,
a HttpOnly bekapcsolt, a SameSite `lax`. Productionben HTTPS-es
`APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`,
`SESSION_SECURE_COOKIE=true` és `SESSION_HTTP_ONLY=true` szükséges;
az SMTP-t a tényleges szolgáltatóhoz kell konfigurálni.
Productionben a Secure cookie explicit beállítás nélkül is bekapcsolt.

## Első admin létrehozása

A migrációk futtatása után:

```bash
php artisan app:create-admin
```

A parancs nevet, emailcímet, rejtetten megadott jelszót és rejtett
jelszómegerősítést kér. A jelszó legalább 8 karakter legyen. Kizárólag
új fiókot hoz létre; foglalt emailcímnél hibával kilép, és meglévő
studentet nem emel adminná. Nincs rögzített adminjelszó vagy adminseeder.

A megbízható CLI-folyamat `admin` + `approved` fiókot hoz létre,
kitöltött `email_verified_at` és `approved_at` mezőkkel. Az `approved_by`
ilyenkor `null`, mert a létrehozást nem egy bejelentkezett admin végzi.
Az admin adatait te adod meg; a megvalósítás nem hozott létre helyi admint.

## Hozzáférési szabályok

| Fiók állapota | Elérhető hitelesített funkciók |
| --- | --- |
| Nincs belépve | Regisztráció, belépés, elfelejtett jelszó és visszaállítás |
| Nem megerősített email | Megerősítő oldal, megerősítés, újraküldés, kijelentkezés |
| Megerősített, pending | Jóváhagyásra váró oldal, kijelentkezés |
| Megerősített, approved student | Védett kezdőoldal (`/home`) |
| Megerősített, approved admin | Védett kezdőoldal és jóváhagyások (`/admin/approvals`) |

A nyilvános kezdőlap minden állapotban elérhető. A PHP backed enumok:
`Role` (`admin`, `student`) és `ApprovalStatus` (`pending`, `approved`).
A `users` tábla `created_at` és `updated_at` időbélyegeit az Eloquent
automatikusan kezeli regisztrációkor és adminlétrehozáskor is. Jóváhagyáskor
a `created_at` megmarad, az `updated_at` frissül; ismételt jóváhagyáskor
egyik időbélyeg sem változik.

A regisztráció mindig `student` + `pending`, és nem fogad el szerepet,
jóváhagyási vagy email-megerősítési adatot a kliensből.

A védett route-ok middleware-sorrendje `auth`, `verified`, `approved`,
az admin route-oknál ezenfelül `admin`. A Remember me is ezen a védelmen
halad át. A Fortify szolgáltatja az auth route-okat és folyamatokat
a [Laravel 13 hivatalos Fortify dokumentációja](https://github.com/laravel/docs/blob/13.x/fortify.md) alapján;
a további jelszómegerősítő auth route-ok is korlátozottak pending fióknál.
A belépés email + IP szerint percenként 5 kísérletre, a megerősítő
levelek és linkek percenként 6 kérésre korlátozottak.

Email-megerősítés után a megerősített és approved adminok levelet kapnak.
Admin csak megerősített emailű pending fiókot hagyhat jóvá, CSRF-védett
PATCH kéréssel. A jóváhagyási action rögzíti az állapotot, az időpontot és
az admin azonosítóját. Ismételt kérés nem változtatja meg ezeket és nem
küld újabb levelet. Az admin- és jóváhagyási értesítések csak az
adatbázis-tranzakció sikeres lezárása után mennek ki; visszagörgetéskor
nem küldődnek el. A jóváhagyó törlésekor az `approved_by` nullázódik.

## Auth tesztelése

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

A PHPUnit a projekt meglévő tesztkeretrendszere. A tesztek kényszerítetten
külön, memóriabeli SQLite-adatbázison (`:memory:`) és fake értesítésekkel
futnak; a helyi adatbázishoz és a Mailpithez nem nyúlnak.
Az auth feature tesztek a regisztrációt és jogosultságemelés elleni
védelmet, állapotellenőrzéseket, admin-műveleteket, tranzakciókat,
megerősítő linkeket, jelszó-visszaállítást, Remember me működést,
rate limiteket, CSRF-et és az adminparancsot ellenőrzik.

Kézi böngészős ellenőrzés a helyi szerver és Mailpit mellett:

1. Hozz létre admint a fenti paranccsal, majd egy másik böngészőprofilban
   regisztrálj kitalált studentadatokkal. A `/home` a megerősítő oldalra vigyen.
2. A Mailpitben nyisd meg a megerősítő levelet, kattints a linkre ugyanabban
   a studentprofilban. A jóváhagyásra váró oldal jelenjen meg, az adminnak
   érkezzen értesítés. Próbáld ki a levél újraküldését is külön regisztrációval.
3. Adminként lépj be a `/admin/approvals` oldalra. A megerősített pending
   student szerepeljen, a nem megerősített fiók ne. Hagyd jóvá a studentet.
4. Ellenőrizd a jóváhagyási levelet a Mailpitben; a student már érje el a
   `/home` oldalt. Az admin URL-je és PATCH művelete studentként legyen tiltott.
5. Próbáld ki a kijelentkezést, a Remember me belépést, az elfelejtett jelszót
   és a visszaállító levél linkjét. A régi jelszó ne működjön, az új működjön.
6. Hibás vagy lejárt megerősítő link ne legyen elfogadható; már jóváhagyott
   fiók ismételt jóváhagyása ne küldjön második levelet.

A Google-belépés és az edzéskezelés még nincs megvalósítva.

## Adatok és titkos konfiguráció

- Jelszó, hozzáférési token, privát kulcs és valódi résztvevői adat
  nem kerülhet a repositoryba.
- A `.env` és a helyi SQLite-adatbázis ki van zárva a Gitből.
- A `.env.example` kizárólag megosztható példaértékeket tartalmazhat.
- A későbbi factoryk és seederek csak kitalált mintaadatokat használhatnak.
- A Composer és az npm lockfájljait verziókövetjük.
- A telepített függőségek és a generált buildfájlok nincsenek verziókövetve.

