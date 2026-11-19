@extends('layouts.app')
@section('title', 'Buy airtime and data or grow your VTU business')
@section('content')
<section class="home-hero">
  <div class="home-hero-inner">
    <div class="hero-copy">
      <span class="section-kicker">Digital services, made simpler</span>
      <h1>Buy for yourself.<br>Build your business.<br><span>One clear platform.</span></h1>
      <p>Whether you need a simple way to purchase digital services or want to run your own VTU reseller business, start with an account designed around you.</p>
      <div class="hero-actions"><a class="btn" href="{{ route('account.type') }}">Choose your account <span aria-hidden="true">→</span></a><a class="btn secondary" href="{{ route('login') }}">Sign in to your account</a></div>
      <div class="hero-proof"><span>Separate customer and reseller spaces</span><span>Secure account access</span><span>Clear transaction records</span></div>
    </div>
    <div class="preview-shell" aria-label="Illustrative preview of the platform workspace">
      <div class="preview-top"><div><small>Platform workspace preview</small><strong>Your digital services, organised</strong></div><span class="preview-tag">Illustrative preview</span></div>
      <div class="preview-balance"><span class="preview-label">A single place to manage your activity</span><strong>Buy · Sell · Track</strong><small>Tools and views depend on your account type and enabled services.</small></div>
      <div class="preview-services">
        <div class="preview-service"><i>↗</i><b>Airtime</b><small>Top-ups</small></div>
        <div class="preview-service"><i>▤</i><b>Data</b><small>Data bundles</small></div>
        <div class="preview-service"><i>◎</i><b>More services</b><small>When enabled</small></div>
      </div>
      <div class="preview-activity"><div class="preview-activity-head"><span>Activity overview</span><span class="muted">Workspace preview</span></div><div class="preview-bars" aria-hidden="true"><span style="height:35%"></span><span style="height:52%"></span><span style="height:43%"></span><span style="height:68%"></span><span style="height:59%"></span><span style="height:77%"></span><span style="height:64%"></span><span style="height:88%"></span><span style="height:71%"></span><span style="height:81%"></span><span style="height:61%"></span><span style="height:93%"></span></div></div>
      <div class="preview-foot"><i>✓</i><span><b>Account-specific workspace</b><br><span class="muted">Customers and resellers see the tools relevant to them.</span></span></div>
      <p class="preview-disclaimer">Illustrative interface only. No live balances, provider status or transaction figures are represented.</p>
    </div>
  </div>
</section>

<section class="home-section" id="features">
  <div class="section-inner">
    <div class="section-head"><span class="section-kicker">One platform, clear experiences</span><h2 class="title">The tools you need, without the guesswork.</h2><p class="section-intro">Choose a personal account for your own purchases, or a reseller account if you are building a digital services business. Each experience is organised around the work you need to do.</p></div>
    <div class="feature-grid">
      <article class="feature-card"><div class="feature-icon">↗</div><h3>Digital service purchases</h3><p>Find supported airtime, data and other available services in one organised customer experience.</p></article>
      <article class="feature-card"><div class="feature-icon">▣</div><h3>Wallet and transaction records</h3><p>Review account activity and transaction history with clear records of what has happened.</p></article>
      <article class="feature-card"><div class="feature-icon">⌂</div><h3>Dedicated reseller workspace</h3><p>Manage your reseller business, customer relationships and operational activity from a separate workspace.</p></article>
      <article class="feature-card"><div class="feature-icon">⌘</div><h3>Developer tools pathway</h3><p>Approved reseller businesses can access API and webhook capabilities when configured and enabled.</p></article>
    </div>
  </div>
</section>

