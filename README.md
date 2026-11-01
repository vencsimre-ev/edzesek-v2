# Edzések v2

Mászóedzések, résztvevők, részvételek, bérletalkalmak és manuálisan
rögzített befizetések kezelésére készülő webalkalmazás.

A regisztráció, email-megerősítés, admin jóváhagyás és résztvevőkezelés elkészült.
Az edzések adatmodellje, admin REST API-ja és magyar Blade-adminfelülete elkészült.
Az edzésekhez manuális részvételi névsor is kezelhető.

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
- Résztvevőmodell, kereshető és lapozott admin CRUD, archiválás és explicit fiókkapcsolás.
- TrainingSession REST API és Blade-adminfelület, egyszerű helyi időbevitel,
  tervezett/megtartott/elmaradt állapotok, végleges edzéstörlés.
- Nyolchetes fix csütörtöki edzésrend automatikus pótlása és napi lezárás.
- Edzésenkénti manuális részvételi lista, aktív résztvevő kiválasztásával és új vendég hozzáadásával.
- Interaktív adminlétrehozó és idempotens résztvevőpótló parancs, auth/API feature tesztek.

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
| Megerősített, approved admin | Védett kezdőoldal, jóváhagyások (`/admin/approvals`) és résztvevők (`/admin/participants`) |

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

Az edzések az adminfelületen és az admin REST API-n is kezelhetők. A Google-folyamat kézi
ellenőrzése a következő szakaszban olvasható.

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
- A `/me` végpont a `MeController::show()`, a résztvevő fiókkapcsolása a
  `LinkParticipantUserController::store()` metódust hívja, explicit route-hozzárendeléssel.
- A felhasználó jóváhagyását az `ApproveUserController::approve()` metódus kezeli.
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

## Résztvevők és bejelentkezési fiókok

A `User` a bejelentkezési fiók; a `Participant` az edzésen részt vevő személy.
Egy fiókhoz legfeljebb egy résztvevő tartozhat, de a vendégnek nincs fiókja.
A `participants.user_id` nullable, egyedi foreign key. Fióktörlés esetén
nullázódik, a résztvevő története megmarad. Név vagy email alapján nincs
automatikus személyazonosítás, összekapcsolás vagy összevonás; ezek nem egyedi mezők.

A résztvevő kötelező neve mellett az email, telefonszám és belső megjegyzés
opcionális. A megjegyzés kizárólag admin számára érhető el. A résztvevő
szerkesztése nem módosítja a fiók nevét, emailcímét, szerepét vagy jóváhagyását,
és a résztvevő emailje nem igazol email-megerősítést. A `/api/v1/me` változatlan
UserResource-t ad: résztvevői megjegyzés vagy más személy adata nem kerül bele.

Normál és új Google-regisztrációkor a student fiók és a hozzá tartozó,
induló névvel és emaillel kitöltött résztvevő egy tranzakcióban jön létre.
Visszatérő Google-belépés nem készít új rekordot és nem írja át a résztvevő adatait.
Az adminlétrehozó parancs nem készít résztvevőt.

Meglévő telepítésnél, a migráció után:

```bash
php artisan migrate
php artisan app:backfill-participants
```

A pótló parancs csak a hiányzó student rekordokat készíti el, fiókonként
tranzakcióban. Újra futtatható: nem módosít meglévő résztvevőt, és archivált
rekord mellé sem készít másodikat. Nem használ személyes adatot migrációban vagy seederben.

### Adminfelület

A kezdőoldal **Résztvevők** gombja a `/admin/participants` oldalra vezet.
Verified + approved + admin szükséges, szerveroldali middleware és policy védi.
A lista két oszlopból áll: név és email; a névre kattintva nyílnak a részletek.
A név melletti kis ikon jelzi a kapcsolt bejelentkezési fiókot. Telefonszám és
külön fiókoszlop nincs; név, email és telefonszám alapján továbbra is kereshető,
lapozott. Új vendég létrehozható; a részletek oldalon szerkesztés és
fiókkapcsolás érhető el, az archiválás egy visszafogott, lenyitható művelet.
Az edzésenkénti részvételek az edzés adatlapján, külön Attendance-névsorban kezelhetők.
A név/email/telefon keresés részszövegre keres, a `%` és `_` karaktert szó szerint kezeli.
Az SQLite beépített `lower()` függvénye az ASCII betűknél kis-/nagybetűfüggetlen;
az ékezetes betűknél a megfelelő írásmódot kell használni.

Vendégnél a **Student fiók összekapcsolása** oldalon név vagy email alapján
kereshető, lapozott fióklistából választhatsz. A kapcsolás explicit művelet,
csak student fiókra engedélyezett, és nem ad jóváhagyást vagy adminjogot.
Ha a fiókhoz már tartozik másik (akár archivált) résztvevő, vagy a résztvevő
már másik fiókhoz kapcsolódik, magyar hibaüzenetet kapsz. Automatikus törlés,
áthelyezés és teljes résztvevő-összevonás nincs. Ugyanazon kapcsolat ismétlése
idempotens. Kapcsoláskor az üres résztvevői emailmezőt a fiók emailjével töltjük
ki; a már megadott kapcsolattartási emailt megőrizzük. Ezután nincs folyamatos
email-szinkronizálás. Egyedi adatbázis-korlát és tranzakció védi a kapcsolást.

A felület **Archiválás** művelete a háttérben soft delete
(`deleted_at`): a résztvevő eltűnik az aktív listából,
az aktív részletek és módosító végpontok számára 404-et ad. A kapcsolt User
megmarad, be tud lépni a korábbi jogosultságaival. Archivált résztvevő nem
kapcsolható fiókhoz. Végleges törlés és visszaállító felület nincs ebben a szakaszban.
Minden résztvevőhöz `created_at` és `updated_at` tartozik.

### Résztvevő REST API

A meglévő Sanctum session/CSRF hitelesítést használja. Minden alábbi
endpoint verified + approved + admin jogosultságot igényel.
A web és az API közös `ManageParticipants` és `LinkParticipantUser` actionöket hív.

