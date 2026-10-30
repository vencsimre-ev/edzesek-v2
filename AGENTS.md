# Projektutasítások

## Környezet és technológiák

- Meglévő Laravel 13 projekt, PHP 8.3, SQLite.
- Frontend: Blade + Bootstrap 5 + Vite.
- Node.js 24 NVM-en keresztül.
- Fejlesztői email: a már futó Mailpit Docker-konténer.
- Ne vezess be Tailwindot, SPA-t, Inertiát, Vue-t vagy Reactot.
- Ne telepíts Laravel Boostot, és ne futtass boost:install vagy
  boost:update parancsot. Ebben a projektben nem használunk Boostot.
- A működő fejlesztői környezetet ne telepítsd újra, és ne válts
  PHP-, Node- vagy Laravel-főverziót a feladat részeként.


## Architektúra:
- Kifejezetten REST API alapú backend kialakítására törekedj.
- Web controller: Blade-nézet vagy redirect.
- API controller: JSON-válasz JsonResource használatával.
- Mindkettő ugyanazt a service/action üzleti logikát hívja.
- Ne duplikáld a jóváhagyás vagy a fiók-összekapcsolás szabályait.
- A Blade-felület ne indítson HTTP-kérést a saját backendjéhez.
- Ne legyen üzleti logika Resource vagy Blade fájlban.
- Ne készíts általános repository-réteget vagy univerzális
  response-wrapper osztályt pusztán az egységesség kedvéért.

## API-konvenciók:
- routes/api.php, /api/v1 prefix.
- Controllers/Api/V1 és Controllers/Web elkülönítés.
- Resource controller és Route::apiResource a tényleges CRUD
  erőforrásokhoz, csak az engedélyezett műveletekkel.
- Külön endpoint a speciális üzleti műveletekhez.
- Ne használj controller `__invoke` metódust. Használj névvel ellátott
  metódusokat: `index`, `show`, `store`, `update`, `destroy`, `approve`, `cancel` stb.
- FormRequest az alkalmazás saját validációjához;
  a Fortify beépített validációját ne duplikáld indokolatlanul.
- JsonResource explicit mezőlistával, collection és lapozás.
- Enumok értékeinek következetes JSON-reprezentációja.
- A műveletek jogosultságát policykkal ellenőrizd; a webes és API-controllerek
  közös service/action üzleti logikát használjanak.
- Jelszó, remember token és OAuth-token soha ne szerepeljen válaszban.
- Következetes HTTP-státuszok és JSON-hibák:
  401, 403, 404, 422; konfliktusnál indokolt esetben 409.
- Az API hibánál ne HTML-oldalt vagy login redirectet adjon.

## Megvalósítás

- Először vizsgáld meg a repository aktuális állapotát és konvencióit.
- Az adott Laravel-verzió hivatalos dokumentációját használd.
- Az üzleti szabályok service/action osztályokban legyenek.
- A controllerek maradjanak rövidek.
- A validációhoz használd a Laravel és Fortify meglévő megoldásait.
- A jogosultságokat szerveroldalon ellenőrizd.
- Egyszerű, működő megoldásra törekedj; ne építs szükségtelen absztrakciókat.
- Kis, külön átnézhető egységekben dolgozz.
- A felület, a README és a munkáról adott magyarázat legyen magyar.
- A kódnevek és a commitüzenetek legyenek angolul.
- Minden modell/tábla esetén a létrehozásnál használjuk az időbélyegeket: created_at és updated_at

## Adatok és konfiguráció

- Ne írd ki vagy commitold a .env tartalmát, jelszavakat, tokeneket,
  privát kulcsokat vagy valódi résztvevői adatokat.
- A .env.example csak megosztható példaértékeket tartalmazhat.
- Az SQLite-adatbázist ne commitold.
- Tesztekben, factorykban és seederekben kizárólag mintaadat legyen.
- A tesztek külön tesztadatbázist használjanak.
- Ne futtass migrate:fresh, adatbázistörlést vagy más adatvesztéssel
  járó parancsot a meglévő helyi adatbázison.
- Más projektek Docker-konténereit és konfigurációját ne módosítsd.

## Ellenőrzés és Git

- Írj érdemi feature teszteket az auth- és jogosultsági szabályokra.
- Futtasd a releváns teszteket, frontend-változásnál a buildet is.
- Frissítsd a magyar README-t az elkészült működés szerint.
- A felhasználó meglévő változtatásait őrizd meg, ne írd felül.
- A commitokat a felhasználó készíti. Ne futtass `git add`, `git commit`
  vagy `git push` parancsot.
- Minden egység végén adj változásösszefoglalót, ellenőrzési eredményeket
  és javasolt angol commitüzenetet, majd állj meg átnézésre.
