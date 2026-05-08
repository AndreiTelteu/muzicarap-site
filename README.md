# MuzicaRap

MuzicaRap este o aplicație Laravel + Inertia + Vue pentru administrarea și publicarea unui catalog de muzică rap. Proiectul include panou admin cu Filament, player public pentru piese publicate și flux de sincronizare a versurilor.

## Ce face aplicația

- gestionează artiști, albume și piese din Filament
- permite încărcarea locală de fișiere MP3 pentru piese
- publică un catalog cu ultimele piese, pagini de artist, album și piesă
- oferă streaming audio prin rute Laravel, fără expunere directă a fișierelor
- afișează versuri simple sau versuri sincronizate cu highlight și auto-scroll
- include flux admin pentru crawl, curățare și sincronizare manuală a versurilor

## Cerințe

- PHP 8.5+
- Composer
- Node.js 22+
- npm
- o bază de date configurată în `.env`

## Mediu local

- pe mașina asta, binarul PHP folosit pentru comenzi CLI este `/home/andrei/.config/herd-lite/bin/php`
- dacă `php` nu există în `PATH`, folosește explicit `/home/andrei/.config/herd-lite/bin/php artisan ...`

## Instalare locală

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```

Pentru rulare completă în development poți folosi și comenzile Herd / Laravel locale deja configurate pe mașină.

## Flux recomandat de lucru

### Admin

1. Creezi sau publici un artist.
2. Creezi un album dacă piesa aparține unui album sau EP.
3. Adaugi o piesă din Filament.
4. În formularul piesei poți încărca un fișier MP3 local în câmpul `audio_path`.
5. Din zona admin poți sincroniza versurile și verifica audio-ul încărcat.

### Public

- `/` afișează cele mai noi piese publicate
- `/artisti/{artist}` afișează artistul și materialele publice
- `/artisti/{artist}/albume/{album}` afișează albumul și tracklist-ul
- `/artisti/{artist}/piese/{song}` afișează playerul public, versurile și sincronizarea pe timestamps

## Verificare

Comenzile de verificare folosite în proiect:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run lint:check
npm run types:check
```

## Observații

- audio-ul public este servit de aplicație din filesystem-ul implicit configurat în Laravel
- pentru playerul public, piesa trebuie să fie publicată și să aibă fișier audio existent
- versurile sincronizate devin animate automat când există segmente cu timestamps
