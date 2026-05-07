# Public Player + Catalog Web Implementation Plan

> **For Hermes:** Implement this plan in `~/muzicarap` with TDD where practical, keeping controllers thin and local-only git commits.

**Goal:** Add a public MuzicaRap site that lists the latest published songs, exposes published artist/album/song detail pages, supports local audio uploads in Filament, and renders animated synced lyrics on the public song page when timestamps exist.

**Architecture:** Keep the admin upload/editor flow in Filament, then expose a separate public read-only catalog via Inertia pages and thin controllers. Use nested slug routes under artists for scoped binding, storage-backed audio streaming routes for local files, and a Vue audio player that highlights/scrolls synchronized lyric segments based on timestamps.

**Tech Stack:** Laravel 12, Inertia.js, Vue 3, Filament 5, Pest, Tailwind CSS, local filesystem storage.

---

### Task 1: Add failing public catalog feature tests

**Objective:** Lock expected public behavior before implementation.

**Files:**
- Create: `tests/Feature/PublicCatalogTest.php`

**Steps:**
1. Add a test for the home page showing latest published songs and hiding unpublished ones.
2. Add a test for the artist detail page returning published albums/songs only.
3. Add a test for the album detail page returning track listing.
4. Add a test for the song detail page returning synced lyric segments and public audio URL.
5. Add a test for the public audio stream route serving a local file for a published song.

**Verification:**
- Run `php artisan test tests/Feature/PublicCatalogTest.php --compact`
- Expected first run: FAIL because routes/pages/controllers do not exist yet.

### Task 2: Prepare route binding and model helpers

**Objective:** Make artist/album/song URLs stable and storage-aware.

**Files:**
- Modify: `app/Models/Artist.php`
- Modify: `app/Models/Album.php`
- Modify: `app/Models/Song.php`
- Modify: `app/Models/Lyric.php`

**Steps:**
1. Add `getRouteKeyName(): string` for slug-based binding on artist/album/song.
2. Add typed published scopes return values if needed.
3. Add helper methods for public audio availability / synced lyrics state if they reduce duplication.
4. Keep changes minimal and model-centric.

**Verification:**
- Re-run targeted tests; route binding failures should move forward.

### Task 3: Add public catalog controllers and routes

**Objective:** Serve read-only public pages from published records only.

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/Public/HomeController.php`
- Create: `app/Http/Controllers/Public/ArtistShowController.php`
- Create: `app/Http/Controllers/Public/AlbumShowController.php`
- Create: `app/Http/Controllers/Public/SongShowController.php`
- Create: `app/Http/Controllers/Public/StreamPublishedSongAudioController.php`

**Steps:**
1. Replace the placeholder welcome route with a catalog home route.
2. Add nested scoped public routes:
   - `/artisti/{artist:slug}`
   - `/artisti/{artist:slug}/albume/{album:slug}`
   - `/artisti/{artist:slug}/melodii/{song:slug}`
   - `/melodii/{song:slug}/audio`
3. In controllers, load only published/public-safe data and abort on mismatched nesting.
4. Stream local audio through Laravel so the default local filesystem works without S3.

**Verification:**
- Run `php artisan test tests/Feature/PublicCatalogTest.php --compact`

### Task 4: Support audio uploads properly in Filament

**Objective:** Make admin upload workflow production-usable for MP3 files on local storage.

**Files:**
- Modify: `app/Filament/Resources/Songs/Schemas/SongForm.php`
- Modify if needed: `app/Filament/Resources/Songs/Tables/SongsTable.php`
- Modify if needed: `database/factories/SongFactory.php`
- Modify if needed: `database/seeders/DemoCatalogSeeder.php`

**Steps:**
1. Restrict uploads to audio/mp3-like MIME types.
2. Store path only in DB using the configured default disk.
3. Add helper text so future S3 config remains transparent.
4. Ensure seeded/demo song can optionally expose a local audio path if useful, but do not require a real media file for tests.

**Verification:**
- Re-run existing song admin tests if any and full feature suite later.

### Task 5: Add public Inertia/Vue pages

**Objective:** Render the public catalog and player UX.

**Files:**
- Create: `resources/js/pages/Home.vue`
- Create: `resources/js/pages/Public/Artists/Show.vue`
- Create: `resources/js/pages/Public/Albums/Show.vue`
- Create: `resources/js/pages/Public/Songs/Show.vue`
- Modify: `resources/js/types/index.ts`
- Create: `resources/js/types/public-catalog.ts`

**Steps:**
1. Home page: latest songs list with links and metadata.
2. Artist page: hero, bio, published albums, standalone songs.
3. Album page: album meta + ordered track list.
4. Song page: audio player + animated synced lyric list.
5. Auto-highlight current segment and scroll active line into view only when timestamps are present.
6. Fall back gracefully when lyrics exist but are not synced.

**Verification:**
- Run `npm run types:check`
- Run `npm run lint:check`

### Task 6: Add Romanian README

**Objective:** Document the app in Romanian for local development and current storage behavior.

**Files:**
- Create: `README.md`

**Steps:**
1. Describe stack, setup, local run commands, admin login seeding, and current local filesystem audio behavior.
2. Mention that storage path is saved in DB and S3 can be enabled later via filesystem config.
3. Document the public pages and admin lyrics workflow briefly.

**Verification:**
- Read the file back for clarity.

### Task 7: Full verification and cleanup

**Objective:** Leave the repo in a tested state.

**Files:**
- Modify whatever the failing checks point to.

**Steps:**
1. Run `php artisan test --compact`
2. Run `vendor/bin/pint --dirty --format agent`
3. Run `npm run lint:check`
4. Run `npm run types:check`
5. Fix failures and rerun until all pass.

**Completion criteria:**
- Public catalog routes/pages work.
- Admin can upload local audio files.
- Song page animates synced lyrics when timestamps exist.
- README is in Romanian.
- All required verification commands pass.