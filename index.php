<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Digital Tourism Hub</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <link rel="stylesheet" href="style.css">
</head>
<body>

  <header class="hero">
    <nav class="login-buttons">
      <a href="login_client.php" class="btn client">
        <i class="bi bi-person"></i> Client Login
      </a>
      <a href="login_agency.php" class="btn agency">
        <i class="bi bi-building"></i> Agency Login
      </a>
    </nav>

    <div class="hero-content">
      <div class="tag">
        <i class="bi bi-stars"></i> AI-Powered Platform
      </div>
      <h1>Digital Tourism Hub: Connecting Travel Agencies and Clients</h1>
      <p>Transforming Pakistan's tourism industry through intelligent technology.</p>
    </div>
  </header>

  <main>

    <!-- PLATFORM OVERVIEW -->
    <section class="overview">
      <div class="container">
        <h2><i class="bi bi-compass"></i> Platform Overview</h2>
        <div class="overview-cards">
          <article class="card blue">
            <h3><i class="bi bi-person-walking"></i> For Travelers</h3>
            <p>Enter budget, days, and group size. AI automatically creates personalized itineraries and total cost.</p>
          </article>

          <article class="card yellow">
            <h3><i class="bi bi-briefcase"></i> For Travel Agencies</h3>
            <p>Post tours, offers, and news on one platform. Access all local clients with a small fee and grow your business digitally.</p>
          </article>
        </div>
      </div>
    </section>
    
    <!-- HOW IT WORKS / PLATFORM HIGHLIGHTS -->
    <section class="how-it-works">
      <div class="container">
        <h2><i class="bi bi-gear-wide-connected"></i> Platform Highlights</h2>
        <div class="how-cards">

          <article class="how-card highlight">
            <div class="icon"><i class="bi bi-cpu"></i></div>
            <h3>AI Planning</h3>
            <p>Personalized itineraries created automatically using your preferences — budget, duration, type and interests.</p>
            <a href="#" class="cta">Try Now →</a>
          </article>

          <article class="how-card">
            <div class="icon"><i class="bi bi-building"></i></div>
            <h3>Agency Hub</h3>
            <p>All local travel agencies unified under one digital platform.</p>
          </article>

          <article class="how-card">
            <div class="icon"><i class="bi bi-trophy"></i></div> 
            <h3>Loyalty Rewards</h3>
            <p>Earn points on every trip and review, then redeem them for exclusive discounts.</p>
          </article>

          <article class="how-card">
            <div class="icon"><i class="bi bi-shield-lock"></i></div>
            <h3>Secure Booking</h3>
            <p>Safe, transparent and verified booking system for travelers and agencies.</p>
          </article>

        </div>
      </div>
    </section>

    <!-- TOP AGENCIES -->
    <section class="top-agencies">
      <div class="container">
        <h2><i class="bi bi-award"></i> Top Travel Agencies</h2>
        <div class="agency-grid">
          <article class="agency-card">
            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80">
            <h3>Explore Pakistan Travels</h3>
            <p>Rated 4.9★ | Northern Pakistan specialists</p>
          </article>

          <article class="agency-card">
            <img src="https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=600&q=80">
            <h3>Adventure Seekers</h3>
            <p>Rated 4.8★ | Hiking & camping experts</p>
          </article>

          <article class="agency-card">
            <img src="https://picsum.photos/seed/agency3/600/400">
            <h3>Luxury Voyages</h3>
            <p>Rated 4.7★ | Premium travel services</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ✅ NEW SECTION: POPULAR DESTINATIONS -->
    <section class="popular-destinations">
  <div class="container">
    <h2><i class="bi bi-geo-alt-fill"></i> Popular Destinations</h2>

    <div class="destination-grid">

      <article class="destination-card">
  <img src="b.jpeg" alt="Naran Kaghan">
  <h3>Naran Kaghan</h3>
  <p>Famous for lakes, lush valleys and family-friendly tours.</p>
</article>


      <article class="destination-card">
        <img src="s.jpeg" alt="Skardu">
        <h3>Skardu</h3>
        <p>Mountains, lakes and adventure tourism hub of Pakistan.</p>
      </article>

      <article class="destination-card">
        <img src="m.jpeg" alt="Murree">
        <h3>Murree</h3>
        <p>Most accessible hill station with cool weather.</p>
      </article>

      <article class="destination-card">
        <img src="https://images.unsplash.com/photo-1609137144813-7d9921338f24?auto=format&fit=crop&w=800&q=80" alt="Hunza Valley">
        <h3>Hunza Valley</h3>
        <p>Premium destination with culture and scenic beauty.</p>
      </article>

    </div>
  </div>
</section>


    <!-- SMART FEATURES -->
    <section class="features">
      <div class="container">
        <h2><i class="bi bi-lightning-charge"></i> Smart Features</h2>
        <div class="feature-grid">
          <div class="feature-box">
            <i class="bi bi-robot"></i>
            <h3>AI Assistant</h3>
            <p>Instant travel suggestions and planning support.</p>
          </div>
          <div class="feature-box">
            <i class="bi bi-shield-check"></i>
            <h3>Verified Agencies</h3>
            <p>Only trusted and approved travel partners.</p>
          </div>
          <div class="feature-box">
            <i class="bi bi-calendar-check"></i>
            <h3>Smart Booking</h3>
            <p>Fast and easy booking workflow.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- STATS -->
    <section class="stats">
      <div class="container">
        <div class="stats-grid">
          <div class="stat"><h3>50+</h3><p>Agencies</p></div>
          <div class="stat"><h3>10K+</h3><p>Travelers</p></div>
          <div class="stat"><h3>200+</h3><p>Packages</p></div>
          <div class="stat"><h3>AI</h3><p>Powered</p></div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
      <div class="container">
        <h2>Start Your Smart Journey</h2>
        <p>Join Pakistan’s first AI-powered tourism ecosystem.</p>
        <div class="cta-buttons">
          <a href="login_client.php" class="cta-btn primary">Traveler</a>
          <a href="login_agency.php" class="cta-btn secondary">Agency</a>
        </div>
      </div>
    </section>

    <!-- ABOUT (UNCHANGED & PRESENT) -->
    <section class="about">
      <div class="container">
        <h2><i class="bi bi-info-circle"></i> About Digital Tourism Hub</h2>
        <p>
          Digital Tourism Hub is Pakistan’s first AI-powered tourism platform that connects travelers and travel agencies in one unified space.
        </p>
      </div>
    </section>

<!-- DISCLAIMER / FOOTER NOTE -->
<footer class="site-footer">
  <div class="container">
    <p>
      © 2025 <strong>Digital Tourism Hub</strong> — Final Year Project (FYP), 
      <span>City University of Science & Information Technology (CUSIT)</span>
    </p>
  
  </div>
</footer>



  </main>

</body>
</html>
