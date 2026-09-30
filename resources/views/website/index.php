<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#06271e">
  <meta name="description" content="Collect the world with Kroo. Track your travels, explore new places, and build your passport of memories.">
  <title>Kroo — Collect the World</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--green:#06271e;--green-2:#092f24;--cream:#f8efdf;--copper:#d7946c;--muted:#d7d6c6;--line:#315045}
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;background:var(--green);color:var(--cream);font:16px/1.55 'Roboto',Arial,sans-serif}
    h1,h2,h3,p{margin-top:0}
    h1,h2,h3{font-family:'Roboto',Arial,sans-serif;line-height:1.13}
    a{color:inherit;text-decoration:none}
    img{max-width:100%;display:block}
    .wrap{width:min(1240px,calc(100% - 64px));margin:auto}
    .button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 26px;border:1px solid #e4ad89;border-radius:999px;background:var(--copper);color:#132d24;font-size:14px;font-weight:700;box-shadow:0 6px 18px #0003;transition:transform .2s,background .2s}
    .button:hover{transform:translateY(-2px);background:#e9ac84}
    .button:focus-visible,a:focus-visible{outline:3px solid #fff;outline-offset:4px}
    .eyebrow{color:var(--copper);font-size:12px;font-weight:700;letter-spacing:.21em;text-transform:uppercase}
    .site-header{position:absolute;inset:0 0 auto;z-index:5;background:linear-gradient(#031d17dc,#031d1700)}
    .nav{height:84px;display:flex;align-items:center;justify-content:space-between;gap:28px}
    .logo{display:inline-flex;align-items:center;gap:12px;flex:none}
    .logo img{width:156px;height:auto;object-fit:contain;object-position:left center}
    .logo-text{display:flex;flex-direction:column;color:var(--copper);font:700 31px/.85 'Roboto',Arial,sans-serif}
    .logo-text small{margin-top:7px;color:var(--cream);font:10px/1 'Roboto',Arial,sans-serif;letter-spacing:.03em}
    .nav-links{display:flex;align-items:center;gap:32px;font-size:13px}
    .nav-links a:not(.button){padding:8px 0;border-bottom:1px solid transparent}
    .nav-links a:not(.button):hover,.nav-links a[aria-current="page"]{border-color:var(--copper)}
    .nav-links .button{min-height:36px;padding:6px 18px;font-size:12px}
    .hero{position:relative;min-height:660px;display:flex;align-items:center;background:#153629 url('<?= route('website.page-image', ['filename' => '1.jpg']) ?>') center center/cover no-repeat;isolation:isolate}
    .hero:before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,#031d17f5 0%,#031d17e8 20%,#031d1799 43%,#031d1700 75%),linear-gradient(0deg,#031d17d9 0%,#031d1700 42%),linear-gradient(180deg,#031d17a6 0%,transparent 24%)}
    .hero-content{padding-top:95px;padding-bottom:58px}
    .hero h1{max-width:580px;margin:0 0 20px;font-size:clamp(58px,7.1vw,105px);letter-spacing:-.055em}
    .hero h1 span{color:#f2b991}
    .hero p{max-width:470px;margin-bottom:28px;font:18px/1.5 'Roboto',Arial,sans-serif}
    .hero-actions{display:flex;align-items:center;flex-wrap:wrap;gap:20px}
    .store-badges{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .icon-sprite{position:absolute;width:0;height:0;overflow:hidden}
    .stat-icon svg{width:35px;height:35px;fill:none;stroke:currentColor;stroke-width:1.2;stroke-linecap:round;stroke-linejoin:round}
    .feature-icon svg{width:38px;height:38px;fill:none;stroke:currentColor;stroke-width:1.4;stroke-linecap:round;stroke-linejoin:round}
    .plus-icon svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
    .store-badge{display:flex;align-items:center;gap:7px;height:42px;min-width:133px;padding:3px 10px;background:#050505;border:1px solid #eee;border-radius:6px;color:#fff;box-shadow:0 2px 8px #0007}
    .store-badge svg{width:25px;height:29px;flex:none}
    .store-badge span{display:flex;flex-direction:column;line-height:1.02}
    .store-badge small{font:9px/1.1 'Roboto',Arial,sans-serif}
    .store-badge strong{font:600 17px/1.1 'Roboto',Arial,sans-serif;white-space:nowrap}
    .store-badges a:hover{transform:translateY(-2px)}
    .store-badges a{transition:transform .2s}
    .stats{border-top:1px solid var(--line);border-bottom:1px solid var(--line);background:#05231b}
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:0}
    .stat{display:flex;align-items:center;justify-content:center;gap:15px;min-height:108px;border-right:1px solid var(--line)}
    .stat:last-child{border:0}
    .stat-icon{width:48px;height:48px;display:grid;place-items:center;border:1px solid #aa785b;border-radius:50%;color:var(--copper);font-size:25px}
    .stat strong{display:block;font:26px/1.1 'Roboto',Arial,sans-serif}
    .stat small{color:var(--muted);font:12px 'Roboto',Arial,sans-serif}
    .features{overflow:hidden;background:linear-gradient(90deg,#06271ef2,#06271ee8),url('<?= route('website.page-image', ['filename' => '2.jpg']) ?>') center/cover no-repeat}
    .features-grid{display:grid;grid-template-columns:46% 54%;align-items:center;min-height:690px;gap:24px}
    .phone-wrap{align-self:end;display:flex;justify-content:center;max-height:670px;overflow:hidden}
    .phone-wrap img{height:650px;width:auto;object-fit:contain;object-position:bottom;filter:drop-shadow(22px 22px 22px #0009)}
    .features-copy{padding:64px 50px 64px 0}
    .features h2{max-width:650px;margin:8px 0 35px;font-size:clamp(37px,4.3vw,62px)}
    .feature-list{display:grid;gap:21px}
    .feature{display:grid;grid-template-columns:54px 1fr;gap:14px;align-items:start}
    .feature-icon{color:var(--copper);font-size:35px;line-height:1}
    .feature h3{margin:0 0 3px;font-size:19px}
    .feature p{max-width:430px;margin:0;color:var(--muted);font-size:13px;line-height:1.5}
    .explore{padding:36px 0;background:#0b3024;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
    .explore-grid{display:grid;grid-template-columns:minmax(270px,1fr) minmax(0,1.8fr);gap:36px;align-items:center}
    .explore h2,.split-copy h2{margin:0 0 10px;font-size:clamp(33px,3.7vw,51px)}
    .explore p,.split-copy p{max-width:405px;color:var(--muted);font-size:14px}
    .stamps{display:flex;justify-content:flex-end;gap:16px;min-width:0}
    .stamps img{height:185px;width:auto;min-width:0;filter:drop-shadow(0 4px 5px #0006);transition:transform .2s}
    .stamps img:hover{transform:translateY(-5px) rotate(-2deg)}
    .image-split{display:grid;grid-template-columns:1fr 1fr;min-height:440px;background:var(--green)}
    .split-image{min-height:440px;background-position:center;background-size:cover}
    .split-copy{display:flex;flex-direction:column;justify-content:center;align-items:flex-start;padding:48px max(40px,calc((100vw - 1240px)/2));background:radial-gradient(circle at 100% 100%,#124435,var(--green) 60%)}
    .split-copy ul{padding:0;margin:10px 0 24px;list-style:none;display:grid;gap:15px}
    .split-copy li{display:grid;grid-template-columns:38px 1fr;gap:14px;align-items:start;font-size:14px}
    .split-copy li span{color:var(--copper);font-size:26px;line-height:1}
    .split-copy li strong{display:block;font-family:'Roboto',Arial,sans-serif;font-size:16px}
    .split-copy li small{display:block;color:var(--muted);font-size:12px}
    .kroo-plus .split-image{background-image:url('<?= route('website.page-image', ['filename' => '3.jpg']) ?>')}
    .passport .split-image{background-image:url('<?= route('website.page-image', ['filename' => '4.jpg']) ?>')}
    .passport .split-copy{background:linear-gradient(90deg,#06271e,#0b3428)}
    .passport .split-copy p{font:18px/1.55 'Roboto',Arial,sans-serif}
    .download{position:relative;min-height:280px;display:grid;place-items:center;text-align:center;background:#173528 url('<?= route('website.page-image', ['filename' => '5.jpg']) ?>') center/cover no-repeat;isolation:isolate}
    .download:before{content:"";position:absolute;inset:0;z-index:-1;background:#03251b98}
    .download h2{margin:0 0 4px;font-size:clamp(32px,3.8vw,48px)}
    .download p{margin:0 0 18px;font:17px 'Roboto',Arial,sans-serif}
    .download .store-badges{justify-content:center}
    .footer{padding:42px 0;background:#05231b;border-top:1px solid var(--line)}
    .footer-grid{display:grid;grid-template-columns:2fr repeat(3,1fr) 1.5fr;gap:28px}
    .footer h3{margin:0 0 12px;color:var(--copper);font:16px 'Roboto',Arial,sans-serif}
    .footer-col{display:flex;flex-direction:column;gap:7px;font-size:12px;color:var(--muted)}
    .footer-col a:hover{color:var(--cream)}
    .footer-note{font-size:11px;color:var(--muted)}
    @media(max-width:900px){.nav-links{gap:14px}.features-grid{grid-template-columns:42% 58%}.phone-wrap img{height:560px}.features-copy{padding-right:12px}.stamps img{height:145px}.image-split{min-height:360px}.split-image{min-height:360px}.split-copy{padding:40px}}
    @media(max-width:680px){.wrap{width:min(100% - 36px,520px)}.nav{height:72px}.logo img{width:130px}.logo-text{font-size:26px}.nav-links a:not(.button){display:none}.nav-links .button{padding:7px 12px}.hero{min-height:640px;background-position:58% center}.hero:before{background:linear-gradient(90deg,#031d17f5 0%,#031d17d6 52%,#031d1770),linear-gradient(0deg,#031d17d9,transparent 55%)}.hero h1{font-size:clamp(56px,13vw,78px)}.hero p{font-size:17px}.hero-actions{align-items:flex-start;flex-direction:column}.stats-grid{grid-template-columns:repeat(2,1fr)}.stat{min-height:92px;justify-content:flex-start;padding-left:10px}.stat:nth-child(2){border:0}.stat:nth-child(-n+2){border-bottom:1px solid var(--line)}.stat-icon{width:39px;height:39px;font-size:21px}.stat strong{font-size:22px}.features-grid{display:flex;flex-direction:column-reverse;gap:0}.features-copy{padding:62px 0 24px}.features h2{margin-bottom:28px}.phone-wrap{align-self:center;max-height:500px}.phone-wrap img{height:500px}.explore-grid{grid-template-columns:1fr;gap:24px}.stamps{justify-content:space-between;gap:8px}.stamps img{height:auto;width:31%}.image-split{grid-template-columns:1fr}.split-image{min-height:270px}.split-copy{padding:44px 18px}.passport .split-image{order:-1}.download{min-height:280px}.footer-grid{grid-template-columns:repeat(2,1fr);gap:30px}.footer-grid>:first-child{grid-column:1/-1}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*:before,*:after{transition:none!important}}
  </style>
</head>
<body>
  <svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="icon-globe" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20"/><path d="M4 24h40M24 4v40M9 12c9 6 21 6 30 0M9 36c9-6 21-6 30 0M24 4c-7 5-11 12-11 20s4 15 11 20M24 4c7 5 11 12 11 20s-4 15-11 20"/></symbol>
    <symbol id="icon-mountain" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21"/><path d="m8 34 12-17 7 9 4-5 9 13M16 34l5-7 4 5"/></symbol>
    <symbol id="icon-city" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21"/><path d="M9 35h30M13 35V22h7v13M20 35V11h9v24M29 35V18h7v17M23 15h3M23 20h3M23 25h3M15 26h2M32 23h2M32 28h2"/></symbol>
    <symbol id="icon-camera" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21"/><path d="M12 18h6l3-4h7l3 4h5v17H12z"/><circle cx="24" cy="26" r="6"/></symbol>
    <symbol id="icon-pin" viewBox="0 0 48 48"><path d="M24 43S11 29 11 20a13 13 0 0 1 26 0c0 9-13 23-13 23Z"/><circle cx="24" cy="20" r="4"/></symbol>
    <symbol id="icon-score" viewBox="0 0 48 48"><path d="M7 40h34M10 38V27h7v11M21 38V19h7v19M32 38V10h7v28"/></symbol>
    <symbol id="icon-bulb" viewBox="0 0 48 48"><path d="M17 35h14M18 39h12M20 43h8M17 32c0-6-7-9-7-17a14 14 0 0 1 28 0c0 8-7 11-7 17M24 5V1M7 6 4 3M41 6l3-3"/></symbol>
    <symbol id="icon-trophy" viewBox="0 0 48 48"><path d="M14 7h20v11c0 9-4 14-10 14s-10-5-10-14V7ZM14 11H7v5c0 7 4 10 9 10M34 11h7v5c0 7-4 10-9 10M24 32v7M17 40h14"/></symbol>
    <symbol id="icon-star" viewBox="0 0 48 48"><path d="m24 5 5.9 12 13.1 1.9-9.5 9.2 2.2 13.1L24 35l-11.7 6.2 2.2-13.1L5 18.9 18.1 17z"/></symbol>
    <symbol id="icon-apple" viewBox="0 0 32 32"><path fill="currentColor" stroke="none" d="M22.4 15.7c0-3 2.5-4.5 2.7-4.6-1.4-2-3.4-2.3-4.1-2.3-1.7-.2-3.3 1-4.2 1s-2.2-1-3.7-1c-2 0-3.8 1.2-4.8 3-2 3.5-.5 8.7 1.4 11.5 1 1.4 2.1 3 3.6 3s2-.9 3.7-.9 2.2.9 3.7.8c1.5 0 2.5-1.4 3.5-2.8 1.2-1.7 1.7-3.3 1.7-3.4-.1 0-3.5-1.4-3.5-4.3ZM19.8 7c.8-1 1.4-2.3 1.2-3.6-1.2.1-2.6.8-3.4 1.8-.8.9-1.5 2.2-1.3 3.4 1.3.1 2.7-.7 3.5-1.6Z"/></symbol>
    <symbol id="icon-play" viewBox="0 0 32 32"><path fill="#40c4ff" stroke="none" d="M5 3v26l13-13Z"/><path fill="#35d77b" stroke="none" d="M5 3 20 13l-2 3Z"/><path fill="#ffcc3f" stroke="none" d="m18 16 8 5-21 8Z"/><path fill="#fb5d62" stroke="none" d="m18 16 8-5-6 2Z"/></symbol>
  </svg>
  <header class="site-header">
    <nav class="nav wrap" aria-label="Main navigation">
      <a class="logo" href="#top" aria-label="Kroo home"><img src="<?= route('website.asset', ['filename' => 'kroo-logo.png']) ?>" alt=""></a>
      <div class="nav-links"><a href="#top" aria-current="page">Home</a><a href="#features">Features</a><a href="#explore">Explore</a><a href="#kroo-plus">Kroo+</a><a href="#footer">About</a></div>
    </nav>
  </header>
  <main id="top">
    <section class="hero" aria-labelledby="hero-title"><div class="wrap hero-content">
      <h1 id="hero-title">Collect<br><span>the world.</span></h1>
      <p>Track your travels, discover new places, and collect a lifetime of memories.</p>
      <div class="hero-actions"><div class="store-badges"><a class="store-badge" href="#download" aria-label="App Store download information"><svg aria-hidden="true"><use href="#icon-apple"/></svg><span><small>Download on the</small><strong>App Store</strong></span></a><a class="store-badge" href="#download" aria-label="Google Play download information"><svg aria-hidden="true"><use href="#icon-play"/></svg><span><small>GET IT ON</small><strong>Google Play</strong></span></a></div></div>
    </div></section>
    <section class="stats" aria-label="Kroo travel catalog"><div class="wrap stats-grid">
      <div class="stat"><span class="stat-icon" aria-hidden="true"><svg><use href="#icon-globe"/></svg></span><div><strong>195</strong><small>Countries</small></div></div>
      <div class="stat"><span class="stat-icon" aria-hidden="true"><svg><use href="#icon-mountain"/></svg></span><div><strong>7</strong><small>Continents</small></div></div>
      <div class="stat"><span class="stat-icon" aria-hidden="true"><svg><use href="#icon-city"/></svg></span><div><strong>2,000+</strong><small>Cities</small></div></div>
      <div class="stat"><span class="stat-icon" aria-hidden="true"><svg><use href="#icon-camera"/></svg></span><div><strong>10,000+</strong><small>Verified sights</small></div></div>
    </div></section>
    <section id="features" class="features" aria-labelledby="features-title"><div class="wrap features-grid">
      <div class="phone-wrap"><img src="<?= route('website.page-image', ['filename' => 'phone.png']) ?>" alt="Kroo app showing a travel score, country progress and world map" loading="lazy"></div>
      <div class="features-copy"><div class="eyebrow">All in one app</div><h2 id="features-title">Track. Explore.<br>Learn. Be Inspired.</h2>
        <div class="feature-list">
          <div class="feature"><span class="feature-icon" aria-hidden="true"><svg><use href="#icon-pin"/></svg></span><div><h3>Track Your Travels</h3><p>Mark countries, cities, sights and airports you have visited.</p></div></div>
          <div class="feature"><span class="feature-icon" aria-hidden="true"><svg><use href="#icon-score"/></svg></span><div><h3>Grow Your Kroo Score</h3><p>Turn your journeys into a score and see how you rank.</p></div></div>
          <div class="feature"><span class="feature-icon" aria-hidden="true"><svg><use href="#icon-bulb"/></svg></span><div><h3>Kroo IQ</h3><p>Daily quizzes to learn about the world and its amazing places.</p></div></div>
          <div class="feature"><span class="feature-icon" aria-hidden="true"><svg><use href="#icon-mountain"/></svg></span><div><h3>Special Lists &amp; Challenges</h3><p>Explore iconic lists and complete unique challenges.</p></div></div>
        </div>
      </div>
    </div></section>
    <section id="explore" class="explore" aria-labelledby="explore-title"><div class="wrap explore-grid"><div><h2 id="explore-title">Explore the World</h2><p>Discover countries, cities and iconic sights. Add them to your map and grow your Kroo Score.</p><a class="button" href="#download">Start Exploring <span aria-hidden="true">&nbsp;→</span></a></div><div class="stamps" aria-label="Kroo country stamps"><img src="<?= route('website.page-image', ['filename' => 'stamp1.png']) ?>" alt="Japan travel stamp" loading="lazy"><img src="<?= route('website.page-image', ['filename' => 'stamp2.png']) ?>" alt="Italy travel stamp" loading="lazy"><img src="<?= route('website.page-image', ['filename' => 'stamp3.png']) ?>" alt="Peru travel stamp" loading="lazy"></div></div></section>
    <section id="kroo-plus" class="image-split kroo-plus" aria-labelledby="plus-title"><div class="split-image" role="img" aria-label="Traveler overlooking a Mediterranean village at sunset"></div><div class="split-copy"><h2 id="plus-title">Kroo+</h2><p class="eyebrow">Unlock More Adventures</p><ul><li><span class="plus-icon" aria-hidden="true"><svg><use href="#icon-trophy"/></svg></span><div><strong>Dream Vacation Challenge</strong><small>Chance to win $5,000 cash</small></div></li><li><span class="plus-icon" aria-hidden="true"><svg><use href="#icon-globe"/></svg></span><div><strong>Access to Kroo IQ</strong><small>Daily lessons and quizzes</small></div></li><li><span class="plus-icon" aria-hidden="true"><svg><use href="#icon-camera"/></svg></span><div><strong>Full Access to All Sights</strong><small>Explore thousands of iconic places</small></div></li><li><span class="plus-icon" aria-hidden="true"><svg><use href="#icon-star"/></svg></span><div><strong>Exclusive Challenges</strong><small>Special events and collections</small></div></li></ul><a class="button" href="#download">Learn About Kroo+ <span aria-hidden="true">&nbsp;→</span></a></div></section>
    <section id="passport" class="image-split passport" aria-labelledby="passport-title"><div class="split-copy"><h2 id="passport-title">The Kroo Passport</h2><p>A beautifully designed passport to accompany your digital journey.</p><a class="button" href="#download">Get Your Passport <span aria-hidden="true">&nbsp;→</span></a></div><div class="split-image" role="img" aria-label="Kroo passport on a vintage map with a compass"></div></section>
    <section id="download" class="download" aria-labelledby="download-title"><div class="wrap"><h2 id="download-title">Your Next Adventure Awaits</h2><p>Download Kroo and start collecting the world today.</p><div class="store-badges"><a class="store-badge" href="#download" aria-label="App Store download information"><svg aria-hidden="true"><use href="#icon-apple"/></svg><span><small>Download on the</small><strong>App Store</strong></span></a><a class="store-badge" href="#download" aria-label="Google Play download information"><svg aria-hidden="true"><use href="#icon-play"/></svg><span><small>GET IT ON</small><strong>Google Play</strong></span></a></div></div></section>
  </main>
  <footer id="footer" class="footer"><div class="wrap footer-grid"><a class="logo" href="#top" aria-label="Kroo home"><img src="<?= route('website.asset', ['filename' => 'kroo-logo.png']) ?>" alt=""></a><div class="footer-col"><h3>Explore</h3><a href="#explore">Countries</a><a href="#explore">Cities</a><a href="#explore">Sights</a><a href="#features">Challenges</a></div><div class="footer-col"><h3>About</h3><a href="#features">Our Story</a><a href="#passport">Passport</a><a href="#download">Contact</a></div><div class="footer-col"><h3>Legal</h3><a href="<?= route('website.privacy') ?>">Privacy Policy</a></div><div class="footer-note">© <?= date('Y') ?> Kroo. All rights reserved.</div></div></footer>
</body>
</html>
