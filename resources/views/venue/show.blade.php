<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->display_name }} · Playdrive</title>
    <meta name="description" content="{{ $tenant->tagline ?? Str::limit(strip_tags((string) $tenant->public_description), 150) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-base: #0a0612;
            --bg-glass: rgba(255, 255, 255, 0.04);
            --bg-glass-strong: rgba(20, 14, 36, 0.6);
            --border-glass: rgba(207, 199, 246, 0.12);
            --border-glass-strong: rgba(207, 199, 246, 0.25);
            --text-primary: #f4f0fb;
            --text-secondary: rgba(207, 199, 246, 0.7);
            --text-muted: rgba(207, 199, 246, 0.45);
            --accent-purple: #8a7fe8;
            --accent-pink: #d4537e;
            --accent-green: #5dcaa5;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            line-height: 1.55;
        }

        /* Background gradient blobs */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 30% 0%, rgba(127, 119, 221, 0.25), transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 30%, rgba(212, 83, 126, 0.18), transparent 60%),
                radial-gradient(ellipse 70% 60% at 50% 100%, rgba(83, 74, 183, 0.2), transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container { position: relative; z-index: 1; max-width: 960px; margin: 0 auto; padding: 0 24px; }

        /* Header */
        .header {
            position: sticky; top: 0; z-index: 50;
            background: var(--bg-glass-strong);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 0.5px solid var(--border-glass);
        }
        .header-inner {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px; max-width: 960px; margin: 0 auto;
        }
        .header-brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 500; font-size: 16px; color: var(--text-primary); text-decoration: none;
        }
        .header-brand-mark {
            width: 28px; height: 28px; border-radius: 8px;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
        }
        .header-back {
            font-size: 13px; color: var(--text-secondary); text-decoration: none;
        }
        .header-back:hover { color: var(--text-primary); }

        /* Hero */
        .hero {
            position: relative; padding: 64px 0 48px; text-align: left;
        }
        .hero-image {
            position: relative;
            aspect-ratio: 21/9;
            border-radius: 24px;
            overflow: hidden;
            border: 0.5px solid var(--border-glass);
            margin-bottom: 32px;
            background:
                radial-gradient(circle at 30% 50%, rgba(212, 83, 126, 0.4), transparent 60%),
                radial-gradient(circle at 70% 50%, rgba(138, 127, 232, 0.5), transparent 60%),
                #1a0f2e;
        }
        .hero-image img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .hero-image-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(10, 6, 18, 0.7) 100%);
        }
        .hero-image-empty {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); font-size: 14px;
        }
        .badge-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--bg-glass);
            border: 0.5px solid var(--border-glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--text-secondary);
            font-size: 12px; font-weight: 500;
        }
        .badge-live { color: var(--accent-green); }
        .badge-live::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
            background: var(--accent-green);
            box-shadow: 0 0 8px var(--accent-green);
        }
        .hero h1 {
            font-size: 44px; font-weight: 600; line-height: 1.1;
            margin: 0 0 12px;
            letter-spacing: -0.025em;
            color: var(--text-primary);
        }
        .hero-tagline {
            font-size: 17px; color: var(--text-secondary);
            margin: 0 0 24px; max-width: 600px;
        }
        .hero-meta {
            display: flex; gap: 16px; align-items: center; flex-wrap: wrap;
            font-size: 14px; color: var(--text-secondary);
        }
        .hero-meta-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--text-muted); }

        /* CTA buttons */
        .cta-row { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 22px; border-radius: 999px; font-size: 14px; font-weight: 500;
            text-decoration: none;
            border: 0.5px solid var(--border-glass-strong);
            background: var(--bg-glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--text-primary);
            transition: transform 0.15s, background 0.15s;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            color: white;
            border: none;
            box-shadow: 0 8px 24px rgba(138, 127, 232, 0.4);
        }

        /* Content blocks */
        section { margin-bottom: 32px; }
        .section-title {
            font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--text-muted); margin-bottom: 14px;
        }
        .glass-card {
            background: var(--bg-glass);
            border: 0.5px solid var(--border-glass);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 28px;
        }

        /* Description */
        .description { white-space: pre-line; color: var(--text-secondary); font-size: 15px; line-height: 1.7; }

        /* Activities grid */
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }
        .activity-card {
            background: var(--bg-glass);
            border: 0.5px solid var(--border-glass);
            border-radius: 14px;
            padding: 16px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .activity-name { font-weight: 500; font-size: 14px; margin-bottom: 4px; color: var(--text-primary); }
        .activity-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }

        /* Amenities */
        .amenities-row { display: flex; gap: 8px; flex-wrap: wrap; }
        .amenity-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 999px;
            background: var(--bg-glass);
            border: 0.5px solid var(--border-glass);
            color: var(--text-secondary);
            font-size: 13px;
        }
        .amenity-chip strong { color: var(--text-primary); font-weight: 500; }

        /* Photo gallery */
        .gallery {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .gallery-item {
            aspect-ratio: 1; border-radius: 14px; overflow: hidden;
            border: 0.5px solid var(--border-glass);
        }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* Map */
        .map-wrap {
            border-radius: 14px; overflow: hidden;
            border: 0.5px solid var(--border-glass);
        }
        .map-wrap iframe { display: block; width: 100%; height: 280px; border: 0; filter: grayscale(0.3) brightness(0.85); }

        /* Contact info */
        .info-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
        }
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 32px; }
            .hero { padding: 32px 0 24px; }
        }
        .info-item { padding: 14px 0; border-bottom: 0.5px solid var(--border-glass); }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 4px; }
        .info-value { font-size: 14px; color: var(--text-primary); }
        .info-value a { color: var(--accent-purple); text-decoration: none; }
        .info-value a:hover { color: var(--accent-pink); }

        /* Social */
        .links-row { display: flex; gap: 8px; flex-wrap: wrap; }

        /* Video */
        .video-wrap {
            aspect-ratio: 16/9; border-radius: 14px; overflow: hidden;
            border: 0.5px solid var(--border-glass);
        }
        .video-wrap iframe { display: block; width: 100%; height: 100%; border: 0; }

        /* Footer */
        footer {
            margin-top: 80px;
            border-top: 0.5px solid var(--border-glass);
            background: rgba(10, 6, 18, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .footer-inner {
            max-width: 960px; margin: 0 auto; padding: 24px;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 12px; color: var(--text-muted);
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="header-inner">
            <a href="/" class="header-brand">
                <div class="header-brand-mark">▶</div>
                <span>Playdrive</span>
            </a>
            <a href="/venues" class="header-back">← Alle venues</a>
        </div>
    </header>

    <div class="container">

        {{-- HERO --}}
        <div class="hero">
            <div class="hero-image">
                @if ($tenant->hero_image_url)
                    <img src="{{ $tenant->hero_image_url }}" alt="{{ $tenant->display_name }}">
                    <div class="hero-image-overlay"></div>
                @else
                    <div class="hero-image-empty">Hero-afbeelding</div>
                @endif
            </div>

            <div class="badge-row">
                <span class="badge badge-live">Live op Playdrive</span>
                @if ($tenant->city)
                    <span class="badge">{{ $tenant->city }}</span>
                @endif
            </div>

            <h1>{{ $tenant->display_name }}</h1>

            @if ($tenant->tagline)
                <p class="hero-tagline">{{ $tenant->tagline }}</p>
            @endif

            <div class="cta-row">
                @if ($tenant->website_url)
                    <a href="{{ $tenant->website_url }}" target="_blank" rel="noopener" class="btn btn-primary">
                        Bezoek website
                    </a>
                @endif
                @if ($tenant->phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $tenant->phone) }}" class="btn">
                        Bel {{ $tenant->phone }}
                    </a>
                @endif
                @if ($tenant->email)
                    <a href="mailto:{{ $tenant->email }}" class="btn">E-mail</a>
                @endif
            </div>
        </div>

        {{-- DESCRIPTION --}}
        @if ($tenant->public_description)
            <section>
                <div class="section-title">Over deze venue</div>
                <div class="glass-card">
                    <div class="description">{{ $tenant->public_description }}</div>
                </div>
            </section>
        @endif

        {{-- ACTIVITIES --}}
        @php $visibleActivities = $tenant->activities->where('is_visible', true); @endphp
        @if ($visibleActivities->count())
            <section>
                <div class="section-title">Wat kan je hier doen</div>
                <div class="activities-grid">
                    @foreach ($visibleActivities as $activity)
                        <div class="activity-card">
                            <div class="activity-name">{{ $activity->name }}</div>
                            @if ($activity->description)
                                <div class="activity-desc">{{ $activity->description }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- VIDEO --}}
        @if ($tenant->video_url)
            @php
                $videoEmbedUrl = null;
                $url = $tenant->video_url;
                if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $m)) {
                    $videoEmbedUrl = 'https://www.youtube.com/embed/' . $m[1];
                } elseif (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $m)) {
                    $videoEmbedUrl = 'https://www.youtube.com/embed/' . $m[1];
                } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
                    $videoEmbedUrl = 'https://player.vimeo.com/video/' . $m[1];
                }
            @endphp
            @if ($videoEmbedUrl)
                <section>
                    <div class="section-title">Video</div>
                    <div class="video-wrap">
                        <iframe src="{{ $videoEmbedUrl }}" allowfullscreen></iframe>
                    </div>
                </section>
            @endif
        @endif

        {{-- AMENITIES --}}
        @php
            $amenityConfig = config('venue_amenities', []);
            $availableAmenities = $tenant->amenities->where('is_available', true);
        @endphp
        @if ($availableAmenities->count())
            <section>
                <div class="section-title">Voorzieningen</div>
                <div class="glass-card">
                    <div class="amenities-row">
                        @foreach ($availableAmenities as $amenity)
                            <span class="amenity-chip">
                                <strong>{{ $amenityConfig[$amenity->key]['label'] ?? $amenity->key }}</strong>
                                @if ($amenity->value)
                                    · {{ $amenity->value }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- GALLERY --}}
        @if ($tenant->photos->count())
            <section>
                <div class="section-title">Foto's</div>
                <div class="gallery">
                    @foreach ($tenant->photos as $photo)
                        <div class="gallery-item">
                            <img src="{{ $photo->url }}" alt="{{ $photo->alt_text ?? $tenant->display_name }}">
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- PRACTICAL INFO --}}
        <section>
            <div class="section-title">Praktisch</div>
            <div class="glass-card">
                <div class="info-grid">
                    @if ($tenant->full_address)
                        <div class="info-item">
                            <div class="info-label">Adres</div>
                            <div class="info-value">{{ $tenant->full_address }}</div>
                        </div>
                    @endif
                    @if ($tenant->phone)
                        <div class="info-item">
                            <div class="info-label">Telefoon</div>
                            <div class="info-value">
                                <a href="tel:{{ preg_replace('/\s+/', '', $tenant->phone) }}">{{ $tenant->phone }}</a>
                            </div>
                        </div>
                    @endif
                    @if ($tenant->email)
                        <div class="info-item">
                            <div class="info-label">E-mail</div>
                            <div class="info-value">
                                <a href="mailto:{{ $tenant->email }}">{{ $tenant->email }}</a>
                            </div>
                        </div>
                    @endif
                    @if ($tenant->website_url)
                        <div class="info-item">
                            <div class="info-label">Website</div>
                            <div class="info-value">
                                <a href="{{ $tenant->website_url }}" target="_blank" rel="noopener">{{ parse_url($tenant->website_url, PHP_URL_HOST) ?? $tenant->website_url }}</a>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Map --}}
                @if ($tenant->latitude && $tenant->longitude)
                    <div class="map-wrap" style="margin-top: 16px;">
                        <iframe
                            src="https://www.google.com/maps?q={{ $tenant->latitude }},{{ $tenant->longitude }}&z=15&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                        ></iframe>
                    </div>
                @elseif ($tenant->full_address)
                    <div class="map-wrap" style="margin-top: 16px;">
                        <iframe
                            src="https://www.google.com/maps?q={{ urlencode($tenant->full_address) }}&z=15&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                        ></iframe>
                    </div>
                @endif
            </div>
        </section>

        {{-- LINKS --}}
        @if ($tenant->links->count())
            <section>
                <div class="section-title">Volg op social</div>
                <div class="links-row">
                    @foreach ($tenant->links as $link)
                        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="btn">
                            {{ ucfirst($link->type) }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </div>

    <footer>
        <div class="footer-inner">
            <div>{{ $tenant->display_name }} op Playdrive</div>
            <div>
                <a href="/" style="color: var(--text-muted); text-decoration: none; margin-right: 16px;">Home</a>
                <a href="/voor-venues" style="color: var(--text-muted); text-decoration: none;">Voor venues</a>
            </div>
        </div>
    </footer>

</body>
</html>
