# Edzések v2

Mászóedzések, résztvevők, részvételek, bérletalkalmak és manuálisan
rögzített befizetések kezelésére készülő webalkalmazás.

A fejlesztés lépésenként halad. Az edzéskezelés és a hitelesítés
funkciói még nincsenek megvalósítva.

## Jelenlegi állapot

- Laravel 13 projekt létrehozva.
- Helyi SQLite-adatbázis és az alapmigrációk működnek.
- Frontend függőségek telepítve, a build ellenőrizve.
- Az alkalmazás helyben elindul.
- Git-verziókövetés és GitHub-kapcsolat beállítva.

## Technológiák

- Laravel 13
- PHP 8.3+
- SQLite
- Node.js 24 LTS és npm
- Vite

Tervezett kiegészítések:

- Blade és Bootstrap felület
- Laravel Fortify hitelesítés
- Google-belépés Laravel Socialite használatával
- Mailpit a fejlesztés közbeni emailteszteléshez

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

## Tervezett hitelesítési folyamat

Emailes és jelszavas regisztráció:

1. Regisztráció.
2. Az emailcím megerősítése.
3. Várakozás az admin jóváhagyására.
4. Jóváhagyás után hozzáférés az alkalmazás védett funkcióihoz.

A Google-regisztrációhoz is szükséges lesz admin jóváhagyás.

A még nem megerősített vagy jóváhagyásra váró felhasználók csak
a korlátozott hitelesítési és állapotjelző oldalakat érhetik majd el.

## Adatok és titkos konfiguráció

- Jelszó, hozzáférési token, privát kulcs és valódi résztvevői adat
  nem kerülhet a repositoryba.
- A `.env` és a helyi SQLite-adatbázis ki van zárva a Gitből.
- A `.env.example` kizárólag megosztható példaértékeket tartalmazhat.
- A későbbi factoryk és seederek csak kitalált mintaadatokat használhatnak.
- A Composer és az npm lockfájljait verziókövetjük.
- A telepített függőségek és a generált buildfájlok nincsenek verziókövetve.

