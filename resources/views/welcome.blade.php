<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>We4Work – Smart CRM & Project Management for Field Businesses</title>
  <meta name="description" content="All-in-one CRM, Lead Management, Project Tracking, Gantt Charts & Staff Management built for Engineering, CCTV, Solar & Fire Protection businesses." />
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20width%3D%2276%22%20height%3D%2256%22%20viewBox%3D%220%200%2076%2056%22%20fill%3D%22none%22%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%3E%3Cpath%20d%3D%22M10%2019C14.9706%2019%2019%2014.9706%2019%2010C19%205.02944%2014.9706%201%2010%201C5.02944%201%201%205.02944%201%2010C1%2014.9706%205.02944%2019%2010%2019Z%22%20fill%3D%22%23253E5F%22%20stroke%3D%22%2380AADD%22%20stroke-width%3D%222%22/%3E%3Cpath%20d%3D%22M66%2019C70.9706%2019%2075%2014.9706%2075%2010C75%205.02944%2070.9706%201%2066%201C61.0294%201%2057%205.02944%2057%2010C57%2014.9706%2061.0294%2019%2066%2019Z%22%20fill%3D%22%231B4614%22%20stroke%3D%22%237AB948%22%20stroke-width%3D%222%22/%3E%3Cpath%20d%3D%22M10%2055C14.9706%2055%2019%2050.9706%2019%2046C19%2041.0294%2014.9706%2037%2010%2037C5.02944%2037%201%2041.0294%201%2046C1%2050.9706%205.02944%2055%2010%2055Z%22%20fill%3D%22%23483A0F%22%20stroke%3D%22%23D1A041%22%20stroke-width%3D%222%22/%3E%3Cpath%20d%3D%22M66%2055C70.9706%2055%2075%2050.9706%2075%2046C75%2041.0294%2070.9706%2037%2066%2037C61.0294%2037%2057%2041.0294%2057%2046C57%2050.9706%2061.0294%2055%2066%2055Z%22%20fill%3D%22%2380AADD%22%20stroke%3D%22%2380AADD%22%20stroke-width%3D%222%22/%3E%3Cpath%20d%3D%22M38%2040C44.6274%2040%2050%2034.6274%2050%2028C50%2021.3726%2044.6274%2016%2038%2016C31.3726%2016%2026%2021.3726%2026%2028C26%2034.6274%2031.3726%2040%2038%2040Z%22%20fill%3D%22%2380AADD%22/%3E%3Cpath%20d%3D%22M38%2033C40.7614%2033%2043%2030.7614%2043%2028C43%2025.2386%2040.7614%2023%2038%2023C35.2386%2023%2033%2025.2386%2033%2028C33%2030.7614%2035.2386%2033%2038%2033Z%22%20fill%3D%22%2330302E%22/%3E%3Cpath%20d%3D%22M35.6369%2029.6577V29.0057L38.5134%2024.4545H38.9864V25.4645H38.6668L36.4935%2028.9034V28.9545H40.3671V29.6577H35.6369ZM38.7179%2031V29.4595V29.1559V24.4545H39.4722V31H38.7179Z%22%20fill%3D%22%2380AADD%22/%3E%3C/svg%3E" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <style>
    /* ============================================================
       RESET & BASE
    ============================================================ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary:   #2563eb;
      --primary-d: #1d4ed8;
      --accent:    #06b6d4;
      --success:   #10b981;
      --warning:   #f59e0b;
      --danger:    #ef4444;
      --purple:    #8b5cf6;
      --dark:      #0f172a;
      --dark2:     #1e293b;
      --dark3:     #334155;
      --mid:       #64748b;
      --light:     #f1f5f9;
      --lighter:   #f8fafc;
      --white:     #ffffff;
      --grad1: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
      --grad2: linear-gradient(135deg, #2563eb, #06b6d4);
      --grad3: linear-gradient(135deg, #8b5cf6, #2563eb);
      --shadow-sm: 0 1px 3px rgba(0,0,0,.12), 0 1px 2px rgba(0,0,0,.06);
      --shadow:    0 4px 24px rgba(0,0,0,.10);
      --shadow-lg: 0 20px 60px rgba(0,0,0,.15);
      --radius:    12px;
      --radius-lg: 20px;
    }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--white);
      color: var(--dark);
      line-height: 1.6;
      overflow-x: hidden;
    }
    a { text-decoration: none; color: inherit; }
    img { max-width: 100%; display: block; }
    ul { list-style: none; }
    .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }

    /* ============================================================
       UTILITY
    ============================================================ */
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 28px; border-radius: 8px; font-weight: 600;
      font-size: 15px; cursor: pointer; border: none; transition: all .25s;
    }
    .btn-primary { background: var(--grad2); color: #fff; box-shadow: 0 4px 20px rgba(37,99,235,.35); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,.45); }
    .btn-outline { background: transparent; border: 2px solid rgba(255,255,255,.4); color: #fff; }
    .btn-outline:hover { background: rgba(255,255,255,.1); border-color: #fff; }
    .btn-dark { background: var(--dark); color: #fff; }
    .btn-dark:hover { background: var(--dark2); transform: translateY(-2px); }
    .btn-lg { padding: 16px 36px; font-size: 17px; border-radius: 10px; }
    .tag {
      display: inline-block; padding: 5px 14px; border-radius: 100px;
      font-size: 13px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase;
    }
    .tag-blue { background: rgba(37,99,235,.12); color: var(--primary); }
    .tag-accent { background: rgba(6,182,212,.12); color: var(--accent); }
    .section-label { margin-bottom: 12px; }
    .section-title { font-size: clamp(28px, 4vw, 44px); font-weight: 800; line-height: 1.15; margin-bottom: 16px; }
    .section-sub { font-size: 17px; color: var(--mid); max-width: 580px; line-height: 1.7; }
    .text-center { text-align: center; }
    .text-center .section-sub { margin: 0 auto; }
    .highlight { background: var(--grad2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
    .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
    .flex { display: flex; }
    .flex-center { display: flex; align-items: center; justify-content: center; }
    .gap-3 { gap: 12px; }
    .gap-4 { gap: 16px; }
    .check-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
    .check-item svg { flex-shrink: 0; margin-top: 2px; }
    .check-item span { font-size: 15px; color: var(--dark2); }

    /* ============================================================
       NAVBAR
    ============================================================ */
    .navbar {
      position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
      padding: 0 24px; height: 68px;
      display: flex; align-items: center; justify-content: space-between;
      background: rgba(15,23,42,.95);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,.06);
      transition: all .3s;
    }
    .navbar.scrolled { box-shadow: 0 4px 30px rgba(0,0,0,.3); }
    .nav-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }
    .nav-logo {
      width: 219px;
      max-width: 219px;
      height: auto;
      display: flex;
      align-items: center;
      justify-content: flex-start;
    }
    .nav-logo svg {
      width: 100%;
      height: auto;
      display: block;
    }
    .nav-name {
      font-size: 28px;
      font-weight: 800;
      color: #fff;
      letter-spacing: -.3px;
      white-space: nowrap;
    }
    .nav-name span { color: var(--accent); }
    .nav-links { display: flex; align-items: center; gap: 32px; }
    .nav-links a { font-size: 14px; font-weight: 500; color: rgba(255,255,255,.7); transition: color .2s; }
    .nav-links a:hover { color: #fff; }
    .nav-cta { display: flex; align-items: center; gap: 12px; }
    .nav-cta a { font-size: 14px; font-weight: 500; color: rgba(255,255,255,.7); }
    .nav-cta a:hover { color: #fff; }
    .hamburger { display: none; background: none; border: none; cursor: pointer; }
    .hamburger span { display: block; width: 24px; height: 2px; background: #fff; margin: 5px 0; border-radius: 2px; transition: all .3s; }

    /* ============================================================
       HERO SECTION
    ============================================================ */
    .hero {
      min-height: 100vh;
      background: var(--grad1);
      display: flex; align-items: center;
      position: relative; overflow: hidden;
      padding: 100px 24px 80px;
    }
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(37,99,235,.25) 0%, transparent 70%);
    }
    .hero-grid {
      position: absolute; inset: 0; opacity: .04;
      background-image: linear-gradient(rgba(255,255,255,1) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px);
      background-size: 60px 60px;
    }
    .hero-blob {
      position: absolute; border-radius: 50%; filter: blur(80px); opacity: .15;
    }
    .hero-blob-1 { width: 500px; height: 500px; background: var(--primary); top: -100px; right: -100px; }
    .hero-blob-2 { width: 400px; height: 400px; background: var(--accent); bottom: -50px; left: -100px; }
    .hero .container { position: relative; z-index: 1; }
    .hero-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
    .hero-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(37,99,235,.2); border: 1px solid rgba(37,99,235,.4);
      padding: 6px 14px; border-radius: 100px; margin-bottom: 24px;
    }
    .hero-badge span { font-size: 13px; font-weight: 600; color: #93c5fd; letter-spacing: .3px; }
    .hero-badge svg { width: 14px; height: 14px; fill: #93c5fd; }
    .hero h1 {
      font-size: clamp(36px, 5vw, 60px); font-weight: 900; line-height: 1.08;
      color: #fff; margin-bottom: 20px; letter-spacing: -1.5px;
    }
    .hero h1 .line2 { color: var(--accent); display: block; }
    .hero-sub {
      font-size: 18px; color: rgba(255,255,255,.65); margin-bottom: 36px;
      line-height: 1.7; max-width: 500px;
    }
    .hero-actions { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .hero-stats {
      display: flex; gap: 36px; margin-top: 52px;
      padding-top: 36px; border-top: 1px solid rgba(255,255,255,.1);
    }
    .hero-stat-num { font-size: 28px; font-weight: 800; color: #fff; line-height: 1; }
    .hero-stat-label { font-size: 13px; color: rgba(255,255,255,.5); margin-top: 4px; }

    /* Dashboard mockup */
    .hero-visual { position: relative; }
    .dashboard-mockup {
      background: #1e293b;
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 40px 100px rgba(0,0,0,.5);
    }
    .mockup-bar {
      background: #0f172a;
      padding: 12px 16px;
      display: flex; align-items: center; gap: 8px;
    }
    .mockup-dot { width: 10px; height: 10px; border-radius: 50%; }
    .mockup-title { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.5); margin-left: 8px; }
    .mockup-body { padding: 20px; }
    .mockup-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
    .m-stat {
      padding: 12px; border-radius: 10px; color: #fff;
    }
    .m-stat-num { font-size: 20px; font-weight: 800; }
    .m-stat-label { font-size: 10px; opacity: .7; margin-top: 2px; }
    .m-stat-change { font-size: 10px; font-weight: 700; margin-top: 6px; }
    .m-stat-1 { background: linear-gradient(135deg,#0ea5e9,#2563eb); }
    .m-stat-2 { background: linear-gradient(135deg,#10b981,#059669); }
    .m-stat-3 { background: linear-gradient(135deg,#f59e0b,#ea580c); }
    .m-stat-4 { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
    .mockup-kanban { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .kanban-col { background: rgba(255,255,255,.04); border-radius: 8px; padding: 10px; }
    .kanban-col-title { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
    .kanban-dot { width: 6px; height: 6px; border-radius: 50%; }
    .kanban-card { background: rgba(255,255,255,.07); border-radius: 6px; padding: 8px; margin-bottom: 6px; }
    .kanban-card-name { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.85); }
    .kanban-card-cat { font-size: 9px; color: rgba(255,255,255,.4); margin-top: 2px; }
    .kanban-card-val { font-size: 11px; font-weight: 700; color: #60a5fa; margin-top: 4px; }
    .badge-pill {
      display: inline-block; padding: 2px 7px; border-radius: 100px;
      font-size: 9px; font-weight: 700;
    }
    .pill-green { background: rgba(16,185,129,.2); color: #34d399; }
    .pill-orange { background: rgba(245,158,11,.2); color: #fbbf24; }
    .pill-blue { background: rgba(37,99,235,.2); color: #93c5fd; }

    .floating-card {
      position: absolute;
      background: rgba(15,23,42,.9);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 12px;
      padding: 12px 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,.4);
    }
    .floating-card-1 { bottom: -24px; left: -48px; }
    .floating-card-2 { top: 40px; right: -40px; }
    .fc-label { font-size: 10px; font-weight: 600; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .fc-value { font-size: 20px; font-weight: 800; color: #fff; }
    .fc-sub { font-size: 11px; color: #34d399; display: flex; align-items: center; gap: 3px; margin-top: 2px; }

    /* ============================================================
       MARQUEE / LOGOS
    ============================================================ */
    .logos-section {
      padding: 28px 0;
      background: var(--lighter);
      border-top: 1px solid #e2e8f0;
      border-bottom: 1px solid #e2e8f0;
      overflow: hidden;
    }
    .logos-label { font-size: 13px; font-weight: 600; color: var(--mid); text-align: center; margin-bottom: 20px; letter-spacing: .5px; text-transform: uppercase; }
    .marquee-track { display: flex; gap: 60px; animation: marquee 20s linear infinite; width: max-content; }
    .marquee-track:hover { animation-play-state: paused; }
    .industry-pill {
      display: flex; align-items: center; gap: 10px; white-space: nowrap;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 100px;
      padding: 10px 22px; font-size: 14px; font-weight: 600; color: var(--dark2);
      box-shadow: var(--shadow-sm);
    }
    .industry-pill svg { width: 18px; height: 18px; }
    @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }

    /* ============================================================
       FEATURES
    ============================================================ */
    .features { padding: 100px 0; background: var(--white); }
    .features-header { margin-bottom: 64px; }
    .feature-card {
      background: var(--lighter);
      border: 1px solid #e2e8f0;
      border-radius: var(--radius-lg);
      padding: 32px;
      transition: all .3s;
      position: relative; overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: var(--grad2); opacity: 0; transition: opacity .3s;
    }
    .feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: transparent; }
    .feature-card:hover::before { opacity: 1; }
    .feature-icon {
      width: 56px; height: 56px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 20px;
    }
    .feature-icon svg { width: 28px; height: 28px; }
    .fi-blue { background: rgba(37,99,235,.1); }
    .fi-blue svg { color: var(--primary); stroke: var(--primary); }
    .fi-teal { background: rgba(6,182,212,.1); }
    .fi-teal svg { color: var(--accent); stroke: var(--accent); }
    .fi-green { background: rgba(16,185,129,.1); }
    .fi-green svg { color: var(--success); stroke: var(--success); }
    .fi-amber { background: rgba(245,158,11,.1); }
    .fi-amber svg { color: var(--warning); stroke: var(--warning); }
    .fi-purple { background: rgba(139,92,246,.1); }
    .fi-purple svg { color: var(--purple); stroke: var(--purple); }
    .fi-red { background: rgba(239,68,68,.1); }
    .fi-red svg { color: var(--danger); stroke: var(--danger); }
    .feature-card h3 { font-size: 19px; font-weight: 700; margin-bottom: 10px; }
    .feature-card p { font-size: 14px; color: var(--mid); line-height: 1.7; margin-bottom: 16px; }
    .feature-tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .feat-tag {
      font-size: 11px; font-weight: 600; padding: 3px 10px;
      border-radius: 100px; background: rgba(37,99,235,.07); color: var(--primary);
    }

    /* ============================================================
       HOW IT WORKS
    ============================================================ */
    .how-it-works { padding: 100px 0; background: var(--dark); position: relative; overflow: hidden; }
    .how-it-works::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse 60% 50% at 50% 50%, rgba(37,99,235,.12) 0%, transparent 70%);
    }
    .how-it-works .section-title { color: #fff; }
    .how-it-works .section-sub { color: rgba(255,255,255,.55); }
    .how-it-works .section-label .tag { background: rgba(37,99,235,.2); color: #93c5fd; }
    .steps { display: flex; gap: 0; position: relative; z-index: 1; }
    .step { flex: 1; padding: 40px 32px; text-align: center; position: relative; }
    .step:not(:last-child)::after {
      content: '';
      position: absolute; top: 60px; right: -1px;
      width: 2px; height: calc(100% - 80px);
      background: rgba(255,255,255,.06);
    }
    .step-num {
      width: 64px; height: 64px; border-radius: 50%;
      background: var(--grad2);
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; font-weight: 900; color: #fff;
      margin: 0 auto 20px; box-shadow: 0 8px 30px rgba(37,99,235,.4);
    }
    .step h3 { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 12px; }
    .step p { font-size: 14px; color: rgba(255,255,255,.5); line-height: 1.7; }

    /* ============================================================
       INDUSTRIES
    ============================================================ */
    .industries { padding: 100px 0; background: var(--lighter); }
    .industry-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 60px; }
    .industry-card {
      border-radius: var(--radius-lg); overflow: hidden;
      background: var(--white); border: 1px solid #e2e8f0;
      transition: all .3s; cursor: pointer;
    }
    .industry-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); }
    .industry-card-img {
      height: 180px;
      display: flex; align-items: center; justify-content: center;
      font-size: 64px; position: relative; overflow: hidden;
    }
    .industry-card-img svg { width: 72px; height: 72px; }
    .ind-eng { background: linear-gradient(135deg, #0f172a, #1e3a5f); }
    .ind-cctv { background: linear-gradient(135deg, #1a1a2e, #16213e); }
    .ind-fire { background: linear-gradient(135deg, #7f1d1d, #991b1b); }
    .ind-solar { background: linear-gradient(135deg, #713f12, #92400e); }
    .ind-hvac { background: linear-gradient(135deg, #064e3b, #065f46); }
    .ind-const { background: linear-gradient(135deg, #312e81, #3730a3); }
    .industry-card-body { padding: 24px; }
    .industry-card-body h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
    .industry-card-body p { font-size: 14px; color: var(--mid); line-height: 1.6; margin-bottom: 16px; }
    .industry-features { display: flex; flex-direction: column; gap: 6px; }
    .industry-feature { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--dark2); }
    .dot-check { width: 16px; height: 16px; border-radius: 50%; background: var(--success); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
    .dot-check svg { width: 9px; height: 9px; stroke: #fff; }

    /* ============================================================
       MODULE SHOWCASE (GANTT / KANBAN)
    ============================================================ */
    .showcase { padding: 100px 0; background: var(--white); }
    .showcase-tabs { display: flex; gap: 8px; margin-bottom: 48px; }
    .s-tab {
      padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 600;
      cursor: pointer; border: 2px solid #e2e8f0; color: var(--mid); background: #fff;
      transition: all .2s;
    }
    .s-tab.active { background: var(--grad2); color: #fff; border-color: transparent; box-shadow: 0 4px 16px rgba(37,99,235,.3); }
    .showcase-panel { display: none; }
    .showcase-panel.active { display: grid; grid-template-columns: 1fr 1.2fr; gap: 64px; align-items: center; }
    .showcase-text h2 { font-size: 32px; font-weight: 800; margin-bottom: 16px; line-height: 1.2; }
    .showcase-text p { font-size: 16px; color: var(--mid); margin-bottom: 28px; line-height: 1.7; }
    .showcase-screen {
      background: var(--dark2);
      border-radius: 16px; overflow: hidden;
      border: 1px solid rgba(255,255,255,.08);
      box-shadow: var(--shadow-lg);
    }
    .screen-bar {
      background: var(--dark); padding: 10px 16px;
      display: flex; align-items: center; gap: 8px;
    }
    .screen-dot { width: 8px; height: 8px; border-radius: 50%; }
    .screen-body { padding: 16px; }

    /* Gantt mock */
    .gantt-row { display: flex; align-items: center; gap: 0; margin-bottom: 8px; }
    .gantt-label { width: 120px; font-size: 10px; color: rgba(255,255,255,.6); flex-shrink: 0; padding-right: 8px; }
    .gantt-track { flex: 1; height: 24px; background: rgba(255,255,255,.04); border-radius: 4px; position: relative; overflow: hidden; }
    .gantt-bar {
      position: absolute; top: 4px; height: 16px; border-radius: 3px;
      display: flex; align-items: center; padding: 0 8px;
      font-size: 9px; font-weight: 700; color: #fff; white-space: nowrap;
    }

    /* Kanban mini mock */
    .kanban-mini { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .km-col { background: rgba(255,255,255,.04); border-radius: 8px; padding: 10px; }
    .km-col-hdr { font-size: 9px; font-weight: 700; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid rgba(255,255,255,.06); }
    .km-card { background: rgba(255,255,255,.07); border-radius: 6px; padding: 8px; margin-bottom: 6px; border-left: 3px solid; }
    .km-card-name { font-size: 10px; font-weight: 600; color: rgba(255,255,255,.85); }
    .km-card-sub { font-size: 9px; color: rgba(255,255,255,.4); margin-top: 2px; }
    .km-tag { display: inline-block; font-size: 8px; font-weight: 700; padding: 2px 6px; border-radius: 3px; margin-top: 5px; }

    /* ============================================================
       PRICING
    ============================================================ */
    .pricing { padding: 100px 0; background: var(--lighter); }
    .pricing-toggle { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 56px; }
    .toggle-label { font-size: 14px; font-weight: 600; color: var(--mid); }
    .toggle-switch {
      width: 48px; height: 26px; border-radius: 13px; background: var(--grad2);
      cursor: pointer; position: relative;
    }
    .toggle-switch::after {
      content: '';
      position: absolute; top: 3px; left: 3px;
      width: 20px; height: 20px; border-radius: 50%; background: #fff;
      transition: transform .3s;
    }
    .toggle-switch.annual::after { transform: translateX(22px); }
    .save-badge { background: var(--success); color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 100px; }
    .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
    .pricing-card {
      background: var(--white); border-radius: var(--radius-lg); padding: 36px 32px;
      border: 1px solid #e2e8f0; transition: all .3s; position: relative;
    }
    .pricing-card.popular {
      border: 2px solid var(--primary);
      box-shadow: 0 8px 40px rgba(37,99,235,.18);
    }
    .pricing-card:hover:not(.popular) { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
    .popular-badge {
      position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
      background: var(--grad2); color: #fff; font-size: 12px; font-weight: 700;
      padding: 4px 16px; border-radius: 100px;
    }
    .pricing-plan { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--mid); margin-bottom: 8px; }
    .pricing-price { font-size: 48px; font-weight: 900; color: var(--dark); line-height: 1; margin-bottom: 4px; }
    .pricing-price sup { font-size: 22px; font-weight: 700; vertical-align: top; margin-top: 10px; display: inline-block; }
    .pricing-price sub { font-size: 15px; font-weight: 500; color: var(--mid); }
    .pricing-desc { font-size: 14px; color: var(--mid); margin-bottom: 28px; padding-bottom: 28px; border-bottom: 1px solid #e2e8f0; }
    .pricing-features { margin-bottom: 32px; }
    .pricing-feature { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: 14px; color: var(--dark2); }
    .pf-icon { width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
    .pf-check { background: rgba(16,185,129,.15); }
    .pf-check svg { width: 10px; height: 10px; stroke: var(--success); }
    .pf-cross { background: rgba(100,116,139,.1); }
    .pf-cross svg { width: 10px; height: 10px; stroke: var(--mid); }
    .pricing-note { font-size: 12px; color: var(--mid); text-align: center; margin-top: 12px; }
    .modules-banner {
      margin-top: 48px; background: var(--dark2);
      border-radius: var(--radius-lg); padding: 36px 40px;
      display: flex; align-items: center; justify-content: space-between; gap: 24px;
    }
    .modules-banner h3 { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 8px; }
    .modules-banner p { font-size: 15px; color: rgba(255,255,255,.55); }
    .module-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
    .module-pill {
      display: flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1);
      border-radius: 100px; padding: 6px 14px; font-size: 12px; font-weight: 600; color: rgba(255,255,255,.8);
    }
    .module-pill svg { width: 12px; height: 12px; }

    /* ============================================================
       TESTIMONIALS
    ============================================================ */
    .testimonials { padding: 100px 0; background: var(--white); overflow: hidden; }
    .testimonials-track-wrap { overflow: hidden; margin-top: 56px; }
    .testimonials-track {
      display: flex; gap: 24px;
      animation: scroll-testimonials 30s linear infinite;
      width: max-content;
    }
    .testimonials-track:hover { animation-play-state: paused; }
    .t-card {
      width: 340px; flex-shrink: 0;
      background: var(--lighter); border: 1px solid #e2e8f0;
      border-radius: var(--radius-lg); padding: 28px;
    }
    .t-stars { display: flex; gap: 3px; margin-bottom: 16px; }
    .t-stars svg { width: 16px; height: 16px; fill: var(--warning); }
    .t-quote { font-size: 15px; color: var(--dark2); line-height: 1.7; margin-bottom: 20px; font-style: italic; }
    .t-author { display: flex; align-items: center; gap: 12px; }
    .t-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; font-weight: 800; color: #fff;
      flex-shrink: 0;
    }
    .t-name { font-size: 14px; font-weight: 700; }
    .t-role { font-size: 12px; color: var(--mid); }
    .t-industry { font-size: 11px; color: var(--primary); font-weight: 600; background: rgba(37,99,235,.08); padding: 2px 8px; border-radius: 4px; margin-top: 2px; display: inline-block; }
    @keyframes scroll-testimonials { from { transform: translateX(0); } to { transform: translateX(-50%); } }

    /* ============================================================
       STATS STRIP
    ============================================================ */
    .stats-strip { padding: 80px 0; background: var(--dark); }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; }
    .stat-item {
      text-align: center; padding: 40px 24px;
      border-right: 1px solid rgba(255,255,255,.07);
    }
    .stat-item:last-child { border-right: none; }
    .stat-num { font-size: 52px; font-weight: 900; color: #fff; line-height: 1; letter-spacing: -2px; }
    .stat-num span { background: var(--grad2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .stat-label { font-size: 15px; color: rgba(255,255,255,.45); margin-top: 8px; }

    /* ============================================================
       CTA BANNER
    ============================================================ */
    .cta-banner { padding: 100px 0; background: var(--lighter); }
    .cta-inner {
      background: var(--grad1);
      border-radius: 24px; padding: 80px 64px;
      text-align: center; position: relative; overflow: hidden;
    }
    .cta-inner::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse 70% 70% at 50% 50%, rgba(37,99,235,.2) 0%, transparent 70%);
    }
    .cta-inner * { position: relative; z-index: 1; }
    .cta-inner h2 { font-size: clamp(30px, 4vw, 48px); font-weight: 900; color: #fff; margin-bottom: 16px; }
    .cta-inner p { font-size: 18px; color: rgba(255,255,255,.6); margin-bottom: 40px; }
    .cta-actions { display: flex; align-items: center; justify-content: center; gap: 16px; flex-wrap: wrap; }
    .cta-trust { display: flex; align-items: center; justify-content: center; gap: 20px; margin-top: 28px; flex-wrap: wrap; }
    .cta-trust-item { display: flex; align-items: center; gap: 6px; font-size: 13px; color: rgba(255,255,255,.5); }
    .cta-trust-item svg { width: 14px; height: 14px; stroke: rgba(255,255,255,.5); }

    /* ============================================================
       CONTACT
    ============================================================ */
    .contact { padding: 100px 0; background: var(--white); }
    .contact-grid { display: grid; grid-template-columns: 1fr 1.3fr; gap: 80px; align-items: start; margin-top: 60px; }
    .contact-info h3 { font-size: 24px; font-weight: 800; margin-bottom: 16px; }
    .contact-info p { font-size: 15px; color: var(--mid); margin-bottom: 36px; line-height: 1.7; }
    .contact-item { display: flex; gap: 16px; margin-bottom: 28px; }
    .contact-icon { width: 48px; height: 48px; border-radius: 12px; background: rgba(37,99,235,.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .contact-icon svg { width: 22px; height: 22px; stroke: var(--primary); }
    .contact-label { font-size: 12px; font-weight: 600; color: var(--mid); text-transform: uppercase; letter-spacing: .5px; }
    .contact-value { font-size: 15px; font-weight: 600; color: var(--dark); margin-top: 2px; }
    .contact-form { background: var(--lighter); border-radius: var(--radius-lg); padding: 40px; border: 1px solid #e2e8f0; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--dark2); margin-bottom: 6px; }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 12px 16px;
      border: 1.5px solid #e2e8f0; border-radius: 8px;
      font-size: 14px; font-family: inherit; color: var(--dark);
      background: #fff; outline: none;
      transition: border-color .2s;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }
    .form-group textarea { min-height: 110px; resize: vertical; }
    .form-submit { width: 100%; padding: 14px; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; border: none; background: var(--grad2); color: #fff; box-shadow: 0 4px 20px rgba(37,99,235,.3); transition: all .25s; }
    .form-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,.4); }

    /* ============================================================
       FOOTER
    ============================================================ */
    .footer { background: var(--dark); padding: 72px 0 0; }
    .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 48px; padding-bottom: 56px; }
    .footer-brand .nav-brand {
      min-width: 0;
      justify-content: flex-start;
    }
    .footer-brand .nav-logo {
      width: 190px;
      max-width: 190px;
      height: auto;
    }
    .footer-brand .nav-logo svg {
      width: 100%;
      height: auto;
    }
    .footer-brand p { font-size: 14px; color: rgba(255,255,255,.45); line-height: 1.8; margin-top: 16px; max-width: 280px; }
    .footer-socials { display: flex; gap: 10px; margin-top: 24px; }
    .social-btn {
      width: 36px; height: 36px; border-radius: 8px;
      background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
      display: flex; align-items: center; justify-content: center;
      transition: all .2s;
    }
    .social-btn:hover { background: var(--primary); border-color: var(--primary); }
    .social-btn svg { width: 16px; height: 16px; stroke: rgba(255,255,255,.6); }
    .social-btn:hover svg { stroke: #fff; }
    .footer-col h4 { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 20px; }
    .footer-col ul li { margin-bottom: 10px; }
    .footer-col ul li a { font-size: 14px; color: rgba(255,255,255,.4); transition: color .2s; }
    .footer-col ul li a:hover { color: rgba(255,255,255,.9); }
    .footer-bottom {
      border-top: 1px solid rgba(255,255,255,.07);
      padding: 24px 0;
      display: flex; align-items: center; justify-content: space-between;
      gap: 12px;
    }
    .footer-bottom-left { display: flex; flex-direction: column; gap: 4px; }
    .footer-bottom p { font-size: 13px; color: rgba(255,255,255,.3); }
    .footer-brandline { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.55); letter-spacing: .2px; }
    .footer-bottom-links { display: flex; gap: 24px; }
    .footer-bottom-links a { font-size: 13px; color: rgba(255,255,255,.3); transition: color .2s; }
    .footer-bottom-links a:hover { color: rgba(255,255,255,.7); }

    /* ============================================================
       RESPONSIVE
    ============================================================ */
    @media (max-width: 1024px) {
      .hero-inner { grid-template-columns: 1fr; gap: 48px; }
      .hero-visual { display: none; }
      .grid-4 { grid-template-columns: repeat(2, 1fr); }
      .footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
      .nav-brand { min-width: 180px; }
      .nav-logo { width: 180px; max-width: 180px; }
      .nav-logo svg { width: 100%; height: auto; }
      .footer-brand .nav-logo { width: 160px; max-width: 160px; }
      .footer-brand .nav-logo svg { width: 100%; height: auto; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .stat-item:nth-child(2) { border-right: none; }
      .stat-item:nth-child(3) { border-top: 1px solid rgba(255,255,255,.07); }
      .stat-item:nth-child(4) { border-top: 1px solid rgba(255,255,255,.07); }
      .pricing-grid { grid-template-columns: 1fr; max-width: 500px; margin: 0 auto; }
      .showcase-panel.active { grid-template-columns: 1fr; }
      .contact-grid { grid-template-columns: 1fr; }
      .industry-cards { grid-template-columns: repeat(2, 1fr); }
      .modules-banner { flex-direction: column; }
    }
    @media (max-width: 768px) {
      .nav-links { display: none; }
      .hamburger { display: block; }
      .nav-cta { gap: 8px; }
      .nav-cta a { font-size: 12px; white-space: nowrap; }
      .nav-cta a.btn {
        padding: 8px 12px !important;
        font-size: 12px !important;
        line-height: 1.15;
        white-space: normal;
      }
      .grid-2 { grid-template-columns: 1fr; }
      .grid-3 { grid-template-columns: 1fr; }
      .grid-4 { grid-template-columns: 1fr; }
      .industry-cards { grid-template-columns: 1fr; }
      .steps { flex-direction: column; }
      .step:not(:last-child)::after { display: none; }
      .form-row { grid-template-columns: 1fr; }
      .hero-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 16px;
        margin-top: 32px;
        padding-top: 24px;
      }
      .hero-stats > div { min-width: 0; }
      .hero-stat-num { font-size: clamp(20px, 7vw, 28px); }
      .hero-stat-label { font-size: 12px; }
      .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .stat-item {
        padding: 26px 14px;
        min-width: 0;
      }
      .stat-num {
        font-size: clamp(34px, 9vw, 52px);
        letter-spacing: -1px;
        word-break: break-word;
      }
      .stat-label { font-size: 12px; line-height: 1.35; }
      .stat-item:nth-child(2) { border-right: none; }
      .stat-item:nth-child(3),
      .stat-item:nth-child(4) { border-top: 1px solid rgba(255,255,255,.07); }
      .cta-inner { padding: 48px 28px; }
      .footer-grid { grid-template-columns: 1fr; }
      .footer-bottom { flex-direction: column; text-align: center; }
      .footer-bottom-left { align-items: center; }
      .footer-bottom-links { justify-content: center; flex-wrap: wrap; gap: 14px; }
      .nav-brand { min-width: 0; }
      .nav-logo { width: 160px; max-width: 160px; }
      .nav-logo svg { width: 100%; height: auto; }
      .footer-brand .nav-logo { width: 145px; max-width: 145px; }
      .footer-brand .nav-logo svg { width: 100%; height: auto; }
      .showcase-tabs { flex-wrap: wrap; }
    }

    @media (max-width: 420px) {
      .nav-cta a.btn {
        padding: 7px 10px !important;
        min-width: 94px;
      }
      .nav-cta a:not(.btn) {
        font-size: 11px;
      }
      .hero-stats {
        grid-template-columns: 1fr;
      }
      .stats-grid {
        grid-template-columns: 1fr;
      }
      .stat-item:nth-child(2),
      .stat-item:nth-child(3),
      .stat-item:nth-child(4) {
        border-right: none;
      }
    }

    /* ============================================================
       SCROLL ANIMATIONS
    ============================================================ */
    .fade-up { opacity: 0; transform: translateY(30px); transition: opacity .6s ease, transform .6s ease; }
    .fade-up.visible { opacity: 1; transform: translateY(0); }
    .delay-1 { transition-delay: .1s; }
    .delay-2 { transition-delay: .2s; }
    .delay-3 { transition-delay: .3s; }
    .delay-4 { transition-delay: .4s; }
    .delay-5 { transition-delay: .5s; }
  </style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav class="navbar" id="navbar">
  <div class="nav-brand">
    <div class="nav-logo">
      <svg width="246" height="69" viewBox="0 0 246 69" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g filter="url(#filter0_d_36_40)">
        <path d="M14.2939 22.5254C19.4268 22.5254 23.5879 18.3468 23.5879 13.1921C23.5879 8.03744 19.4268 3.85876 14.2939 3.85876C9.16104 3.85876 5 8.03744 5 13.1921C5 18.3468 9.16104 22.5254 14.2939 22.5254Z" fill="#253E5F" stroke="#80AADD" stroke-width="2"/>
        <path d="M72.1229 22.5254C77.2558 22.5254 81.4168 18.3468 81.4168 13.1921C81.4168 8.03744 77.2558 3.85876 72.1229 3.85876C66.99 3.85876 62.829 8.03744 62.829 13.1921C62.829 18.3468 66.99 22.5254 72.1229 22.5254Z" fill="#1B4614" stroke="#7AB948" stroke-width="2"/>
        <path d="M14.2939 59.8587C19.4268 59.8587 23.5879 55.6801 23.5879 50.5254C23.5879 45.3708 19.4268 41.1921 14.2939 41.1921C9.16104 41.1921 5 45.3708 5 50.5254C5 55.6801 9.16104 59.8587 14.2939 59.8587Z" fill="#483A0F" stroke="#D1A041" stroke-width="2"/>
        <path d="M72.1229 59.8587C77.2558 59.8587 81.4168 55.6801 81.4168 50.5254C81.4168 45.3708 77.2558 41.1921 72.1229 41.1921C66.99 41.1921 62.829 45.3708 62.829 50.5254C62.829 55.6801 66.99 59.8587 72.1229 59.8587Z" fill="#80AADD" stroke="#80AADD" stroke-width="2"/>
        <path d="M43.2084 44.3032C50.0522 44.3032 55.6003 38.7316 55.6003 31.8587C55.6003 24.9859 50.0522 19.4143 43.2084 19.4143C36.3645 19.4143 30.8165 24.9859 30.8165 31.8587C30.8165 38.7316 36.3645 44.3032 43.2084 44.3032Z" fill="#80AADD"/>
        <path d="M43.2084 37.0439C46.06 37.0439 48.3717 34.7224 48.3717 31.8587C48.3717 28.995 46.06 26.6735 43.2084 26.6735C40.3568 26.6735 38.0451 28.995 38.0451 31.8587C38.0451 34.7224 40.3568 37.0439 43.2084 37.0439Z" fill="#30302E"/>
        <path d="M40.9964 33.3125V32.6563L43.6889 28.0761H44.1316V29.0925H43.8325L41.7982 32.5534V32.6049H45.424V33.3125H40.9964ZM43.8803 34.6634V33.1131V32.8075V28.0761H44.5864V34.6634H43.8803Z" fill="#80AADD"/>
        <path d="M227.711 28.5909L227.663 24.8341H228.238L236.279 16.0339H239.774L231.205 25.3487H230.966L227.711 28.5909ZM225.079 35.7958V9.44659H227.903V35.7958H225.079ZM236.758 35.7958L229.578 26.0178L231.588 23.9078L240.348 35.7958H236.758Z" fill="white"/>
        <path d="M212.541 35.7958V16.0339H215.269V19.0187H215.46C215.796 18.0409 216.402 17.2476 217.279 16.6386C218.157 16.0296 219.146 15.7251 220.247 15.7251C220.455 15.7251 220.714 15.7294 221.025 15.738C221.336 15.7465 221.571 15.7594 221.731 15.7766V18.8644C221.635 18.8386 221.416 18.8 221.073 18.7486C220.738 18.6885 220.383 18.6585 220.008 18.6585C219.114 18.6585 218.316 18.8601 217.614 19.2632C216.92 19.6577 216.37 20.2067 215.963 20.91C215.564 21.6048 215.365 22.3982 215.365 23.2902V35.7958H212.541Z" fill="white"/>
        <path d="M199.901 36.2075C198.242 36.2075 196.786 35.783 195.533 34.9338C194.289 34.0847 193.315 32.8967 192.613 31.37C191.919 29.8432 191.572 28.0592 191.572 26.0178C191.572 23.9593 191.919 22.1623 192.613 20.627C193.315 19.0917 194.289 17.8995 195.533 17.0503C196.786 16.2012 198.242 15.7766 199.901 15.7766C201.56 15.7766 203.012 16.2012 204.257 17.0503C205.509 17.8995 206.483 19.0917 207.177 20.627C207.879 22.1623 208.23 23.9593 208.23 26.0178C208.23 28.0592 207.879 29.8432 207.177 31.37C206.483 32.8967 205.509 34.0847 204.257 34.9338C203.012 35.783 201.56 36.2075 199.901 36.2075ZM199.901 33.48C201.161 33.48 202.199 33.1326 203.012 32.4378C203.826 31.7431 204.428 30.8296 204.819 29.6974C205.21 28.5652 205.406 27.3387 205.406 26.0178C205.406 24.6969 205.21 23.4661 204.819 22.3253C204.428 21.1845 203.826 20.2625 203.012 19.5592C202.199 18.8558 201.161 18.5042 199.901 18.5042C198.64 18.5042 197.603 18.8558 196.79 19.5592C195.976 20.2625 195.374 21.1845 194.983 22.3253C194.592 23.4661 194.396 24.6969 194.396 26.0178C194.396 27.3387 194.592 28.5652 194.983 29.6974C195.374 30.8296 195.976 31.7431 196.79 32.4378C197.603 33.1326 198.64 33.48 199.901 33.48Z" fill="white"/>
        <path d="M169.844 35.7958L164.244 16.0339H167.211L171.184 31.1641H171.376L175.301 16.0339H178.316L182.193 31.1127H182.385L186.358 16.0339H189.325L183.725 35.7958H180.949L176.928 20.6142H176.641L172.62 35.7958H169.844Z" fill="white"/>
        <path d="M143.437 30.3921V27.7675L154.207 9.44659H155.978V13.5122H154.781L146.644 27.3558V27.5617H161.147V30.3921H143.437ZM154.972 35.7958V29.5945V28.3722V9.44659H157.797V35.7958H154.972Z" fill="white"/>
        <path d="M132.152 36.2075C130.381 36.2075 128.853 35.7872 127.569 34.9467C126.293 34.0975 125.307 32.9139 124.613 31.3957C123.927 29.869 123.584 28.0935 123.584 26.0693C123.584 24.045 123.927 22.261 124.613 20.7171C125.307 19.1646 126.273 17.9552 127.509 17.0889C128.754 16.214 130.206 15.7766 131.865 15.7766C132.822 15.7766 133.768 15.9482 134.701 16.2912C135.635 16.6343 136.484 17.1919 137.25 17.9638C138.016 18.7272 138.626 19.7393 139.081 21.0001C139.536 22.261 139.763 23.8135 139.763 25.6576V26.9441H125.595V24.3195H136.891C136.891 23.2045 136.684 22.2095 136.269 21.3346C135.862 20.4598 135.279 19.7693 134.522 19.2632C133.772 18.7572 132.886 18.5042 131.865 18.5042C130.74 18.5042 129.767 18.8044 128.945 19.4048C128.131 19.9966 127.505 20.7685 127.066 21.7206C126.628 22.6727 126.408 23.6934 126.408 24.7827V26.5324C126.408 28.0249 126.648 29.29 127.126 30.3278C127.613 31.3571 128.287 32.1419 129.149 32.6823C130.01 33.2141 131.011 33.48 132.152 33.48C132.894 33.48 133.564 33.3685 134.163 33.1455C134.769 32.9139 135.291 32.5708 135.73 32.1162C136.169 31.653 136.508 31.0784 136.747 30.3922L139.476 31.2156C139.189 32.2105 138.706 33.0854 138.028 33.8402C137.35 34.5864 136.512 35.1697 135.515 35.59C134.518 36.0017 133.397 36.2075 132.152 36.2075Z" fill="white"/>
        <path d="M101.856 35.7958L96.2555 16.0339H99.2232L103.196 31.1641H103.388L107.313 16.0339H110.328L114.205 31.1127H114.397L118.37 16.0339H121.337L115.737 35.7958H112.961L108.94 20.6142H108.653L104.632 35.7958H101.856Z" fill="white"/>
        <path d="M180.143 50.1353C180.143 50.9845 180.001 51.7183 179.715 52.3368C179.43 52.9553 179.039 53.4323 178.542 53.7678C178.045 54.1033 177.477 54.271 176.838 54.271C176.199 54.271 175.631 54.1033 175.134 53.7678C174.637 53.4323 174.245 52.9553 173.96 52.3368C173.675 51.7183 173.532 50.9845 173.532 50.1353C173.532 49.2862 173.675 48.5524 173.96 47.9339C174.245 47.3153 174.637 46.8384 175.134 46.5029C175.631 46.1674 176.199 45.9997 176.838 45.9997C177.477 45.9997 178.045 46.1674 178.542 46.5029C179.039 46.8384 179.43 47.3153 179.715 47.9339C180.001 48.5524 180.143 49.2862 180.143 50.1353ZM179.266 50.1353C179.266 49.4382 179.157 48.8498 178.94 48.3702C178.726 47.8906 178.435 47.5276 178.066 47.2813C177.701 47.0349 177.291 46.9117 176.838 46.9117C176.384 46.9117 175.974 47.0349 175.606 47.2813C175.24 47.5276 174.949 47.8906 174.732 48.3702C174.517 48.8498 174.41 49.4382 174.41 50.1353C174.41 50.8325 174.517 51.4208 174.732 51.9005C174.949 52.3801 175.24 52.7431 175.606 52.9894C175.974 53.2358 176.384 53.3589 176.838 53.3589C177.291 53.3589 177.701 53.2358 178.066 52.9894C178.435 52.7431 178.726 52.3801 178.94 51.9005C179.157 51.4208 179.266 50.8325 179.266 50.1353Z" fill="#C2C0B6"/>
        <path d="M167.239 54.1609V46.1098H169.769C170.354 46.1098 170.834 46.2173 171.21 46.4322C171.585 46.6444 171.863 46.9367 172.043 47.3088C172.224 47.681 172.314 48.1042 172.314 48.5786C172.314 49.053 172.224 49.4736 172.043 49.8405C171.863 50.2074 171.586 50.4957 171.213 50.7054C170.84 50.9124 170.364 51.016 169.784 51.016H167.736V50.1354H169.754C170.154 50.1354 170.476 50.0725 170.72 49.9467C170.966 49.8209 171.144 49.6427 171.254 49.412C171.366 49.1788 171.422 48.901 171.422 48.5786C171.422 48.2562 171.366 47.9745 171.254 47.7334C171.141 47.4923 170.962 47.3062 170.716 47.1752C170.47 47.0415 170.144 46.9747 169.74 46.9747H168.146V54.1609H167.239ZM170.764 50.5442L172.606 54.1609H171.553L169.74 50.5442H170.764Z" fill="#C2C0B6"/>
        <path d="M160.703 54.1609V46.1098H163.233C163.821 46.1098 164.301 46.2238 164.674 46.4518C165.049 46.6772 165.327 46.9825 165.507 47.3678C165.688 47.7531 165.778 48.1829 165.778 48.6572C165.778 49.1316 165.688 49.5627 165.507 49.9506C165.329 50.3385 165.054 50.6477 164.681 50.8784C164.308 51.1064 163.83 51.2204 163.248 51.2204H161.434V50.3555H163.218C163.621 50.3555 163.944 50.2808 164.187 50.1314C164.431 49.9821 164.608 49.7803 164.718 49.526C164.83 49.2692 164.886 48.9796 164.886 48.6572C164.886 48.3349 164.83 48.0466 164.718 47.7924C164.608 47.5381 164.43 47.339 164.184 47.1948C163.938 47.0481 163.611 46.9747 163.204 46.9747H161.61V54.1609H160.703Z" fill="#C2C0B6"/>
        <path d="M149.393 54.1609L147.346 46.1098H148.267L149.832 52.6671H149.905L151.5 46.1098H152.523L154.118 52.6671H154.191L155.756 46.1098H156.677L154.63 54.1609H153.693L152.041 47.7452H151.982L150.33 54.1609H149.393Z" fill="#C2C0B6"/>
        <path d="M146.509 50.1353C146.509 50.9845 146.366 51.7183 146.081 52.3368C145.796 52.9553 145.404 53.4323 144.907 53.7678C144.41 54.1033 143.842 54.271 143.203 54.271C142.564 54.271 141.996 54.1033 141.499 53.7678C141.002 53.4323 140.611 52.9553 140.325 52.3368C140.04 51.7183 139.898 50.9845 139.898 50.1353C139.898 49.2862 140.04 48.5524 140.325 47.9339C140.611 47.3153 141.002 46.8384 141.499 46.5029C141.996 46.1674 142.564 45.9997 143.203 45.9997C143.842 45.9997 144.41 46.1674 144.907 46.5029C145.404 46.8384 145.796 47.3153 146.081 47.9339C146.366 48.5524 146.509 49.2862 146.509 50.1353ZM145.631 50.1353C145.631 49.4382 145.523 48.8498 145.306 48.3702C145.091 47.8906 144.8 47.5276 144.432 47.2813C144.066 47.0349 143.657 46.9117 143.203 46.9117C142.75 46.9117 142.339 47.0349 141.971 47.2813C141.605 47.5276 141.314 47.8906 141.097 48.3702C140.882 48.8498 140.775 49.4382 140.775 50.1353C140.775 50.8325 140.882 51.4208 141.097 51.9005C141.314 52.3801 141.605 52.7431 141.971 52.9894C142.339 53.2358 142.75 53.3589 143.203 53.3589C143.657 53.3589 144.066 53.2358 144.432 52.9894C144.8 52.7431 145.091 52.3801 145.306 51.9005C145.523 51.4208 145.631 50.8325 145.631 50.1353Z" fill="#C2C0B6"/>
        <path d="M134.69 54.1609V46.1098H135.597V53.2961H139.078V54.1609H134.69Z" fill="#C2C0B6"/>
        <path d="M128.647 54.1609V46.1098H133.137V46.9747H129.554V49.6951H132.8V50.5599H129.554V54.1609H128.647Z" fill="#C2C0B6"/>
        <path d="M121.93 54.1609V46.1098H122.837V50.1039H122.924L126.288 46.1098H127.473L124.329 49.7423L127.473 54.1609H126.376L123.773 50.4184L122.837 51.5506V54.1609H121.93Z" fill="#C2C0B6"/>
        <path d="M115.344 54.1609V46.1098H117.874C118.459 46.1098 118.939 46.2173 119.315 46.4322C119.69 46.6444 119.968 46.9367 120.148 47.3088C120.329 47.681 120.419 48.1042 120.419 48.5786C120.419 49.053 120.329 49.4736 120.148 49.8405C119.968 50.2074 119.691 50.4957 119.318 50.7054C118.945 50.9124 118.469 51.016 117.889 51.016H115.841V50.1354H117.859C118.259 50.1354 118.581 50.0725 118.825 49.9467C119.071 49.8209 119.249 49.6427 119.358 49.412C119.471 49.1788 119.527 48.901 119.527 48.5786C119.527 48.2562 119.471 47.9745 119.358 47.7334C119.246 47.4923 119.067 47.3062 118.821 47.1752C118.575 47.0415 118.249 46.9747 117.845 46.9747H116.25V54.1609H115.344ZM118.869 50.5442L120.711 54.1609H119.658L117.845 50.5442H118.869Z" fill="#C2C0B6"/>
        <path d="M113.819 50.1353C113.819 50.9845 113.676 51.7183 113.391 52.3368C113.106 52.9553 112.715 53.4323 112.217 53.7678C111.72 54.1033 111.152 54.271 110.514 54.271C109.875 54.271 109.307 54.1033 108.81 53.7678C108.312 53.4323 107.921 52.9553 107.636 52.3368C107.351 51.7183 107.208 50.9845 107.208 50.1353C107.208 49.2862 107.351 48.5524 107.636 47.9339C107.921 47.3153 108.312 46.8384 108.81 46.5029C109.307 46.1674 109.875 45.9997 110.514 45.9997C111.152 45.9997 111.72 46.1674 112.217 46.5029C112.715 46.8384 113.106 47.3153 113.391 47.9339C113.676 48.5524 113.819 49.2862 113.819 50.1353ZM112.941 50.1353C112.941 49.4382 112.833 48.8498 112.616 48.3702C112.401 47.8906 112.11 47.5276 111.742 47.2813C111.376 47.0349 110.967 46.9117 110.514 46.9117C110.06 46.9117 109.649 47.0349 109.281 47.2813C108.916 47.5276 108.624 47.8906 108.407 48.3702C108.193 48.8498 108.086 49.4382 108.086 50.1353C108.086 50.8325 108.193 51.4208 108.407 51.9005C108.624 52.3801 108.916 52.7431 109.281 52.9894C109.649 53.2358 110.06 53.3589 110.514 53.3589C110.967 53.3589 111.376 53.2358 111.742 52.9894C112.11 52.7431 112.401 52.3801 112.616 51.9005C112.833 51.4208 112.941 50.8325 112.941 50.1353Z" fill="#C2C0B6"/>
        <path d="M99.4391 54.1609L97.3914 46.1098H98.3129L99.8778 52.6671H99.951L101.545 46.1098H102.569L104.163 52.6671H104.236L105.801 46.1098H106.723L104.675 54.1609H103.739L102.086 47.7452H102.028L100.375 54.1609H99.4391Z" fill="#C2C0B6"/>
        </g>
        <defs>
        <filter id="filter0_d_36_40" x="0" y="2.85876" width="244.348" height="66" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
        <feFlood flood-opacity="0" result="BackgroundImageFix"/>
        <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
        <feOffset dy="4"/>
        <feGaussianBlur stdDeviation="2"/>
        <feComposite in2="hardAlpha" operator="out"/>
        <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0"/>
        <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_36_40"/>
        <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_36_40" result="shape"/>
        </filter>
        </defs>
        </svg>

    </div>
  </div>
  <ul class="nav-links">
    <li><a href="#features">Features</a></li>
    <li><a href="#industries">Industries</a></li>
    <li><a href="#showcase">Modules</a></li>
    <li><a href="#pricing">Pricing</a></li>
    <li><a href="#testimonials">Reviews</a></li>
    <li><a href="#contact">Contact</a></li>
  </ul>
  <div class="nav-cta">
    @auth
      <a href="{{ url('/dashboard') }}">Dashboard</a>
    @else
      <a href="{{ route('login') }}">Login</a>
    @endauth
     <a href="{{ route('register.company') }}" class="btn btn-primary" style="padding:9px 20px;font-size:13px;">Start Free Trial</a>
  </div>
  <button class="hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- ============================================================
     HERO
============================================================ -->
<section class="hero" id="home">
  <div class="hero-grid"></div>
  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="container">
    <div class="hero-inner">
      <div class="hero-content">
        <div class="hero-badge">
          <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          <span>All-in-One Business OS for Field Industries</span>
        </div>
        <h1>
          Manage Leads,<br>Projects &amp;
          <span class="line2">Teams. Together.</span>
        </h1>
        <p class="hero-sub">
          We4Work unifies your CRM, project pipeline, staff attendance,
          and quotation — purpose-built for Engineering, CCTV, Fire Protection,
          Solar &amp; construction businesses.
        </p>
        <div class="hero-actions">
    <a href="{{ route('register.company') }}" class="btn btn-primary btn-lg">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            Start Free 14-Day Trial
          </a>
          <a href="#showcase" class="btn btn-outline btn-lg">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
            Watch Demo
          </a>
        </div>
        <div class="hero-stats">
          <div>
            <div class="hero-stat-num">1,200+</div>
            <div class="hero-stat-label">Active Companies</div>
          </div>
          <div>
            <div class="hero-stat-num">50K+</div>
            <div class="hero-stat-label">Projects Managed</div>
          </div>
          <div>
            <div class="hero-stat-num">98%</div>
            <div class="hero-stat-label">Customer Satisfaction</div>
          </div>
        </div>
      </div>

      <!-- Dashboard Visual -->
      <div class="hero-visual">
        <div class="dashboard-mockup">
          <div class="mockup-bar">
            <div class="mockup-dot" style="background:#ef4444;"></div>
            <div class="mockup-dot" style="background:#f59e0b;"></div>
            <div class="mockup-dot" style="background:#10b981;"></div>
            <span class="mockup-title">We4Work — Dashboard</span>
          </div>
          <div class="mockup-body">
            <div class="mockup-stats">
              <div class="m-stat m-stat-1">
                <div class="m-stat-num">1285</div>
                <div class="m-stat-label">Total Customers</div>
                <div class="m-stat-change">+11% ↑</div>
              </div>
              <div class="m-stat m-stat-2">
                <div class="m-stat-num">₹5.4L</div>
                <div class="m-stat-label">Revenue This Month</div>
                <div class="m-stat-change">+18% ↑</div>
              </div>
              <div class="m-stat m-stat-3">
                <div class="m-stat-num">142</div>
                <div class="m-stat-label">Active Deals</div>
                <div class="m-stat-change">+7% ↑</div>
              </div>
              <div class="m-stat m-stat-4">
                <div class="m-stat-num">89%</div>
                <div class="m-stat-label">Satisfaction</div>
                <div class="m-stat-change">+3% ↑</div>
              </div>
            </div>
            <div class="mockup-kanban">
              <div class="kanban-col">
                <div class="kanban-col-title"><div class="kanban-dot" style="background:#60a5fa;"></div> Fresh Lead</div>
                <div class="kanban-card"><div class="kanban-card-name">Raj Solar Co.</div><div class="kanban-card-cat">Solar</div><div class="kanban-card-val">₹6,00,000</div></div>
                <div class="kanban-card"><div class="kanban-card-name">TechFire Ltd</div><div class="kanban-card-cat">Fire Protection</div><div class="kanban-card-val">₹85,000</div></div>
              </div>
              <div class="kanban-col">
                <div class="kanban-col-title"><div class="kanban-dot" style="background:#fbbf24;"></div> Site Survey</div>
                <div class="kanban-card"><div class="kanban-card-name">Nexus CCTV</div><div class="kanban-card-cat">Security</div><div class="kanban-card-val">₹2,40,000</div><div style="margin-top:4px;"><span class="badge-pill pill-orange">High</span></div></div>
              </div>
              <div class="kanban-col">
                <div class="kanban-col-title"><div class="kanban-dot" style="background:#34d399;"></div> Won</div>
                <div class="kanban-card"><div class="kanban-card-name">BuildRight Eng.</div><div class="kanban-card-cat">Engineering</div><div class="kanban-card-val">₹12,00,000</div><div style="margin-top:4px;"><span class="badge-pill pill-green">Won</span></div></div>
                <div class="kanban-card"><div class="kanban-card-name">Sharma Infra</div><div class="kanban-card-cat">Construction</div><div class="kanban-card-val">₹4,50,000</div><div style="margin-top:4px;"><span class="badge-pill pill-green">Won</span></div></div>
              </div>
            </div>
          </div>
        </div>

        <div class="floating-card floating-card-1">
          <div class="fc-label">Projects On Track</div>
          <div class="fc-value">13 / 13</div>
          <div class="fc-sub">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            0 Delayed Today
          </div>
        </div>

        <div class="floating-card floating-card-2">
          <div class="fc-label">Staff Present</div>
          <div class="fc-value">24 / 28</div>
          <div class="fc-sub" style="color:#fbbf24;">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            4 Absent
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     MARQUEE INDUSTRIES
============================================================ -->
<div class="logos-section">
  <p class="logos-label">Trusted across industries</p>
  <div class="marquee-track">
    <!-- First set -->
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      Engineering Firms
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M12 2c-5.5 0-9 4.5-9 9 0 6 9 13 9 13s9-7 9-13c0-4.5-3.5-9-9-9z"/><circle cx="12" cy="11" r="3"/></svg>
      CCTV & Security
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M12 2L8 6H4l2 4-4 2 4 2-2 4h4l4 4 4-4h4l-2-4 4-2-4-2 2-4h-4z"/></svg>
      Solar Energy
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M17 12c0-2.76-2.24-5-5-5S7 9.24 7 12c0 1.38.56 2.63 1.46 3.54L12 19l3.54-3.46C16.44 14.63 17 13.38 17 12z"/><path d="M12 7V2M12 22v-3"/></svg>
      Fire Protection
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      HVAC Systems
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><polygon points="3 9 12 2 21 9 21 20 3 20 3 9"/></svg>
      Construction
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      MEP Contractors
    </div>
    <!-- Duplicate for seamless loop -->
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      Engineering Firms
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M12 2c-5.5 0-9 4.5-9 9 0 6 9 13 9 13s9-7 9-13c0-4.5-3.5-9-9-9z"/><circle cx="12" cy="11" r="3"/></svg>
      CCTV & Security
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M12 2L8 6H4l2 4-4 2 4 2-2 4h4l4 4 4-4h4l-2-4 4-2-4-2 2-4h-4z"/></svg>
      Solar Energy
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M17 12c0-2.76-2.24-5-5-5S7 9.24 7 12c0 1.38.56 2.63 1.46 3.54L12 19l3.54-3.46C16.44 14.63 17 13.38 17 12z"/><path d="M12 7V2M12 22v-3"/></svg>
      Fire Protection
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      HVAC Systems
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><polygon points="3 9 12 2 21 9 21 20 3 20 3 9"/></svg>
      Construction
    </div>
    <div class="industry-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      MEP Contractors
    </div>
  </div>
</div>

<!-- ============================================================
     FEATURES
============================================================ -->
<section class="features" id="features">
  <div class="container">
    <div class="features-header text-center fade-up">
      <div class="section-label"><span class="tag tag-blue">Everything You Need</span></div>
      <h2 class="section-title">One Platform. Every Workflow.</h2>
      <p class="section-sub">From capturing a lead to delivering the project — manage it all without switching tools.</p>
    </div>
    <div class="grid-3">
      <div class="feature-card fade-up delay-1">
        <div class="feature-icon fi-blue">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <h3>CRM & Lead Management</h3>
        <p>Capture leads, move them through pipeline stages — Fresh Lead → Contacted → Site Survey → Quote → Won. Kanban or list view, your choice.</p>
        <div class="feature-tags">
          <span class="feat-tag">Kanban Board</span>
          <span class="feat-tag">Lead Scoring</span>
          <span class="feat-tag">Follow-up Alerts</span>
          <span class="feat-tag">Document Upload</span>
        </div>
      </div>
      <div class="feature-card fade-up delay-2">
        <div class="feature-icon fi-teal">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <h3>Project Management</h3>
        <p>Track projects across stages — Drawing → Material → Production → Dispatch → Delivery. Visual Gantt charts give your team full timeline clarity.</p>
        <div class="feature-tags">
          <span class="feat-tag">Gantt Chart</span>
          <span class="feat-tag">Kanban View</span>
          <span class="feat-tag">Folder View</span>
          <span class="feat-tag">Milestone Tracking</span>
        </div>
      </div>
      <div class="feature-card fade-up delay-3">
        <div class="feature-icon fi-green">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <h3>Quotation Management</h3>
        <p>Generate professional quotations directly from leads. Track versions, send to clients, and convert winning quotes into projects automatically.</p>
        <div class="feature-tags">
          <span class="feat-tag">Quote Builder</span>
          <span class="feat-tag">PDF Export</span>
          <span class="feat-tag">Version History</span>
          <span class="feat-tag">Auto Convert</span>
        </div>
      </div>
      <div class="feature-card fade-up delay-1">
        <div class="feature-icon fi-amber">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h3>Staff Management</h3>
        <p>Maintain staff profiles, mark daily attendance in bulk, and track who's present or absent. Full attendance history with downloadable reports.</p>
        <div class="feature-tags">
          <span class="feat-tag">Bulk Attendance</span>
          <span class="feat-tag">Staff Profiles</span>
          <span class="feat-tag">Absence Tracking</span>
          <span class="feat-tag">Reports</span>
        </div>
      </div>
      <div class="feature-card fade-up delay-2">
        <div class="feature-icon fi-purple">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h3>Role & Permission System</h3>
        <p>Create custom roles — Company Admin, HR, Employee — and precisely control what each user can view, edit, delete, or approve.</p>
        <div class="feature-tags">
          <span class="feat-tag">Custom Roles</span>
          <span class="feat-tag">Module Permissions</span>
          <span class="feat-tag">Feature-level Access</span>
        </div>
      </div>
      <div class="feature-card fade-up delay-3">
        <div class="feature-icon fi-red">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <h3>Analytics Dashboard</h3>
        <p>Real-time metrics on total customers, monthly revenue, active deals, satisfaction scores, and activity calendar — everything at a glance.</p>
        <div class="feature-tags">
          <span class="feat-tag">Sales Pipeline</span>
          <span class="feat-tag">Activity Calendar</span>
          <span class="feat-tag">Revenue Charts</span>
          <span class="feat-tag">Storage Monitor</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     HOW IT WORKS
============================================================ -->
<section class="how-it-works" id="how">
  <div class="container">
    <div class="text-center fade-up" style="margin-bottom:64px;">
      <div class="section-label"><span class="tag">Simple Process</span></div>
      <h2 class="section-title" style="color:#fff;">From Lead to Delivery in 4 Steps</h2>
      <p class="section-sub" style="margin:0 auto;">Your entire business cycle, structured and automated.</p>
    </div>
    <div class="steps">
      <div class="step fade-up delay-1">
        <div class="step-num">1</div>
        <h3>Capture Lead</h3>
        <p>Log new enquiries instantly via form or manual entry. Add contact info, project category, source, and estimated value.</p>
      </div>
      <div class="step fade-up delay-2">
        <div class="step-num">2</div>
        <h3>Survey & Quote</h3>
        <p>Schedule site visits, upload survey data, and generate professional quotations right inside the platform.</p>
      </div>
      <div class="step fade-up delay-3">
        <div class="step-num">3</div>
        <h3>Run the Project</h3>
        <p>Convert won leads to projects. Track drawing → production → dispatch on Kanban or Gantt. Assign staff and deadlines.</p>
      </div>
      <div class="step fade-up delay-4">
        <div class="step-num">4</div>
        <h3>Deliver & Close</h3>
        <p>Mark delivery complete, collect client summary sign-off, and archive everything for future reference.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     INDUSTRIES
============================================================ -->
<section class="industries" id="industries">
  <div class="container">
    <div class="text-center fade-up" style="margin-bottom:0;">
      <div class="section-label"><span class="tag tag-blue">Built for Your Industry</span></div>
      <h2 class="section-title">Purpose-Built for Field &amp; Technical Businesses</h2>
      <p class="section-sub">We4Work is designed around the real workflows of businesses that do drawing, site survey, production, and installation.</p>
    </div>
    <div class="industry-cards">
      <div class="industry-card fade-up delay-1">
        <div class="industry-card-img ind-eng">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/><polyline points="7 8 12 13 17 8"/></svg>
        </div>
        <div class="industry-card-body">
          <h3>Engineering & MEP</h3>
          <p>Track complex projects across drawing, BOM, production, and delivery phases. Manage multiple engineers and sub-teams.</p>
          <div class="industry-features">
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Drawing stage tracking</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Material procurement status</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Gantt timeline view</div>
          </div>
        </div>
      </div>
      <div class="industry-card fade-up delay-2">
        <div class="industry-card-img ind-cctv">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
        </div>
        <div class="industry-card-body">
          <h3>CCTV & Security</h3>
          <p>Manage client site surveys, camera layout drawings, installation teams, and warranty tracking in one place.</p>
          <div class="industry-features">
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Site survey module</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Installation project tracking</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Client document storage</div>
          </div>
        </div>
      </div>
      <div class="industry-card fade-up delay-3">
        <div class="industry-card-img ind-fire">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"><path d="M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 01-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 002.5 2.5z"/></svg>
        </div>
        <div class="industry-card-body">
          <h3>Fire Protection</h3>
          <p>Handle AMC contracts, compliance documentation, alarm system installations, and annual service schedules effortlessly.</p>
          <div class="industry-features">
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> AMC & service contracts</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Compliance document upload</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Team scheduling</div>
          </div>
        </div>
      </div>
      <div class="industry-card fade-up delay-1">
        <div class="industry-card-img ind-solar">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </div>
        <div class="industry-card-body">
          <h3>Solar Energy</h3>
          <p>From rooftop survey to panel installation and commissioning — track every milestone, government approvals, and subsidies.</p>
          <div class="industry-features">
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Survey to installation pipeline</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Quotation with subsidy calc</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Gantt commissioning tracker</div>
          </div>
        </div>
      </div>
      <div class="industry-card fade-up delay-2">
        <div class="industry-card-img ind-hvac">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
        </div>
        <div class="industry-card-body">
          <h3>HVAC & Plumbing</h3>
          <p>Manage duct design, equipment procurement, subcontractor coordination, and post-installation service tickets smoothly.</p>
          <div class="industry-features">
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Design to delivery stages</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Material order tracking</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Staff work allocation</div>
          </div>
        </div>
      </div>
      <div class="industry-card fade-up delay-3">
        <div class="industry-card-img ind-const">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"><polygon points="3 9 12 2 21 9 21 20 3 20 3 9"/><rect x="9" y="14" width="6" height="6"/></svg>
        </div>
        <div class="industry-card-body">
          <h3>Construction & Interior</h3>
          <p>Coordinate architects, contractors, and vendors. Track BOQ, site progress, punch lists, and handover in one dashboard.</p>
          <div class="industry-features">
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> BOQ & quotation builder</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Multi-team coordination</div>
            <div class="industry-feature"><div class="dot-check"><svg fill="none" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Progress photo uploads</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     MODULE SHOWCASE
============================================================ -->
<section class="showcase" id="showcase">
  <div class="container">
    <div class="text-center fade-up" style="margin-bottom:56px;">
      <div class="section-label"><span class="tag tag-accent">See It In Action</span></div>
      <h2 class="section-title">Powerful Views for Every Workflow</h2>
      <p class="section-sub">Switch between Gantt, Kanban, and list — the data stays the same, the view adapts to you.</p>
    </div>
    <div class="showcase-tabs fade-up">
      <button class="s-tab active" onclick="showTab('gantt')">📊 Gantt Chart</button>
      <button class="s-tab" onclick="showTab('kanban')">🗂 Lead Kanban</button>
      <button class="s-tab" onclick="showTab('project')">⚙️ Project Kanban</button>
      <button class="s-tab" onclick="showTab('detail')">📋 Lead Detail</button>
    </div>

    <!-- GANTT -->
    <div class="showcase-panel active" id="tab-gantt">
      <div class="showcase-text fade-up">
        <h2>Gantt Chart — See Your Entire Project Timeline</h2>
        <p>Visualize every project's start and end date on a shared timeline. Instantly spot overlaps, delays, and team bandwidth at a glance.</p>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Drag-and-drop timeline adjustment</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Color-coded by project status</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Linked to lead and quotation records</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>"Missing start date" alerts for incomplete projects</span></div>
        <a href="{{ route('login') }}" class="btn btn-primary" style="margin-top:20px;">Login</a>
      </div>
      <div class="showcase-screen fade-up delay-2">
        <div class="screen-bar">
          <div class="screen-dot" style="background:#ef4444;"></div>
          <div class="screen-dot" style="background:#f59e0b;"></div>
          <div class="screen-dot" style="background:#10b981;"></div>
          <span style="font-size:11px;color:rgba(255,255,255,.4);margin-left:8px;">Project Gantt View</span>
        </div>
        <div class="screen-body">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span style="font-size:11px;color:rgba(255,255,255,.5);">PROJECT</span>
            <div style="display:flex;gap:6px;">
              <span style="font-size:9px;color:rgba(255,255,255,.4);">Mar</span>
              <span style="font-size:9px;color:rgba(255,255,255,.4);">Apr</span>
              <span style="font-size:9px;color:rgba(255,255,255,.4);">May</span>
              <span style="font-size:9px;color:rgba(255,255,255,.4);">Jun</span>
              <span style="font-size:9px;color:rgba(255,255,255,.4);">Jul</span>
            </div>
          </div>
          <div class="gantt-row">
            <div class="gantt-label">Raj Solar Co.</div>
            <div class="gantt-track"><div class="gantt-bar" style="left:5%;width:55%;background:linear-gradient(90deg,#f59e0b,#ea580c);">25 Mar – 15 May</div></div>
          </div>
          <div class="gantt-row">
            <div class="gantt-label">TechFire Install</div>
            <div class="gantt-track"><div class="gantt-bar" style="left:30%;width:40%;background:linear-gradient(90deg,#10b981,#059669);">01 Apr – 13 May</div></div>
          </div>
          <div class="gantt-row">
            <div class="gantt-label">Nexus CCTV</div>
            <div class="gantt-track"><div class="gantt-bar" style="left:5%;width:65%;background:linear-gradient(90deg,#8b5cf6,#6d28d9);">25 Mar – 28 May</div></div>
          </div>
          <div class="gantt-row">
            <div class="gantt-label">BuildRight Eng.</div>
            <div class="gantt-track"><div class="gantt-bar" style="left:30%;width:55%;background:linear-gradient(90deg,#2563eb,#0ea5e9);">01 Apr – 30 Jun</div></div>
          </div>
          <div class="gantt-row">
            <div class="gantt-label">Sharma HVAC</div>
            <div class="gantt-track"><div class="gantt-bar" style="left:0%;width:45%;background:linear-gradient(90deg,#06b6d4,#2563eb);">25 Mar – 10 May</div></div>
          </div>
          <div class="gantt-row">
            <div class="gantt-label">Omega Infra</div>
            <div class="gantt-track"><div class="gantt-bar" style="left:30%;width:75%;background:linear-gradient(90deg,#ec4899,#8b5cf6);">01 Apr – 30 Jul</div></div>
          </div>
          <div class="gantt-row">
            <div class="gantt-label">Test Project</div>
            <div class="gantt-track"><div style="padding:4px 8px;font-size:9px;color:rgba(255,255,255,.3);font-style:italic;">Missing start date or deadline.</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- LEAD KANBAN -->
    <div class="showcase-panel" id="tab-kanban">
      <div class="showcase-text fade-up">
        <h2>Lead Kanban — Pipeline at a Glance</h2>
        <p>Visualize every lead's journey from first contact to closed deal. Drag cards across stages or click in to see full details.</p>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>5-stage pipeline: Fresh Lead → Won</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Priority badges (High / Medium / Low)</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Assigned-to avatars on each card</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Total, Won, and Projects counters</span></div>
        <a href="{{ route('login') }}" class="btn btn-primary" style="margin-top:20px;">Login</a>
      </div>
      <div class="showcase-screen fade-up delay-2">
        <div class="screen-bar">
          <div class="screen-dot" style="background:#ef4444;"></div>
          <div class="screen-dot" style="background:#f59e0b;"></div>
          <div class="screen-dot" style="background:#10b981;"></div>
          <span style="font-size:11px;color:rgba(255,255,255,.4);margin-left:8px;">Lead Management — Kanban</span>
        </div>
        <div class="screen-body">
          <div class="kanban-mini">
            <div class="km-col">
              <div class="km-col-hdr">🔵 Fresh Lead <span style="color:rgba(255,255,255,.6);">2</span></div>
              <div class="km-card" style="border-color:#3b82f6;"><div class="km-card-name">Dipak</div><div class="km-card-sub">New Construction</div><div style="color:#60a5fa;font-size:10px;font-weight:700;margin-top:4px;">₹60,000</div></div>
              <div class="km-card" style="border-color:#3b82f6;"><div class="km-card-name">Niranjan</div><div class="km-card-sub">New Construction</div><div style="color:#60a5fa;font-size:10px;font-weight:700;margin-top:4px;">₹50,000</div></div>
            </div>
            <div class="km-col">
              <div class="km-col-hdr">🟡 Site Survey <span style="color:rgba(255,255,255,.6);">2</span></div>
              <div class="km-card" style="border-color:#f59e0b;"><div class="km-card-name">Kasimir Vance</div><div class="km-card-sub">New Construction</div><div style="color:#60a5fa;font-size:10px;margin-top:4px;">₹8.00</div></div>
              <div class="km-card" style="border-color:#f59e0b;"><div class="km-card-name">Jignesh</div><div class="km-card-sub">New Construction</div><div style="color:#60a5fa;font-size:10px;margin-top:4px;">₹50,000</div><div><span class="km-tag" style="background:rgba(239,68,68,.2);color:#fca5a5;">High</span></div></div>
            </div>
            <div class="km-col">
              <div class="km-col-hdr">🟣 Quote <span style="color:rgba(255,255,255,.6);">0</span></div>
              <div style="font-size:10px;color:rgba(255,255,255,.2);padding:8px 0;font-style:italic;">No leads here</div>
            </div>
            <div class="km-col">
              <div class="km-col-hdr">🟢 Won <span style="color:rgba(255,255,255,.6);">16</span></div>
              <div class="km-card" style="border-color:#10b981;"><div class="km-card-name">Nitesh</div><div class="km-card-sub">New Construction</div><div style="color:#60a5fa;font-size:10px;margin-top:4px;">₹2,000</div></div>
              <div class="km-card" style="border-color:#10b981;"><div class="km-card-name">Ram Pal</div><div class="km-card-sub">New Construction</div><div style="color:#60a5fa;font-size:10px;margin-top:4px;">₹600</div><div><span class="km-tag" style="background:rgba(239,68,68,.2);color:#fca5a5;">High</span></div></div>
              <div class="km-card" style="border-color:#10b981;"><div class="km-card-name">Raj Pal</div><div class="km-card-sub">New Construction</div><div style="color:#60a5fa;font-size:10px;margin-top:4px;">₹6,00,000</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PROJECT KANBAN -->
    <div class="showcase-panel" id="tab-project">
      <div class="showcase-text fade-up">
        <h2>Project Kanban — Track Every Stage</h2>
        <p>From Drawing to Delivery — your project board gives every team member clarity on what's happening right now.</p>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>5 stages: Drawing → Material → Production → Ready → Delivery</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>% completion and deadline on each card</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>On Track / Delayed status badges</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Linked to lead, client, and project ID</span></div>
        <a href="{{ route('login') }}" class="btn btn-primary" style="margin-top:20px;">Login</a>
      </div>
      <div class="showcase-screen fade-up delay-2">
        <div class="screen-bar">
          <div class="screen-dot" style="background:#ef4444;"></div>
          <div class="screen-dot" style="background:#f59e0b;"></div>
          <div class="screen-dot" style="background:#10b981;"></div>
          <span style="font-size:11px;color:rgba(255,255,255,.4);margin-left:8px;">Project Management — Kanban</span>
        </div>
        <div class="screen-body">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
            <div class="km-col">
              <div class="km-col-hdr">🔵 Drawing <span style="color:rgba(255,255,255,.6);">5</span></div>
              <div class="km-card" style="border-color:#3b82f6;"><div class="km-card-name">Blossom George</div><div class="km-card-sub">PRJ-2026-0016</div><div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;">10% • Due 11 Apr</div><div><span class="km-tag" style="background:rgba(16,185,129,.15);color:#34d399;">On Track</span></div></div>
              <div class="km-card" style="border-color:#3b82f6;"><div class="km-card-name">Britanney Mason</div><div class="km-card-sub">PRJ-2026-0003</div><div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;">10% • Due 28 May</div><div><span class="km-tag" style="background:rgba(16,185,129,.15);color:#34d399;">On Track</span></div></div>
            </div>
            <div class="km-col">
              <div class="km-col-hdr">🟠 Material <span style="color:rgba(255,255,255,.6);">1</span></div>
              <div class="km-card" style="border-color:#f59e0b;"><div class="km-card-name">Test User</div><div class="km-card-sub">PRJ-2026-0002</div><div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;">25% • Due 01 May</div><div><span class="km-tag" style="background:rgba(16,185,129,.15);color:#34d399;">On Track</span></div></div>
            </div>
            <div class="km-col">
              <div class="km-col-hdr">🟢 Delivery <span style="color:rgba(255,255,255,.6);">2</span></div>
              <div class="km-card" style="border-color:#10b981;"><div class="km-card-name">Orlando Dickson</div><div class="km-card-sub">PRJ-2026-0013</div><div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;">80% complete</div></div>
              <div class="km-card" style="border-color:#10b981;"><div class="km-card-name">Giacomo Jimenez</div><div class="km-card-sub">PRJ-2026-0005</div><div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px;">80% complete</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- LEAD DETAIL -->
    <div class="showcase-panel" id="tab-detail">
      <div class="showcase-text fade-up">
        <h2>Lead Detail — Everything About a Client</h2>
        <p>Click any lead to see full contact info, lead details, stage history, uploaded documents, quotations, surveys, and activity log.</p>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Tabs: Overview, Survey, Quotation, Summary, Documents</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Activity log with timestamps</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Stage badge and priority display</span></div>
        <div class="check-item"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>Comments and internal notes</span></div>
        <a href="{{ route('login') }}" class="btn btn-primary" style="margin-top:20px;">Login</a>
      </div>
      <div class="showcase-screen fade-up delay-2">
        <div class="screen-bar">
          <div class="screen-dot" style="background:#ef4444;"></div>
          <div class="screen-dot" style="background:#f59e0b;"></div>
          <div class="screen-dot" style="background:#10b981;"></div>
          <span style="font-size:11px;color:rgba(255,255,255,.4);margin-left:8px;">Lead Details: Dipak</span>
          <div style="margin-left:auto;display:flex;gap:6px;">
            <span style="font-size:9px;background:rgba(59,130,246,.2);color:#93c5fd;padding:2px 8px;border-radius:100px;font-weight:700;">Fresh Lead</span>
            <span style="font-size:9px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.6);padding:2px 8px;border-radius:100px;font-weight:700;">Stage 1</span>
          </div>
        </div>
        <div class="screen-body">
          <div style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid rgba(255,255,255,.06);padding-bottom:10px;">
            <span style="font-size:10px;font-weight:700;color:#60a5fa;border-bottom:2px solid #3b82f6;padding-bottom:8px;">Overview</span>
            <span style="font-size:10px;color:rgba(255,255,255,.4);">Survey</span>
            <span style="font-size:10px;color:rgba(255,255,255,.4);">Quotation</span>
            <span style="font-size:10px;color:rgba(255,255,255,.4);">Summary</span>
            <span style="font-size:10px;color:rgba(255,255,255,.4);">Documents</span>
            <span style="font-size:10px;color:rgba(255,255,255,.4);">Activity</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
              <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.7);margin-bottom:10px;">Contact Information</div>
              <div style="font-size:10px;color:rgba(255,255,255,.4);margin-bottom:5px;"><span style="color:rgba(255,255,255,.6);font-weight:600;">Email:</span> <a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="dbbfb2abbab09ba2b4abb6bab2b7f5b8b4b6">[email&#160;protected]</a></div>
              <div style="font-size:10px;color:rgba(255,255,255,.4);margin-bottom:5px;"><span style="color:rgba(255,255,255,.6);font-weight:600;">Mobile:</span> 9689566334</div>
              <div style="font-size:10px;color:rgba(255,255,255,.4);"><span style="color:rgba(255,255,255,.6);font-weight:600;">Address:</span> N/A</div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.7);margin-bottom:10px;">Lead Details</div>
              <div style="font-size:10px;color:rgba(255,255,255,.4);margin-bottom:5px;"><span style="color:rgba(255,255,255,.6);font-weight:600;">Category:</span> New Construction</div>
              <div style="font-size:10px;color:rgba(255,255,255,.4);margin-bottom:5px;"><span style="color:rgba(255,255,255,.6);font-weight:600;">Value:</span> ₹60,000</div>
              <div style="font-size:10px;color:rgba(255,255,255,.4);margin-bottom:5px;"><span style="color:rgba(255,255,255,.6);font-weight:600;">Priority:</span> <span style="color:#34d399;">Low</span></div>
              <div style="font-size:10px;color:rgba(255,255,255,.4);"><span style="color:rgba(255,255,255,.6);font-weight:600;">Follow Up:</span> Apr 06, 2026</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     STATS STRIP
============================================================ -->
<section class="stats-strip">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item fade-up">
        <div class="stat-num"><span>1,200</span>+</div>
        <div class="stat-label">Businesses Using We4Work</div>
      </div>
      <div class="stat-item fade-up delay-1">
        <div class="stat-num"><span>50K</span>+</div>
        <div class="stat-label">Projects Tracked to Date</div>
      </div>
      <div class="stat-item fade-up delay-2">
        <div class="stat-num"><span>₹200</span>Cr+</div>
        <div class="stat-label">Revenue Managed by Clients</div>
      </div>
      <div class="stat-item fade-up delay-3">
        <div class="stat-num"><span>98</span>%</div>
        <div class="stat-label">Customer Satisfaction Score</div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     PRICING
============================================================ -->

<!-- ============================================================
     TESTIMONIALS
============================================================ -->
<section class="testimonials" id="testimonials">
  <div class="container">
    <div class="text-center fade-up">
      <div class="section-label"><span class="tag tag-blue">Customer Stories</span></div>
      <h2 class="section-title">Loved by Field Business Owners</h2>
      <p class="section-sub">Real feedback from engineers, CCTV installers, solar companies, and fire protection teams using We4Work daily.</p>
    </div>
  </div>
  <div class="testimonials-track-wrap" style="margin-top:56px;">
    <div class="testimonials-track">
      <!-- Set 1 -->
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"The Gantt chart alone saved us from 3 project overlaps last month. We can finally see all 13 projects on one screen."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#2563eb,#06b6d4);">RK</div>
          <div>
            <div class="t-name">Rajesh Kumar</div>
            <div class="t-role">Owner, RK Engineering Pvt. Ltd.</div>
            <span class="t-industry">Engineering</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"Before We4Work, we were tracking leads in Excel and WhatsApp. Now my entire sales pipeline is visual and my team is accountable."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#ef4444,#dc2626);">PS</div>
          <div>
            <div class="t-name">Priya Sharma</div>
            <div class="t-role">Director, SecureEye CCTV Solutions</div>
            <span class="t-industry">CCTV & Security</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"We've converted 11 out of 13 leads this quarter — the site survey to quote flow is seamless. Our close rate went from 40% to 85%."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#f59e0b,#ea580c);">AM</div>
          <div>
            <div class="t-name">Arjun Mehta</div>
            <div class="t-role">CEO, SunRise Solar Systems</div>
            <span class="t-industry">Solar Energy</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"Staff attendance used to be a nightmare. Bulk attendance in one click, and I get absent alerts every morning. Simple and powerful."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">MP</div>
          <div>
            <div class="t-name">Mahesh Patil</div>
            <div class="t-role">Operations Head, FireGuard Systems</div>
            <span class="t-industry">Fire Protection</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"The role permission system is excellent. My sales team only sees what they need. Accounts see quotations. Nothing leaks. Perfect control."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#059669,#064e3b);">ST</div>
          <div>
            <div class="t-name">Suresh Tiwari</div>
            <div class="t-role">Managing Director, Tiwari HVAC Ltd.</div>
            <span class="t-industry">HVAC Systems</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"We handle 30+ active construction projects. The kanban board gives every site manager clarity on their stage without any meetings."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#0891b2,#0e7490);">VN</div>
          <div>
            <div class="t-name">Vinay Naik</div>
            <div class="t-role">Partner, Naik Construction Group</div>
            <span class="t-industry">Construction</span>
          </div>
        </div>
      </div>
      <!-- Duplicate for loop -->
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"The Gantt chart alone saved us from 3 project overlaps last month. We can finally see all 13 projects on one screen."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#2563eb,#06b6d4);">RK</div>
          <div>
            <div class="t-name">Rajesh Kumar</div>
            <div class="t-role">Owner, RK Engineering Pvt. Ltd.</div>
            <span class="t-industry">Engineering</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"Before We4Work, we were tracking leads in Excel and WhatsApp. Now my entire sales pipeline is visual and my team is accountable."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#ef4444,#dc2626);">PS</div>
          <div>
            <div class="t-name">Priya Sharma</div>
            <div class="t-role">Director, SecureEye CCTV Solutions</div>
            <span class="t-industry">CCTV & Security</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"We've converted 11 out of 13 leads this quarter — the site survey to quote flow is seamless. Our close rate went from 40% to 85%."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#f59e0b,#ea580c);">AM</div>
          <div>
            <div class="t-name">Arjun Mehta</div>
            <div class="t-role">CEO, SunRise Solar Systems</div>
            <span class="t-industry">Solar Energy</span>
          </div>
        </div>
      </div>
      <div class="t-card">
        <div class="t-stars">★★★★★</div>
        <p class="t-quote">"Staff attendance used to be a nightmare. Bulk attendance in one click, and I get absent alerts every morning. Simple and powerful."</p>
        <div class="t-author">
          <div class="t-avatar" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">MP</div>
          <div>
            <div class="t-name">Mahesh Patil</div>
            <div class="t-role">Operations Head, FireGuard Systems</div>
            <span class="t-industry">Fire Protection</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     CTA BANNER
============================================================ -->
<section class="cta-banner">
  <div class="container">
    <div class="cta-inner fade-up">
      <div class="section-label" style="margin-bottom:16px;"><span class="tag" style="background:rgba(37,99,235,.25);color:#93c5fd;">Login Today</span></div>
      <h2>Ready to Streamline Your Business?</h2>
      <p>Join 1,200+ field businesses who closed more deals and delivered more projects using We4Work.</p>
      <div class="cta-actions">
        <a href="{{ route('register.company') }}" class="btn btn-primary btn-lg">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          Start Your Free 14-Day Trial
        </a>
        <a href="#contact" class="btn btn-outline btn-lg">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.73a16 16 0 0 0 6.29 6.29l1.62-.81a2 2 0 0 1 2.11.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Book a Live Demo
        </a>
      </div>
      <div class="cta-trust">
        <div class="cta-trust-item"><svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> No credit card required</div>
        <div class="cta-trust-item"><svg fill="none" viewBox="0 0 24 24" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Cancel anytime</div>
        <div class="cta-trust-item"><svg fill="none" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Setup in under 10 minutes</div>
        <div class="cta-trust-item"><svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Dedicated onboarding support</div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     CONTACT
============================================================ -->
<section class="contact" id="contact">
  <div class="container">
    <div class="text-center fade-up">
      <div class="section-label"><span class="tag tag-blue">Contact Us</span></div>
      <h2 class="section-title">Get in Touch</h2>
      <p class="section-sub">Have questions? Want a live demo? Our team is ready to help you get started.</p>
    </div>
    <div class="contact-grid">
      <div class="contact-info fade-up delay-1">
        <h3>We're Here to Help</h3>
        <p>Whether you're a 3-person team or a 300-person enterprise, we'll make sure We4Work is set up perfectly for your business.</p>
        <div class="contact-item">
          <div class="contact-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.73a16 16 0 0 0 6.29 6.29l1.62-.81a2 2 0 0 1 2.11.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          <div>
            <div class="contact-label">Phone</div>
            <div class="contact-value">+91 6351366116</div>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <div>
            <div class="contact-label">Email</div>
            <div class="contact-value"><a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="5a323f3636351a2d3528313c36352d2a2835743334">info@sourcecubeindia.com</a></div>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          <div>
            <div class="contact-label">Office</div>
            <div class="contact-value">323 Atlanta shopping center,althan bhimrad road,althan vesu surat</div>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div>
            <div class="contact-label">Support Hours</div>
            <div class="contact-value">Mon–Sat: 9:00 AM – 7:00 PM IST</div>
          </div>
        </div>
      </div>

      <div class="contact-form fade-up delay-2">
        <h3 style="font-size:20px;font-weight:700;margin-bottom:24px;">Send Us a Message</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Full Name *</label>
            <input type="text" placeholder="Rajesh Kumar" />
          </div>
          <div class="form-group">
            <label>Company Name *</label>
            <input type="text" placeholder="RK Engineering Pvt. Ltd." />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email Address *</label>
            <input type="email" placeholder="rajesh@rkengineering.com" />
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" placeholder="+91 98765 43210" />
          </div>
        </div>
        <div class="form-group">
          <label>Your Industry</label>
          <select>
            <option value="">Select your industry...</option>
            <option>Engineering / MEP</option>
            <option>CCTV & Security</option>
            <option>Solar Energy</option>
            <option>Fire Protection</option>
            <option>HVAC & Plumbing</option>
            <option>Construction & Interior</option>
            <option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>How can we help?</label>
          <textarea placeholder="Tell us about your team size, current challenges, and what you're looking for..."></textarea>
        </div>
        <button type="submit" class="form-submit">Send Message →</button>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     FOOTER
============================================================ -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="nav-brand">
          <div class="nav-logo">
                    <svg width="246" height="69" viewBox="0 0 246 69" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g filter="url(#filter0_d_36_40)">
        <path d="M14.2939 22.5254C19.4268 22.5254 23.5879 18.3468 23.5879 13.1921C23.5879 8.03744 19.4268 3.85876 14.2939 3.85876C9.16104 3.85876 5 8.03744 5 13.1921C5 18.3468 9.16104 22.5254 14.2939 22.5254Z" fill="#253E5F" stroke="#80AADD" stroke-width="2"/>
        <path d="M72.1229 22.5254C77.2558 22.5254 81.4168 18.3468 81.4168 13.1921C81.4168 8.03744 77.2558 3.85876 72.1229 3.85876C66.99 3.85876 62.829 8.03744 62.829 13.1921C62.829 18.3468 66.99 22.5254 72.1229 22.5254Z" fill="#1B4614" stroke="#7AB948" stroke-width="2"/>
        <path d="M14.2939 59.8587C19.4268 59.8587 23.5879 55.6801 23.5879 50.5254C23.5879 45.3708 19.4268 41.1921 14.2939 41.1921C9.16104 41.1921 5 45.3708 5 50.5254C5 55.6801 9.16104 59.8587 14.2939 59.8587Z" fill="#483A0F" stroke="#D1A041" stroke-width="2"/>
        <path d="M72.1229 59.8587C77.2558 59.8587 81.4168 55.6801 81.4168 50.5254C81.4168 45.3708 77.2558 41.1921 72.1229 41.1921C66.99 41.1921 62.829 45.3708 62.829 50.5254C62.829 55.6801 66.99 59.8587 72.1229 59.8587Z" fill="#80AADD" stroke="#80AADD" stroke-width="2"/>
        <path d="M43.2084 44.3032C50.0522 44.3032 55.6003 38.7316 55.6003 31.8587C55.6003 24.9859 50.0522 19.4143 43.2084 19.4143C36.3645 19.4143 30.8165 24.9859 30.8165 31.8587C30.8165 38.7316 36.3645 44.3032 43.2084 44.3032Z" fill="#80AADD"/>
        <path d="M43.2084 37.0439C46.06 37.0439 48.3717 34.7224 48.3717 31.8587C48.3717 28.995 46.06 26.6735 43.2084 26.6735C40.3568 26.6735 38.0451 28.995 38.0451 31.8587C38.0451 34.7224 40.3568 37.0439 43.2084 37.0439Z" fill="#30302E"/>
        <path d="M40.9964 33.3125V32.6563L43.6889 28.0761H44.1316V29.0925H43.8325L41.7982 32.5534V32.6049H45.424V33.3125H40.9964ZM43.8803 34.6634V33.1131V32.8075V28.0761H44.5864V34.6634H43.8803Z" fill="#80AADD"/>
        <path d="M227.711 28.5909L227.663 24.8341H228.238L236.279 16.0339H239.774L231.205 25.3487H230.966L227.711 28.5909ZM225.079 35.7958V9.44659H227.903V35.7958H225.079ZM236.758 35.7958L229.578 26.0178L231.588 23.9078L240.348 35.7958H236.758Z" fill="white"/>
        <path d="M212.541 35.7958V16.0339H215.269V19.0187H215.46C215.796 18.0409 216.402 17.2476 217.279 16.6386C218.157 16.0296 219.146 15.7251 220.247 15.7251C220.455 15.7251 220.714 15.7294 221.025 15.738C221.336 15.7465 221.571 15.7594 221.731 15.7766V18.8644C221.635 18.8386 221.416 18.8 221.073 18.7486C220.738 18.6885 220.383 18.6585 220.008 18.6585C219.114 18.6585 218.316 18.8601 217.614 19.2632C216.92 19.6577 216.37 20.2067 215.963 20.91C215.564 21.6048 215.365 22.3982 215.365 23.2902V35.7958H212.541Z" fill="white"/>
        <path d="M199.901 36.2075C198.242 36.2075 196.786 35.783 195.533 34.9338C194.289 34.0847 193.315 32.8967 192.613 31.37C191.919 29.8432 191.572 28.0592 191.572 26.0178C191.572 23.9593 191.919 22.1623 192.613 20.627C193.315 19.0917 194.289 17.8995 195.533 17.0503C196.786 16.2012 198.242 15.7766 199.901 15.7766C201.56 15.7766 203.012 16.2012 204.257 17.0503C205.509 17.8995 206.483 19.0917 207.177 20.627C207.879 22.1623 208.23 23.9593 208.23 26.0178C208.23 28.0592 207.879 29.8432 207.177 31.37C206.483 32.8967 205.509 34.0847 204.257 34.9338C203.012 35.783 201.56 36.2075 199.901 36.2075ZM199.901 33.48C201.161 33.48 202.199 33.1326 203.012 32.4378C203.826 31.7431 204.428 30.8296 204.819 29.6974C205.21 28.5652 205.406 27.3387 205.406 26.0178C205.406 24.6969 205.21 23.4661 204.819 22.3253C204.428 21.1845 203.826 20.2625 203.012 19.5592C202.199 18.8558 201.161 18.5042 199.901 18.5042C198.64 18.5042 197.603 18.8558 196.79 19.5592C195.976 20.2625 195.374 21.1845 194.983 22.3253C194.592 23.4661 194.396 24.6969 194.396 26.0178C194.396 27.3387 194.592 28.5652 194.983 29.6974C195.374 30.8296 195.976 31.7431 196.79 32.4378C197.603 33.1326 198.64 33.48 199.901 33.48Z" fill="white"/>
        <path d="M169.844 35.7958L164.244 16.0339H167.211L171.184 31.1641H171.376L175.301 16.0339H178.316L182.193 31.1127H182.385L186.358 16.0339H189.325L183.725 35.7958H180.949L176.928 20.6142H176.641L172.62 35.7958H169.844Z" fill="white"/>
        <path d="M143.437 30.3921V27.7675L154.207 9.44659H155.978V13.5122H154.781L146.644 27.3558V27.5617H161.147V30.3921H143.437ZM154.972 35.7958V29.5945V28.3722V9.44659H157.797V35.7958H154.972Z" fill="white"/>
        <path d="M132.152 36.2075C130.381 36.2075 128.853 35.7872 127.569 34.9467C126.293 34.0975 125.307 32.9139 124.613 31.3957C123.927 29.869 123.584 28.0935 123.584 26.0693C123.584 24.045 123.927 22.261 124.613 20.7171C125.307 19.1646 126.273 17.9552 127.509 17.0889C128.754 16.214 130.206 15.7766 131.865 15.7766C132.822 15.7766 133.768 15.9482 134.701 16.2912C135.635 16.6343 136.484 17.1919 137.25 17.9638C138.016 18.7272 138.626 19.7393 139.081 21.0001C139.536 22.261 139.763 23.8135 139.763 25.6576V26.9441H125.595V24.3195H136.891C136.891 23.2045 136.684 22.2095 136.269 21.3346C135.862 20.4598 135.279 19.7693 134.522 19.2632C133.772 18.7572 132.886 18.5042 131.865 18.5042C130.74 18.5042 129.767 18.8044 128.945 19.4048C128.131 19.9966 127.505 20.7685 127.066 21.7206C126.628 22.6727 126.408 23.6934 126.408 24.7827V26.5324C126.408 28.0249 126.648 29.29 127.126 30.3278C127.613 31.3571 128.287 32.1419 129.149 32.6823C130.01 33.2141 131.011 33.48 132.152 33.48C132.894 33.48 133.564 33.3685 134.163 33.1455C134.769 32.9139 135.291 32.5708 135.73 32.1162C136.169 31.653 136.508 31.0784 136.747 30.3922L139.476 31.2156C139.189 32.2105 138.706 33.0854 138.028 33.8402C137.35 34.5864 136.512 35.1697 135.515 35.59C134.518 36.0017 133.397 36.2075 132.152 36.2075Z" fill="white"/>
        <path d="M101.856 35.7958L96.2555 16.0339H99.2232L103.196 31.1641H103.388L107.313 16.0339H110.328L114.205 31.1127H114.397L118.37 16.0339H121.337L115.737 35.7958H112.961L108.94 20.6142H108.653L104.632 35.7958H101.856Z" fill="white"/>
        <path d="M180.143 50.1353C180.143 50.9845 180.001 51.7183 179.715 52.3368C179.43 52.9553 179.039 53.4323 178.542 53.7678C178.045 54.1033 177.477 54.271 176.838 54.271C176.199 54.271 175.631 54.1033 175.134 53.7678C174.637 53.4323 174.245 52.9553 173.96 52.3368C173.675 51.7183 173.532 50.9845 173.532 50.1353C173.532 49.2862 173.675 48.5524 173.96 47.9339C174.245 47.3153 174.637 46.8384 175.134 46.5029C175.631 46.1674 176.199 45.9997 176.838 45.9997C177.477 45.9997 178.045 46.1674 178.542 46.5029C179.039 46.8384 179.43 47.3153 179.715 47.9339C180.001 48.5524 180.143 49.2862 180.143 50.1353ZM179.266 50.1353C179.266 49.4382 179.157 48.8498 178.94 48.3702C178.726 47.8906 178.435 47.5276 178.066 47.2813C177.701 47.0349 177.291 46.9117 176.838 46.9117C176.384 46.9117 175.974 47.0349 175.606 47.2813C175.24 47.5276 174.949 47.8906 174.732 48.3702C174.517 48.8498 174.41 49.4382 174.41 50.1353C174.41 50.8325 174.517 51.4208 174.732 51.9005C174.949 52.3801 175.24 52.7431 175.606 52.9894C175.974 53.2358 176.384 53.3589 176.838 53.3589C177.291 53.3589 177.701 53.2358 178.066 52.9894C178.435 52.7431 178.726 52.3801 178.94 51.9005C179.157 51.4208 179.266 50.8325 179.266 50.1353Z" fill="#C2C0B6"/>
        <path d="M167.239 54.1609V46.1098H169.769C170.354 46.1098 170.834 46.2173 171.21 46.4322C171.585 46.6444 171.863 46.9367 172.043 47.3088C172.224 47.681 172.314 48.1042 172.314 48.5786C172.314 49.053 172.224 49.4736 172.043 49.8405C171.863 50.2074 171.586 50.4957 171.213 50.7054C170.84 50.9124 170.364 51.016 169.784 51.016H167.736V50.1354H169.754C170.154 50.1354 170.476 50.0725 170.72 49.9467C170.966 49.8209 171.144 49.6427 171.254 49.412C171.366 49.1788 171.422 48.901 171.422 48.5786C171.422 48.2562 171.366 47.9745 171.254 47.7334C171.141 47.4923 170.962 47.3062 170.716 47.1752C170.47 47.0415 170.144 46.9747 169.74 46.9747H168.146V54.1609H167.239ZM170.764 50.5442L172.606 54.1609H171.553L169.74 50.5442H170.764Z" fill="#C2C0B6"/>
        <path d="M160.703 54.1609V46.1098H163.233C163.821 46.1098 164.301 46.2238 164.674 46.4518C165.049 46.6772 165.327 46.9825 165.507 47.3678C165.688 47.7531 165.778 48.1829 165.778 48.6572C165.778 49.1316 165.688 49.5627 165.507 49.9506C165.329 50.3385 165.054 50.6477 164.681 50.8784C164.308 51.1064 163.83 51.2204 163.248 51.2204H161.434V50.3555H163.218C163.621 50.3555 163.944 50.2808 164.187 50.1314C164.431 49.9821 164.608 49.7803 164.718 49.526C164.83 49.2692 164.886 48.9796 164.886 48.6572C164.886 48.3349 164.83 48.0466 164.718 47.7924C164.608 47.5381 164.43 47.339 164.184 47.1948C163.938 47.0481 163.611 46.9747 163.204 46.9747H161.61V54.1609H160.703Z" fill="#C2C0B6"/>
        <path d="M149.393 54.1609L147.346 46.1098H148.267L149.832 52.6671H149.905L151.5 46.1098H152.523L154.118 52.6671H154.191L155.756 46.1098H156.677L154.63 54.1609H153.693L152.041 47.7452H151.982L150.33 54.1609H149.393Z" fill="#C2C0B6"/>
        <path d="M146.509 50.1353C146.509 50.9845 146.366 51.7183 146.081 52.3368C145.796 52.9553 145.404 53.4323 144.907 53.7678C144.41 54.1033 143.842 54.271 143.203 54.271C142.564 54.271 141.996 54.1033 141.499 53.7678C141.002 53.4323 140.611 52.9553 140.325 52.3368C140.04 51.7183 139.898 50.9845 139.898 50.1353C139.898 49.2862 140.04 48.5524 140.325 47.9339C140.611 47.3153 141.002 46.8384 141.499 46.5029C141.996 46.1674 142.564 45.9997 143.203 45.9997C143.842 45.9997 144.41 46.1674 144.907 46.5029C145.404 46.8384 145.796 47.3153 146.081 47.9339C146.366 48.5524 146.509 49.2862 146.509 50.1353ZM145.631 50.1353C145.631 49.4382 145.523 48.8498 145.306 48.3702C145.091 47.8906 144.8 47.5276 144.432 47.2813C144.066 47.0349 143.657 46.9117 143.203 46.9117C142.75 46.9117 142.339 47.0349 141.971 47.2813C141.605 47.5276 141.314 47.8906 141.097 48.3702C140.882 48.8498 140.775 49.4382 140.775 50.1353C140.775 50.8325 140.882 51.4208 141.097 51.9005C141.314 52.3801 141.605 52.7431 141.971 52.9894C142.339 53.2358 142.75 53.3589 143.203 53.3589C143.657 53.3589 144.066 53.2358 144.432 52.9894C144.8 52.7431 145.091 52.3801 145.306 51.9005C145.523 51.4208 145.631 50.8325 145.631 50.1353Z" fill="#C2C0B6"/>
        <path d="M134.69 54.1609V46.1098H135.597V53.2961H139.078V54.1609H134.69Z" fill="#C2C0B6"/>
        <path d="M128.647 54.1609V46.1098H133.137V46.9747H129.554V49.6951H132.8V50.5599H129.554V54.1609H128.647Z" fill="#C2C0B6"/>
        <path d="M121.93 54.1609V46.1098H122.837V50.1039H122.924L126.288 46.1098H127.473L124.329 49.7423L127.473 54.1609H126.376L123.773 50.4184L122.837 51.5506V54.1609H121.93Z" fill="#C2C0B6"/>
        <path d="M115.344 54.1609V46.1098H117.874C118.459 46.1098 118.939 46.2173 119.315 46.4322C119.69 46.6444 119.968 46.9367 120.148 47.3088C120.329 47.681 120.419 48.1042 120.419 48.5786C120.419 49.053 120.329 49.4736 120.148 49.8405C119.968 50.2074 119.691 50.4957 119.318 50.7054C118.945 50.9124 118.469 51.016 117.889 51.016H115.841V50.1354H117.859C118.259 50.1354 118.581 50.0725 118.825 49.9467C119.071 49.8209 119.249 49.6427 119.358 49.412C119.471 49.1788 119.527 48.901 119.527 48.5786C119.527 48.2562 119.471 47.9745 119.358 47.7334C119.246 47.4923 119.067 47.3062 118.821 47.1752C118.575 47.0415 118.249 46.9747 117.845 46.9747H116.25V54.1609H115.344ZM118.869 50.5442L120.711 54.1609H119.658L117.845 50.5442H118.869Z" fill="#C2C0B6"/>
        <path d="M113.819 50.1353C113.819 50.9845 113.676 51.7183 113.391 52.3368C113.106 52.9553 112.715 53.4323 112.217 53.7678C111.72 54.1033 111.152 54.271 110.514 54.271C109.875 54.271 109.307 54.1033 108.81 53.7678C108.312 53.4323 107.921 52.9553 107.636 52.3368C107.351 51.7183 107.208 50.9845 107.208 50.1353C107.208 49.2862 107.351 48.5524 107.636 47.9339C107.921 47.3153 108.312 46.8384 108.81 46.5029C109.307 46.1674 109.875 45.9997 110.514 45.9997C111.152 45.9997 111.72 46.1674 112.217 46.5029C112.715 46.8384 113.106 47.3153 113.391 47.9339C113.676 48.5524 113.819 49.2862 113.819 50.1353ZM112.941 50.1353C112.941 49.4382 112.833 48.8498 112.616 48.3702C112.401 47.8906 112.11 47.5276 111.742 47.2813C111.376 47.0349 110.967 46.9117 110.514 46.9117C110.06 46.9117 109.649 47.0349 109.281 47.2813C108.916 47.5276 108.624 47.8906 108.407 48.3702C108.193 48.8498 108.086 49.4382 108.086 50.1353C108.086 50.8325 108.193 51.4208 108.407 51.9005C108.624 52.3801 108.916 52.7431 109.281 52.9894C109.649 53.2358 110.06 53.3589 110.514 53.3589C110.967 53.3589 111.376 53.2358 111.742 52.9894C112.11 52.7431 112.401 52.3801 112.616 51.9005C112.833 51.4208 112.941 50.8325 112.941 50.1353Z" fill="#C2C0B6"/>
        <path d="M99.4391 54.1609L97.3914 46.1098H98.3129L99.8778 52.6671H99.951L101.545 46.1098H102.569L104.163 52.6671H104.236L105.801 46.1098H106.723L104.675 54.1609H103.739L102.086 47.7452H102.028L100.375 54.1609H99.4391Z" fill="#C2C0B6"/>
        </g>
        <defs>
        <filter id="filter0_d_36_40" x="0" y="2.85876" width="244.348" height="66" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
        <feFlood flood-opacity="0" result="BackgroundImageFix"/>
        <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
        <feOffset dy="4"/>
        <feGaussianBlur stdDeviation="2"/>
        <feComposite in2="hardAlpha" operator="out"/>
        <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0"/>
        <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_36_40"/>
        <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_36_40" result="shape"/>
        </filter>
        </defs>
        </svg>

          </div>
        </div>
        <p>The all-in-one business management platform for engineering, CCTV, solar, fire protection, and field service companies across India.</p>
        <div class="footer-socials">
          <a href="#" class="social-btn" aria-label="Twitter">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53A4.48 4.48 0 0 0 22.43 1a9 9 0 0 1-2.88 1.1A4.52 4.52 0 0 0 16.11 0c-2.5 0-4.52 2.02-4.52 4.52 0 .35.04.7.11 1.03C7.69 5.37 4.07 3.58 1.64.9a4.52 4.52 0 0 0-.61 2.27c0 1.57.8 2.95 2.01 3.76a4.49 4.49 0 0 1-2.05-.57v.06c0 2.19 1.56 4.02 3.63 4.43a4.5 4.5 0 0 1-2.04.08 4.52 4.52 0 0 0 4.22 3.14A9.06 9.06 0 0 1 0 19.54 12.78 12.78 0 0 0 6.92 21.5c8.3 0 12.84-6.88 12.84-12.84 0-.2 0-.39-.01-.58A9.17 9.17 0 0 0 23 3z"/></svg>
          </a>
          <a href="#" class="social-btn" aria-label="LinkedIn">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
          </a>
          <a href="#" class="social-btn" aria-label="YouTube">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/></svg>
          </a>
          <a href="#" class="social-btn" aria-label="WhatsApp">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
          </a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Product</h4>
        <ul>
          <li><a href="#features">CRM & Leads</a></li>
          <li><a href="#features">Project Management</a></li>
          <li><a href="#features">Gantt Charts</a></li>
          <li><a href="#features">Staff Management</a></li>
          <li><a href="#features">Quotation Builder</a></li>
          <li><a href="#features">Analytics</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Industries</h4>
        <ul>
          <li><a href="#industries">Engineering</a></li>
          <li><a href="#industries">CCTV & Security</a></li>
          <li><a href="#industries">Solar Energy</a></li>
          <li><a href="#industries">Fire Protection</a></li>
          <li><a href="#industries">HVAC & Plumbing</a></li>
          <li><a href="#industries">Construction</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Company</h4>
        <ul>
          <li><a href="#contact">Contact Us</a></li>
          <li><a href="#pricing">Pricing</a></li>
          <li><a href="#">Blog</a></li>
          <li><a href="#">Careers</a></li>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="{{ config('legal.terms_url') }}">Terms of Service</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="footer-bottom-left">
        <p>&copy; 2026 We4Work. All rights reserved. Made with &hearts; in India.</p>
        <div class="footer-brandline">A Product by Sourrcecube Technologies pvt Ltd</div>
      </div>
      <div class="footer-bottom-links">
        <a href="#">Privacy</a>
        <a href="{{ config('legal.terms_url') }}">Terms</a>
        <a href="#">Support</a>
      </div>
    </div>
  </div>
</footer>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script><script>
  // Navbar scroll effect
  window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 20);
  });

  // Intersection Observer for fade-up
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
  }, { threshold: 0.12 });
  document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));

  // Module showcase tabs
  function showTab(id) {
    document.querySelectorAll('.showcase-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.s-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    event.target.classList.add('active');
  }

  // Pricing toggle
  let isAnnual = true;
  const prices = {
    monthly: { starter: '1,999', pro: '4,999', ent: '9,999' },
    annual:  { starter: '1,599', pro: '3,999', ent: '7,999' }
  };
  function togglePrice() {
    isAnnual = !isAnnual;
    const toggle = document.getElementById('priceToggle');
    toggle.classList.toggle('annual', isAnnual);
    const set = isAnnual ? prices.annual : prices.monthly;
    document.getElementById('price-starter').textContent = set.starter;
    document.getElementById('price-pro').textContent     = set.pro;
    document.getElementById('price-ent').textContent     = set.ent;
  }

  // Hamburger (mobile — simple toggle for nav-links visibility)
  document.getElementById('hamburger').addEventListener('click', function() {
    const links = document.querySelector('.nav-links');
    if (links.style.display === 'flex') {
      links.style.display = '';
    } else {
      links.style.cssText = 'display:flex;flex-direction:column;position:absolute;top:68px;left:0;right:0;background:rgba(15,23,42,.98);padding:24px;gap:20px;border-bottom:1px solid rgba(255,255,255,.06);z-index:999;';
    }
  });

  // Smooth contact form submit (demo)
  document.querySelector('.form-submit').addEventListener('click', function(e) {
    this.textContent = 'Message Sent! ✓';
    this.style.background = 'linear-gradient(135deg,#10b981,#059669)';
    setTimeout(() => {
      this.textContent = 'Send Message →';
      this.style.background = '';
    }, 3000);
  });
</script>
</body>
</html>
