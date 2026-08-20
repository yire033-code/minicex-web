<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Metodología de cálculo MINI-CEX — Fórmulas, indicadores y análisis detallado de las evaluaciones clínicas por alumno.">
<title>Metodología de Cálculo · MINI-CEX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════════
   DESIGN TOKENS
   ═══════════════════════════════════════════════════════════════ */
:root {
  --bg-primary: #050a18;
  --bg-secondary: #0a1128;
  --bg-tertiary: #0f1937;
  --bg-card: rgba(15, 25, 55, 0.6);
  --bg-glass: rgba(255, 255, 255, 0.03);
  --bg-glass-border: rgba(255, 255, 255, 0.06);
  --bg-code: #0d1117;

  --text-primary: #e2e8f0;
  --text-secondary: #94a3b8;
  --text-muted: #64748b;

  --blue: #3b82f6;
  --blue-vivid: #60a5fa;
  --blue-deep: #1d4ed8;
  --blue-glow: rgba(59, 130, 246, 0.12);

  --gold: #d4a012;
  --gold-vivid: #fbbf24;
  --gold-glow: rgba(251, 191, 36, 0.12);

  --green: #22c55e;
  --green-glow: rgba(34, 197, 94, 0.12);

  --purple: #a855f7;
  --rose: #f43f5e;
  --teal: #14b8a6;
  --amber: #f59e0b;

  --radius: 16px;
  --radius-md: 12px;
  --radius-sm: 8px;

  --font: 'Inter', system-ui, -apple-system, sans-serif;
  --font-heading: 'Outfit', var(--font);
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;

  --ease: cubic-bezier(0.4, 0, 0.2, 1);
  --speed: 0.3s;

  --sidebar-w: 250px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html {
  scroll-behavior: smooth;
  scroll-padding-top: 100px;
}

body {
  font-family: var(--font);
  background: var(--bg-primary);
  color: var(--text-primary);
  line-height: 1.7;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a { text-decoration: none; color: inherit; }

::selection {
  background: rgba(59, 130, 246, 0.3);
  color: #fff;
}

::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--bg-primary); }
::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, var(--blue), var(--purple));
  border-radius: 3px;
}

/* ═══════════════════════════════════════════════════════════════
   LAYOUT
   ═══════════════════════════════════════════════════════════════ */
.app-layout {
  display: flex;
  min-height: 100vh;
}

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR
   ═══════════════════════════════════════════════════════════════ */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: var(--sidebar-w);
  height: 100vh;
  overflow-y: auto;
  z-index: 100;
  background: var(--bg-secondary);
  border-right: 1px solid rgba(255, 255, 255, 0.04);
  padding: 0;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  padding: 22px 20px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  flex-shrink: 0;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sidebar-logo {
  width: 30px;
  height: 30px;
  filter: brightness(0) invert(1);
  flex-shrink: 0;
}

.sidebar-brand-text {
  display: flex;
  flex-direction: column;
}

.sidebar-brand-name {
  font-family: var(--font-heading);
  font-weight: 800;
  font-size: 15px;
  color: #fff;
  letter-spacing: -0.3px;
  line-height: 1.1;
}

.sidebar-brand-sub {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--gold-vivid);
  font-weight: 700;
}

.sidebar-nav {
  padding: 12px 0;
  flex: 1;
  overflow-y: auto;
}

.sidebar-section {
  margin-bottom: 2px;
}

.sidebar-section-title {
  padding: 8px 20px 5px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: var(--text-muted);
  opacity: 0.5;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 20px;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  transition: all var(--speed) var(--ease);
  border-left: 2px solid transparent;
}

.sidebar-link:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.03);
}

.sidebar-link.active {
  color: var(--blue-vivid);
  background: var(--blue-glow);
  border-left-color: var(--blue);
}

.sidebar-link .link-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.4;
  flex-shrink: 0;
}

.sidebar-link.active .link-dot {
  opacity: 1;
  box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
}

.sidebar-footer {
  padding: 14px 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.04);
  flex-shrink: 0;
}

.sidebar-footer a {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--text-muted);
  transition: color var(--speed) var(--ease);
}

.sidebar-footer a:hover { color: var(--blue-vivid); }

/* ═══════════════════════════════════════════════════════════════
   MAIN CONTENT
   ═══════════════════════════════════════════════════════════════ */
.main-content {
  margin-left: var(--sidebar-w);
  flex: 1;
  min-width: 0;
  padding: 0;
}

