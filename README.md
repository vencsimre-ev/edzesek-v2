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
- Google-regisztráció és belépés Socialite-tal, explicit fiók-összekapcsolás.
- Sanctum session alapú REST API: saját állapot, adminlista és jóváhagyás.
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
- Laravel Socialite és Sanctum
- Mailpit a helyi emailteszteléshez

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

Az edzéskezelés még nincs megvalósítva. A Google-folyamat kézi ellenőrzése
a következő szakaszban olvasható.

## Google-regisztráció és belépés

A Google OAuth session alapú webfolyamat, Socialite state-ellenőrzéssel.
Új fiókhoz a Google által megerősített emailcím szükséges. A fiók
`student` + `pending` állapotot kap, kitöltött `email_verified_at` mezővel;
az adminnak ugyanaz az értesítés megy, mint normál email-megerősítéskor.
Az alkalmazás eléréséhez továbbra is admin jóváhagyás kell.

A `users.google_id` egyedi, nullable Google-azonosító. Az összekapcsolás
üzleti szabályai az `AuthenticateWithGoogle` actionben vannak. Google
access/refresh tokent nem tárolunk. A már összekapcsolt fiókot a Google
stabil azonosítója alapján ismerjük fel; helyi nevét, emailcímét, szerepét
és jóváhagyását belépéskor nem írjuk felül.

Email-egyezés alapján nincs automatikus összekapcsolás vagy belépés.
Meglévő fióknál előbb a saját jelszóval kell belépni és megerősíteni
az emailcímet, majd a kezdőoldalon vagy a pending oldalon megnyomni a
**Google-fiók összekapcsolása** gombot. Ez CSRF-védett POST műveletből
indul. A Google és a helyi emailcímnek egyeznie kell, másik fiókhoz tartozó
Google-azonosító nem használható, meglévő kapcsolat nem cserélhető le.
A callbacknek ugyanahhoz a bejelentkezett sessionhöz kell visszatérnie.

Új Google-fiók helyi jelszava egy ismeretlen, véletlen érték hash-e.
Az elfelejtett jelszó folyamatával később helyi jelszó állítható be.
Google-belépéskor nincs automatikus Remember me bekapcsolás.

### Helyi Google-beállítás

A Google Cloud Console-ban hozz létre OAuth **Web application** klienst,
állítsd be a consent screent, és szükség esetén add hozzá a tesztfelhasználókat.
Engedélyezett redirect URI pontosan:

```text
http://127.0.0.1:8000/auth/google/callback
```

A `.env` fájlban add meg a saját értékeidet; ne kerüljenek Gitbe:

```dotenv
GOOGLE_CLIENT_ID=<saját kliensazonosító>
GOOGLE_CLIENT_SECRET=<saját kliens titka>
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000,localhost:8000
```

Ezután `php artisan config:clear`. Hiányzó Google-konfigurációval a
Google-gomb rejtett, a Google-route-ok 503-at adnak. A `.env.example`
nem tartalmaz valódi Google-adatokat. A production callback és stateful
host a tényleges HTTPS-es alkalmazáscímhez igazodjon.

| Web endpoint | Működés |
| --- | --- |
| GET `/auth/google/redirect` | Vendég Google-belépés/regisztráció indítása |
| POST `/auth/google/link` | Belépett, verified fiók explicit összekapcsolása |
| GET `/auth/google/callback` | OAuth callback, session/state ellenőrzéssel |

Kézi ellenőrzés saját Google-konfigurációval:

1. Új Google-emaillel regisztrálj; a pending oldalra kerülj, az admin
   kapjon értesítést. Jóváhagyás után a következő Google-belépés érje el a `/home` oldalt.
2. Meglévő helyi emaillel próbálj Google-belépést: ne lépjen be automatikusan.
   Jelszavas belépés után kapcsold össze a fiókot, majd kijelentkezés után
   ellenőrizd a Google-belépést.
