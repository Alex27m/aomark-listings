# Aomark Listings 3.5.0 — handoff i stanje rada

Datum: 14. jul 2026.  
Radna grana: `agent/listings-audit-ux`

Ovaj dokument beleži šta je urađeno tokom audita i pojednostavljivanja plugina, šta je automatski provereno i šta prvo treba ručno proveriti na test WordPress sajtu. Cilj izmene je da postojeća funkcionalnost ostane ista, a da konfiguracija, frontend ponašanje i održavanje budu sigurniji i jasniji.

## Glavni rezultat

- Verzija je pripremljena kao `3.5.0`.
- Postojeći listing modeli, post type ključevi, taxonomy slugovi, field ID-jevi i meta ključevi ostaju kompatibilni.
- Normalan admin interfejs više ne dozvoljava slučajnu promenu postojećih tehničkih identifikatora.
- Podešavanje je pretvoreno u vođeniji tok sa presetima, objašnjenjima, praznim stanjima i jasnim sledećim koracima.
- Filteri, sortiranje i paginacija imaju normalan URL/no-JavaScript tok; AJAX je progresivno poboljšanje.
- Filter–Results–Map grupe su izolovane modelom i opcionim Connection ID-jem.
- Leaflet se isporučuje lokalno, a eksterni Photon/OpenStreetMap servisi su dokumentovani u readme i WordPress Privacy Policy Guide-u.

## Urađene izmene

### 1. Modeli, migracije i zaštita podataka

- Uvedena je eksplicitna schema verzija i idempotentan backup prethodnog registra modela pre upgrade-a.
- Rewrite pravila se osvežavaju kontrolisano, samo kada je konfiguracija promenjena.
- Registry validacija proverava duple model ID-jeve, post type ključeve, taxonomy slugove, field ID-jeve i meta ključeve.
- Location polje sada rezerviše i izvedene ključeve `_address`, `_lat` i `_lng`, pa obično polje ne može da prepiše koordinate.
- Dupliranje preseta dobija deterministički namespace bez promene istorijskih ključeva prvog ugrađenog preseta.
- Tehnički identifikatori postojećih redova proveravaju se preko originalnog ID-ja, a malformed admin redovi failuju zatvoreno umesto da tiho obrišu konfiguraciju.
- Validan string ID ili select vrednost `"0"` više se ne gubi zbog PHP truthiness provera.

### 2. Admin iskustvo

- Dodati su pregled listing tipova, preset kartice, setup koraci, brojači sadržaja, prazna stanja i jasni CTA linkovi.
- Tehničke vrednosti su izdvojene u read-only detalje; prijateljski nazivi ostaju normalno izmenjivi.
- Dodata su kontekstualna objašnjenja, WordPress help tabovi i Site Health provera konfiguracije.
- Novi field/taxonomy ID-jevi imaju JavaScript generator i server-side fallback, tako da kreiranje radi i bez admin JavaScript-a.
- Label dužine se proveravaju serverski i multibyte-safe su.
- Location editor ima lokalni Leaflet, pretragu adrese, tastaturnu interakciju, abort/stale-request zaštitu i jasne status poruke.
- Photon geocoder je ograničen na ovlašćene editore, nonce, pet rezultata, 30 uncached zahteva po minutu i 12-časovni cache.

### 3. Meta vrednosti i editor listinga

- Brojevi i cene podržavaju standardni i evropski zapis; scientific notation se prevodi u konačan decimalni zapis.
- Ne-finite i malformed vrednosti se odbacuju bez PHP warning/fatal greške.
- Datumi se proveravaju kao stvarni ISO datumi.
- URL/email, image/gallery i location vrednosti imaju scalar, attachment i coordinate validaciju.
- Location koordinate imaju dozvoljeni opseg i podršku za legacy decimalni zarez/scientific zapis pri čitanju.

### 4. Javni query, filteri i AJAX

- Javni upiti eksplicitno koriste tačan CPT, `publish` status i isključuju password-protected objave pre paginacije.
- Nepoznat model više ne pada na prvi dostupni model.
- Filter ključevi, vrednosti, broj uslova, page/per-page i map limit su allowlistovani i ograničeni.
- AJAX konfiguracija koristi HMAC-potpisane, purpose-bound deskriptore za Results i Map.
- Tampered, unsigned ili pogrešan descriptor/model failuje zatvoreno.
- Filteri su model-scoped, uključujući stranice sa više listing tipova.
- Filter forme, reset, sortiranje, numbered pagination i Load More imaju ispravne normalne linkove kada JavaScript nije dostupan.
- AJAX pagination linkovi ostaju vezani za javni dokument, a ne za `admin-ajax.php`.

### 5. Frontend i browser istorija

- Svaki Results widget ima zasebno stanje, AbortController i stale-response zaštitu.
- Filter, Results i query Map se povezuju istim modelom i opcionim Connection ID-jem.
- Browser Back/Forward čuva snapshot po modelu i konekciji, uključujući stranice sa više grupa.
- Load More ne prepisuje URL-addressable history snapshot parcijalnom drugom stranom.
- Form sync proverava i model i Connection ID, pa isti Connection ID na različitim modelima ne ukršta forme.
- Dodati su status regioni, loading/error/retry stanja, focus ponašanje i custom lifecycle događaji.