.top-bar {
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 40px;
  background: rgba(5, 10, 24, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.top-bar-title {
  font-family: var(--font-heading);
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.3px;
}

.top-bar-title span {
  color: var(--text-muted);
  font-weight: 400;
}

.top-bar-version {
  padding: 4px 12px;
  background: var(--blue-glow);
  color: var(--blue-vivid);
  font-size: 11px;
  font-weight: 600;
  border-radius: 999px;
  letter-spacing: 0.3px;
}

.sidebar-toggle {
  display: none;
  background: none;
  border: none;
  color: #fff;
  cursor: pointer;
  padding: 6px;
}

/* ═══════════════════════════════════════════════════════════════
   CONTENT WRAPPER
   ═══════════════════════════════════════════════════════════════ */
.content-wrapper {
  max-width: 860px;
  margin: 0 auto;
  padding: 36px 40px 80px;
}

/* ═══════════════════════════════════════════════════════════════
   HERO
   ═══════════════════════════════════════════════════════════════ */
.hero-section {
  margin-bottom: 44px;
  padding-bottom: 28px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.hero-section h1 {
  font-family: var(--font-heading);
  font-size: 34px;
  font-weight: 900;
  color: #fff;
  letter-spacing: -1.5px;
  line-height: 1.15;
  margin-bottom: 10px;
}

.hero-section .hero-gradient {
  background: linear-gradient(135deg, var(--blue-vivid), var(--purple), var(--gold-vivid));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-section p {
  font-size: 16px;
  color: var(--text-secondary);
  max-width: 680px;
  line-height: 1.7;
}

.hero-stats {
  display: flex;
  gap: 10px;
  margin-top: 16px;
  flex-wrap: wrap;
}

.hero-stat {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: var(--bg-glass);
  border: 1px solid var(--bg-glass-border);
  border-radius: 6px;
  font-size: 12px;
  color: var(--text-secondary);
}

.hero-stat strong {
  color: var(--blue-vivid);
  font-weight: 600;
}

/* ═══════════════════════════════════════════════════════════════
   SECTIONS
   ═══════════════════════════════════════════════════════════════ */
.section-block {
  margin-bottom: 44px;
  scroll-margin-top: 80px;
}

.section-block h2 {
  font-family: var(--font-heading);
  font-size: 24px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.8px;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-block h2 .section-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 30px;
  height: 30px;
  border-radius: 8px;
  background: var(--blue-glow);
  color: var(--blue-vivid);
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
  padding: 0 6px;
}

.section-block h3 {
  font-family: var(--font-heading);
  font-size: 18px;
  font-weight: 700;
  color: #e2e8f0;
  margin-bottom: 12px;
  margin-top: 28px;
  letter-spacing: -0.2px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-block h3 .h3-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: var(--blue-vivid);
}

.section-block p {
  font-size: 15px;
  color: var(--text-secondary);
  line-height: 1.7;
  margin-bottom: 14px;
}

.section-block ul, .section-block ol {
  padding-left: 20px;
  margin-bottom: 14px;
}

.section-block li {
  font-size: 14px;
  color: var(--text-secondary);
  margin-bottom: 5px;
  line-height: 1.6;
}

.section-block li strong { color: var(--text-primary); }

/* ═══════════════════════════════════════════════════════════════
   FORMULA BOX
   ═══════════════════════════════════════════════════════════════ */
.formula-box {
  background: var(--bg-tertiary);
  border: 1px solid rgba(59, 130, 246, 0.15);
  border-radius: var(--radius-sm);
  padding: 18px 22px;
  margin: 12px 0 18px;
  text-align: center;
  position: relative;
}

.formula-box .formula-label {
  position: absolute;
  top: -8px;
  left: 12px;
  background: var(--bg-tertiary);
  padding: 0 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: var(--blue-vivid);
}

.formula-box .formula {
  font-family: var(--font-mono);
  font-size: 15px;
  color: #fff;
  line-height: 1.8;
  overflow-x: auto;
  white-space: nowrap;
}

.formula-box .formula .f-blue { color: var(--blue-vivid); }
.formula-box .formula .f-gold { color: var(--gold-vivid); }
.formula-box .formula .f-green { color: var(--green); }
.formula-box .formula .f-rose { color: var(--rose); }
.formula-box .formula .f-purple { color: var(--purple); }

/* ═══════════════════════════════════════════════════════════════
   STEP / PARAMETER CARDS
   ═══════════════════════════════════════════════════════════════ */
.step-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 18px;
}

.step-item {
  background: var(--bg-card);
  border: 1px solid var(--bg-glass-border);
  border-radius: var(--radius-md);
  padding: 16px 20px;
  border-left: 3px solid var(--blue);
  transition: border-color var(--speed) var(--ease);
}

.step-item:hover {
  border-color: rgba(255, 255, 255, 0.1);
}

.step-item.warn  { border-left-color: var(--amber); }
.step-item.success { border-left-color: var(--green); }
.step-item.danger { border-left-color: var(--rose); }
.step-item.gold { border-left-color: var(--gold); }

.step-item .step-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: var(--text-muted);
  margin-bottom: 4px;
}

.step-item .step-body {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.65;
}

.step-item .step-body strong { color: var(--text-primary); }

.step-item .step-body .param {
  display: inline-block;
  padding: 1px 7px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  font-family: var(--font-mono);
}

.param-blue { background: rgba(59,130,246,0.12); color: var(--blue-vivid); }
.param-gold { background: rgba(251,191,36,0.12); color: var(--gold-vivid); }
.param-green { background: rgba(34,197,94,0.12); color: var(--green); }
.param-rose { background: rgba(244,63,94,0.12); color: var(--rose); }
.param-purple { background: rgba(168,85,247,0.12); color: var(--purple); }
.param-teal { background: rgba(20,184,166,0.12); color: var(--teal); }
.param-white { background: rgba(255,255,255,0.06); color: var(--text-primary); }

/* ═══════════════════════════════════════════════════════════════
   STEP-THROUGH BOX (flow with arrows)
   ═══════════════════════════════════════════════════════════════ */
.flow-seq {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 12px 0 16px;
  padding: 0;
  list-style: none;
}

.flow-seq li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 14px;
  color: var(--text-secondary);
  padding: 8px 14px;
  background: rgba(255, 255, 255, 0.02);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.04);
}

.flow-seq .fs-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--blue-glow);
  color: var(--blue-vivid);
  font-size: 11px;
  font-weight: 700;
  flex-shrink: 0;
  margin-top: 1px;
}

.flow-seq li strong { color: var(--text-primary); }

/* ═══════════════════════════════════════════════════════════════
   TABLES
   ═══════════════════════════════════════════════════════════════ */
.table-wrap {
  overflow-x: auto;
  margin-bottom: 18px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: var(--radius-sm);
}

.table-wrap table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.table-wrap th {
  background: rgba(255, 255, 255, 0.03);
  font-weight: 600;
  color: var(--text-primary);
  text-align: left;
  padding: 10px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table-wrap td {
  padding: 10px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  color: var(--text-secondary);
  vertical-align: top;
}

.table-wrap tr:last-child td { border-bottom: none; }
.table-wrap tr:hover td { background: rgba(255, 255, 255, 0.015); }

/* ── Tags dentro de tablas ── */
.tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.tag-blue { background: rgba(59, 130, 246, 0.15); color: var(--blue-vivid); }
.tag-green { background: rgba(34, 197, 94, 0.15); color: var(--green); }
.tag-red { background: rgba(244, 63, 94, 0.15); color: var(--rose); }
.tag-gold { background: rgba(251, 191, 36, 0.15); color: var(--gold-vivid); }
.tag-purple { background: rgba(168, 85, 247, 0.15); color: var(--purple); }

/* ═══════════════════════════════════════════════════════════════
   INFO BOXES
   ═══════════════════════════════════════════════════════════════ */
.info-box {
  display: flex;
  gap: 12px;
  padding: 14px 18px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 16px;
}

.info-box-icon {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  margin-top: 2px;
}

.info-box.info {
  background: rgba(59, 130, 246, 0.08);
  border: 1px solid rgba(59, 130, 246, 0.15);
  color: var(--blue-vivid);
}

.info-box.warn {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.15);
  color: var(--gold-vivid);
}

.info-box.success {
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.15);
  color: var(--green);
}