3. Másik emailcímű Google-fiókkal az összekapcsolás hibázzon; a helyi
   szerep és jóváhagyás maradjon változatlan. Próbáld ki a Google-hozzájárulás
   megszakítását is: magyar hibajelzés után újra indítható legyen a folyamat.

## REST API és backend szerkezet

Az [appointment-api referencia](https://github.com/vencsimre-ev/appointment-api)
Requests, Resources, Controllers, Services, Enums és `routes/api.php`
fájljait megvizsgáltuk. Az elkülönített rétegeket, explicit resource mezőket,
enumértékeket és erőforrás/üzleti művelet route-okat vettük át.
A referencia általános válaszburkolóját és üzleti szabályait nem másoltuk.

- `Controllers/Web`: Blade és redirect; `Controllers/Api/V1`: JSON resource.
- `Requests`: saját kérésvalidáció és policy alapú engedélyezés.
- `Resources/UserResource`: kizárólag explicit válaszmezők; dátumok ISO 8601 UTC-ben.
- `Actions`: közös jóváhagyás, Google-fiókkezelés és felhasználólista.
- `Services/GoogleAuthentication`: OAuth/session folyamat.
- `Policies/UserPolicy`: saját állapot és admin-műveletek engedélyezése.
- `routes/api.php`: `/api/v1`, admin erőforrásnál `apiResource(...)->only('index')`.

A Blade közvetlenül a web controllereken keresztül hívja a közös actionöket;
nem kérdez HTTP-n az alkalmazás saját API-jától. Nincs teljes user CRUD,
repository-réteg, univerzális response-wrapper, JWT vagy személyes API-token.

### API-hitelesítés

Sanctum `statefulApi()` middleware és `auth:sanctum` guard használatos,
a Fortify meglévő `web` sessionjével. Tokenes hitelesítés kikapcsolt.
A stateful kérés `Origin` vagy `Referer` hostja szerepeljen a
`SANCTUM_STATEFUL_DOMAINS` listában, porttal együtt. A saját böngészős
kliens ugyanarról az originről hívja az API-t, küldje a cookie-kat,
és kérjen JSON-t: `Accept: application/json`.

Belépés előtt GET `/sanctum/csrf-cookie`, majd a Fortify POST `/login`
hívása szükséges, vagy a meglévő Blade/Google-belépés használható.
Az URL-dekódolt `XSRF-TOKEN` cookie értékét minden állapotmódosító
kérésnél küldd `X-XSRF-TOKEN` headerben. A session/CSRF cookie-kat a
Laravel kezeli; a védett API nem mentesül a CSRF-ellenőrzés alól.
Külön originről futó frontendhez később külön CORS-konfiguráció kell;
a jelenlegi megoldás az alkalmazás saját originjét célozza.

### Endpointok

| Endpoint | Jogosultság | Sikeres válasz |
| --- | --- | --- |
| GET `/api/v1/me` | Belépett; pending és nem verified is | 200, `data`: UserResource |
| GET `/api/v1/admin/users` | Verified + approved + admin | 200, lapozott resource collection |
| PATCH `/api/v1/admin/users/{user}/approve` | Verified + approved + admin | 200, `data`: UserResource |

Listaszűrés: `approval_status=pending` vagy `approved`. A pending listába
csak verified felhasználók kerülnek. Szűrő nélkül az admin minden fiókot
láthat. `per_page`: 1–100, alapérték 20; `page`: pozitív egész.
A lapozási linkek megőrzik a validált szűrőt és az oldalméretet.

Böngészőkonzolból, már belépett admin sessionjével:

```javascript
await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
const xsrf = () => decodeURIComponent(
    document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN=')).slice('XSRF-TOKEN='.length)
);
const me = await fetch('/api/v1/me', {
    credentials: 'same-origin', headers: { Accept: 'application/json' }
}).then(response => response.json());
const users = await fetch('/api/v1/admin/users?approval_status=pending&per_page=20', {
    credentials: 'same-origin', headers: { Accept: 'application/json' }
}).then(response => response.json());
// A listából választott, megerősített pending felhasználó azonosítója.
const userId = users.data[0].id;
const approved = await fetch(`/api/v1/admin/users/${userId}/approve`, {
    method: 'PATCH', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() }
}).then(response => response.json());
```

Példa `GET /api/v1/me` válaszra, kizárólag kitalált adatokkal:

```json
{
  "data": {
    "id": 2,
    "name": "Minta Tanuló",
    "email": "student@example.test",
    "role": "student",
    "approval_status": "pending",
    "email_verified_at": "2026-10-12T10:00:00.000000Z",
    "approved_at": null,
    "approved_by": null,
    "created_at": "2026-10-12T10:00:00.000000Z",
    "updated_at": "2026-10-12T10:00:00.000000Z"
  }
}
```

A lista válasza `data` tömb, `links` és `meta` (például `current_page`,
`per_page`, `total`). A jóváhagyási PATCH kérésnek nincs kötelező törzse;
a szerver választja ki a jóváhagyót és az állapotot. A válaszban
`approval_status` értéke `approved`, az `approved_at`, `approved_by` és
`updated_at` frissített. Ismételt web/API jóváhagyás is 200-as API-választ
ad, és nem módosítja az adatokat vagy küld újabb értesítést.
Jelszó, remember token, Google-azonosító és OAuth-token egyik resource-ban sem szerepel.

| Hibakód | Jelentés |
| --- | --- |
| 401 | Nincs érvényes belépett session |
| 403 | Hiányzó email-megerősítés, jóváhagyás vagy admin jogosultság |
| 404 | Nem létező felhasználó vagy endpoint |
| 422 | Hibás szűrő/lapozás, vagy nem megerősített célfiók jóváhagyása |
| 419 | Hiányzó/hibás CSRF-token vagy lejárt session |

Az API hibák `message` mezős JSON-válaszok, 422-nél `errors` mezővel is;
`Accept` header hiányában sem adnak HTML-t vagy login redirectet.
A jóváhagyás idempotens, ezért az ismétléshez nem használunk 409-et.
Példa hibás szűrőre:

```json
{
  "message": "A(z) jóváhagyási állapot értéke érvénytelen.",
  "errors": { "approval_status": ["A(z) jóváhagyási állapot értéke érvénytelen."] }
}
```

Az API- és Google-feature tesztek fake értesítésekkel, fake Google-adatokkal
és külön `:memory:` adatbázissal futnak. A valódi session-cookie hitelesítést,
CSRF-védelmet, JSON-hibákat, mezők kizárását és a közös jóváhagyás
idempotenciáját is ellenőrzik. A Google-tesztek nem igényelnek valódi
kliensazonosítót vagy titkot. Valódi Google-hozzájárulás és böngészős
végigkattintás csak saját OAuth-konfigurációval ellenőrizhető kézzel.

Hivatalos dokumentáció:
[Laravel 13 Sanctum](https://laravel.com/framework/docs/13.x/sanctum),
[Laravel 13 Socialite](https://laravel.com/framework/docs/13.x/socialite),
[Google OAuth webalkalmazás-beállítás](https://developers.google.com/identity/protocols/oauth2/web-server).

## Adatok és titkos konfiguráció

- Jelszó, hozzáférési token, privát kulcs és valódi résztvevői adat
  nem kerülhet a repositoryba.
- A `.env` és a helyi SQLite-adatbázis ki van zárva a Gitből.
- A `.env.example` kizárólag megosztható példaértékeket tartalmazhat.
- A későbbi factoryk és seederek csak kitalált mintaadatokat használhatnak.
- A Composer és az npm lockfájljait verziókövetjük.
- A telepített függőségek és a generált buildfájlok nincsenek verziókövetve.