<section class="home-section alt" id="how-it-works">
  <div class="section-inner">
    <div class="steps-layout">
      <div class="steps-copy"><span class="section-kicker">Getting started</span><h2 class="title">A simple start.<br>A clearer way forward.</h2><p class="section-intro">Choose the account that matches your goal. Your registration and workspace will follow that choice.</p><a class="btn secondary" style="margin-top:19px" href="{{ route('account.type') }}">See account options <span aria-hidden="true">→</span></a></div>
      <div class="steps-grid">
        <article class="step-card"><span class="step-number">01</span><h3>Choose your account</h3><p>Tell us whether you are here to buy for yourself or to operate a VTU reseller business.</p></article>
        <article class="step-card"><span class="step-number">02</span><h3>Create your profile</h3><p>Complete the registration form for your selected account type and verify your details.</p></article>
        <article class="step-card"><span class="step-number">03</span><h3>Sign in and get started</h3><p>Use the secure sign-in page. Your account role determines the workspace you can access.</p></article>
      </div>
    </div>
    <div class="audience-grid">
      <article class="audience-card"><div class="feature-icon">◎</div><div><h3>For everyday customers</h3><p>A personal account for supported purchases, wallet activity and your own transaction history.</p></div></article>
      <article class="audience-card"><div class="feature-icon">▥</div><div><h3>For resellers and businesses</h3><p>A business workspace for managing your customers, services and VTU operations.</p></div></article>
      <article class="audience-card"><div class="feature-icon">⌘</div><div><h3>For API developers</h3><p>Developer access is connected to an approved reseller business rather than a separate consumer account.</p></div></article>
    </div>
  </div>
</section>


<section class="home-section" id="services">
  <div class="section-inner">
    <div class="section-head"><span class="section-kicker">Services in one place</span><h2 class="title">Everyday digital services, made easier to access.</h2><p class="section-intro">Explore the service categories available through your account. Actual availability depends on the services enabled on the platform.</p></div>
    <div class="service-showcase">
      <article class="service-tile"><span class="service-symbol">↗</span><div><h3>Airtime top-ups</h3><p>Convenient airtime purchases for supported networks.</p></div><span class="service-arrow">→</span></article>
      <article class="service-tile"><span class="service-symbol">▤</span><div><h3>Mobile data</h3><p>Browse available data bundles and supported plans.</p></div><span class="service-arrow">→</span></article>
      <article class="service-tile"><span class="service-symbol">▦</span><div><h3>More digital services</h3><p>Additional categories can appear as services are configured and activated.</p></div><span class="service-arrow">→</span></article>
    </div>
    <p class="service-note">Service catalogue and network availability may vary. Only services shown as available in your signed-in account can be purchased.</p>
  </div>
</section>

<section class="home-section alt" id="customer-benefits">
  <div class="section-inner benefits-layout">
    <div class="benefits-copy"><span class="section-kicker">A better everyday experience</span><h2 class="title">Your purchases and activity, easier to follow.</h2><p class="section-intro">A personal customer account keeps the essentials together, so you can focus on the services you need rather than navigating a business dashboard.</p><a class="btn" style="margin-top:20px" href="{{ route('register') }}">Create customer account <span aria-hidden="true">→</span></a></div>
    <div class="benefit-list">
      <div class="benefit-row"><span>01</span><div><h3>A personal account space</h3><p>Sign in to the customer experience associated with your account.</p></div></div>
      <div class="benefit-row"><span>02</span><div><h3>Clear transaction history</h3><p>Review your purchases and their recorded status.</p></div></div>
      <div class="benefit-row"><span>03</span><div><h3>Account support</h3><p>Find the support pathway available to help with your account.</p></div></div>
    </div>
  </div>
</section>

<section class="home-section" id="reseller-tools">
  <div class="section-inner">
    <div class="section-head"><span class="section-kicker">For growing businesses</span><h2 class="title">A workspace built around your reseller operation.</h2><p class="section-intro">Start with a reseller application and, once approved, manage your business through a dedicated workspace.</p></div>
    <div class="reseller-feature-grid">
      <article class="reseller-feature"><span class="reseller-index">01 / OPERATIONS</span><h3>Manage your business workspace</h3><p>Keep your reseller activity and business account separate from personal customer use.</p></article>
      <article class="reseller-feature"><span class="reseller-index">02 / CUSTOMERS</span><h3>Serve your own customers</h3><p>Use the reseller tools made available to your approved business to manage customer activity.</p></article>
      <article class="reseller-feature"><span class="reseller-index">03 / INTEGRATIONS</span><h3>Build towards automation</h3><p>Eligible businesses can work towards API and webhook access after the required configuration and approval.</p></article>
    </div>
    <div class="reseller-cta"><div><strong>Ready to operate as a reseller?</strong><p>Choose the business account path to begin your application.</p></div><a class="btn" href="{{ route('register.reseller') }}">Apply as a reseller <span aria-hidden="true">→</span></a></div>
  </div>