.info-box strong { color: #fff; }

.info-box code {
  background: rgba(255, 255, 255, 0.08);
  padding: 1px 6px;
  border-radius: 4px;
  font-family: var(--font-mono);
  font-size: 12px;
}

/* ═══════════════════════════════════════════════════════════════
   INLINE CODE
   ═══════════════════════════════════════════════════════════════ */
.section-block code {
  background: rgba(59, 130, 246, 0.1);
  padding: 1px 6px;
  border-radius: 4px;
  font-family: var(--font-mono);
  font-size: 13px;
  color: var(--blue-vivid);
}

.step-item .step-body code {
  background: rgba(255, 255, 255, 0.06);
  color: var(--blue-vivid);
}

/* ═══════════════════════════════════════════════════════════════
   THRESHOLD SCALE VISUALIZATION
   ═══════════════════════════════════════════════════════════════ */
.scale-vis {
  display: flex;
  gap: 0;
  margin: 14px 0 18px;
  border-radius: var(--radius-sm);
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.scale-seg {
  flex: 1;
  padding: 14px 10px;
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  line-height: 1.4;
}

.scale-seg .seg-range {
  display: block;
  font-size: 10px;
  opacity: 0.8;
  font-weight: 400;
  margin-top: 2px;
}

.scale-green { background: rgba(34, 197, 94, 0.25); color: var(--green); }
.scale-gold { background: rgba(251, 191, 36, 0.2); color: var(--gold-vivid); }
.scale-red { background: rgba(244, 63, 94, 0.2); color: var(--rose); }

/* ═══════════════════════════════════════════════════════════════
   DATA FLOW DIAGRAM
   ═══════════════════════════════════════════════════════════════ */
.data-flow {
  display: flex;
  flex-direction: column;
  gap: 0;
  margin: 14px 0 18px;
  padding: 0;
  list-style: none;
}

.data-flow li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  font-size: 13px;
  color: var(--text-secondary);
  background: rgba(255, 255, 255, 0.015);
  border-left: 2px solid var(--bg-glass-border);
  position: relative;
}

.data-flow li::before {
  content: '▸';
  color: var(--blue-vivid);
  font-size: 14px;
  flex-shrink: 0;
}

.data-flow li .df-step {
  display: inline-block;
  padding: 1px 7px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  font-family: var(--font-mono);
  margin-right: 4px;
}

.data-flow li strong { color: var(--text-primary); }

/* ═══════════════════════════════════════════════════════════════
   FOOTER
   ═══════════════════════════════════════════════════════════════ */
.guide-footer {
  margin-top: 56px;
  padding-top: 22px;
  border-top: 1px solid rgba(255, 255, 255, 0.04);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.guide-footer p {
  font-size: 12px;
  color: var(--text-muted);
  margin-bottom: 0;
}

.guide-footer a {
  font-size: 12px;
  color: var(--blue-vivid);
  transition: color var(--speed) var(--ease);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.guide-footer a:hover { color: #fff; }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════════════════════════ */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 99;
}

.sidebar-overlay.show { display: block; }

@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); transition: transform 0.3s var(--ease); }
  .sidebar.open { transform: translateX(0); }
  .sidebar-toggle { display: flex; }
  .main-content { margin-left: 0; }
  .content-wrapper { padding: 24px 24px 60px; }
  .top-bar { padding: 12px 24px; }
  .formula-box .formula { font-size: 13px; }
}

@media (max-width: 640px) {
  .hero-section h1 { font-size: 26px; }
  .section-block h2 { font-size: 20px; }
  .section-block h3 { font-size: 16px; }
  .content-wrapper { padding: 16px 16px 60px; }
  .top-bar { padding: 10px 16px; }
  .step-item { padding: 14px 16px; }
  .guide-footer { flex-direction: column; text-align: center; }
  .formula-box { padding: 14px 12px; }
  .formula-box .formula { font-size: 11px; white-space: normal; word-break: break-all; }
  .scale-vis { flex-direction: column; }
}
</style>
</head>
<body>

