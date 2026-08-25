<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kroo — Collect the World</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,800;0,9..144,900;1,9..144,600&family=Space+Mono:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg-deep:#0E3B2C; --bg-surface:#123F30; --bg-surface-2:#164A39;
    --accent-mint:#3ECF8E; --accent-copper:#C97C54;
    --cream:#F6F1E4; --text-muted:rgba(246,241,228,0.62); --line:rgba(246,241,228,0.14);
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  html{ scroll-behavior:smooth; }
  body{
    background:var(--bg-deep); color:var(--cream); font-family:'Inter',sans-serif;
    line-height:1.5; overflow-x:hidden;
  }
  .wrap{ max-width:1080px; margin:0 auto; padding:0 28px; }
  h1,h2,h3{ font-family:'Fraunces',serif; }
  a{ color:inherit; text-decoration:none; }

  body::before{
    content:""; position:fixed; inset:0; pointer-events:none; z-index:1;
    background-image:radial-gradient(rgba(246,241,228,0.035) 1px, transparent 1px);
    background-size:20px 20px;
  }

  nav{
    position:sticky; top:0; z-index:50; backdrop-filter:blur(14px);
    background:rgba(14,59,44,0.85); border-bottom:1px solid var(--line);
  }
  .navwrap{ max-width:1080px; margin:0 auto; padding:16px 28px; display:flex; align-items:center; justify-content:space-between; }
  .brand{ display:flex; align-items:center; gap:8px; }
  .brand svg{ width:22px; height:22px; }
  .brand span{ font-family:'Fraunces',serif; font-weight:700; font-size:19px; color:var(--accent-copper); }
  .navlinks{ display:flex; gap:28px; align-items:center; }
  .navlinks a{ font-family:'Space Mono',monospace; font-size:11.5px; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-muted); }
  .navlinks a:hover{ color:var(--cream); }
  .navcta{
    background:var(--accent-copper); color:var(--bg-deep) !important; padding:9px 18px; border-radius:20px;
    font-weight:700 !important;
  }
  @media (max-width:720px){ .navlinks a:not(.navcta){ display:none; } }

  .hero{ position:relative; padding:90px 0 60px; text-align:center; z-index:2; }
  .kicker{
    font-family:'Space Mono',monospace; font-size:11.5px; letter-spacing:0.2em; text-transform:uppercase;
    color:var(--accent-mint); margin-bottom:20px;
  }
  .hero h1{
    font-size:clamp(38px, 6vw, 64px); font-weight:800; line-height:1.06; max-width:780px; margin:0 auto 22px;
  }
  .hero h1 em{ font-style:italic; color:var(--accent-copper); font-weight:600; }
  .hero p.sub{
    font-family:'Inter',sans-serif; font-size:16.5px; color:var(--text-muted); max-width:520px; margin:0 auto 34px;
  }
  .storebadges{ display:flex; gap:14px; justify-content:center; margin-bottom:18px; flex-wrap:wrap; }
  .storebtn{
    display:flex; align-items:center; gap:9px; padding:12px 22px; border-radius:12px;
    border:1.5px solid var(--line); font-family:'Inter',sans-serif; font-size:13px; font-weight:600;
  }
  .storebtn:hover{ border-color:var(--accent-copper); }
  .microline{ font-family:'Space Mono',monospace; font-size:10.5px; color:var(--text-muted); }

  .stampvisual{ margin:60px auto 0; display:flex; justify-content:center; }
  .stamp-outer{
    width:260px; height:260px; border-radius:50%; position:relative;
    display:flex; align-items:center; justify-content:center;
    animation:pressStamp 1.1s cubic-bezier(.2,.9,.3,1.2) both;
  }
  @keyframes pressStamp{
    0%{ transform:scale(2.4) rotate(-16deg); opacity:0; }
    60%{ transform:scale(0.94) rotate(-4deg); opacity:1; }
    100%{ transform:scale(1) rotate(-4deg); opacity:1; }
  }
  .stamp-outer::before{ content:""; position:absolute; inset:0; border-radius:50%; border:3px solid var(--accent-copper); opacity:0.9; }
  .stamp-outer::after{ content:""; position:absolute; inset:16px; border-radius:50%; border:1.5px solid var(--accent-copper); opacity:0.55; }
  .stamp-core{ text-align:center; }
  .stamp-core .frac{ font-family:'Space Mono',monospace; font-weight:700; color:var(--accent-copper); font-size:46px; line-height:1; }
  .stamp-core .frac span{ font-size:20px; opacity:0.65; }
  .stamp-core .lbl{ font-family:'Space Mono',monospace; font-size:9.5px; letter-spacing:0.2em; text-transform:uppercase; color:var(--accent-copper); margin-top:8px; opacity:0.85; }

  .statbar{
    display:flex; justify-content:center; gap:56px; padding:56px 0; flex-wrap:wrap;
    border-top:1px solid var(--line); border-bottom:1px solid var(--line); margin-top:56px;
  }
  .statitem{ text-align:center; }
  .statitem .n{ font-family:'Fraunces',serif; font-weight:800; font-size:32px; color:var(--accent-copper); }
  .statitem .l{ font-family:'Space Mono',monospace; font-size:9.5px; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-muted); margin-top:4px; }

  section{ padding:90px 0; position:relative; z-index:2; }
  .sectionhead{ text-align:center; max-width:600px; margin:0 auto 56px; }
  .sectionhead .kicker{ margin-bottom:14px; }
  .sectionhead h2{ font-size:clamp(28px,4vw,40px); font-weight:700; line-height:1.15; }
  .sectionhead p{ color:var(--text-muted); margin-top:14px; font-size:15px; }

  .featuregrid{ display:grid; grid-template-columns:repeat(3, 1fr); gap:2px; background:var(--line); border:1px solid var(--line); border-radius:20px; overflow:hidden; }
  .featcell{ background:var(--bg-deep); padding:34px 28px; }
  .featcell .fi{ font-size:26px; margin-bottom:16px; }
  .featcell h3{ font-size:17px; font-weight:600; margin-bottom:9px; }
  .featcell p{ font-size:13.5px; color:var(--text-muted); line-height:1.55; }
  @media (max-width:820px){ .featuregrid{ grid-template-columns:1fr; } }

  .stepsrow{ display:flex; gap:20px; counter-reset:stepnum; }
  .stepitem{ flex:1; position:relative; padding-top:44px; }
  .stepitem .stepnum{
    position:absolute; top:0; left:0; font-family:'Fraunces',serif; font-weight:800; font-size:15px;
    color:var(--accent-copper); border:1.5px solid var(--accent-copper); width:34px; height:34px;
    border-radius:50%; display:flex; align-items:center; justify-content:center;
  }
  .stepitem h3{ font-size:15.5px; font-weight:600; margin-bottom:8px; }
  .stepitem p{ font-size:13px; color:var(--text-muted); line-height:1.5; }
  @media (max-width:820px){ .stepsrow{ flex-direction:column; } }

  .depthgrid{ display:grid; grid-template-columns:1.1fr 1fr; gap:50px; align-items:center; }
  .depthtext h2{ font-size:clamp(26px,3.6vw,36px); margin-bottom:16px; line-height:1.2; }
  .depthtext p{ color:var(--text-muted); font-size:15px; margin-bottom:22px; }
  .depthlist{ display:flex; flex-direction:column; gap:12px; }
  .depthlist div{ display:flex; gap:10px; align-items:flex-start; font-size:13.5px; }
  .depthlist svg{ width:16px; height:16px; color:var(--accent-mint); flex-shrink:0; margin-top:2px; }

  .minidevice{
    width:230px; margin:0 auto; background:var(--bg-surface); border:8px solid #1a1a1a; border-radius:34px;
    box-shadow:0 30px 60px rgba(0,0,0,0.4); overflow:hidden;
  }
  .minidevice .msc{ padding:18px 16px; }
  .msc .mtitle{ font-family:'Fraunces',serif; font-weight:700; font-size:15px; margin-bottom:12px; }
  .mstamprow{ display:flex; gap:8px; margin-bottom:10px; }
  .mstamp{ flex:1; aspect-ratio:1; border-radius:8px; border:1.5px solid var(--accent-copper); background:rgba(201,124,84,0.1); }
  .msightrow{ display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--line); font-size:10.5px; }
  .msightrow .dot{ width:16px; height:16px; border-radius:50%; border:1.5px solid var(--accent-copper); flex-shrink:0; }
  @media (max-width:820px){ .depthgrid{ grid-template-columns:1fr; } .minidevice{ margin-top:30px; } }

  .pricetable{ border:1px solid var(--line); border-radius:20px; overflow:hidden; }
  .prow{ display:grid; grid-template-columns:1fr 90px 90px; align-items:center; padding:14px 24px; border-bottom:1px solid var(--line); font-size:13.5px; }
  .prow:last-child{ border-bottom:none; }
  .prow.head{ background:var(--bg-surface); font-family:'Space Mono',monospace; font-size:10px; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-muted); }
  .prow.head .ph{ text-align:center; }
  .prow.head .ph.plus{ color:var(--accent-copper); font-weight:700; }
  .prow .pc{ text-align:center; }
  .check{ color:var(--accent-mint); }
  .dash{ color:rgba(246,241,228,0.25); }

  .challengecard{
    border-radius:26px; padding:56px 44px; text-align:center;
    background:linear-gradient(135deg, rgba(201,124,84,0.18), rgba(62,207,142,0.06));
    border:1px solid rgba(201,124,84,0.35);
  }
  .challengecard h2{ font-size:clamp(26px,4vw,36px); margin-bottom:14px; }
  .challengecard p{ color:var(--text-muted); max-width:560px; margin:0 auto 26px; font-size:15px; }
  .challengecta{
    display:inline-block; background:var(--accent-copper); color:var(--bg-deep); padding:14px 30px;
    border-radius:14px; font-family:'Space Mono',monospace; font-weight:700; font-size:12px;
    letter-spacing:0.05em; text-transform:uppercase;
  }

  .faqlist{ max-width:720px; margin:0 auto; }
  details{ border-bottom:1px solid var(--line); padding:20px 0; }
  summary{ font-family:'Fraunces',serif; font-weight:600; font-size:15.5px; cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center; }
  summary::-webkit-details-marker{ display:none; }
  summary::after{ content:"+"; font-family:'Space Mono',monospace; color:var(--accent-copper); font-size:18px; }
  details[open] summary::after{ content:"–"; }
  details p{ margin-top:12px; font-size:13.5px; color:var(--text-muted); line-height:1.6; }

  footer{ border-top:1px solid var(--line); padding:70px 0 40px; text-align:center; }
  footer h2{ font-size:clamp(28px,4vw,40px); margin-bottom:22px; }
  .footlinks{ display:flex; justify-content:center; gap:24px; margin-top:40px; flex-wrap:wrap; }
  .footlinks a{ font-family:'Space Mono',monospace; font-size:10.5px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; }
  .copyright{ margin-top:24px; font-family:'Space Mono',monospace; font-size:10px; color:rgba(246,241,228,0.35); }

  .brand-logo{width:132px;height:auto;display:block}
  .hero{padding:76px 0 0;text-align:left;overflow:hidden}
  .hero-layout{display:grid;grid-template-columns:minmax(380px,.95fr) minmax(480px,1.05fr);align-items:center;gap:34px;min-height:540px}
  .hero-copy .kicker{margin-bottom:16px}.hero h1{max-width:610px;margin:0 0 22px;font-size:clamp(48px,5.5vw,72px)}
  .hero p.sub{max-width:565px;margin:0 0 30px;font-size:18px;line-height:1.6}.hero .storebadges{justify-content:flex-start;margin-bottom:14px}
  .official-badge{width:180px;height:56px;padding:7px 13px;display:grid;grid-template-columns:31px 1fr;align-items:center;gap:8px;border:1px solid rgba(255,255,255,.55);border-radius:7px;background:#050505;color:#fff}
  .official-badge:hover{border-color:var(--accent-mint)}.official-badge svg{width:28px;height:28px}.official-badge small,.official-badge strong{display:block;line-height:1.05}.official-badge small{font-size:9px;text-transform:uppercase}.official-badge strong{font-size:16px;white-space:nowrap}
  .map-stage{position:relative;min-height:440px}.world-map{position:absolute;inset:35px -36px auto 0;height:350px;background:url("assets/world-map.svg") center/contain no-repeat}
  .map-label{position:absolute;right:0;top:26px;padding:11px 14px;border:1px solid var(--line);border-radius:7px;background:rgba(9,48,36,.9);font:10px 'Space Mono',monospace;color:var(--text-muted);text-transform:uppercase}
  .map-label:before{content:"";display:inline-block;width:8px;height:8px;margin-right:8px;border-radius:50%;background:var(--accent-mint)}
  .kroo-counter{position:absolute;right:4px;bottom:6px;width:145px;aspect-ratio:1}.kroo-counter img{width:100%;height:100%;object-fit:contain}.kroo-counter b{position:absolute;inset:42% 0 auto;display:flex;justify-content:center;align-items:baseline;color:#f3d474;font-family:'Fraunces',serif}.kroo-counter strong{font-size:31px;line-height:1}.kroo-counter small{margin-left:2px;font:700 13px 'Space Mono',monospace;opacity:.78}
  .statbar{max-width:1080px;margin:0 auto;padding:52px 28px;gap:58px;flex-wrap:nowrap;background:transparent;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
  .statitem{width:auto;min-width:112px;padding:0;border:0}.statitem .n{color:var(--accent-copper);font-size:34px}.statitem .l{color:var(--text-muted)}
  @media(max-width:860px){.hero-layout{grid-template-columns:1fr}.hero{padding-top:56px}.map-stage{min-height:410px}.world-map{inset:0 -30px auto;height:350px}.kroo-counter{width:145px}.map-label{top:5px}}
  @media(max-width:560px){.brand-logo{width:110px}.hero{padding-top:44px}.hero-layout{min-height:auto}.hero h1{font-size:43px}.hero p.sub{font-size:17px}.official-badge{width:calc(50% - 7px);min-width:150px;padding-inline:9px}.official-badge strong{font-size:14px}.map-stage{min-height:315px}.world-map{inset:4px -20px auto;height:270px}.kroo-counter{width:112px}.kroo-counter b{font-size:24px}.map-label{top:0}.statbar{display:grid;grid-template-columns:1fr 1fr;gap:30px 18px;margin-inline:20px;padding:36px 8px}.statitem{width:auto;min-width:0}.statitem .n{font-size:28px}}
  .official-store-link{height:58px;display:flex;align-items:center}.official-store-link img{display:block;width:auto}.official-store-link.apple img{height:54px}.official-store-link.google img{height:70px;margin-left:-10px;margin-right:-10px}
  .minidevice{width:242px;border:7px solid #191c1b;border-radius:34px;background:#002e23;box-shadow:0 24px 45px rgba(0,0,0,.4)}
  .minidevice .msc{padding:15px 14px 22px}.phone-status{display:flex;justify-content:space-between;padding:0 6px 12px;font-size:9px;font-weight:700}.phone-head{display:grid;grid-template-columns:30px 1fr 30px;align-items:center;margin-bottom:12px}.phone-action{width:30px;height:30px;display:grid;place-items:center;border-radius:50%;background:#204e40;color:var(--cream);font-size:16px}.phone-title{text-align:center;color:var(--accent-copper);font:700 20px 'Fraunces',serif}.phone-hero{height:142px;border-radius:12px;overflow:hidden;background:var(--cream)}.phone-hero img{width:100%;height:100%;object-fit:cover;object-position:center 65%}.phone-stats{display:grid;grid-template-columns:repeat(3,1fr);margin:10px 0 18px;border:1px solid rgba(62,207,142,.45);border-radius:9px}.phone-stat{padding:12px 4px;text-align:center;border-right:1px solid rgba(62,207,142,.25)}.phone-stat:last-child{border:0}.phone-stat b{display:block;color:var(--accent-copper);font:700 16px 'Fraunces',serif}.phone-stat span{font:700 7px 'Space Mono',monospace}.phone-section-title{margin:0 0 8px;font:700 15px 'Fraunces',serif}.sight{display:grid;grid-template-columns:34px 1fr 18px;align-items:center;gap:8px;min-height:44px;border-bottom:1px solid var(--line);font-size:10px}.sight-thumb{width:32px;height:32px;border-radius:7px;background:#31594d}.sight-thumb.stamp{background:url("assets/thailand-stamp.png") center/cover}.sight-check{width:16px;height:16px;border:1.5px solid var(--accent-mint);border-radius:50%}.unlock-row{margin:12px 0;padding:10px 9px;display:flex;justify-content:space-between;align-items:center;border-radius:8px;background:var(--accent-copper);font-size:8px;font-weight:700}.unlock-row span{padding:5px 8px;border-radius:6px;background:var(--cream);color:var(--accent-copper)}.collections-title{margin-top:16px;padding-bottom:5px;border-bottom:2px solid var(--cream);font:700 15px 'Fraunces',serif}
  @media(max-width:560px){.official-store-link{width:calc(50% - 7px)}.official-store-link.apple img{width:100%;height:auto}.official-store-link.google img{width:calc(100% + 16px);height:auto;margin-inline:-8px}.kroo-counter strong{font-size:23px}.kroo-counter small{font-size:9px}}
  .country-screen-shot{width:190px;margin:0 auto;filter:drop-shadow(0 22px 28px rgba(0,0,0,.38))}
  .country-screen-shot img{display:block;width:100%;height:auto}
  @media(max-width:820px){.country-screen-shot{width:176px;margin-top:24px}}
</style>
</head>
<body>

<nav>
  <div class="navwrap">
    <a class="brand" href="#" aria-label="Kroo home"><img class="brand-logo" src="assets/kroo-logo.png" alt="Kroo"></a>
    <div class="navlinks">
      <a href="#how">How it works</a>
      <a href="#depth">Sights</a>
      <a href="#kroo-plus">Kroo+</a>
      <a href="#faq">FAQ</a>
      <a href="#get" class="navcta">Get the app</a>
    </div>
  </div>
</nav>

<header class="hero">
  <div class="wrap hero-layout">
    <div class="hero-copy">
      <div class="kicker">Collect the world</div>
      <h1>Kroo is the ultimate travel app for explorers.</h1>
      <p class="sub">Collect continents, countries, cities, sights and more.</p>
      <div class="storebadges">
        <a href="#" class="official-store-link apple" aria-label="Download Kroo on the App Store"><img src="assets/app-store-badge.svg" alt="Download on the App Store"></a>
        <a href="#" class="official-store-link google" aria-label="Get Kroo on Google Play"><img src="assets/google-play-badge.png" alt="Get it on Google Play"></a>
      </div>
      <div class="microline">Free to download · Available for iPhone and Android</div>
    </div>
    <div class="map-stage" role="img" aria-label="World map and Kroo travel counter">
      <div class="world-map"></div><div class="map-label">Your world, collected</div>
      <div class="kroo-counter"><img src="assets/kroo-number.png" alt=""><b><strong>24</strong><small>/195</small></b></div>
    </div>
  </div>
</header>

<div class="statbar wrap">
  <div class="statitem"><div class="n">195</div><div class="l">Countries</div></div>
  <div class="statitem"><div class="n">10,000+</div><div class="l">Verified sights</div></div>
  <div class="statitem"><div class="n">7</div><div class="l">Continents</div></div>
  <div class="statitem"><div class="n">2,000+</div><div class="l">Cities</div></div>
</div>

<section id="how">
  <div class="wrap">
    <div class="sectionhead">
      <div class="kicker">A tracker that actually proves it</div>
      <h2>No spreadsheets. No guessing. Just proof.</h2>
      <p>Kroo confirms your travels with real location data, then turns it into something worth collecting.</p>
    </div>

    <div class="featuregrid">
      <div class="featcell"><div class="fi">🛂</div><h3>GPS-verified stamps</h3><p>Walk into a country or landmark and Kroo confirms it — no self-reporting required, no guessing whether it "counts."</p></div>
      <div class="featcell"><div class="fi">🧭</div><h3>Kroo Score</h3><p>One number, out of 100, that reflects continents, countries, cities, sights, and challenges — your real share of the world.</p></div>
      <div class="featcell"><div class="fi">🏆</div><h3>Special Lists</h3><p>New 7 Wonders, UNESCO's Top 50, the Continental Grand Slam — curated challenges to chase, or build your own.</p></div>
      <div class="featcell"><div class="fi">📖</div><h3>Daily Destination</h3><p>A new place to learn about every day, with a 60-second quiz. Build your Kroo IQ even between trips.</p></div>
      <div class="featcell"><div class="fi">🎟️</div><h3>Your passport, physical</h3><p>Order a real printed passport booklet and stamp stickers — proof of travel you can actually hold.</p></div>
      <div class="featcell"><div class="fi">🎁</div><h3>Gift Kroo+</h3><p>Send a year of Kroo+ to a fellow traveler in one tap — it's the easiest "I'm thinking of our next trip" there is.</p></div>
    </div>
  </div>
</section>

<section style="background:var(--bg-surface);">
  <div class="wrap">
    <div class="sectionhead">
      <div class="kicker">From landing to legend</div>
      <h2>How Kroo works</h2>
    </div>
    <div class="stepsrow">
      <div class="stepitem"><div class="stepnum">1</div><h3>Land somewhere new</h3><p>Kroo quietly recognizes when you've arrived in a new country or city — no need to open the app first.</p></div>
      <div class="stepitem"><div class="stepnum">2</div><h3>Get your stamp</h3><p>A GPS-verified stamp appears in your passport, in the same moment it happened — not backfilled from memory.</p></div>
      <div class="stepitem"><div class="stepnum">3</div><h3>Go deeper</h3><p>Check off the landmark, temple, or trail that actually made the trip — your Kroo Score grows with real depth, not just a checkmark.</p></div>
      <div class="stepitem"><div class="stepnum">4</div><h3>Share the passport</h3><p>Export a stamp, your Wrapped recap, or your full passport — built to be posted, not just stored.</p></div>
    </div>
  </div>
</section>

<section id="depth">
  <div class="wrap">
    <div class="depthgrid">
      <div class="depthtext">
        <div class="kicker">Beyond the checkmark</div>
        <h2>Go deeper than "I was in Thailand."</h2>
        <p>Most trackers stop at the border. Kroo goes down to the landmark — because "I went to Thailand" and "I stood inside the Grand Palace" are different memories, and only one of them deserves its own stamp.</p>
        <div class="depthlist">
          <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Top sights, ranked and checkable per country</div>
          <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cities visited, tracked separately from countries</div>
          <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Rarity stats — see how few travelers share your stamp</div>
        </div>
      </div>
      <figure class="country-screen-shot">
        <img src="assets/thailand-country-screen.png" alt="Kroo Thailand country page showing travel counts, top sights, Kroo+, and collections">
      </figure>
    </div>
  </div>
</section>

<section id="kroo-plus" style="background:var(--bg-surface);">
  <div class="wrap">
    <div class="sectionhead">
      <div class="kicker">Free vs Kroo+</div>
      <h2>Everything you need is free. Kroo+ goes further.</h2>
      <p>Mark every stamp, build your passport, and share your map — no paywall. Kroo+ unlocks the rest.</p>
    </div>
    <div class="pricetable">
      <div class="prow head"><div>Feature</div><div class="ph">Free</div><div class="ph plus">Kroo+</div></div>
      <div class="prow"><div>Country &amp; landmark stamps</div><div class="pc check">✓</div><div class="pc check">✓</div></div>
      <div class="prow"><div>Kroo Score &amp; passport</div><div class="pc check">✓</div><div class="pc check">✓</div></div>
      <div class="prow"><div>Photo verifications / month</div><div class="pc">5</div><div class="pc check">∞</div></div>
      <div class="prow"><div>Special Lists</div><div class="pc">3</div><div class="pc check">All</div></div>
      <div class="prow"><div>Streak freeze</div><div class="pc dash">—</div><div class="pc check">✓</div></div>
      <div class="prow"><div>Custom passport covers</div><div class="pc dash">—</div><div class="pc check">✓</div></div>
      <div class="prow"><div>Ad-free</div><div class="pc dash">—</div><div class="pc check">✓</div></div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="challengecard">
      <div class="kicker">Kroo+ exclusive</div>
      <h2>Win your dream vacation.</h2>
      <p>Complete the Dream Vacation Challenge — travel, learn, and grow your Kroo community — and Kroo covers $5,000 toward the trip you've been dreaming about.</p>
      <a href="#get" class="challengecta">See the requirements</a>
    </div>
  </div>
</section>

<section id="faq" style="background:var(--bg-surface);">
  <div class="wrap">
    <div class="sectionhead">
      <div class="kicker">Before you download</div>
      <h2>Frequently asked questions</h2>
    </div>
    <div class="faqlist">
      <details open>
        <summary>Is Kroo free to use?</summary>
        <p>Yes. Marking countries, earning verified stamps, tracking your Kroo Score, and sharing your passport are all free. Kroo+ is an optional subscription that unlocks unlimited verification, every Special List, and more.</p>
      </details>
      <details>
        <summary>How does GPS verification actually work?</summary>
        <p>Kroo uses geofencing to quietly recognize when you've arrived somewhere new, plus checks like mock-location detection and travel-plausibility to keep stamps honest. You can also add past trips manually — they're marked as self-reported until you add supporting proof.</p>
      </details>
      <details>
        <summary>How many countries does Kroo track?</summary>
        <p>195 by default, based on the widely used "travelers' list" (UN members plus a handful of commonly visited non-UN destinations like Taiwan). You can switch to a UN-only count in Settings if you prefer.</p>
      </details>
      <details>
        <summary>What's the difference between Kroo Score and Kroo IQ?</summary>
        <p>Kroo Score reflects where you've actually been — continents, countries, cities, sights, and challenges. Kroo IQ is separate: it grows from the daily destination quiz, and reflects how much you've learned, not where you've traveled.</p>
      </details>
      <details>
        <summary>Can I gift Kroo+ to someone?</summary>
        <p>Yes. A one-year Kroo+ gift is available for $59.99 — the recipient gets a redemption code to activate it on their own account, no subscription required to receive it.</p>
      </details>
      <details>
        <summary>Is my location data private?</summary>
        <p>Your location is only used to verify stamps and is never sold. You control what's visible on your public profile, and you can delete your account and data at any time.</p>
      </details>
    </div>
  </div>
</section>

<footer id="get">
  <div class="wrap">
    <h2>Start your passport today.</h2>
    <p class="microline">Free to download. Your first stamp is a tap away.</p>
    <div class="storebadges" style="margin-top:26px;">
      <a href="#" class="official-store-link apple" aria-label="Download Kroo on the App Store"><img src="assets/app-store-badge.svg" alt="Download on the App Store"></a>
      <a href="#" class="official-store-link google" aria-label="Get Kroo on Google Play"><img src="assets/google-play-badge.png" alt="Get it on Google Play"></a>
    </div>
    <div class="footlinks">
      <a href="#">Privacy</a>
      <a href="#">Terms</a>
      <a href="#">Dream Vacation Challenge Rules</a>
      <a href="#">Contact</a>
    </div>
    <div class="copyright">© 2026 Kroo. Do you Kroo?</div>
  </div>
</footer>

</body>
</html>
