<section class="aec-page-hero" aria-labelledby="public-page-title">
    <div class="aec-page-hero__inner">
        <div class="aec-page-hero__copy">
            <p class="aec-page-hero__eyebrow">{{ $eyebrow ?? setting('site_name', "Alom's English Center") }}</p>
            <h1 id="public-page-title">{{ $title }}</h1>
            @if (!empty($description))
                <p class="aec-page-hero__description whitespace-pre-line">{{ $description }}</p>
            @endif
            @if (!empty($actionUrl) && !empty($actionLabel))
                <a href="{{ $actionUrl }}" class="aec-page-hero__action">{{ $actionLabel }} <span aria-hidden="true">→</span></a>
            @endif
        </div>
        <div class="aec-page-hero__visual" aria-hidden="true">
            <div class="aec-page-hero__orb aec-page-hero__orb--one"></div>
            <div class="aec-page-hero__orb aec-page-hero__orb--two"></div>
            <div class="aec-page-hero__learning-card">
                <div class="aec-page-hero__learning-mark">{{ mb_substr(setting('site_name', 'A'), 0, 1) }}</div>
                <div>
                    <span class="aec-page-hero__card-label">{{ setting('public_page_visual_label', 'A better way to learn') }}</span>
                    <strong>{{ setting('public_page_visual_title', 'Learn · Practice · Grow') }}</strong>
                </div>
                <span class="aec-page-hero__spark" aria-hidden="true">✦</span>
            </div>
            <div class="aec-page-hero__float aec-page-hero__float--top">🎯 Clear learning goals</div>
            <div class="aec-page-hero__float aec-page-hero__float--bottom">✓ Learn at your pace</div>
        </div>
    </div>
</section>
