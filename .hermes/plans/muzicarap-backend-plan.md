# MuzicaRap Backend Implementation Plan

## Current baseline

- Project is still the blank Laravel 13 + Inertia v3 + Vue 3 starter.
- Only the `Welcome` Inertia page is wired on `/`.
- `filament/filament` and `laravel/ai` are not installed yet.
- Existing tables are only the starter `users`, `cache`, and `jobs` tables.
- Use **local git commits only** after each completed phase; do not push until the full backend is accepted.

## Delivery goals

Build an admin-first music backend that supports:

1. Artists, albums, songs, and lyrics management.
2. Filament admin CRUD for artists, albums, and songs.
3. A custom lyrics sync editor with manual timestamping via spacebar while audio plays, plus progress bar, auto-scroll, and zoom.
4. A manual crawl flow that searches SearxNG, crawls candidate pages, cleans lyrics with `laravel/ai`, and stores the result.
5. Solid authorization, queues, retries, observability, and Pest coverage.

## Recommended implementation phases

### Phase 0 - Project foundation and package setup

**Goal:** establish admin/auth, queue, and AI dependencies before building the domain.

**Packages and setup**

1. Install Filament v5:
   - `composer require filament/filament:"^5.0"`
   - `php artisan filament:install --panels --no-interaction`
2. Install Laravel AI:
   - `composer require laravel/ai`
   - `php artisan ai:install --no-interaction`
3. Add HTML parsing support for crawling if not already present:
   - `composer require symfony/dom-crawler symfony/css-selector`
4. Run migrations and verify Filament panel boot:
   - `php artisan migrate`
5. Decide the queue backend for local/dev:
   - start with database queue
   - keep jobs table already present
   - ensure `QUEUE_CONNECTION=database`

**Auth/admin foundation**

- Extend `users` with an `is_admin` boolean.
- Use Filament panel auth for admin login.
- Restrict panel access through the user model/panel access contract and policies.
- Seed one local admin user for development.

**Git checkpoint**

- Local commit after Filament + AI + admin access are working.

### Phase 1 - Core music domain schema

**Goal:** model artists, releases, songs, lyrics, sync segments, and crawl attempts cleanly.

**Database design**

1. `artists`
   - `id`
   - `name`
   - `slug` unique
   - `bio` nullable long text
   - `image_path` nullable
   - `is_published` boolean default false
   - timestamps
   - indexes: `slug`, `is_published`
2. `albums`
   - `id`
   - `artist_id` foreign key
   - `title`
   - `slug`
   - `type` enum: `album|ep`
   - `release_date` nullable
   - `cover_path` nullable
   - `description` nullable long text
   - timestamps
   - unique index on `artist_id + slug`
   - indexes: `artist_id`, `type`, `release_date`
3. `songs`
   - `id`
   - `artist_id` foreign key
   - `album_id` nullable foreign key
   - `title`
   - `slug`
   - `track_number` nullable
   - `parent_type` enum: `album|ep|single`
   - `duration_seconds` nullable
   - `audio_path` nullable
   - `is_published` boolean default false
   - timestamps
   - unique index on `artist_id + slug`
   - indexes: `artist_id`, `album_id`, `parent_type`, `is_published`
4. `lyrics`
   - `id`
   - `song_id` unique foreign key
   - `lyrics` long text
   - `external_source_url` nullable text
   - `source_status` enum: `manual|queued|crawled|cleaned|failed`
   - `synced_at` nullable timestamp
   - `crawl_confidence` nullable decimal
   - timestamps
5. `lyric_segments`
   - `id`
   - `lyric_id` foreign key
   - `position` integer
   - `text` text
   - `starts_at_ms` nullable integer
   - `ends_at_ms` nullable integer
   - `is_instrumental_gap` boolean default false
   - timestamps
   - unique index on `lyric_id + position`