</section>

<section class="home-section alt" id="trust-and-control">
  <div class="section-inner trust-layout">
    <div><span class="section-kicker">Built for responsible operations</span><h2 class="title">Clarity and control at every step.</h2><p class="section-intro">A digital services platform needs more than a polished interface. Account access, transaction records and external integrations must be handled carefully.</p></div>
    <div class="trust-grid">
      <article><span class="trust-check">✓</span><h3>Account-based access</h3><p>Customer, reseller and platform roles are designed for distinct workspaces.</p></article>
      <article><span class="trust-check">✓</span><h3>Recorded activity</h3><p>Transactions and operational events are recorded for review and reconciliation.</p></article>
      <article><span class="trust-check">✓</span><h3>Controlled integrations</h3><p>Payment and fulfilment connections require configuration and verification before live use.</p></article>
      <article><span class="trust-check">✓</span><h3>Transparent availability</h3><p>Services are presented according to what has actually been enabled for the platform.</p></article>
    </div>
  </div>
</section>

<section class="safety-band">
  <div class="safety-inner"><div><span class="section-kicker">Designed with control in mind</span><h2 class="title" style="font-size:23px;margin-top:5px">Clear records. Controlled integrations.</h2><p class="muted" style="max-width:620px;margin:0">Payment channels, service providers and fulfilment routes must be configured and verified before live transactions are enabled.</p></div><div class="safety-points"><span>Account-based access</span><span>Transaction records</span><span>Verified integrations</span></div></div>
</section>

<section class="home-section" id="faq">
  <div class="section-inner faq-layout">
    <div><span class="section-kicker">Frequently asked questions</span><h2 class="title">A few things you may be wondering.</h2><p class="section-intro">Not sure which account to choose? These answers will help you get started.</p><a class="btn secondary" style="margin-top:18px" href="{{ route('account.type') }}">Get started <span aria-hidden="true">→</span></a></div>
    <div class="faq-list">
      <details><summary>What is the difference between a customer and reseller account?</summary><p>A customer account is for buying supported digital services for personal use. A reseller account is for a person or business that wants to sell services to its own customers and manage a reseller workspace.</p></details>
      <details><summary>Which account should I create if I only want to buy data or airtime?</summary><p>Choose the customer account. It is designed for personal purchases and your own account activity.</p></details>
      <details><summary>What if I want to start my own VTU business?</summary><p>Choose the reseller account. You will submit your business details for review before the reseller workspace is activated.</p></details>
      <details><summary>Do customers and resellers use different sign-in pages?</summary><p>They use the same secure sign-in page. After authentication, the platform directs each account to the workspace allowed by its account role and access status.</p></details>
      <details><summary>Can I access the API as a customer?</summary><p>API and webhook tools are intended for approved reseller businesses. You should start with a reseller account if you need developer access.</p></details>
      <details><summary>Are all payment providers and digital services available now?</summary><p>Availability depends on provider configuration, verification and activation. The platform will only offer live fulfilment routes after the required integrations have been configured and tested.</p></details>
    </div>
  </div>
</section>

<section class="closing-cta">
  <div><small>A clearer way to buy and grow</small><h2>Start with the account that fits your goal.</h2><p>Create a personal account or apply for a reseller workspace today.</p></div>
  <a class="btn" href="{{ route('account.type') }}">Choose your account <span aria-hidden="true">→</span></a>
</section>
@endsection