### 6. Mape i eksterni servisi

- Leaflet 1.9.4 je vendorizovan u `assets/vendor/leaflet/` sa licencom i potrebnim slikama.
- Plugin interno koristi prefiksovane asset handle-ove; stari generički Leaflet aliasi ostaju radi kompatibilnosti.
- Standardni OpenStreetMap tile URL je `https://tile.openstreetmap.org/{z}/{x}/{y}.png`.
- Map upit odbacuje malformed koordinate pre ograničenog WP_Query rezultata, a finalna PHP validacija proverava finite vrednosti i opseg.
- Prazne i dinamički ažurirane mape imaju dostupna empty/status stanja.
- Photon i OpenStreetMap prenos podataka, privatnost, politike i atribucija opisani su u `readme.txt`, `README.md` i WordPress Privacy Policy Guide-u.

### 7. Elementor i asset kompatibilnost

- Sačuvana su postojeća imena svih šest widgeta.
- Dodata su jasnija empty/editor stanja i opisi kontrola.
- Moderni Elementor registration hook je primaran; deprecated legacy hook se kači samo za Elementor stariji od 3.5.
- Registracija asseta je idempotentna i pokriva frontend/editor hookove.

### 8. Dokumentacija i CI

- Ažurirani su `README.md` i WordPress `readme.txt` za verziju 3.5.0, upgrade napomene, no-JS ponašanje, više widget grupa i eksterne servise.
- Dodati su:
  - `docs/quick-start.md`
  - `docs/compatibility-contract.md`
  - `docs/testing.md`
  - ovaj handoff dokument
- Dodati su lightweight PHP model-contract testovi i Node static-contract testovi.
- GitHub Actions proverava PHP 7.4, 8.3 i 8.5, JavaScript sintaksu, ugovorne testove i WordPress Plugin Check.
- Plugin Check sada dobija čist `build/aomark-listings` staging direktorijum bez `.git*`, `.github` i razvojnih test fajlova.

## Automatske provere na ovom snapshotu

Sledeće provere su prošle neposredno pre handoff commita:

```text
PHP lint: svi PHP fajlovi — PASS
tests/model-contract.php — PASS
Node syntax: assets/js/*.js — PASS
tests/static-contracts.mjs — PASS
git diff --check — PASS
```

Komande:

```powershell
$php = Join-Path $env:TEMP 'aomark-php-8.3.29\php.exe'
& $php tests\model-contract.php
node tests\static-contracts.mjs
node --check assets\js\listings.js
node --check assets\js\admin-listings.js
git diff --check
```

Nije pokrenut kompletan WordPress + Elementor browser test u ovom folderu jer lokalno nije bio dostupan spreman Docker/WSL WordPress runtime. To je sledeći glavni korak.

## Prioritetni ručni test na lažnom sajtu

1. Napraviti backup test sajta i očistiti page/CDN cache posle instalacije 3.5.0.
2. Aktivirati plugin na čistom sajtu i napraviti po jedan Real Estate, Directory i Custom tip.
3. Dodati listing sa svakim tipom polja, posebno price, select vrednost `0`, image/gallery, date i location.
4. Sačuvati listing, ponovo ga otvoriti i proveriti da nijedna vrednost nije izgubljena.
5. Napraviti Elementor stranu sa Filter + Results + Map i istim modelom/Connection ID-jem.
6. Proveriti filter, reset, sort, numbered pagination, Load More, map update, retry i Back/Forward.
7. Isključiti JavaScript i ponoviti filter/reset/sort/pagination.
8. Na istoj strani dodati drugi listing model i potvrditi da se grupe ne mešaju.
9. Proveriti current-listing Field, Meta, Gallery i Map widgete na published, draft i password-protected sadržaju.
10. Proveriti Photon pretragu, ručno pomeranje pina, praznu mapu i ponašanje bez mreže.
11. Upgrade test: instalirati 3.4.x podatke, zatim 3.5.0, proveriti backup option, stare Elementor dokumente i postojeće URL-ove.
12. Pregledati Site Health i WordPress Privacy Policy Guide.

Detaljna matrica je u `docs/testing.md`.

## Poznata ograničenja / odluke

- Javni `alm_*` URL predstavlja jedno stanje po listing tipu. Dve grupe istog tipa mogu biti odvojene uživo različitim Connection ID-jevima, ali za potpuno nezavisne shareable/reload URL-ove treba koristiti odvojene stranice.
- Listing modeli i native editor rade bez Elementora; šest vizuelnih widgeta zahteva Elementor.
- Mapa i address search podrazumevano koriste spoljne OSM/Photon servise kada su te funkcije uključene.
- Pre finalnog release tag-a i dalje je obavezan ručni WordPress/Elementor test iz prethodne sekcije i pregled rezultata GitHub Plugin Check job-a.

## Nastavak rada sa drugog računara

```bash
git fetch origin
git checkout agent/listings-audit-ux
git pull
```

Posle ručnog testa, nalaze beležiti uz konkretan model, widget, Connection ID, URL i browser korake, pa ispravke dodavati na istu granu/draft PR.
