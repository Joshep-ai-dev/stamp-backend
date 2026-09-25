<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Kroo Privacy Policy">
<title>Kroo Privacy Policy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0e3b2c;
    --surface: #123f30;
    --cream: #f6f1e4;
    --muted: rgba(246, 241, 228, 0.72);
    --line: rgba(246, 241, 228, 0.14);
    --copper: #c97c54;
    --mint: #3ecf8e;
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body { margin: 0; background: var(--bg); color: var(--cream); font-family: 'Inter', sans-serif; line-height: 1.72; }
  body::before { content: ''; position: fixed; inset: 0; pointer-events: none; background-image: radial-gradient(rgba(246,241,228,.035) 1px, transparent 1px); background-size: 20px 20px; }
  a { color: inherit; }
  nav { position: sticky; top: 0; z-index: 2; border-bottom: 1px solid var(--line); background: rgba(14,59,44,.9); backdrop-filter: blur(14px); }
  .nav-inner { max-width: 960px; margin: 0 auto; padding: 15px 28px; display: flex; align-items: center; justify-content: space-between; }
  .brand img { width: 132px; display: block; }
  .back { color: var(--muted); font-family: 'Space Mono', monospace; font-size: 11px; letter-spacing: .05em; text-decoration: none; text-transform: uppercase; }
  .back:hover { color: var(--cream); }
  main { position: relative; max-width: 800px; margin: 0 auto; padding: 76px 28px 100px; }
  header { padding-bottom: 42px; margin-bottom: 42px; border-bottom: 1px solid var(--line); }
  .eyebrow { margin-bottom: 12px; color: var(--mint); font-family: 'Space Mono', monospace; font-size: 11px; letter-spacing: .18em; text-transform: uppercase; }
  h1, h2, h3 { font-family: 'Fraunces', serif; line-height: 1.2; }
  h1 { margin: 0; font-size: clamp(40px, 7vw, 62px); color: var(--copper); }
  h2 { margin: 48px 0 14px; font-size: 28px; }
  h3 { margin: 30px 0 10px; font-size: 20px; }
  p { margin: 0 0 16px; color: var(--muted); }
  ul { margin: 8px 0 22px; padding-left: 24px; color: var(--muted); }
  li { margin: 8px 0; padding-left: 5px; }
  li::marker { color: var(--copper); }
  .contact { margin-top: 12px; padding: 22px 24px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface); }
  .contact a { color: var(--mint); }
  footer { border-top: 1px solid var(--line); padding: 28px; color: rgba(246,241,228,.45); font-family: 'Space Mono', monospace; font-size: 10px; text-align: center; }
  @media (max-width: 600px) { main { padding-top: 52px; } .brand img { width: 110px; } h2 { font-size: 24px; } }
</style>
</head>
<body>
<nav>
  <div class="nav-inner">
    <a class="brand" href="<?= route('website.home') ?>" aria-label="Kroo home"><img src="<?= asset('assets/kroo-logo.png') ?>" alt="Kroo"></a>
    <a class="back" href="<?= route('website.home') ?>">&larr; Back to Kroo</a>
  </div>
</nav>