6. `lyrics_crawl_runs`
   - `id`
   - `song_id` foreign key
   - `status` enum: `queued|searching|crawling|cleaning|stored|failed`
   - `search_query`
   - `candidate_urls` json nullable
   - `selected_url` nullable text
   - `failure_reason` nullable text
   - `response_snapshot` nullable json
   - `started_at` nullable timestamp
   - `finished_at` nullable timestamp
   - timestamps
   - indexes: `song_id`, `status`, `created_at`

**Model layer**

- Models: `Artist`, `Album`, `Song`, `Lyric`, `LyricSegment`, `LyricsCrawlRun`.
- Relationships:
  - artist `hasMany` albums, songs
  - album `belongsTo` artist and `hasMany` songs
  - song `belongsTo` artist, `belongsTo` album nullable, `hasOne` lyric
  - lyric `belongsTo` song and `hasMany` lyric segments
  - crawl run `belongsTo` song
- Add casts for enums, booleans, json, and date fields.
- Add query scopes for published content and incomplete lyrics coverage.

**Policy plan**

- `ArtistPolicy`
- `AlbumPolicy`
- `SongPolicy`
- `LyricPolicy`
- `LyricsCrawlRunPolicy` if exposed in admin
- First version can be admin-only; keep policy methods explicit so public/editor roles can be added later.

**Git checkpoint**

- Local commit after migrations, models, factories, seeders, and policies exist.

### Phase 2 - Filament admin CRUD

**Goal:** make content manageable without touching raw database records.

**Filament resources**

1. `ArtistResource`
   - forms: name, slug, bio, image, published toggle
   - table: name, slug, albums count, songs count, published state
   - actions: view songs, bulk publish/unpublish
2. `AlbumResource`
   - forms: artist, title, slug, type, release date, cover, description
   - table: title, artist, type, release date, songs count
   - filters: artist, type, published artist
3. `SongResource`
   - forms: artist, album nullable, title, slug, track number, parent type, duration, audio file, published toggle
   - table: title, artist, album, parent type, lyric status, crawl status
   - row/header actions:
     - open lyrics sync editor
     - queue manual crawl
     - clear existing lyrics after confirmation

**Filament relation managers / embedded management**

- Artist -> Albums relation manager
- Artist -> Songs relation manager
- Song -> Lyric read-only summary or quick inline editor for raw lyrics
- Song -> Crawl runs relation manager for audit history

**Filament pages/widgets**

1. Dashboard widgets
   - total artists/albums/songs
   - lyrics coverage percentage
   - recent crawl failures
   - songs missing lyrics
2. `LyricsQueueMonitor` widget
   - queued, running, failed counts
3. `MissingLyricsTable` widget
   - direct jump to crawl or sync
4. Optional custom Filament page:
   - `LyricsOperationsPage` for reviewing crawl attempts in one place

**Why not keep the sync editor fully inside Filament Livewire**

- CRUD belongs in Filament.
- The timestamp editor is high-interaction audio UX and is better implemented as a dedicated Inertia/Vue admin page, linked from Filament actions.
- This keeps Filament clean while still making the editor part of the admin workflow.

**Git checkpoint**

- Local commit after CRUD flows work end-to-end in the admin panel.

### Phase 3 - Lyrics sync data model and editor workflow

**Goal:** support manual lyric timing with a durable storage format.

**Persistent sync model**

- Store the canonical full text in `lyrics.lyrics`.
- Split the text into ordered `lyric_segments`.
- Save timestamps per segment in milliseconds.
- Mark `lyrics.synced_at` when all required segments are timed and saved.
- Rebuild the full text from segments only for validation/debugging; the canonical editable body remains in `lyrics.lyrics`.

**Editor behavior**

1. Load song metadata, audio file, lyric text, and existing segments.
2. Display line-by-line lyric segments beside a timeline/progress bar.
3. Pressing **space** while audio plays stamps the current line's `starts_at_ms` and advances focus to the next line.
4. Support manual previous/next adjustments, reset line, and save-all.
5. Auto-scroll the active line into view while playback advances.
6. Provide zoom levels for the timeline so short and long songs remain editable.
7. Show unsaved changes and last saved timestamp.
8. Allow re-segmentation when the raw lyric text changes.