| Endpoint | Működés | Siker |
| --- | --- | --- |
| GET `/api/v1/participants` | Aktív, lapozott lista | 200 |
| POST `/api/v1/participants` | Vendég létrehozása | 201 |
| GET `/api/v1/participants/{participant}` | Részletek | 200 |
| PATCH `/api/v1/participants/{participant}` | Résztvevőadatok módosítása | 200 |
| DELETE `/api/v1/participants/{participant}` | Archiválás, nem végleges törlés | 204, üres válasz |
| POST `/api/v1/participants/{participant}/link-user` | Explicit student fiókkapcsolás | 200 |

Listaparaméterek: `search` opcionális szöveg, legfeljebb 255 karakter;
`per_page` 1–100 (alapérték 20); `page` pozitív egész. A válasz `data`,
`links`, `meta` mezőket tartalmaz, a lapozási linkek megőrzik a validált szűrőket.

Létrehozáskor a `name` kötelező, legfeljebb 255 karakter. Az `email` nullable
email, legfeljebb 255 karakter; a `phone` nullable szöveg, legfeljebb 50 karakter;
a `notes` nullable szöveg, legfeljebb 10 000 karakter. PATCH esetén csak a megadott
mezők változnak, a név nem üríthető. A CRUD nem fogad el `user_id` vagy auth-mezőket;
a fiókkapcsoláshoz külön kérés kell, `{"user_id": 42}` törzzsel.

A ParticipantResource explicit mezői: `id`, `user_id`, `name`, `email`, `phone`,
`notes`, `created_at`, `updated_at`, `deleted_at`. A dátumok ISO 8601 UTC-ben
érkeznek. Beágyazott User, jelszó, remember token vagy OAuth-token nincs a válaszban.

Példák belépett admin böngészőkonzoljából, kizárólag mintaadatokkal
(a korábban bemutatott `xsrf()` segédfüggvénnyel):

```javascript
const headers = { Accept: 'application/json', 'Content-Type': 'application/json',
    'X-XSRF-TOKEN': xsrf() };
const guest = await fetch('/api/v1/participants', {
    method: 'POST', credentials: 'same-origin', headers,
    body: JSON.stringify({ name: 'Minta Vendég', email: 'guest@example.test',
        phone: '+36 30 000 0000', notes: 'Kitalált admin megjegyzés' })
}).then(response => response.json());
const list = await fetch('/api/v1/participants?search=Minta&per_page=10', {
    credentials: 'same-origin', headers: { Accept: 'application/json' }
}).then(response => response.json());
await fetch(`/api/v1/participants/${guest.data.id}`, {
    method: 'PATCH', credentials: 'same-origin', headers,
    body: JSON.stringify({ phone: null })
});
// Meglévő, még résztvevőhöz nem kapcsolt student fiók saját azonosítója.
const studentId = 42;
await fetch(`/api/v1/participants/${guest.data.id}/link-user`, {
    method: 'POST', credentials: 'same-origin', headers,
    body: JSON.stringify({ user_id: studentId })
});
await fetch(`/api/v1/participants/${guest.data.id}`, {
    method: 'DELETE', credentials: 'same-origin', headers
}); // 204: nincs JSON-törzs.
```

Hibák: 401 belépés hiányában; 403 hiányzó jogosultság; 404 hiányzó vagy
archivált résztvevő; 422 validációs hiba (például nem student célfiók);
409 foglalt fiók/résztvevő; 419 CSRF/session hiba. Az API minden esetben JSON-hibát
ad, 422-nél mezőnkénti `errors` mezővel. Kapcsolási konfliktus nem módosít adatot.

### Ellenőrzések és kézi próba

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

A résztvevőtesztek külön memóriabeli SQLite-on ellenőrzik a regisztrációt,
tranzakciós visszagörgetést, Google-visszatérést, idempotens pótlást, web/API CRUD-ot,
keresést, lapozást, jogosultságokat, notes kizárását, explicit kapcsolást,
konfliktusokat, adatbáziskorlátot és az archiválás/fióktörlés utáni adatmegőrzést.
A teljes tesztfuttatás a meglévő auth-, Google- és user API-teszteket is tartalmazza.

Az alábbi kézi folyamatok még ellenőrizendők; az automatizált teszt nem jelent
valódi Google- vagy böngészős ellenőrzést:

1. A migráció után futtasd kétszer a pótló parancsot; a második futás 0 új
   résztvevőt jelezzen. Adminnak ne keletkezzen kötelező résztvevője.
2. Adminként hozz létre vendéget, keress név/email/telefon szerint, lapozz,
   szerkeszd és nézd meg a részleteit. A belső megjegyzés csak adminnak látszódjon.
3. Új normál regisztráció után ellenőrizd az induló résztvevőt, majd email-megerősítés
   és jóváhagyás után lépj be studentként. A résztvevő adminfelület és API legyen tiltott.
4. Saját OAuth-konfigurációval: **új Google-regisztráció → pending → admin jóváhagyás
   → kijelentkezés → visszatérő Google-belépés**. A `/home` legyen elérhető, és
   pontosan egy résztvevő maradjon a fiókhoz.
5. Kapcsolj vendéget még szabad student fiókhoz. Ismételd meg ugyanazt API-n;
   ne keletkezzen duplikáció. Foglalt fióknál legyen magyar webhiba, API-n 409.
   Kapcsolt résztvevő áthelyezése és adminfiók kapcsolása legyen tiltott.
6. Archiválj kapcsolt résztvevőt: az aktív listában ne szerepeljen, a fiók
   továbbra is beléphessen, szerepe/jóváhagyása maradjon változatlan.
7. Böngészőkonzolból ellenőrizd a session/CSRF API-kéréseket, a státuszkódokat
   és a student `/me` válaszát: belső megjegyzés ne szerepeljen.