<!-- ═══ SIDEBAR OVERLAY ═══ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="<?= base_url() ?>" class="sidebar-brand">
      <img src="<?= base_url('logo_small.png') ?>" alt="MINI-CEX" class="sidebar-logo">
      <div class="sidebar-brand-text">
        <span class="sidebar-brand-name">MINI-CEX</span>
        <span class="sidebar-brand-sub">Metodología</span>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <div class="sidebar-section-title">Fundamentos</div>
      <a href="#intro" class="sidebar-link active"><span class="link-dot"></span> Introducción</a>
      <a href="#escala" class="sidebar-link"><span class="link-dot"></span> Escala de Puntuación</a>
      <a href="#flujo" class="sidebar-link"><span class="link-dot"></span> Flujo de Datos</a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-section-title">Indicadores</div>
      <a href="#calificacion-total" class="sidebar-link"><span class="link-dot"></span> Calificación Total</a>
      <a href="#promedio" class="sidebar-link"><span class="link-dot"></span> Promedio General</a>
      <a href="#tendencia" class="sidebar-link"><span class="link-dot"></span> Tendencia</a>
      <a href="#consistencia" class="sidebar-link"><span class="link-dot"></span> Consistencia</a>
      <a href="#progreso" class="sidebar-link"><span class="link-dot"></span> Progreso</a>
      <a href="#competencias" class="sidebar-link"><span class="link-dot"></span> Competencias</a>
      <a href="#complejidad" class="sidebar-link"><span class="link-dot"></span> Complejidad</a>
      <a href="#areas-mejora" class="sidebar-link"><span class="link-dot"></span> Áreas de Mejora</a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-section-title">Ejemplo</div>
      <a href="#ejemplo" class="sidebar-link"><span class="link-dot"></span> Caso Práctico</a>
    </div>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= base_url('admin') ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Ir al Panel
    </a>
  </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main-content">

  <div class="top-bar">
    <div style="display:flex;align-items:center;gap:12px;">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menú">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <span class="top-bar-title">Metodología <span>de Cálculo</span></span>
    </div>
    <span class="top-bar-version">v2.0</span>
  </div>

  <div class="content-wrapper">

    <!-- ════════════════════════════════════════════════
         HERO
         ════════════════════════════════════════════════ -->
    <div class="hero-section" id="intro">
      <h1>Metodología de Cálculo <span class="hero-gradient">MINI-CEX</span></h1>
      <p>Documentación técnica completa de cómo se calculan cada una de las métricas, indicadores y puntuaciones que aparecen en los reportes individuales de los alumnos. Esta página describe las fórmulas, umbrales y algoritmos utilizados en el sistema.</p>
      <div class="hero-stats">
        <span class="hero-stat"><strong>9</strong> indicadores</span>
        <span class="hero-stat"><strong>5</strong> umbrales de interpretación</span>
        <span class="hero-stat"><strong>9</strong> competencias evaluables</span>
        <span class="hero-stat"><strong>0–9</strong> escala por rubro</span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         1. ESCALA DE PUNTUACIÓN
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="escala">
      <h2><span class="section-num">1</span> Escala de Puntuación</h2>
      <p>Cada evaluación clínica (MINI-CEX) consta de <strong>9 competencias</strong> que el evaluador califica individualmente. Cada competencia recibe un puntaje en la escala <strong>1–9</strong>, que se agrupa en tres niveles de desempeño:</p>

      <div class="scale-vis">
        <div class="scale-seg scale-green">
          Sobresaliente
          <span class="seg-range">7 – 9</span>
        </div>
        <div class="scale-seg scale-gold">
          Satisfactorio
          <span class="seg-range">4 – 6</span>
        </div>
        <div class="scale-seg scale-red">
          Insatisfactorio
          <span class="seg-range">1 – 3</span>
        </div>
      </div>

      <p>Adicionalmente, el valor <code>0</code> se utiliza para indicar <strong>"No Evaluado"</strong> (se muestra como <code>—</code> en PDF y tablas). Las competencias evaluadas son las del estándar MINI-CEX:</p>

      <div class="table-wrap">
      <table>
        <tr><th>#</th><th>Competencia</th><th>Abreviatura</th></tr>
        <tr><td>1</td><td>Historia Clínica</td><td><span class="tag tag-blue">HC</span></td></tr>
        <tr><td>2</td><td>Exploración Física</td><td><span class="tag tag-blue">EF</span></td></tr>
        <tr><td>3</td><td>Profesionalismo</td><td><span class="tag tag-blue">PR</span></td></tr>
        <tr><td>4</td><td>Juicio Clínico</td><td><span class="tag tag-blue">JC</span></td></tr>
        <tr><td>5</td><td>Habilidades Clínicas</td><td><span class="tag tag-blue">HCL</span></td></tr>
        <tr><td>6</td><td>Organización y Eficiencia</td><td><span class="tag tag-blue">OE</span></td></tr>
        <tr><td>7</td><td>Comunicación con Paciente</td><td><span class="tag tag-blue">CP</span></td></tr>
        <tr><td>8</td><td>Razonamiento Diagnóstico</td><td><span class="tag tag-blue">RD</span></td></tr>
        <tr><td>9</td><td>Actitud y Responsabilidad</td><td><span class="tag tag-blue">AR</span></td></tr>
      </table>
      </div>

      <div class="info-box info">
        <svg class="info-box-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span><strong>Nota:</strong> La cantidad de competencias no es fija — la app Android puede enviar un número variable de rubros por evaluación. El sistema promedia automáticamente las que existan.</span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         2. FLUJO DE DATOS
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="flujo">
      <h2><span class="section-num">2</span> Flujo de Datos</h2>
      <p>El siguiente diagrama muestra el recorrido completo de los datos desde que el evaluador califica en la app Android hasta que se generan los reportes y gráficas en el panel administrativo.</p>

      <ol class="data-flow">
        <li><strong>App Android (Kotlin)</strong> — El evaluador asigna un puntaje (1–9) a cada competencia. La app suma todos los puntajes → <span class="param param-blue df-step">calificacionTotal</span></li>
        <li><strong>Sincronización HTTP</strong> — La app envía un JSON vía <code>POST /api/sync/evaluations</code> con la evaluación completa y sus detalles</li>
        <li><strong><code>ApiController::syncEvaluations()</code></strong> — El servidor almacena <span class="param param-blue df-step">calificacion_total</span> y cada <span class="param param-green df-step">puntaje</span> individual en MySQL</li>
        <li><strong><code>Base de Datos MySQL</code></strong> — Tablas: <code>evaluaciones</code> (cabecera) y <code>detalles_rubrica</code> (detalle por competencia)</li>
        <li><strong><code>AdminController::reportesData()</code></strong> — Consulta todas las evaluaciones del alumno, promedios por competencia, distribución de complejidad y áreas de mejora</li>
        <li><strong><code>computeIndices()</code></strong> — Calcula los 9 indicadores (promedio, tendencia, consistencia, progreso, etc.)</li>
        <li><strong>Panel web (<code>reportes.php</code>)</strong> — Renderiza las gráficas (Chart.js) y la tabla detallada con los datos calculados</li>
      </ol>

      <div class="info-box warn">
        <svg class="info-box-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span><strong>Importante:</strong> La <span class="param param-blue">calificacion_total</span> la calcula la app Android (suma de rubros) y se almacena sin modificaciones. El servidor nunca la recalcula — esto garantiza que el valor coincide exactamente con lo que el evaluador vio al finalizar.</span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         3. CALIFICACIÓN TOTAL POR EVALUACIÓN
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="calificacion-total">
      <h2><span class="section-num">3</span> Calificación Total por Evaluación</h2>
      <p>Es la <strong>suma aritmética</strong> de todos los puntajes de competencia en una misma evaluación. Se calcula del lado del dispositivo Android y se envía como <code>calificacionTotal</code> en el JSON de sincronización.</p>

      <div class="formula-box">
        <span class="formula-label">Fórmula</span>
        <div class="formula">
          <span class="f-blue">Calificación Total</span> =
          <span class="f-green">puntaje₁</span> + <span class="f-green">puntaje₂</span> + … + <span class="f-green">puntaje<sub>n</sub></span>
        </div>
      </div>

      <p>Ejemplo con 9 competencias calificadas:</p>

      <div class="table-wrap">
      <table>
        <tr><th>Competencia</th><th>Puntaje</th></tr>
        <tr><td>Historia Clínica</td><td>8</td></tr>
        <tr><td>Exploración Física</td><td>7</td></tr>
        <tr><td>Profesionalismo</td><td>9</td></tr>
        <tr><td>Juicio Clínico</td><td>6</td></tr>
        <tr><td>Habilidades Clínicas</td><td>8</td></tr>
        <tr><td>Organización y Eficiencia</td><td>7</td></tr>
        <tr><td>Comunicación con Paciente</td><td>8</td></tr>
        <tr><td>Razonamiento Diagnóstico</td><td>7</td></tr>
        <tr><td>Actitud y Responsabilidad</td><td>9</td></tr>
        <tr style="background:rgba(59,130,246,0.05)"><td><strong>Calificación Total</strong></td><td><strong>69</strong></td></tr>
      </table>
      </div>

      <h3>Visualización en el sistema</h3>
      <p>Para hacer la puntuación más legible, el sistema divide entre <strong>10</strong> y la muestra como <span class="param param-gold">X.X / 10</span>. En el ejemplo anterior:</p>

      <div class="step-list">
        <div class="step-item success">
          <div class="step-label">Conversión a /10</div>
          <div class="step-body">
            69 ÷ 10 = <strong>6.9 / 10</strong>
          </div>
        </div>
      </div>

      <div class="info-box info">
        <svg class="info-box-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span><strong>Rango típico:</strong> Con 9 competencias en escala 1–9, el rango va de 9 (mínimo: 1×9) a 81 (máximo: 9×9). Al dividir entre 10, el rango mostrado es 0.9 – 8.1 sobre 10.</span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         4. PROMEDIO GENERAL
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="promedio">
      <h2><span class="section-num">4</span> Promedio General</h2>
      <p>Es la <strong>media aritmética</strong> de todas las calificaciones totales registradas para un alumno a lo largo del tiempo. Refleja el rendimiento global del estudiante en todas sus evaluaciones.</p>

      <div class="formula-box">
        <span class="formula-label">Fórmula</span>
        <div class="formula">
          <span class="f-blue">μ</span> =
          <span class="f-green">1</span> / <span class="f-gold">N</span> ·
          <span style="font-size:22px;vertical-align:middle;">∑</span><sub style="font-size:12px;">i=1</sub><sup style="font-size:12px;">N</sup>
          <span class="f-purple">calificación<sub>i</sub></span>
        </div>
      </div>

      <p>Donde:</p>
      <ul>
        <li><strong>N</strong> = Número total de evaluaciones del alumno</li>
        <li><strong>calificación<sub>i</sub></strong> = Calificación total de la evaluación <em>i</em> (valor crudo, previo a la división entre 10)</li>
      </ul>

      <p>El sistema expone dos valores:</p>
      <div class="step-list">
        <div class="step-item">
          <div class="step-label">Valor crudo</div>
          <div class="step-body"><span class="param param-blue">promedio</span> = μ (ej. <code>69.50</code>)</div>
        </div>
        <div class="step-item success">
          <div class="step-label">Valor mostrado</div>
          <div class="step-body"><span class="param param-gold">promedio_display</span> = <code>round(μ / 10, 1)</code> (ej. <code>7.0 / 10</code>)</div>
        </div>
      </div>

      <h3>Ejemplo</h3>
      <p>Un alumno con 4 evaluaciones con calificaciones totales: 69, 72, 65, 78.</p>
      <div class="formula-box">
        <span class="formula-label">Cálculo</span>
        <div class="formula">
          μ = (69 + 72 + 65 + 78) / 4 =
          <span class="f-blue">71.00</span>
          &nbsp;&nbsp;→&nbsp;&nbsp;
          <span class="f-gold">7.1 / 10</span>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         5. TENDENCIA (REGRESIÓN LINEAL)
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="tendencia">
      <h2><span class="section-num">5</span> Tendencia (Regresión Lineal)</h2>
      <p>La tendencia mide si el alumno está <strong>mejorando, empeorando o manteniéndose estable</strong> a lo largo de sus evaluaciones. Se calcula mediante la <strong>pendiente de una regresión lineal simple</strong> sobre los puntajes ordenados cronológicamente.</p>

      <div class="formula-box">
        <span class="formula-label">Fórmula</span>
        <div class="formula">
          <span class="f-blue">m</span> =
          <span class="f-green">N · Σ(xy) − Σx · Σy</span>
          <span style="font-size:18px;color:var(--text-muted);">/</span>
          <span class="f-gold">N · Σ(x²) − (Σx)²</span>
        </div>
      </div>

      <div class="step-list">
        <div class="step-item">
          <div class="step-label">Donde</div>
          <div class="step-body">
            <span class="param param-blue">x</span> = Número de evaluación (1, 2, 3, …, N) — orden cronológico ascendente<br>
            <span class="param param-green">y</span> = <code>calificacion_total</code> de cada evaluación<br>
            <span class="param param-gold">N</span> = Total de evaluaciones (se requiere al menos <strong>2</strong> para calcular)
          </div>
        </div>
      </div>

      <h3>Interpretación</h3>
      <div class="table-wrap">
      <table>
        <tr><th>Valor de <span class="param param-blue">m</span></th><th>Interpretación</th><th>Icono</th></tr>
        <tr><td><span class="tag tag-green">m &gt; 0.5</span></td><td><strong>Mejora constante</strong> — El alumno tiende a subir su puntaje con cada evaluación</td><td>↑</td></tr>
        <tr><td><span class="tag tag-gold">−0.5 ≤ m ≤ 0.5</span></td><td><strong>Estable</strong> — Sin tendencia significativa al alza o a la baja</td><td>→</td></tr>
        <tr><td><span class="tag tag-red">m &lt; −0.5</span></td><td><strong>Requiere atención</strong> — El alumno tiende a bajar su puntaje</td><td>↓</td></tr>
      </table>
      </div>

      <h3>Ejemplo con 5 evaluaciones</h3>
      <div class="table-wrap">
      <table>
        <tr><th># (x)</th><th>Calificación Total (y)</th><th>x·y</th><th>x²</th></tr>
        <tr><td>1</td><td>65</td><td>65</td><td>1</td></tr>
        <tr><td>2</td><td>68</td><td>136</td><td>4</td></tr>
        <tr><td>3</td><td>70</td><td>210</td><td>9</td></tr>
        <tr><td>4</td><td>72</td><td>288</td><td>16</td></tr>
        <tr><td>5</td><td>75</td><td>375</td><td>25</td></tr>
        <tr style="background:rgba(59,130,246,0.05)"><td><strong>Σ</strong></td><td><strong>350</strong></td><td><strong>1074</strong></td><td><strong>55</strong></td></tr>
      </table>
      </div>

      <div class="formula-box">
        <span class="formula-label">Resultado</span>
        <div class="formula">
          m = (5·1074 − 15·350) / (5·55 − 15²) = (5370 − 5250) / (275 − 225) = 120 / 50 = <span class="f-blue">2.40</span>
          <br>
          <span class="f-green" style="font-size:13px;">→ m = 2.40 &gt; 0.5 → "Mejora constante" ↑</span>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         6. CONSISTENCIA (DESVIACIÓN ESTÁNDAR)
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="consistencia">
      <h2><span class="section-num">6</span> Consistencia (Desviación Estándar)</h2>
      <p>Mide la <strong>variabilidad</strong> de las calificaciones del alumno. Una desviación estándar baja indica que el alumno rinde de manera <strong>consistente</strong> (puntajes similares entre evaluaciones), mientras que una alta señala <strong>altibajos</strong> en su desempeño.</p>

      <div class="formula-box">
        <span class="formula-label">Fórmula</span>
        <div class="formula">
          <span class="f-blue">σ</span> =
          <span style="font-size:18px;">√</span>
          <span style="border-top:1px solid currentColor;padding:0 6px;">
            <span class="f-green">∑ (s<sub>i</sub> − μ)²</span>
            <span style="font-size:18px;">/</span>
            <span class="f-gold">(N − 1)</span>
          </span>
        </div>
      </div>

      <p>Donde <strong>s<sub>i</sub></strong> es cada calificación, <strong>μ</strong> es el promedio y <strong>N</strong> el total de evaluaciones. Se utiliza la <strong>desviación estándar muestral</strong> (divide entre N−1). Se requiere al menos <strong>2 evaluaciones</strong> para calcularla; de lo contrario el valor es 0.</p>

      <h3>Interpretación</h3>
      <div class="table-wrap">
      <table>
        <tr><th>Valor de σ</th><th>Interpretación</th><th>Significado clínico</th></tr>
        <tr><td><span class="tag tag-green">σ &lt; 5</span></td><td><strong>Alta consistencia</strong></td><td>El alumno mantiene un nivel de desempeño estable, sin sorpresas</td></tr>
        <tr><td><span class="tag tag-gold">5 ≤ σ &lt; 12</span></td><td><strong>Consistencia moderada</strong></td><td>El alumno muestra cierta variabilidad, puede deberse al tipo de caso</td></tr>
        <tr><td><span class="tag tag-red">σ ≥ 12</span></td><td><strong>Variable</strong></td><td>El rendimiento fluctúa significativamente — puede requerir atención pedagógica</td></tr>
      </table>
      </div>

      <div class="info-box info">
        <svg class="info-box-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span><strong>Nota:</strong> Los umbrales (5 y 12) fueron definidos empíricamente con base en la escala real de <code>calificacion_total</code> (rango típico 9–81). Si se usara la escala /10 los umbrales serían 0.5 y 1.2 respectivamente.</span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         7. PROGRESO (ÚLTIMAS 3 VS PRIMERAS 3)
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="progreso">
      <h2><span class="section-num">7</span> Progreso (Últimas 3 vs Primeras 3)</h2>
      <p>Compara el promedio de las <strong>3 evaluaciones más recientes</strong> contra el promedio de las <strong>3 primeras evaluaciones</strong>. Esto permite detectar si el alumno ha mejorado o empeorado a lo largo de su proceso de aprendizaje.</p>

      <div class="formula-box">
        <span class="formula-label">Fórmula</span>
        <div class="formula">
          <span class="f-blue">Progreso</span> =
          <span class="f-green">promedio(últimas 3)</span>
          <span style="color:var(--text-muted);">−</span>
          <span class="f-rose">promedio(primeras 3)</span>
        </div>
      </div>

      <p>Se requiere un mínimo de <strong>4 evaluaciones</strong> para calcular este indicador. Con menos de 4, el progreso se reporta como <strong>0</strong>.</p>

      <h3>Interpretación</h3>
      <div class="table-wrap">
      <table>
        <tr><th>Valor</th><th>Interpretación</th><th>Representación</th></tr>
        <tr><td><span class="tag tag-green">Progreso &gt; 2</span></td><td><strong>Mejorando</strong></td><td>El alumno rinde mejor ahora que al inicio</td></tr>
        <tr><td><span class="tag tag-gold">−2 ≤ Progreso ≤ 2</span></td><td><strong>Estable</strong></td><td>No hay cambios significativos en su rendimiento</td></tr>
        <tr><td><span class="tag tag-red">Progreso &lt; −2</span></td><td><strong>Disminuyendo</strong></td><td>El alumno rinde peor ahora que al inicio — puede ser señal de alerta</td></tr>
      </table>
      </div>

      <h3>Ejemplo</h3>
      <p>Un alumno con 6 evaluaciones: [65, 68, 70, 72, 75, 78].</p>
      <div class="step-list">
        <div class="step-item">
          <div class="step-label">Primeras 3</div>
          <div class="step-body"><span class="param param-rose">promedio(65, 68, 70)</span> = 67.67</div>
        </div>
        <div class="step-item">
          <div class="step-label">Últimas 3</div>
          <div class="step-body"><span class="param param-green">promedio(72, 75, 78)</span> = 75.00</div>
        </div>
        <div class="step-item success">
          <div class="step-label">Progreso</div>
          <div class="step-body">
            <span class="param param-green">75.00</span>
            <span style="color:var(--text-muted)"> − </span>
            <span class="param param-rose">67.67</span>
            <span style="color:var(--text-muted)"> = </span>
            <strong style="color:var(--green)">7.33</strong>
            <span style="color:var(--text-muted)"> → </span>
            <span class="tag tag-green">Mejorando</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         8. COMPETENCIAS (FORTALEZA Y DEBILIDAD)
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="competencias">
      <h2><span class="section-num">8</span> Competencias — Fuerte y Débil</h2>
      <p>El sistema identifica la <strong>competencia con el promedio más alto</strong> (fortaleza) y la <strong>competencia con el promedio más bajo</strong> (debilidad) para cada alumno, basándose en todas sus evaluaciones.</p>

      <div class="formula-box">
        <span class="formula-label">Cálculo</span>
        <div class="formula" style="white-space:normal;text-align:left;font-family:var(--font);font-size:14px;">
          Consulta SQL: <code style="font-size:13px;">SELECT competencia, AVG(puntaje) as promedio FROM detalles_rubrica GROUP BY competencia</code><br>
          <span class="f-green" style="font-size:13px;">→ Competencia más fuerte = MAX(promedio)</span><br>
          <span class="f-rose" style="font-size:13px;">→ Competencia más débil = MIN(promedio)</span>
        </div>
      </div>

      <p>Se itera sobre todas las competencias registradas para el alumno (agrupadas por nombre) y se identifican los valores extremos:</p>

      <div class="step-list">
        <div class="step-item success">
          <div class="step-label">Competencia más fuerte</div>
          <div class="step-body">La competencia con el <span class="param param-green">promedio más alto</span>. Se muestra con el nombre completo y su promedio asociado.</div>
        </div>
        <div class="step-item warn">
          <div class="step-label">Competencia más débil</div>
          <div class="step-body">La competencia con el <span class="param param-rose">promedio más bajo</span>. Es el área prioritaria para intervención pedagógica.</div>
        </div>
      </div>

      <div class="info-box info">
        <svg class="info-box-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span><strong>Visualización:</strong> La gráfica de radar (<strong>Radar de Competencias</strong>) muestra el promedio de TODAS las competencias simultáneamente, con escala 0–9 y pasos de 3. Esto permite identificar patrones visuales de fortalezas y debilidades.</span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         9. DISTRIBUCIÓN DE COMPLEJIDAD
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="complejidad">
      <h2><span class="section-num">9</span> Distribución de Complejidad</h2>
      <p>Analiza la <strong>distribución de los niveles de complejidad</strong> de los casos clínicos a los que se ha enfrentado el alumno. Cada evaluación registra un nivel de complejidad: <span class="tag tag-green">Baja</span>, <span class="tag tag-gold">Media</span> o <span class="tag tag-red">Alta</span>.</p>

      <div class="formula-box">
        <span class="formula-label">Consulta SQL</span>
        <div class="formula" style="white-space:normal;text-align:left;font-family:var(--font);font-size:13px;">
          <code>SELECT complejidad, COUNT(*) as count FROM evaluaciones WHERE id_alumno = ? GROUP BY complejidad</code>
        </div>
      </div>

      <p>El resultado se representa en una <strong>gráfica de dona</strong> con tres segmentos:</p>

      <div class="table-wrap">
      <table>
        <tr><th>Complejidad</th><th>Color</th><th>Descripción</th></tr>
        <tr><td><span class="tag tag-green">Baja</span></td><td><span style="color:var(--green)">●</span> <code>#22c55e</code></td><td>Casos clínicos sencillos, menor demanda cognitiva</td></tr>
        <tr><td><span class="tag tag-gold">Media</span></td><td><span style="color:var(--gold-vivid)">●</span> <code>#eab308</code></td><td>Casos con complejidad moderada</td></tr>
        <tr><td><span class="tag tag-red">Alta</span></td><td><span style="color:var(--rose)">●</span> <code>#ef4444</code></td><td>Casos complejos que requieren mayor nivel de habilidad clínica</td></tr>
      </table>
      </div>

      <p>En el tooltip de la gráfica se muestra tanto el <strong>conteo absoluto</strong> como el <strong>porcentaje</strong> sobre el total de evaluaciones.</p>
    </div>

    <!-- ════════════════════════════════════════════════
         10. ÁREAS DE MEJORA (FRECUENCIA DE PALABRAS)
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="areas-mejora">
      <h2><span class="section-num">10</span> Áreas de Mejora Recurrentes</h2>
      <p>Es un análisis de <strong>frecuencia de palabras</strong> extraídas del campo <code>a_mejorar</code> que el evaluador escribe en cada rubro de la evaluación. Identifica los temas que se repiten con mayor frecuencia como áreas de oportunidad para el alumno.</p>

      <div class="formula-box">
        <span class="formula-label">Algoritmo</span>
        <div class="formula" style="white-space:normal;text-align:left;font-family:var(--font);font-size:14px;">
          <span class="f-blue">1.</span> Extraer todos los textos de <code>a_mejorar</code> del alumno<br>
          <span class="f-blue">2.</span> Convertir a <strong>minúsculas</strong><br>
          <span class="f-blue">3.</span> Separar en <strong>palabras</strong> (regex: <code>[\s,;.\-!:?()]+</code>)<br>
          <span class="f-blue">4.</span> <strong>Filtrar</strong>: eliminar palabras ≤ 2 caracteres, <em>stop words</em> y números<br>
          <span class="f-blue">5.</span> <strong>Contar</strong> frecuencias<br>
          <span class="f-blue">6.</span> Ordenar de mayor a menor frecuencia<br>
          <span class="f-blue">7.</span> Conservar las <span class="f-gold">10 más frecuentes</span>
        </div>
      </div>

      <h3>Stop words excluidas</h3>
      <p>El sistema excluye las siguientes palabras vacías (35 en total) para evitar ruido en el análisis:</p>
      <div class="step-list">
        <div class="step-item" style="border-left-color:var(--text-muted)">
          <div class="step-body" style="font-size:12px;">
            <span class="param param-white">de</span> <span class="param param-white">la</span> <span class="param param-white">el</span>
            <span class="param param-white">en</span> <span class="param param-white">y</span> <span class="param param-white">a</span>
            <span class="param param-white">los</span> <span class="param param-white">del</span> <span class="param param-white">las</span>
            <span class="param param-white">que</span> <span class="param param-white">por</span> <span class="param param-white">con</span>
            <span class="param param-white">un</span> <span class="param param-white">una</span> <span class="param param-white">para</span>
            <span class="param param-white">es</span> <span class="param param-white">al</span> <span class="param param-white">lo</span>
            <span class="param param-white">su</span> <span class="param param-white">se</span> <span class="param param-white">no</span>
            <span class="param param-white">más</span> <span class="param param-white">pero</span> <span class="param param-white">sus</span>
            <span class="param param-white">le</span> <span class="param param-white">ya</span> <span class="param param-white">este</span>
            <span class="param param-white">entre</span> <span class="param param-white">porque</span> <span class="param param-white">todo</span>
            <span class="param param-white">esta</span> <span class="param param-white">muy</span> <span class="param param-white">sin</span>
            <span class="param param-white">como</span>
          </div>
        </div>
      </div>

      <h3>Representación visual</h3>
      <p>Las áreas de mejora se muestran como <strong>"tags"</strong> o chips cuyo tamaño y opacidad varían según la frecuencia relativa:</p>
      <ul>
        <li><strong>Tamaño</strong>: <code>0.75 + (frecuencia / frecuencia_máxima) × 0.75</code> — la palabra más frecuente es 75% más grande</li>
        <li><strong>Opacidad</strong>: <code>0.55 + (frecuencia / frecuencia_máxima) × 0.45</code> — las más frecuentes son más opacas</li>
        <li>La <strong>palabra más frecuente</strong> (si aparece más de una vez) se resalta con un chip de color <span style="color:var(--rose)">rojo</span></li>
      </ul>

      <div class="info-box info">
        <svg class="info-box-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span><strong>Nota técnica:</strong> Este análisis utiliza <code>mb_strtolower()</code> y <code>mb_strlen()</code> (multibyte) para manejar correctamente caracteres acentuados y eñes del español.</span>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         11. CASO PRÁCTICO COMPLETO
         ════════════════════════════════════════════════ -->
    <div class="section-block" id="ejemplo">
      <h2><span class="section-num">11</span> Caso Práctico Completo</h2>
      <p>A continuación se muestra un ejemplo completo con datos realistas para un alumno con <strong>6 evaluaciones</strong>. Todos los cálculos se muestran paso a paso para que puedas verificar cualquier reporte del sistema.</p>

      <h3>Datos del alumno</h3>
      <div class="table-wrap">
      <table>
        <tr><th>Campo</th><th>Valor</th></tr>
        <tr><td>Matrícula</td><td><code>A2024-012</code></td></tr>
        <tr><td>Nombre</td><td>María García López</td></tr>
        <tr><td>Semestre</td><td>5° "A"</td></tr>
      </table>
      </div>

      <h3>Evaluaciones registradas</h3>
      <div class="table-wrap">
      <table>
        <tr><th>#</th><th>Fecha</th><th>Complejidad</th><th>Puntajes por Competencia</th><th>Calificación Total</th><th>/10</th></tr>
        <tr>
          <td>1</td><td>15/01/2025</td><td><span class="tag tag-gold">Media</span></td>
          <td style="font-size:12px;font-family:var(--font-mono)">7, 6, 8, 5, 7, 6, 8, 6, 7</td>
          <td><strong>60</strong></td><td><span class="tag tag-blue">6.0</span></td>
        </tr>
        <tr>
          <td>2</td><td>30/01/2025</td><td><span class="tag tag-red">Alta</span></td>
          <td style="font-size:12px;font-family:var(--font-mono)">6, 5, 7, 5, 6, 5, 7, 5, 6</td>
          <td><strong>52</strong></td><td><span class="tag tag-blue">5.2</span></td>
        </tr>
        <tr>
          <td>3</td><td>12/02/2025</td><td><span class="tag tag-green">Baja</span></td>
          <td style="font-size:12px;font-family:var(--font-mono)">8, 7, 9, 7, 8, 7, 9, 7, 8</td>
          <td><strong>70</strong></td><td><span class="tag tag-blue">7.0</span></td>
        </tr>
        <tr>
          <td>4</td><td>28/02/2025</td><td><span class="tag tag-gold">Media</span></td>
          <td style="font-size:12px;font-family:var(--font-mono)">7, 7, 8, 6, 7, 7, 8, 6, 7</td>
          <td><strong>63</strong></td><td><span class="tag tag-blue">6.3</span></td>
        </tr>
        <tr>
          <td>5</td><td>15/03/2025</td><td><span class="tag tag-gold">Media</span></td>
          <td style="font-size:12px;font-family:var(--font-mono)">8, 7, 9, 7, 8, 8, 9, 7, 8</td>
          <td><strong>71</strong></td><td><span class="tag tag-blue">7.1</span></td>
        </tr>
        <tr>
          <td>6</td><td>30/03/2025</td><td><span class="tag tag-red">Alta</span></td>
          <td style="font-size:12px;font-family:var(--font-mono)">9, 8, 9, 7, 8, 8, 9, 8, 9</td>
          <td><strong>75</strong></td><td><span class="tag tag-blue">7.5</span></td>
        </tr>
      </table>
      </div>

      <h3>Cálculo paso a paso</h3>

      <div class="step-list">
        <div class="step-item success">
          <div class="step-label">Promedio General</div>
          <div class="step-body">
            μ = (60 + 52 + 70 + 63 + 71 + 75) / 6 = 391 / 6 = <strong>65.17</strong>
            <span style="color:var(--text-muted)"> → </span>
            <span class="param param-gold">65.17 / 10 = <strong>6.5 / 10</strong></span>
          </div>
        </div>

        <div class="step-item">
          <div class="step-label">Tendencia (Regresión Lineal)</div>
          <div class="step-body">
            Σx = 21, Σy = 391, Σxy = 1419, Σx² = 91, N = 6<br>
            m = (6·1419 − 21·391) / (6·91 − 21²) = (8514 − 8211) / (546 − 441) = 303 / 105 = <strong>2.89</strong><br>
            <span style="color:var(--text-muted)">→</span> <span class="param param-green">m = 2.89 &gt; 0.5 → "Mejora constante" ↑</span>
          </div>
        </div>

        <div class="step-item">
          <div class="step-label">Consistencia (Desv. Estándar)</div>
          <div class="step-body">
            σ² = [(60−65.17)² + (52−65.17)² + (70−65.17)² + (63−65.17)² + (71−65.17)² + (75−65.17)²] / 5<br>
            σ² = [26.73 + 173.43 + 23.33 + 4.71 + 33.99 + 96.63] / 5 = 358.82 / 5 = 71.76<br>
            <strong>σ = √71.76 = 8.47</strong>
            <span style="color:var(--text-muted)"> → </span>
            <span class="param param-gold">5 ≤ 8.47 &lt; 12 → "Consistencia moderada"</span>
          </div>
        </div>

        <div class="step-item">
          <div class="step-label">Progreso (últ.3 vs prim.3)</div>
          <div class="step-body">
            Promedio primeras 3: (60 + 52 + 70) / 3 = 60.67<br>
            Promedio últimas 3: (63 + 71 + 75) / 3 = 69.67<br>
            Progreso = 69.67 − 60.67 = <strong>9.00</strong>
            <span style="color:var(--text-muted)"> → </span>
            <span class="param param-green">9.00 &gt; 2 → "Mejorando"</span>
          </div>
        </div>

        <div class="step-item success">
          <div class="step-label">Competencia más fuerte</div>
          <div class="step-body">
            Según los promedios por competencia de todas las evaluaciones, la competencia con el promedio más alto sería <strong>"Profesionalismo"</strong> (promedio 8.33) y la más débil <strong>"Juicio Clínico"</strong> (promedio 6.17).
          </div>
        </div>

        <div class="step-item">
          <div class="step-label">Distribución de Complejidad</div>
          <div class="step-body">
            <span class="tag tag-green">Baja:</span> 1 (16.7%) &nbsp;·&nbsp;
            <span class="tag tag-gold">Media:</span> 3 (50.0%) &nbsp;·&nbsp;
            <span class="tag tag-red">Alta:</span> 2 (33.3%)
          </div>
        </div>

        <div class="step-item">
          <div class="step-label">Áreas de Mejora</div>
          <div class="step-body">
            Suponiendo que los evaluadores escribieron notas como:<br>
            <em>"Mejorar <strong>exploración</strong> física", "Profundizar <strong>exploración</strong>", "Fortalecer <strong>razonamiento</strong> clínico", "Mejorar <strong>exploración</strong> de reflejos"</em><br>
            Resultado: <span class="param param-rose">exploración (3)</span>,
            <span class="param param-gold">razonamiento (1)</span>
          </div>
        </div>
      </div>

      <div class="info-box success">
        <svg class="info-box-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <span><strong>Resumen del caso:</strong> María muestra una <strong>mejora constante</strong> (pendiente +2.89) con <strong>consistencia moderada</strong> (σ = 8.47). Su promedio general es <strong>6.5/10</strong>. Ha progresado significativamente (+9.00 puntos entre las primeras 3 y últimas 3 evaluaciones). Su fortaleza es <strong>Profesionalismo</strong> y su área prioritaria de mejora es <strong>Juicio Clínico</strong>. El área de mejora más mencionada por los evaluadores es "exploración".</span>
      </div>
    </div>

    <!-- ═══ FOOTER ═══ -->
    <div class="guide-footer">
      <p>MINI-CEX v2.0 — Metodología de Cálculo · Documentación técnica</p>
      <a href="<?= base_url('admin') ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Ir al Panel
      </a>
    </div>

  </div>