**Frontend/Inertia pages needed**

1. `Admin/LyricsSync/Edit`
   - admin-only page launched from Filament
   - Vue-driven keyboard/audio interactions
2. Optional `Admin/Songs/Show`
   - only if song detail needs richer UI than Filament provides

**Backend endpoints/services**

- Dedicated admin route/controller or action-backed route for:
  - loading editor payload
  - storing segment timestamps
  - re-splitting lyrics into segments
- Use Form Requests for validation.
- Keep controllers thin and push save logic into actions/services.

**Git checkpoint**

- Local commit after the editor can load, time, save, and reload lyric segments reliably.

### Phase 4 - Manual crawl pipeline and Laravel AI agent

**Goal:** let admins fetch lyrics on demand from SearxNG-backed discovery and AI-assisted cleanup.

**Search and crawl flow**

1. Admin clicks **Manual Crawl** from `SongResource`.
2. App creates a `lyrics_crawl_runs` record and dispatches a unique queue job for the song.
3. Job builds the search query from artist + song, for example:
   - `{artist} {song} versuri`
4. Search request goes to:
   - `http://192.168.0.115:8388/search?q={query}&format=json`
5. App filters the returned candidates:
   - deduplicate URLs
   - discard unsupported domains/content types
   - prioritize likely lyric pages by title/snippet/domain heuristics
6. App fetches the top candidates with Laravel HTTP client using explicit timeout, connect timeout, retry, and status handling.
7. Raw HTML is reduced to text candidate blocks.
8. Laravel AI agent receives the song context plus extracted candidate text and returns:
   - cleaned lyric body
   - confidence/explanation
   - detected source URL
   - rejection when confidence is too low
9. App stores cleaned lyrics and rebuilds lyric segments.
10. Admin can review/edit the result in the sync editor immediately.

**Services and jobs**

- `SearxNgLyricsSearchService`
  - wraps the SearxNG request/response contract
- `LyricsCandidateFetcher`
  - downloads and normalizes candidate pages
- `LyricsExtractionService`
  - extracts text blocks from HTML before AI cleanup
- `CleanLyricsWithAiAction`
  - calls `laravel/ai` agent with strict output schema expectations
- `StoreLyricsFromCrawlAction`
  - writes `lyrics`, regenerates segments, updates crawl run state
- `CrawlLyricsForSongJob`
  - unique per song, handles retries/backoff/failure tracking

**Agent design**

- Keep one dedicated lyrics-cleaning agent rather than a broad multi-purpose agent.
- Agent input:
  - artist name
  - song title
  - optional album title
  - candidate URL/title/snippet
  - extracted raw text
- Agent output contract:
  - `status`: accepted or rejected
  - `clean_lyrics`
  - `source_url`
  - `confidence_score`
  - `notes`
- Rejection rules:
  - text is obviously article/review content
  - too little overlap with expected song title
  - language/content does not resemble lyrics
  - duplicate/garbled repeated blocks dominate the content

**Operational safeguards**

- Unique job lock per song to prevent duplicate crawls.
- Exponential backoff for network/provider errors.
- Clear `failed()` handling to update `lyrics_crawl_runs`.
- Rate-limit middleware around external HTTP and AI calls.
- Log structured crawl context for troubleshooting.

**Git checkpoint**

- Local commit after one-click crawl can search, fetch, clean, and store lyrics.

### Phase 5 - Admin review workflow and observability

**Goal:** make failures, retries, and content QA manageable.

**Admin review capabilities**

- Show latest crawl state directly in the Song table.
- Show crawl history in a relation manager or dedicated page.
- Add actions:
  - retry crawl
  - mark as manual override
  - accept current lyrics and lock them
  - re-segment after manual text edits

**Audit/quality improvements**