A megvalósításhoz használt hivatalos dokumentáció:
[Laravel 13 Eloquent és soft delete](https://laravel.com/framework/docs/13.x/eloquent),
[Laravel 13 policyk](https://laravel.com/framework/docs/13.x/authorization),
[Laravel 13 validáció](https://laravel.com/framework/docs/13.x/validation).

## Edzések: TrainingSession REST API

A `TrainingSession` egy edzésesemény, a `Participant` a személytörzs.
A kettőt az `Attendance` köti össze, edzésenként és személyenként egyedi
kapcsolattal. Egy személy több edzésen is részt vehet. A névsor tényleges
részvételt rögzít; előzetes jelentkezés/reservation, bérlet, fizetés és QR
nem készült. Az elmaradt edzésnek saját állapota van;
az üres névsor nem jelenti azt, hogy az edzés elmaradt.

### Adatmodell és állapotok

A `training_sessions` mezői:

| Mező | Jelentés |
| --- | --- |
| `id` | Edzés azonosítója |
| `title` | Kötelező cím, legfeljebb 255 karakter |
| `location` | Kötelező helyszín, legfeljebb 255 karakter; nincs külön helyszíntörzs |
| `starts_at`, `ends_at` | UTC kezdés és befejezés; a kezdés mindig korábbi |
| `status` | `TrainingSessionStatus` backed enum, JSON-ban a szöveges értéke |
| `cancellation_reason` | Opcionális elmaradási indok, legfeljebb 2000 karakter |
| `created_by` | A létrehozó admin User idegen kulcsa, szerveroldalon kitöltve |
| `created_at`, `updated_at` | Automatikus időbélyegek |

Az edzéshez nem tartozik ár. Korábbi edzést is lehet rögzíteni, az azonos vagy
átfedő időpontú edzéseket nem tiltjuk. A korábbi `price_huf` mezőt kivezettük
a modell tömeges értékadásából, az API-ból és a felületből. Beküldését POST és
PATCH esetén is 422-es hibával utasítjuk el. Az új migráció nullable-é teszi
a régi adatbázisoszlopot: korábbi értékei megmaradnak, új edzésnél üres marad,
és semmilyen elszámoláshoz nem használjuk. A migráció visszagörgetése a régi
kötelező mező kompatibilitásához az üres értékeket 0-ra tölti.
A létrehozó kapcsolata `creator()`; a foreign key megakadályozza a hivatkozott
User törlését, nem törli kaszkáddal az edzést.

| Állapot | Jelentés | Műveletek |
| --- | --- | --- |
| `scheduled` | Tervezett, alapértelmezett | Szerkeszthető, megtartottnak vagy elmaradtnak jelölhető |
| `completed` | Megtartott | Lezárt, nem szerkeszthető és nem állítható vissza |
| `cancelled` | Elmaradt | Lezárt, nem szerkeszthető és nem állítható vissza |

Létrehozáskor `completed` is megadható történeti rögzítéshez, de kizárólag
szigorúan múltbeli `ends_at` esetén. `cancelled` kezdőállapot nem engedélyezett.
A `complete` művelet a befejezési időpont elérésekor már engedélyezett
(`ends_at <= most`). A `cancel` jövőbeli és korábbi tervezett edzésre is működik.

Csak `scheduled` edzés adatai módosíthatók. A `status`, `created_by` és
`cancellation_reason` általános PATCH-ben nem küldhető be, még null vagy üres
értékkel sem: 422-es validációs hiba. A létrehozás szintén elutasítja a kliensből
küldött `created_by` és `cancellation_reason` mezőket. Kézi létrehozásnál a
hitelesített admint a szerver választja ki; automatikus létrehozásnál a később
leírt cron-végpont választ megerősített, jóváhagyott admint. Másik admin
későbbi szerkesztése nem cseréli le a létrehozót.

Ismételt `complete` már megtartott edzésen 200-as sikeres no-op; ismételt
`cancel` már elmaradt edzésen szintén 200, és megőrzi az első indokot.
Az ismétlések az időbélyeget sem módosítják. Megtartott edzés elmaradtnak
jelölése, elmaradt edzés megtartottnak jelölése és lezárt edzés szerkesztése 409.
Túl korai `complete` 422. Az elmaradás jelölése megőrzi az edzést.
Külön DELETE művelettel az admin bármely állapotú edzést véglegesen, fizikailag
törölhet, ha nincs hozzá rögzített részvétel. Részvételek mellett a törlés és az
elmaradtnak jelölés is 409-es konfliktus: előbb a névsort kell rendezni. Ez nem archiválás: a sor eltűnik az adatbázisból, nem állítható vissza.
A létrehozó User megmarad. Az ismételt törlés és a törölt edzés lekérése 404.

A `ManageTrainingSessions` actionben a szerkesztés és állapotváltás tranzakcióban
fut. Az első adatbázis-művelet feltételes UPDATE: maga az adatbázis ellenőrzi
az aktuális `scheduled` állapotot és szükség esetén az aktuális időhatárokat.
Részleges időpontmódosításnál a tárolt másik határ szerepel a feltételben.
Így egy régi modellpéldány vagy párhuzamos kérés nem írhat felül lezárást, illetve
nem hozhat létre hibás időtartamot. Nem támaszkodunk SQLite-on sorzárra vagy
`lockForUpdate()`-ra; Laravel a felismert zárolási ütközést legfeljebb öt
tranzakciós próbálkozással kezeli. Két külön SQLite-kapcsolatos tesztek ellenőrzik
az ütköző módosítás/lezárás és az első indok megőrzésének eseteit.

### Időkezelés és listaszűrés

Az edzések időzónája egy helyen, a `config/training.php` fájlban állítható:

```php
'timezone' => 'Europe/Budapest',
```

A webes űrlap és az API ugyanazt az egyszerű,
perc pontosságú helyi időpontot használja: **`2026-11-05T18:00`**.
Másodpercet, törtmásodpercet vagy időzónaeltolást nem kell és nem lehet beküldeni.
A validáció a Laravel beépített `date_format:Y-m-d\TH:i` szabálya; külön
webes dátumátalakító trait és egyéni időzónás validációs szabály nincs.

A `starts_at` és `ends_at` a JSON-válaszban is ugyanilyen helyi, perc pontosságú
érték. A közös action a konfigurált időzónából UTC-re alakítja a tárolandó adatot,
a resource és a Blade ugyanezt a konfigurációt használja a megjelenítéshez.
A meglévő UTC-adatokat és az alkalmazás többi modelljének időkezelését nem
kell átírni; a `created_at` és `updated_at` technikai időbélyegek maradnak UTC-ben.
A téli/nyári eltolást a szerver kezeli. Nem létező óraátállítási időpontnál
magyar validációs hiba érkezik.

Listaszűrők:

- `status`: `scheduled`, `completed` vagy `cancelled`.
- `starts_from`: kezdőidőpont alsó határa, **beleértve** (`starts_at >= érték`).
- `starts_before`: kezdőidőpont felső határa, **kizárva** (`starts_at < érték`).
- `per_page`: 1–100, alapérték 20; `page`: pozitív egész.

Az időszűrők is a fenti helyi, perc pontosságú formátumot használják. Mindkettő az edzés **kezdését** szűri; nem időszakok átfedését keresik.
Üres vagy fordított intervallum nem ad találatot. A sorrend időrendben növekvő: `starts_at ASC`,
majd `id ASC`, így azonos kezdés esetén is stabil. A lapozási linkek megőrzik
a validált szűrőket.

### Endpointok és példák

Minden endpoint session alapú Sanctum-hitelesítést és **verified + approved +
admin** jogosultságot követel, middleware-rel és `TrainingSessionPolicy`-val.
Student egyelőre nem fér hozzá. A controllerek névvel ellátott metódusokat,
FormRequesteket és explicit `TrainingSessionResource` válaszokat használnak.

| Endpoint | Sikeres válasz |
| --- | --- |
| GET `/api/v1/training-sessions` | 200, lapozott `data`, `links`, `meta` |
| POST `/api/v1/training-sessions` | 201, `data`: TrainingSessionResource |
| GET `/api/v1/training-sessions/{trainingSession}` | 200, `data`: TrainingSessionResource |
| PATCH `/api/v1/training-sessions/{trainingSession}` | 200, `data`: TrainingSessionResource |
| DELETE `/api/v1/training-sessions/{trainingSession}` | Végleges fizikai törlés; 204, üres válasz |
| POST `/api/v1/training-sessions/{trainingSession}/complete` | 200, `data`: TrainingSessionResource |
| POST `/api/v1/training-sessions/{trainingSession}/cancel` | 200, `data`: TrainingSessionResource |

Létrehozási kérés helyi időponttal, kitalált adatokkal:

```json
{
  "title": "Minta mászóedzés",
  "location": "Minta mászóterem",
  "starts_at": "2026-10-30T18:00",
  "ends_at": "2026-10-30T20:00"
}
```

Példaválasz, a mintaazonosítókkal és időbélyegekkel:

```json
{
  "data": {
    "id": 1,
    "title": "Minta mászóedzés",
    "location": "Minta mászóterem",
    "starts_at": "2026-10-30T18:00",
    "ends_at": "2026-10-30T20:00",
    "status": "scheduled",
    "cancellation_reason": null,
    "created_by": 1,
    "created_at": "2026-10-30T12:00:00.000000Z",
    "updated_at": "2026-10-30T12:00:00.000000Z"
  }
}
```

A resource pontosan ezeket a mezőket tartalmazza; beágyazott User-modellt,
jelszót, remember tokent és OAuth-adatot nem ad vissza.

Belépett admin böngészőkonzoljából, a REST API szakaszban bemutatott CSRF-folyamattal
és `xsrf()` segédfüggvénnyel:

```javascript
const headers = { Accept: 'application/json', 'Content-Type': 'application/json',
    'X-XSRF-TOKEN': xsrf() };
const filters = new URLSearchParams({ status: 'scheduled',
    starts_from: '2026-10-30T00:00',
    starts_before: '2026-10-31T00:00', per_page: '20' });
await fetch(`/api/v1/training-sessions?${filters}`, {
    credentials: 'same-origin', headers
}).then(response => response.json());
const trainingSessionId = 1; // Egy ténylegesen kiválasztott edzés azonosítója.
await fetch(`/api/v1/training-sessions/${trainingSessionId}`, {
    method: 'PATCH', credentials: 'same-origin', headers,
    body: JSON.stringify({ location: 'Minta másik terem' })
});
await fetch(`/api/v1/training-sessions/${trainingSessionId}/complete`, {
    method: 'POST', credentials: 'same-origin', headers
}); // Csak ends_at elérése után; egyébként 422.
// Másik, még scheduled edzésnél az elmaradás rögzítése:
await fetch('/api/v1/training-sessions/2/cancel', {
    method: 'POST', credentials: 'same-origin', headers,
    body: JSON.stringify({ cancellation_reason: 'Minta teremzárás' })
});
```

Hibák: 401 nincs belépés; 403 hiányzó admin/megerősítés/jóváhagyás;
404 hiányzó edzés; 422 hibás adatok, tiltott mezők vagy túl korai megtartottá
jelölés; 409 tiltott állapotváltás vagy lezárt edzés módosítása. API-hibánál JSON
érkezik, nincs HTML-átirányítás. A meglévő CSRF/session szabályok is érvényesek (419).

### Migráció és ellenőrzés

Meglévő helyi adatbázis frissítése adatvesztés nélkül:

```bash
php artisan migrate
```

Az első migráció a `training_sessions` táblát hozza létre. Az új
`retire_training_session_price` migráció a korábbi ároszlopot teszi opcionálissá,
a már megadott értékek megőrzésével. Az egyszerűsítési egység végén a helyi
`php artisan migrate` ezt a változást is alkalmazta.

```bash
php artisan test --filter=TrainingSessionApiTest
php artisan test --testsuite=Feature
vendor/bin/pint --test
php artisan route:list --path=training-sessions -vv
```

A tesztek memóriabeli SQLite-adatbázist és a konkurens írások vizsgálatához
ideiglenes, kizárólag teszt-mintaadatokat tartalmazó SQLite-fájlt használnak.
Valódi böngészős/API-klienses kézi teszt ebben az egységben nem történt.
Hivatalos dokumentáció: [Laravel 13 tranzakciók](https://laravel.com/framework/docs/13.x/database),
[FormRequest és egyéni validációs szabály](https://laravel.com/framework/docs/13.x/validation),
[JsonResource válaszok](https://laravel.com/framework/docs/13.x/eloquent-resources).

### Edzések Blade-adminfelülete

A kezdőoldalon adminként az **Edzések** gomb nyitja a
`/admin/training-sessions` oldalt. Verified + approved + admin jogosultság kell
minden webes edzésművelethez is; studentnek nincs edzéskezelő menüpontja.

A lista időpontot és magyar állapotot mutat; tervezett edzés mellett az
**Elmaradás jelzése** gomb a külön Művelet oszlopban az adatlap elmaradási részére
vezet; ez egy művelet, nem az edzés állapota. Új edzés alapállapota Tervezett.
A szűrők lenyithatók,
a lapozás és a szűrési szabályok az API-val közösek.

Az **Új edzés** űrlap alapértékei:

- Cím: **Mászóedzés**.
- Helyszín: **Fless!Buda boulder**.
- Következő csütörtök **18:00–20:00**. Ha a mai csütörtöki kezdés már elmúlt,
  a következő hetet ajánlja fel.

Az időbevitel csak perc pontosságú; az időzónát és törtmásodperceket nem
emeljük ki a felületen. A cím, a helyszín és a történeti megtartott állapot
ritkábban használt beállításai a **További adatok** alatt találhatók.
Ármező nincs sem az űrlapon, sem a listában vagy az adatlapon.

Az adatlapon az **Elmaradást jelzek** gombbal és opcionális indokkal jelezhető
az elmaradás. A kézi megtartottá jelölés a **Megtartott edzés lezárása** alatt
érhető el kiegészítő lehetőségként. A fix heti alkalmakat az alább bemutatott
cron-végpont tölti fel; a napi hívás a befejezett, elmaradtnak nem jelölt
edzéseket automatikusan lezárja. A listát tehát nem szükséges hetente kézzel
létrehozni vagy minden megtartott edzést külön lezárni.

Lezárt edzésnél a szerkesztés és állapotváltás felületei eltűnnek. Ha egy korábban megnyitott
űrlappal vagy ismételt kéréssel tiltott műveletet próbálnak végrehajtani,
a közös action ellenőrzi az aktuális állapotot, a webes controller pedig
magyar hibát jelenít meg. A korábbi indok és az idempotens ismétlés szabályai
az API-val azonosak. Az adatlap alján a lenyitható **Végleges törlés** rész
minden állapotú edzésnél elérhető. A jelölőnégyzetes megerősítést szerveroldali
FormRequest is megköveteli; nélküle nincs törlés. A CSRF-védelem és a
verified + approved + admin policy a törlésre is érvényes.
Az edzés elmaradásának jelzéséhez az **Elmaradás jelzése** művelet való,
a végleges törlés a felesleges vagy tévesen rögzített edzés eltávolítására szolgál.

Az űrlap és az API közös FormRequesteket és közös actiont használ. Mindkettő
ugyanazt az egyszerű helyi időpontot várja; a helyi időzónát a `training.timezone`
konfiguráció adja meg. A Blade-felületen nincs időzóna- vagy másodpercbevitel.
Új időpontnál az őszi kétszer előforduló órában a későbbi, téli időpontot
használjuk. Változatlan helyi percértéket visszaküldve a meglévő tárolt
időpontot megőrizzük, így a korábbi értékek nem tolódnak el szerkesztéskor.

A validációs hiba után megmarad az eredeti helyi űrlapérték; változatlan
percérték visszaküldésekor a szerkesztés megőrzi a tárolt másodpercet és
törtmásodpercet, bár ezeket az űrlap nem mutatja. A Blade-felület közvetlenül a közös actionöket
hívó webes controllerekhez küld CSRF-védett űrlapokat, nem indít HTTP-kérést
a saját API-jához. A részvételi lista az adatlap része, előzetes jelentkezés nincs.

| Webes útvonal | Művelet |
| --- | --- |
| GET `/admin/training-sessions` | Lista és szűrés |
| GET `/admin/training-sessions/create` | Létrehozó űrlap |
| POST `/admin/training-sessions` | Új edzés mentése |
| GET `/admin/training-sessions/{trainingSession}` | Adatlap |
| GET `/admin/training-sessions/{trainingSession}/edit` | Szerkesztő űrlap |
| PATCH `/admin/training-sessions/{trainingSession}` | Adatok mentése |
| DELETE `/admin/training-sessions/{trainingSession}` | Végleges törlés; kötelező `confirm_delete=1` megerősítés |
| POST `/admin/training-sessions/{trainingSession}/complete` | Megtartottként rögzítés |
| POST `/admin/training-sessions/{trainingSession}/cancel` | Elmaradás rögzítése |

Ellenőrzés:

```bash
php artisan test --filter=TrainingSessionWebTest
php artisan test --testsuite=Feature
npm run build
vendor/bin/pint --test
php artisan route:list --path=admin/training-sessions
```

Az automatizált webtesztek a hozzáférési és CSRF-szabályokat, a budapesti/UTC
átalakítást és óraátállítási eseteket, a lista szűrését/lapozását, az eredeti
űrlapértékek visszatöltését, az állapotváltásokat, az idempotenciát és a hibás
módosítások tiltását vizsgálják. A fejlesztői adatbázis adatait nem módosítják.

Kézzel még ellenőrizendő, helyi szerver mellett:

1. Lépj be adminként, és a kezdőoldal **Edzések** gombjával nyisd meg a listát.
2. Hozz létre mintaedzést budapesti időponttal, majd ellenőrizd és szerkeszd.
   Hibás időtartamnál maradjanak meg az űrlapértékek, és jelenjen meg magyar hiba.
3. Szűrj állapotra és kezdési időszakra, majd próbáld ki az üres szűrőket és a lapozást.
4. Rögzíts korábbi edzést megtartottként; külön tervezett edzésnél próbáld ki
   a megtartottként jelölést és az indokkal történő elmaradást. A lezárt adatlap
   ne kínáljon szerkesztést vagy visszaállítást.
5. Studentként és pending fiókkal próbáld meg közvetlenül megnyitni az adminútvonalakat.
6. Kizárólag törlésre szánt mintaedzés adatlapján nyisd le a **Végleges törlés**
   részt. Megerősítés nélkül ne lehessen törölni; megerősítve az edzés tűnjön el.

Valódi böngészős végigkattintás ebben az egységben nem történt.
Hivatalos dokumentáció: [Laravel 13 Blade](https://laravel.com/framework/docs/13.x/blade),
[FormRequest és űrlapadatok visszatöltése](https://laravel.com/framework/docs/13.x/validation).

### Fix heti edzések: egy cronból hívott végpont

```text
POST /api/v1/training-sessions/sync-weekly
```

A `TrainingSessionController::syncWeekly()` a következő nyolc csütörtököt
ellenőrzi. Ha az adott helyi napon már van edzés, annak adatait és állapotát
megőrzi. Ha nincs, létrehoz egy tervezett **Mászóedzés** rekordot, **18:00–20:00**
időponttal, **Fless!Buda boulder** helyszínnel. A hiányzó rekordok létrehozása
után a tényleges befejezésükhöz elért `scheduled` edzéseket `completed` állapotba
zárja, a meglévő közös actionnel. Az elmaradtnak jelölt edzést és az indokát
nem módosítja. Az első verified + approved admin a létrehozó; nincs fióklétrehozás
vagy jogosultságemelés. Az ismételt hívás a már létező napokra nem készít új rekordot.

**Ennél a megoldásnál az elmaradást állapotváltással kell jelezni.** Ha egy
jövőbeli csütörtöki rekordot mégis fizikailag törölsz vagy másik napra helyezel,
a következő hívás újra létrehozza a megüresedett csütörtöki alkalmat. Nincs
külön heti nyilvántartó modell/tábla, törlési kivétellista, Artisan-szinkronizáló
parancs vagy Laravel-ütemezett feladat. `schedule:work` sem szükséges.
A régi belső nyilvántartást a tisztító migráció távolítja el; az edzéseket megőrzi.

A dátumellenőrzés, beszúrás és lezárás egy SQLite-tranzakcióban fut. A létrehozási
és lezárási szabályokat a meglévő `ManageTrainingSessions` action kezeli.
A JSON-válasz a létrehozott és lezárt edzések számát tartalmazza:

```json
{"data":{"created":0,"completed":0}}
```

A végpont szerver–szerver hívásra való; külön, saját titkos kulcs védi.
A `.env` fájlban állíts be egy hosszú, véletlen `TRAINING_CRON_KEY` értéket,
amelyet nem szabad verziókövetni. A konfigurációban a `training.cron_key` olvassa.
Utána szükség esetén `php artisan config:clear`.
Kulcs nélkül a végpont 503-at, hiányzó vagy hibás Authorization headerrel 401-et,
jogosult létrehozó hiányában 422-es JSON-hibát ad. Ez nem Sanctum személyes
API-token, a többi admin API-végpont session/policy/CSRF védelme megmaradt.

Kézi próba, a saját kulcsoddal:

```bash
curl --fail --silent --show-error --request POST \
  --header 'Authorization: Bearer SAJAT_CRON_KULCS' \
  http://127.0.0.1:8000/api/v1/training-sessions/sync-weekly
```

Napi egyszeri cron, például a cron szerver helyi ideje szerinti **20:05-kor**:

```cron
5 20 * * * curl --fail --silent --show-error --request POST --header 'Authorization: Bearer SAJAT_CRON_KULCS' https://SAJAT_DOMAIN/api/v1/training-sessions/sync-weekly
```

A példa domainjét és kulcsát cseréld a sajátodra. A kívánt 20:05-ös helyi
futtatáshoz a cron időzónáját is ennek megfelelően állítsd be; a cron nem az
alkalmazás konfigurációjából veszi az időzónát. Éles környezetben HTTPS-es URL-t
használj. A megvalósítás nem ír át szerveres crontabot vagy titkos `.env` értéket:
a kulcs beállítása és a cronbejegyzés aktiválása telepítési lépés.

Ellenőrzés:

```bash
php artisan migrate
php artisan test --filter=WeeklyTrainingSessionsTest
php artisan test --testsuite=Feature
vendor/bin/pint --test
php artisan route:list --path=sync-weekly
```

A tesztek a kulcsos védelmet, az első feltöltést és ismétlést, a kézi módosítások
és elmaradások megőrzését, a gördülő nyolchetes ablakot, a tényleges befejezés
utáni lezárást, az óraátállítást és a törölt nap újrafeltöltését ellenőrzik.
A tisztító migráció tesztje igazolja, hogy az edzésadatok változatlanul megmaradnak.
Valódi cronból indított HTTP-futás még nem történt.

### Teljes összefoglaló: eltérések az eredeti TrainingSession-tervhez képest

| Eredeti vagy köztes megoldás | Elkészült működés |
| --- | --- |
| Csak adatmodell, REST API és feature tesztek | Magyar Blade-adminfelület és cronból hívott végpont is |
| Minden új edzés adatait kézzel kell kitölteni | Fix csütörtök 18:00–20:00 és Fless!Buda boulder alapértékek; nyolchetes automatikus pótlás |
| Minden megtartott edzést kézzel kell lezárni | A napi futás a tényleges befejezés után automatikusan lezárja; a kézi complete is elérhető |
| Edzésenként kötelező ár | Ár kivezetve az aktív modell/API/felület használatából, korábbi értékek megőrizve |
| Másodpercet, eltolást tartalmazó API-időpontok | Egyszerű helyi `2026-11-05T18:00`, közös timezone-konfigurációval |
| Külön webes dátumátalakító trait és requestek | Közös FormRequestek és actionök, beépített Laravel-validációval |
| Csökkenő időrend | Növekvő időrend, kezdés majd azonosító szerint |
| Nem volt törlési endpoint | Megerősített fizikai törlés, jogosult admin számára, minden állapotban |
| Törölt/áthelyezett alkalmak külön követése a köztes változatban | Nincs külön nyilvántartás; a hiányzó csütörtök újra létrejön, elmaradást állapotváltással jelzünk |
| API-ban explicit JSON-válaszok és szerveroldali védelem | Megmaradt; a web is a közös szabályokat hívja, a cron-végpontot külön titkos kulcs védi |

A normál és Google-auth, admin/student szerepek, email-megerősítés, jóváhagyás,
a Participant személytörzs, admin CRUD és session REST API továbbra is megvan.
A személytörzs és az edzésenkénti Attendance-névsor külön fogalom. A manuális
részvételkezelés elkészült; alkalomlevonást vagy bérletegyenleget még nem kezelünk. Reservation, bérletvásárlás,
fizetés és QR funkció nem készült. Az ezekhez eddig megbeszélt díjak és hozott
alkalmak igényeit a következő szakasz rögzíti.

Hivatalos dokumentáció:
[Laravel 13 migrációk](https://laravel.com/framework/docs/13.x/migrations).

### Edzésenkénti manuális részvételi névsor

Az admin edzésadatlapján a **Résztvevők (N fő)** rész mutatja az adott edzés
névsorát és teljes létszámát. Ez tényleges részvétel rögzítése, nem jelentkezés.
Az edzések listájában is látszik edzésenként a létszám és a résztvevők neve,
egyszerű, vesszővel elválasztott felsorolásban.
A `scheduled` és a `completed` edzés névsora is hozzáadható és javítható,
utólag is. Az edzés lezárt adatai és a névsor szerkeszthetősége külön szabály.
`cancelled` edzéshez nem lehet részvételt hozzáadni.

Használat:

1. **Meglévő résztvevő hozzáadása:** név szerinti keresés, szerveroldali,
   lapozott találati lista, új frontend-csomag nélkül. Kizárólag aktív, az adott
   edzésre még fel nem vett személyek választhatók. A név mellett a személy
   azonosítója látszik, így azonos nevű résztvevők is külön választhatók.
   Hozzáadás után a választó nyitva marad, és a név szerinti keresés megmarad,
   így egymás után több személy is gyorsan felvehető.
2. **Új vendég hozzáadása:** csak a név szükséges; nem készül User-fiók,
   email, telefon és belső megjegyzés sem kötelező. Azonos név alapján nem
   vonunk össze személyeket és nem választunk ki automatikusan meglévő rekordot.
3. **Eltávolítás:** az adott név mellett egyetlen gombnyomással, külön
   megerősítő checkbox nélkül. A meglévő résztvevők választójának nyitott vagy
   csukott állapota és a keresés eltávolítás után megmarad. Kizárólag a részvételi sor
   törlődik, a Participant megmarad és később ismét kiválasztható.

A névsor és a választólista 20 soros oldalakat használ. A keresés részszövegre
keres, a `%` és `_` karaktert szó szerint kezeli. A meglévő SQLite-konvenciót
követi: ASCII betűknél kis-/nagybetűfüggetlen, ékezetes betűknél a megadott
írásmód számít. A keresőparaméter `participant_search`, a választó oldalszáma
`participants_page`, a névsor oldalszáma `page`; lapozáskor a szűrők megmaradnak.

#### Adatmodell és közös szabályok

Az `attendances` mezői: `id`, `training_session_id`, `participant_id`,
`created_by`, `created_at`, `updated_at`, valamint egy opcionális, egyedi
`submission_id` UUID a vendégűrlap ismételt beküldésének felismerésére.
Az edzés–személy párt adatbázis-szintű unique korlát védi.
A foreign key-k nem törölnek kaszkáddal részvételt: az edzés, a személy és a
rögzítő User fizikai törlése a hivatkozás fennállásáig korlátozott.

Az új vendégűrlap automatikusan kap egy küldésazonosítót; az adminnak ezt nem
kell megadnia. A már rögzített vendég ugyanazzal az azonosítóval történő
ismételt beküldése 409, új vendégrekord nélkül. Két külön azonosítóval küldött,
azonos nevű vendég két külön személyt jelent. Eltávolítás utáni visszaadáskor
a már létrehozott személyt válaszd a meglévő résztvevők közül.

Az új Participant és Attendance egy tranzakcióban jön létre. Sikertelen
részvételmentés vagy egyediségi konfliktus nem hagy fölösleges vendégrekordot.
Meglévő személy ismételt hozzáadása 409, nem duplikáció és nem sikeres no-op.
A rögzítő `created_by` mindig a belépett admin, beküldése validációs hiba.

**Részvételekkel rendelkező edzés nem törölhető és nem jelölhető elmaradtnak.**
Az API 409-et, a web érthető magyar hibaüzenetet ad; a felület is jelzi, hogy
előbb a névsort kell rendezni. A műveletek nem törölnek automatikusan részvételt.
Az ellenőrzés feltételes adatbázis-frissítéssel/törléssel, tranzakcióban történik;
a hozzáadás szintén az aktuális állapotot ellenőrzi a tranzakción belül.
A kézi és automatikus megtartottá jelölés megőrzi a névsort, amely utána is javítható.

A Participant archiválása nem törli a korábbi részvételeit. Az Attendance
Participant-kapcsolata az archivált személyt is betölti; neve továbbra is látszik,
a weben Archivált jelzéssel. Új névsorba csak aktív személy vehető fel.
A web és az API a közös `ManageAttendances` actiont és a meglévő
`TrainingSessionPolicy` adminjogosultságait használja, új általános réteg nélkül.

#### REST API

Minden művelethez verified + approved admin és a meglévő Sanctum-session kell.
Az állapotmódosító böngészős kérések CSRF-védettek. A heti cron-végpont nem
kapott névsorkezelési jogosultságot, a részvételeket nem módosítja.

| Endpoint | Sikeres válasz |
| --- | --- |
| GET `/api/v1/training-sessions/{trainingSession}/attendances` | 200, lapozott resource collection |
| POST `/api/v1/training-sessions/{trainingSession}/attendances` | 201, AttendanceResource |
| DELETE `/api/v1/training-sessions/{trainingSession}/attendances/{attendance}` | 204, üres válasz |

A GET paraméterei: `per_page` 1–100, alapérték 20; `page` pozitív egész.
A válasz `data`, `links`, `meta` mezőket tartalmaz, a teljes létszám `meta.total`.
A lista hozzáadási sorrendben, azonosító szerint rendezett.

Meglévő résztvevő POST törzse, kitalált azonosítóval:

```json
{"participant_id": 12}
```

Új vendég POST törzse:

```json
{"name": "Minta Vendég", "submission_id": "21c5516e-4598-4c01-99d8-dd45f4675132"}
```

A két forma egymást kizárja: `participant_id` és `name` egyszerre nem küldhető,
még üres/null alternatívával sem. `submission_id` csak új vendégnél küldhető,
ott kötelező UUID. Egy új vendégművelethez új UUID-t generálj
(például böngészőben `crypto.randomUUID()`), ugyanazon kérés újraküldéséhez
ugyanazt az értéket használd. A név legfeljebb 255 karakter.

Példaválasz:

```json
{
  "data": {
    "id": 1,
    "training_session_id": 5,
    "participant_id": 12,
    "participant_name": "Minta Vendég",
    "created_by": 1,
    "created_at": "2026-10-31T12:00:00.000000Z",
    "updated_at": "2026-10-31T12:00:00.000000Z"
  }
}
```

Kizárólag ezek a mezők szerepelnek; belső notes, email, telefon, User-modell,
auth-adat és küldésazonosító nincs a névsorválaszban. A beágyazott resource-route
scoped bindingot használ: másik edzés részvételét megadva 404 érkezik.
A közös eltávolító action ezt külön is ellenőrzi.

Hibák: 401 nincs belépés; 403 hiányzó jogosultság; 404 hiányzó edzés/részvétel
vagy téves edzés–részvétel párosítás; 422 hibás bemenet/archivált személy;
409 ismételt hozzáadás, elmaradt edzéshez hozzáadás, illetve névsor melletti
edzéstörlés/lemondás. A hibák az API-n mindig JSON-válaszok.
A webes eltávolítás nem kér külön megerősítést; a jogosultság- és CSRF-védelem megmarad.

#### Ellenőrzések

```bash
php artisan migrate
php artisan test --filter=AttendanceTest
php artisan test --testsuite=Feature
vendor/bin/pint --test
npm run build
php artisan route:list --path=attendances
```

A tesztek ellenőrzik a külön edzésnévsorokat, hozzáadást és lapozást, új vendéget,
ismételt beküldést, azonos nevű külön személyeket, tranzakciós visszagörgetést,
adatbáziskorlátokat, állapotszabályokat, téves szülőedzést, archivált személy
előzményeit, a webes keresést és a választó állapotának megőrzését, a CSRF-et és minden korlátozott
fiókállapot hozzáférési tiltását. Külön tesztadatbázison, mintaadatokkal futnak.

Kézi böngészős ellenőrzés még elvégzendő:

1. Adminként nyiss meg egy tervezett edzést. Keress név szerint, adj hozzá egy
   meglévő személyt; a névsor és létszám frissüljön, másik edzésé ne változzon.
2. Adj hozzá új vendéget csak névvel. Két valóban külön, azonos nevű vendég
   külön személy legyen. Azonos küldésazonosítóval API-n ismételve ne keletkezzen
   új rekord; meglévő személy duplázása is 409-et adjon.
3. Próbáld a névsorral rendelkező edzést törölni vagy elmaradtnak jelölni:
   magyar konfliktusüzenet érkezzen, a névsor maradjon meg.
4. Távolíts el téves részvételt a gombbal; a személy maradjon meg a törzsben.
   Megtartott edzés névsorán is lehessen hozzáadni és javítani.
5. Archiválj egy felvett személyt; a korábbi névsorban a neve maradjon látható,
   új hozzáadás választójában ne szerepeljen. Elmaradt edzéshez ne lehessen hozzáadni.
6. Student/pending fiókkal az adminoldal és minden attendance API-művelet legyen tiltott.

Ebben az egységben nem készült `payment_type`, tartozás, alkalomlevonás,
bérlet-, fizetés-, QR- vagy reservation-kezelés. A már egyeztetett díjak és
hozott alkalmak változatlanul későbbi követelmények.

Hivatalos dokumentáció: [Laravel 13 resource controllerek és scoped route-ok](https://laravel.com/framework/docs/13.x/controllers),
[FormRequest-validáció](https://laravel.com/framework/docs/13.x/validation),
[Eloquent-kapcsolatok](https://laravel.com/framework/docs/13.x/eloquent-relationships).

### Későbbi résztvevői alkalom- és díjkezelés

Az edzés árának kivezetése után az alábbi üzleti igényeket külön egységben
valósítjuk meg; ehhez most nem készültek táblák, egyenlegek vagy levonások:

- Egyszeri alkalom jelenlegi díja: **2500 Ft**.
- Tízalkalmas bérlet jelenlegi ára: **22 000 Ft**.
- Másik teremből hozott, korábban megvásárolt alkalmak: résztvevőnként külön
  mennyiség, például **3 alkalom**, ismert pénzérték nélkül.

A résztvevőhöz a megvásárolt bérletek és a felhasználható alkalmak kapcsolódjanak,
ne az edzéshez egyetlen kötelező ár. A hozott alkalmak pénzértéke ismeretlen,
nem 0 Ft: ezért ezekhez nem rendelünk kitalált összeget. Az alkalomfelhasználás
és a részvételek elszámolásának szabályai külön későbbi egységben készülnek el.

## Adatok és titkos konfiguráció

- Jelszó, hozzáférési token, privát kulcs és valódi résztvevői adat
  nem kerülhet a repositoryba.
- A `.env` és a helyi SQLite-adatbázis ki van zárva a Gitből.
- A `.env.example` kizárólag megosztható példaértékeket tartalmazhat.
- A későbbi factoryk és seederek csak kitalált mintaadatokat használhatnak.
- A Composer és az npm lockfájljait verziókövetjük.
- A telepített függőségek és a generált buildfájlok nincsenek verziókövetve.