</main>

<!-- ═══════════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════════ -->
<script>
(function() {
    'use strict';

    // ─── Sidebar ──────────────────────────────
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (toggle) toggle.addEventListener('click', openSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // ─── Active link on scroll ────────────────
    const links = document.querySelectorAll('.sidebar-link');
    const sections = [];

    links.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.startsWith('#')) {
            const target = document.getElementById(href.slice(1));
            if (target) sections.push({ el: target, link: link });
        }
    });

    function updateActive() {
        const scrollY = window.scrollY + 110;
        let current = null;
        let lastVisible = null;

        sections.forEach(({ el, link }) => {
            const top = el.offsetTop;
            const bottom = top + el.offsetHeight;
            if (scrollY >= top && scrollY < bottom) current = link;
            if (scrollY >= top) lastVisible = link;
        });

        if (!current && lastVisible) {
            const bottom = lastVisible.el.offsetTop + lastVisible.el.offsetHeight;
            if (scrollY >= bottom) current = lastVisible;
        }

        if (!current && sections.length > 0) {
            const last = sections[sections.length - 1];
            const bottom = last.el.offsetTop + last.el.offsetHeight;
            if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 50) {
                current = last.link;
            }
        }

        links.forEach(l => l.classList.remove('active'));
        if (current) current.classList.add('active');
    }

    window.addEventListener('scroll', updateActive, { passive: true });
    window.addEventListener('resize', updateActive, { passive: true });
    updateActive();

    // ─── Close sidebar on link click (mobile) ──
    links.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1024) closeSidebar();
        });
    });
})();
</script>
</body>
</html>