- Track whether lyrics were AI-imported or manually corrected.
- Record last editor/admin user on lyrics updates.
- Highlight songs with:
  - no audio attached
  - lyrics present but unsynced
  - failed crawl attempts
  - stale external source URL

**Git checkpoint**

- Local commit after admin users can diagnose and recover crawl/import issues.

### Phase 6 - Public/Inertia site surface

**Goal:** expose only the minimum public pages needed after the backend is stable.

**Recommended order**

1. Replace the scaffold `Welcome` page with a simple published-content landing page.
2. Add public Inertia pages only after admin flows are stable:
   - artist index/detail
   - album detail
   - song detail with lyrics
3. Keep sync editor admin-only; do not expose timestamp editing publicly.

**Public data rules**

- Only published artists/songs appear publicly.
- Do not expose failed crawl data or admin notes.
- If synced timestamps exist, the public song page can later render karaoke-style highlighting, but that is a follow-up phase, not MVP.

**Git checkpoint**

- Local commit after public pages use published data only.

### Phase 7 - Testing plan

**Goal:** prove the backend is safe to extend and safe to run.

**Pest coverage**

1. Feature tests for admin access
   - non-admin denied Filament/admin routes
   - admin allowed
2. Feature tests for CRUD
   - create/update/delete artists, albums, songs
   - validation for album/song relationships
   - `parent_type=single` works with nullable `album_id`
3. Feature tests for lyrics editor endpoints
   - save segments
   - re-segment after lyric text change
   - reject invalid timestamp ordering
4. Queue/job tests
   - manual crawl dispatches unique job
   - failed crawl updates run status
   - retry path preserves audit history
5. HTTP integration tests with fakes
   - fake SearxNG responses
   - fake candidate page fetches
   - block stray HTTP requests
6. AI integration boundary tests
   - fake/stub agent response payloads
   - reject malformed or low-confidence output
7. Policy tests
   - admin-only actions enforced for lyrics, crawl, and sync routes

**Factories/seeders**

- Factories for artist, album, song, lyric, and lyric segments.
- Seeder for one admin user and a small demo catalog.

**Git checkpoint**

- Local commit after the new domain, crawl flow, and admin authorization are covered by Pest.

## Verification checklist

### Baseline verification

1. `composer install` and `npm install` complete cleanly.
2. `php artisan migrate` succeeds.
3. Filament login page loads and admin user can sign in.

### Domain verification

1. Create an artist, album, and song from Filament.
2. Create a standalone single with `album_id = null` and `parent_type = single`.
3. Confirm filters/counts display correctly.

### Lyrics editor verification

1. Open the editor from a song action.
2. Load existing lyric text and audio.
3. Press space during playback to stamp multiple lines.
4. Save, refresh, and verify timestamps persist.
5. Confirm auto-scroll and zoom remain usable on long lyrics.

### Crawl verification

1. Trigger manual crawl from a song without lyrics.
2. Confirm a crawl run record is created and status progresses.
3. Confirm SearxNG results are filtered and one candidate is selected.
4. Confirm cleaned lyrics are stored with `external_source_url`.
5. Confirm low-confidence garbage content is rejected cleanly.

### Regression verification

1. `php artisan test --compact`
2. `vendor/bin/pint --dirty --format agent`
3. `npm run lint:check`
4. `npm run format:check`
5. `npm run types:check`

## Suggested local commit sequence

1. `chore: install filament and laravel ai`
2. `feat: add music domain schema and policies`
3. `feat: add filament resources for catalog management`
4. `feat: add lyrics sync editor and segment persistence`
5. `feat: add lyrics crawl pipeline with ai cleanup`
6. `test: cover admin, crawl, and lyrics sync flows`

## Final implementation notes

- Keep controllers thin; move crawl, cleanup, and sync writes into actions/services.
- Prefer admin-first delivery: CRUD and reliability before public pages.
- Treat the AI agent as a cleaning/decision layer, not as the raw crawler.
- Preserve a full audit trail for each crawl attempt so bad imports are explainable and reversible.