<main>
  <header>
    <div class="eyebrow">Your data, clearly explained</div>
    <h1>Kroo Privacy Policy</h1>
  </header>

  <section>
    <h2>1. Introduction</h2>
    <p>This Privacy Policy explains how KROO ("Kroo," "we," "us," or "our") collects, uses, shares, and protects information in connection with the Kroo mobile application (the "App"). By using the App, you agree to the collection and use of information as described in this Policy.</p>
  </section>

  <section>
    <h2>2. Information We Collect</h2>
    <p>Kroo does not access, collect, or use your device's precise GPS or location data. All travel activity in the App—countries, cities, and sights—is entered manually by you. We collect the following categories of information:</p>

    <h3>2.1 Account Information</h3>
    <ul>
      <li>Email address and/or social sign-in identifiers, if you create a registered account.</li>
      <li>A username or display name you choose.</li>
      <li>Profile information you choose to add (e.g., profile photo, nationality, etc.).</li>
    </ul>

    <h3>2.2 Self-Reported Travel Data</h3>
    <ul>
      <li>Countries, cities, and sights you log as visited.</li>
      <li>Your Kroo Score, Kroo IQ, Collections progress, and Dream Vacation Challenge status.</li>
      <li>Captions, notes, or content you add to your profile or shared stamps.</li>
    </ul>

    <h3>2.3 Subscription and Payment Information</h3>
    <p>Kroo+ subscriptions are processed through the Apple App Store or Google Play. We do not directly collect or store your payment card details; billing is handled entirely by Apple or Google, and by our subscription management provider (e.g., RevenueCat), in accordance with their respective privacy policies. We receive limited subscription status information (e.g., active, canceled, trial status) necessary to provide the App's features.</p>

    <h3>2.4 Referral and Gift Program Data</h3>
    <ul>
      <li>Referral codes, referral relationships (who referred whom), and qualifying actions taken by referred users, to the extent necessary to administer the referral program and Dream Vacation Challenge.</li>
      <li>Gift subscription purchase and redemption records.</li>
    </ul>

    <h3>2.5 Usage and Device Information</h3>
    <ul>
      <li>App usage data (features used, screens viewed, session frequency) collected via analytics tools.</li>
      <li>Device type, operating system, and app version, for troubleshooting and compatibility purposes.</li>
      <li>Crash reports and error logs.</li>
    </ul>
  </section>

  <section>
    <h2>3. How We Use Information</h2>
    <p>We use the information described above to:</p>
    <ul>
      <li>Provide, maintain, and improve the App's features, including the Kroo Score, Kroo IQ, Collections, and Dream Vacation Challenge;</li>
      <li>Process subscription purchases, gift redemptions, and referral rewards;</li>
      <li>Communicate with you about your account, subscription, or Challenge status;</li>
      <li>Detect and prevent fraud, abuse, or violations of our Terms and Conditions;</li>
      <li>Analyze usage trends to improve the App;</li>
      <li>Comply with legal obligations.</li>
    </ul>
  </section>

  <section>
    <h2>4. How We Share Information</h2>
    <p>We do not sell your personal information. We may share information with:</p>
    <ul>
      <li>Service providers who perform functions on our behalf, such as cloud hosting, subscription/entitlement management (e.g., RevenueCat), analytics, and crash reporting—each bound by contractual obligations to protect your information;</li>
      <li>Apple and Google, as necessary to process App Store/Play Store transactions;</li>
      <li>Other users, to the extent you choose to make your profile, stamps, or Collections public;</li>
      <li>Law enforcement or regulators, where required by law or to protect our rights, users, or the public;</li>
      <li>A successor entity, in the event of a merger, acquisition, or sale of assets.</li>
    </ul>
  </section>

  <section>
    <h2>5. Data Retention</h2>
    <p>We retain your information for as long as your account remains active, and for a reasonable period thereafter as necessary to comply with legal obligations, resolve disputes, and enforce our agreements. You may request deletion of your account and associated data at any time, as described in Section 7.</p>
  </section>

  <section>
    <h2>6. Children's Privacy</h2>
    <p>The App is not directed to users under 18 (the minimum age stated in the Terms and Conditions). We do not knowingly collect personal information from children under this age. If we become aware that we have collected such information, we will take steps to delete it.</p>
  </section>

  <section>
    <h2>7. Your Rights and Choices</h2>
    <p>Depending on your jurisdiction, you may have the right to:</p>
    <ul>
      <li>Access, correct, or delete your personal information;</li>
      <li>Object to or restrict certain processing of your information;</li>
      <li>Receive a copy of your information in a portable format;</li>
      <li>Withdraw consent where processing is based on consent;</li>
      <li>Opt out of certain data uses (e.g., analytics), where applicable.</li>
    </ul>
    <p>You can exercise most of these rights directly within the App's account settings, including deleting your account and associated data. For requests we cannot fulfill directly in-app, contact us at <a href="mailto:support@krootravel.com">support@krootravel.com</a>.</p>
  </section>

  <section>
    <h2>8. Data Security</h2>
    <p>We use reasonable administrative, technical, and physical safeguards designed to protect your information. However, no method of transmission or storage is completely secure, and we cannot guarantee absolute security.</p>
  </section>

  <section>
    <h2>9. International Data Transfers</h2>
    <p>If you access the App from outside the United States, your information may be transferred to and processed in that country or other countries where our service providers operate.</p>
  </section>

  <section>
    <h2>10. Third-Party Links and Services</h2>
    <p>The App may contain links to or integrations with third-party services (e.g., travel booking partners referenced in Kroo Partner offers). This Policy does not apply to those third parties, and we encourage you to review their respective privacy policies.</p>
  </section>

  <section>
    <h2>11. Changes to This Policy</h2>
    <p>We may update this Privacy Policy from time to time. Material changes will be communicated through the App or via email. Continued use of the App after changes take effect constitutes acceptance of the revised Policy.</p>
  </section>

  <section>
    <h2>12. Contact Us</h2>
    <p class="contact">Questions about this Privacy Policy may be directed to <a href="mailto:support@krootravel.com">support@krootravel.com</a>.</p>
  </section>
</main>

<footer>&copy; 2026 Kroo. Do you Kroo?</footer>
</body>
</html>
