<!-- ═══════ TAB: REPORTES POR DOCENTE ═══════ -->
<section id="tab-reportes-docente" class="tab" style="display:none">
  <div class="page-head">
    <div>
      <h1>Reportes por Docente</h1>
      <p>Análisis consolidado por docente con métricas individuales por alumno, filtros y exportación detallada</p>
    </div>
    <a href="<?= base_url('admin/metodologia') ?>" target="_blank" class="btn btn-sm btn-outline-blue" title="Ver metodología de cálculo" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      Metodología
    </a>
  </div>

  <!-- ── Teacher selector ── -->
  <div class="card selector-card">
    <div class="card-head">
      <span class="card-title">Seleccionar Docente</span>
    </div>
    <div class="selector-body">
      <div class="fg selector-field">
        <label for="rdDocente">Docente</label>
        <div class="fw">
          <select class="fc" id="rdDocente" onchange="onDocenteChange()">
            <option value="">— Seleccione un docente —</option>
            <?php if (!empty($docentes)): foreach ($docentes as $d): ?>
            <option value="<?= $d['id_usuario'] ?>">
              <?= htmlspecialchars($d['nombre_completo']) ?>
              <small>(<?= htmlspecialchars($d['email']) ?>)</small>
            </option>
            <?php endforeach; endif; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Mode toggle ── -->
  <div id="rdModeRow" style="display:none;margin-bottom:20px;">
    <div class="card" style="padding:12px 20px;">
      <div class="mode-toggles">
        <button class="mode-btn active" data-mode="mine" onclick="setRdMode('mine')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Mis evaluaciones
          <small>Evaluaciones que realizó este docente</small>
        </button>
        <button class="mode-btn" data-mode="all" onclick="setRdMode('all')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Todos los alumnos
          <small>Alumnos asignados con todas sus evaluaciones</small>
        </button>
      </div>
    </div>
  </div>

  <!-- ── Report content (hidden until teacher selected) ── -->
  <div id="rdContent" style="display:none">

    <!-- Summary stats -->
    <div class="stats" id="rdStats"></div>

    <!-- Filters -->
    <div class="card" id="rdFilters">
      <div class="card-head">
        <span class="card-title">Filtros</span>
      </div>
      <div class="filters-body">
        <div class="fg filter-field">
          <label for="rdSearch">Buscar alumno</label>
          <input type="text" class="fc" id="rdSearch" placeholder="Nombre o matrícula…" oninput="filterRdTable()">
        </div>
        <div class="fg filter-field">
          <label for="rdFilterScore">Promedio mínimo</label>
          <select class="fc" id="rdFilterScore" onchange="filterRdTable()">
            <option value="0">Todos</option>
            <option value="9">≥ 9.0</option>
            <option value="8">≥ 8.0</option>
            <option value="7">≥ 7.0</option>
            <option value="6">≥ 6.0</option>
            <option value="5">≥ 5.0</option>
          </select>
        </div>
        <div class="fg filter-field">
          <label for="rdFilterEvals">Mín. evaluaciones</label>
          <select class="fc" id="rdFilterEvals" onchange="filterRdTable()">
            <option value="0">Todos</option>
            <option value="1">≥ 1</option>
            <option value="3">≥ 3</option>
            <option value="5">≥ 5</option>
            <option value="10">≥ 10</option>
          </select>
        </div>
        <div class="fg filter-field">
          <label for="rdFilterTrend">Tendencia</label>
          <select class="fc" id="rdFilterTrend" onchange="filterRdTable()">
            <option value="">Todas</option>
            <option value="up">↑ Mejora constante</option>
            <option value="flat">→ Estable</option>
            <option value="down">↓ Requiere atención</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Students table -->
    <div class="card">
      <div class="card-head">
        <span class="card-title">Alumnos y métricas</span>
        <div class="card-head-actions">
          <span class="result-count" id="rdResultCount"></span>
          <button class="btn btn-gold btn-sm" onclick="exportRdExcel()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Exportar Excel
          </button>
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl" id="rdTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Matrícula</th>
              <th>Nombre Completo</th>
              <th style="width:70px">Eval.</th>
              <th style="width:90px">Promedio</th>
              <th style="width:100px">Tendencia</th>
              <th style="width:70px">σ</th>
              <th style="width:60px">Detalle</th>
            </tr>
          </thead>
          <tbody id="rdTbody"></tbody>
        </table>
      </div>
    </div>

    <!-- Detail modal -->
    <div class="modal-overlay" id="rdModalOverlay" onclick="closeRdModal()"></div>
    <div class="modal-wrap" id="rdModalWrap">
      <div class="modal-box" id="rdModalBox">
        <div class="modal-head">
          <span class="modal-title" id="rdModalTitle">Detalle del Alumno</span>
          <button class="modal-close" onclick="closeRdModal()">&times;</button>
        </div>
        <div class="modal-body" id="rdModalBody"></div>
      </div>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     SCRIPT
     ═══════════════════════════════════════════════════════════════ -->
<script>
let rdData = null;          // full response from server
let rdMode = 'mine';        // current mode
let rdAlumnos = [];         // filtered list
let rdExpandedRow = null;   // currently expanded row index

function onDocenteChange() {
  const sel = document.getElementById('rdDocente');
  const id = sel.value;
  if (!id) {
    document.getElementById('rdModeRow').style.display = 'none';
    document.getElementById('rdContent').style.display = 'none';
    return;
  }
  document.getElementById('rdModeRow').style.display = 'block';
  loadRdData(id, rdMode);
}

function setRdMode(mode) {
  rdMode = mode;
  document.querySelectorAll('.mode-btn').forEach(function(b) {
    b.classList.toggle('active', b.dataset.mode === mode);
  });
  const sel = document.getElementById('rdDocente');
  if (sel.value) loadRdData(sel.value, mode);
}

function loadRdData(docenteId, mode) {
  showLoader('Cargando reporte por docente…');
  fetch('<?= base_url('admin/reportes/docente-data') ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ docente_id: parseInt(docenteId), modo: mode })
  })
  .then(function(r) { return r.json(); })
  .then(function(j) {
    hideLoader();
    if (!j.success) { toast(j.message, false); return; }
    rdData = j;
    rdAlumnos = j.alumnos || [];
    renderRdReport(j);
    document.getElementById('rdContent').style.display = 'block';
  })
  .catch(function(e) {
    hideLoader();
    toast('Error al cargar reporte por docente.', false);
  });
}

function renderRdReport(d) {
  const modoLabel = d.modo === 'mine' ? 'Mis evaluaciones' : 'Todos los alumnos';
  const res = d.resumen;

  // ── Stats ──
  const trendVal = d.modo === 'mine' ? calcTeacherTrend(d.alumnos) : 0;
  const trendIcon = trendVal > 0
    ? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>'
    : '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>';

  document.getElementById('rdStats').innerHTML =
    '<div class="stat"><div class="stat-info"><span class="stat-label">Alumnos</span><span class="stat-num">' + res.total_alumnos + '</span></div><div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>' +
    '<div class="stat gold"><div class="stat-info"><span class="stat-label">Con evaluaciones</span><span class="stat-num">' + res.alumnos_con_evaluaciones + '</span></div><div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 2 4 3 6 3s6-1 6-3v-5"/></svg></div></div>' +
    '<div class="stat"><div class="stat-info"><span class="stat-label">' + modoLabel + '</span><span class="stat-num">' + res.total_evaluaciones + '</span></div><div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></div></div>' +
    '<div class="stat gold"><div class="stat-info"><span class="stat-label">Promedio General</span><span class="stat-num">' + res.promedio_general + '<small style="font-size:14px;color:var(--slate);font-weight:600">/10</small></span></div><div class="stat-icon">' + trendIcon + '</div></div>';

  // ── Table ──
  renderRdTable();
}

function calcTeacherTrend(alumnos) {
  var sum = 0, count = 0;
  alumnos.forEach(function(a) {
    if (a.indices && a.indices.trend) { sum += a.indices.trend; count++; }
  });
  return count > 0 ? (sum / count) : 0;
}

function renderRdTable() {
  var tbody = document.getElementById('rdTbody');
  var filterText = (document.getElementById('rdSearch').value || '').toLowerCase();
  var minScore = parseFloat(document.getElementById('rdFilterScore').value) || 0;
  var minEvals = parseInt(document.getElementById('rdFilterEvals').value) || 0;
  var filterTrend = document.getElementById('rdFilterTrend').value;

  var filtered = rdAlumnos.filter(function(a) {
    var idx = a.indices || {};
    var name = (a.nombre_completo || '').toLowerCase();
    var mat = (a.matricula || '').toLowerCase();
    var queryMatch = !filterText || name.indexOf(filterText) !== -1 || mat.indexOf(filterText) !== -1;
    var scoreMatch = idx.promedio_display >= minScore;
    var evalsMatch = idx.total_evaluaciones >= minEvals;
    var trendMatch = true;
    if (filterTrend === 'up') trendMatch = (idx.trend || 0) > 0.5;
    else if (filterTrend === 'flat') trendMatch = (idx.trend || 0) >= -0.5 && (idx.trend || 0) <= 0.5;
    else if (filterTrend === 'down') trendMatch = (idx.trend || 0) < -0.5;
    return queryMatch && scoreMatch && evalsMatch && trendMatch;
  });

  rdAlumnos = filtered;
  document.getElementById('rdResultCount').textContent = filtered.length + ' alumno' + (filtered.length !== 1 ? 's' : '');

  if (filtered.length === 0) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="8" style="text-align:center;padding:30px;color:var(--slate)">Ningún alumno coincide con los filtros.</td></tr>';
    return;
  }

  var html = '';
  filtered.forEach(function(a, i) {
    var idx = a.indices || {};
    var score = idx.promedio_display || 0;
    var totalEv = idx.total_evaluaciones || 0;
    var trend = idx.trend || 0;
    var trendText = idx.trend_text || 'Estable';
    var trendIcon = trend > 0.5 ? '↑' : (trend < -0.5 ? '↓' : '→');
    var trendCls = trend > 0.5 ? 'trend-up' : (trend < -0.5 ? 'trend-down' : 'trend-flat');
    var consistencia = idx.consistencia || 0;
    var consistenciaText = idx.consistencia_text || '—';
    var fuerte = htmlspecialchars(idx.competencia_fuerte || '—');
    var debil = htmlspecialchars(idx.competencia_debil || '—');
    var scoreColor = score >= 7 ? 'var(--green)' : (score >= 5 ? 'var(--gold-vivid)' : 'var(--rose)');
    var evBadge = totalEv === 0 ? 'badge-red' : (totalEv >= 3 ? 'badge-green' : 'badge-gold');

    html += '<tr class="rd-row" onclick="toggleRdDetail(' + i + ')" data-index="' + i + '">' +
      '<td>' + (i + 1) + '</td>' +
      '<td><code>' + htmlspecialchars(a.matricula) + '</code></td>' +
      '<td><strong>' + htmlspecialchars(a.nombre_completo) + '</strong><br><small style="color:var(--text-muted)">' + htmlspecialchars(a.semestre_grupo || '') + '</small></td>' +
      '<td style="text-align:center"><span class="badge ' + evBadge + '">' + totalEv + '</span></td>' +
      '<td style="text-align:center;font-weight:700;color:' + scoreColor + '">' + (totalEv > 0 ? score : '—') + '</td>' +
      '<td style="text-align:center"><span class="trend-badge ' + trendCls + '" style="font-size:22px!important" title="' + trendText + ' (' + trend.toFixed(2) + ')">' + trendIcon + '</span></td>' +
      '<td style="text-align:center;font-size:13px;color:var(--text-muted)" title="' + consistenciaText + '">' + (totalEv > 0 ? consistencia.toFixed(1) : '—') + '</td>' +
      '<td style="text-align:center"><button class="btn btn-sm btn-outline-blue" onclick="event.stopPropagation();toggleRdDetail(' + i + ')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Ver</button></td>' +
      '</tr>';

    // Detail row (hidden by default)
    var isExpanded = rdExpandedRow === i;
    if (isExpanded) {
      html += '<tr class="rd-detail-row"><td colspan="8"><div class="rd-detail-inner">' + buildRdDetail(a) + '</div></td></tr>';
    }
  });

  tbody.innerHTML = html;
}

function toggleRdDetail(index) {
  if (rdExpandedRow === index) {
    rdExpandedRow = null;
  } else {
    rdExpandedRow = index;
  }
  renderRdTable();
}

function buildRdDetail(a) {
  var idx = a.indices || {};
  var evals = a.evaluaciones || [];
  var comps = a.competencias || [];
  var complejidad = a.complejidad || [];
  var areas = idx.top_areas_mejora || {};

  var areasHtml = '';
  var areaKeys = Object.keys(areas);
  if (areaKeys.length > 0) {
    areaKeys.forEach(function(w) {
      areasHtml += '<span class="mejora-tag" style="font-size:12px;padding:4px 12px;">' + htmlspecialchars(w) + ' (' + areas[w] + ')</span>';
    });
  } else {
    areasHtml = '<span style="color:var(--text-muted);font-size:13px;">Sin áreas registradas.</span>';
  }

  var evHtml = '';
  if (evals.length === 0) {
    evHtml = '<span style="color:var(--text-muted)">Sin evaluaciones.</span>';
  } else {
    evHtml += '<div class="tbl-wrap"><table class="tbl tbl-sm"><thead><tr><th>#</th><th>Fecha</th><th>Entorno</th><th>Complejidad</th><th>Puntaje</th></tr></thead><tbody>';
    evals.forEach(function(ev, ei) {
      var cb = ev.complejidad === 'Alta' ? 'badge-red' : (ev.complejidad === 'Media' ? 'badge-gold' : 'badge-green');
      evHtml += '<tr><td>' + (ei + 1) + '</td><td>' + htmlspecialchars(ev.fecha_evaluacion || '—') + '</td><td>' + htmlspecialchars(ev.entorno_clinico || '—') + '</td><td><span class="badge ' + cb + '">' + htmlspecialchars(ev.complejidad || '—') + '</span></td><td style="font-weight:700">' + (parseFloat(ev.calificacion_total) / 10).toFixed(1) + '/10</td></tr>';
    });
    evHtml += '</tbody></table></div>';
  }

  var compHtml = '';
  if (comps.length > 0) {
    compHtml += '<div class="rd-comp-grid">';
    comps.forEach(function(c) {
      var pct = (parseFloat(c.promedio) / 9 * 100).toFixed(0);
      var barColor = pct >= 70 ? 'var(--green)' : (pct >= 40 ? 'var(--gold-vivid)' : 'var(--rose)');
      compHtml += '<div class="rd-comp-item"><div class="rd-comp-label">' + htmlspecialchars(c.competencia) + '</div><div class="rd-comp-bar-wrap"><div class="rd-comp-bar" style="width:' + pct + '%;background:' + barColor + '"></div></div><div class="rd-comp-val">' + parseFloat(c.promedio).toFixed(1) + '</div></div>';
    });
    compHtml += '</div>';
  }

  return '<div class="rd-detail-grid">' +
    '<div class="rd-detail-section"><div class="rd-detail-section-title">Evaluaciones</div>' + evHtml + '</div>' +
    (compHtml ? '<div class="rd-detail-section"><div class="rd-detail-section-title">Competencias (promedio)</div>' + compHtml + '</div>' : '') +
    (areaKeys.length > 0 ? '<div class="rd-detail-section"><div class="rd-detail-section-title">Áreas de Mejora</div><div style="display:flex;flex-wrap:wrap;gap:6px;">' + areasHtml + '</div></div>' : '') +
    '</div>';
}

// ── Modal for full detail ──
function openRdModal(a) {
  var modal = document.getElementById('rdModalOverlay');
  var wrap = document.getElementById('rdModalWrap');
  document.getElementById('rdModalTitle').textContent = a.nombre_completo + ' (' + a.matricula + ')';
  document.getElementById('rdModalBody').innerHTML = buildRdDetail(a);
  modal.classList.add('show');
  wrap.classList.add('show');
}

function closeRdModal() {
  document.getElementById('rdModalOverlay').classList.remove('show');
  document.getElementById('rdModalWrap').classList.remove('show');
}

// ── Filters ──
function filterRdTable() {
  renderRdTable();
}

// ── Excel Export ──
function exportRdExcel() {
  if (!rdData || rdAlumnos.length === 0) {
    toast('No hay datos para exportar.', false);
    return;
  }
  if (typeof XLSX === 'undefined') {
    toast('Librería XLSX no disponible.', false);
    return;
  }
  showLoader('Generando Excel por docente…');
  try {
    var d = rdData;
    var wb = XLSX.utils.book_new();

    // Styles
    var TITLE = { font: { bold: true, sz: 16, color: { rgb: 'FFFFFF' }, name: 'Calibri' }, fill: { fgColor: { rgb: '1B5E96' } }, alignment: { horizontal: 'center', vertical: 'center' } };
    var SUB = { font: { bold: true, sz: 12, color: { rgb: 'FFFFFF' }, name: 'Calibri' }, fill: { fgColor: { rgb: 'B8860B' } }, alignment: { horizontal: 'left', vertical: 'center' } };
    var HDR = { font: { bold: true, sz: 10, color: { rgb: 'FFFFFF' }, name: 'Calibri' }, fill: { fgColor: { rgb: '2C3E50' } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: { top: { style: 'thin', color: { rgb: 'CCCCCC' } }, bottom: { style: 'thin', color: { rgb: 'CCCCCC' } }, left: { style: 'thin', color: { rgb: 'CCCCCC' } }, right: { style: 'thin', color: { rgb: 'CCCCCC' } } } };
    var LABEL = { font: { bold: true, sz: 10, color: { rgb: '333333' }, name: 'Calibri' }, fill: { fgColor: { rgb: 'F5F5F5' } }, alignment: { horizontal: 'left', vertical: 'center' }, border: { top: { style: 'thin', color: { rgb: 'E0E0E0' } }, bottom: { style: 'thin', color: { rgb: 'E0E0E0' } }, left: { style: 'thin', color: { rgb: 'E0E0E0' } }, right: { style: 'thin', color: { rgb: 'E0E0E0' } } } };
    var VAL = { font: { sz: 10, color: { rgb: '333333' }, name: 'Calibri' }, alignment: { horizontal: 'left', vertical: 'center' }, border: { top: { style: 'thin', color: { rgb: 'E0E0E0' } }, bottom: { style: 'thin', color: { rgb: 'E0E0E0' } }, left: { style: 'thin', color: { rgb: 'E0E0E0' } }, right: { style: 'thin', color: { rgb: 'E0E0E0' } } } };
    var CELL = { font: { sz: 10, color: { rgb: '333333' }, name: 'Calibri' }, alignment: { horizontal: 'center', vertical: 'center' }, border: { top: { style: 'thin', color: { rgb: 'E0E0E0' } }, bottom: { style: 'thin', color: { rgb: 'E0E0E0' } }, left: { style: 'thin', color: { rgb: 'E0E0E0' } }, right: { style: 'thin', color: { rgb: 'E0E0E0' } } } };
    var GREEN = { font: { bold: true, sz: 10, color: { rgb: '22C55E' }, name: 'Calibri' }, fill: { fgColor: { rgb: 'F0FFF4' } }, alignment: { horizontal: 'center', vertical: 'center' } };
    var GOLD = { font: { bold: true, sz: 10, color: { rgb: 'B8860B' }, name: 'Calibri' }, fill: { fgColor: { rgb: 'FFF8E1' } }, alignment: { horizontal: 'center', vertical: 'center' } };
    var RED = { font: { bold: true, sz: 10, color: { rgb: 'EF4444' }, name: 'Calibri' }, fill: { fgColor: { rgb: 'FFF0F0' } }, alignment: { horizontal: 'center', vertical: 'center' } };

    // ── Sheet 1: Resumen Docente ──
    var s1 = [
      ['REPORTE POR DOCENTE — MINI-CEX'],
      [],
      ['DATOS DEL DOCENTE'],
      ['Nombre', d.docente.nombre_completo],
      ['Correo', d.docente.email || '—'],
      ['Modo', d.modo === 'mine' ? 'Evaluaciones realizadas por el docente' : 'Todos los alumnos asignados'],
      ['Fecha de Reporte', new Date().toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' })],
      [],
      ['RESUMEN ESTADÍSTICO'],
      ['Total de Alumnos', String(d.resumen.total_alumnos)],
      ['Alumnos con Evaluaciones', String(d.resumen.alumnos_con_evaluaciones)],
      ['Total de Evaluaciones', String(d.resumen.total_evaluaciones)],
      ['Promedio General', d.resumen.promedio_general + ' / 10'],
    ];
    var ws1 = XLSX.utils.aoa_to_sheet(s1);
    ws1['A1'].s = TITLE;
    ws1['A3'].s = SUB;
    ws1['A9'].s = SUB;
    for (var i = 3; i <= 6; i++) { var lc = XLSX.utils.encode_cell({ r: i, c: 0 }); var vc = XLSX.utils.encode_cell({ r: i, c: 1 }); if (ws1[lc]) ws1[lc].s = LABEL; if (ws1[vc]) ws1[vc].s = VAL; }
    for (var i = 9; i <= 12; i++) { var lc = XLSX.utils.encode_cell({ r: i, c: 0 }); var vc = XLSX.utils.encode_cell({ r: i, c: 1 }); if (ws1[lc]) ws1[lc].s = LABEL; if (ws1[vc]) ws1[vc].s = VAL; }
    ws1['!merges'] = [
      { s: { r: 0, c: 0 }, e: { r: 0, c: 5 } },
      { s: { r: 2, c: 0 }, e: { r: 2, c: 5 } },
      { s: { r: 8, c: 0 }, e: { r: 8, c: 5 } },
    ];
    ws1['!cols'] = [{ wch: 30 }, { wch: 50 }, { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 14 }];
    XLSX.utils.book_append_sheet(wb, ws1, 'Resumen Docente');

    // ── Sheet 2: Detalle de Alumnos ──
    var s2 = [['DETALLE DE ALUMNOS'], [], ['#', 'Matrícula', 'Nombre', 'Semestre', 'Evals', 'Promedio', 'Tendencia', 'Trend Text', 'Consistencia', 'Competencia Fuerte', 'Competencia Débil', 'Progreso']];
    d.alumnos.forEach(function(a, i) {
      var idx = a.indices || {};
      var trendIcon = (idx.trend || 0) > 0.5 ? '↑' : ((idx.trend || 0) < -0.5 ? '↓' : '→');
      s2.push([
        String(i + 1), a.matricula, a.nombre_completo, a.semestre_grupo || '—',
        String(idx.total_evaluaciones || 0),
        idx.total_evaluaciones > 0 ? (idx.promedio_display || 0).toFixed(1) + ' / 10' : '—',
        trendIcon,
        idx.trend_text || '—',
        idx.total_evaluaciones > 0 ? String(idx.consistencia || 0) : '—',
        idx.competencia_fuerte || '—',
        idx.competencia_debil || '—',
        idx.total_evaluaciones >= 4 ? (idx.progreso_text || '—') + ' (' + (idx.progreso || 0).toFixed(1) + ')' : '—',
      ]);
    });
    var ws2 = XLSX.utils.aoa_to_sheet(s2);
    ws2['A1'].s = TITLE;
    for (var c = 0; c < 12; c++) { var hc = XLSX.utils.encode_cell({ r: 2, c: c }); if (ws2[hc]) ws2[hc].s = HDR; }
    for (var r = 3; r < s2.length; r++) {
      for (var c = 0; c < 12; c++) {
        var cr = XLSX.utils.encode_cell({ r: r, c: c });
        if (ws2[cr]) {
          ws2[cr].s = (c <= 1 || c === 3) ? CELL : (c === 2 ? VAL : CELL);
          if (c === 6 && ws2[cr]) {
            var ic = ws2[cr].v;
            if (ic === '↑') ws2[cr].s = GREEN;
            else if (ic === '↓') ws2[cr].s = RED;
          }
        }
      }
    }
    ws2['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 11 } }];
    ws2['!cols'] = [{ wch: 5 }, { wch: 16 }, { wch: 28 }, { wch: 14 }, { wch: 7 }, { wch: 12 }, { wch: 10 }, { wch: 18 }, { wch: 14 }, { wch: 24 }, { wch: 24 }, { wch: 20 }];
    XLSX.utils.book_append_sheet(wb, ws2, 'Detalle Alumnos');

    // ── Sheet 3: Evaluaciones (all) ──
    var s3 = [['EVALUACIONES — ' + (d.modo === 'mine' ? 'Realizadas por el docente' : 'Todas las de los alumnos asignados')], [], ['Alumno', 'Matrícula', 'Fecha', 'Evaluador', 'Entorno', 'Complejidad', 'T.Obs', 'T.Feedback', 'Calificación Total', '/10']];
    d.alumnos.forEach(function(a) {
      (a.evaluaciones || []).forEach(function(ev) {
        s3.push([
          a.nombre_completo, a.matricula,
          ev.fecha_evaluacion || '—',
          ev.evaluador_nombre || '—',
          ev.entorno_clinico || '—',
          ev.complejidad || '—',
          ev.tiempo_observacion ? ev.tiempo_observacion + ' min' : '—',
          ev.tiempo_feedback ? ev.tiempo_feedback + ' min' : '—',
          String(parseFloat(ev.calificacion_total).toFixed(1)),
          (parseFloat(ev.calificacion_total) / 10).toFixed(1) + ' / 10',
        ]);
      });
    });
    var ws3 = XLSX.utils.aoa_to_sheet(s3);
    ws3['A1'].s = TITLE;
    for (var c = 0; c < 10; c++) { var hc = XLSX.utils.encode_cell({ r: 2, c: c }); if (ws3[hc]) ws3[hc].s = HDR; }
    for (var r = 3; r < s3.length; r++) {
      for (var c = 0; c < 10; c++) {
        var cr = XLSX.utils.encode_cell({ r: r, c: c });
        if (ws3[cr]) {
          ws3[cr].s = (c <= 1) ? VAL : CELL;
          if (c === 5 && ws3[cr]) {
            var cv = ws3[cr].v;
            if (cv === 'Alta') ws3[cr].s = RED;
            else if (cv === 'Media') ws3[cr].s = GOLD;
            else if (cv === 'Baja') ws3[cr].s = GREEN;
          }
        }
      }
    }
    ws3['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 9 } }];
    ws3['!cols'] = [{ wch: 26 }, { wch: 14 }, { wch: 14 }, { wch: 26 }, { wch: 18 }, { wch: 14 }, { wch: 10 }, { wch: 10 }, { wch: 14 }, { wch: 10 }];
    XLSX.utils.book_append_sheet(wb, ws3, 'Evaluaciones');

    var filename = 'reporte_docente_' + d.docente.nombre_completo.replace(/[^a-zA-Z0-9]/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.xlsx';
    XLSX.writeFile(wb, filename);
    hideLoader();
    toast('Archivo XLSX descargado correctamente.', true);
  } catch (e) {
    hideLoader();
    toast('Error al generar Excel: ' + e.message, false);
  }
}

function htmlspecialchars(str) {
  if (!str) return '';
  var d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
</script>

<!-- ═══════════════════════════════════════════════════════════════
     STYLE (scoped to this tab)
     ═══════════════════════════════════════════════════════════════ -->
<style>
/* ── Selector ── */
#tab-reportes-docente .selector-card .selector-body {
  display: flex;
  gap: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
  padding: 0 4px 4px;
}
#tab-reportes-docente .selector-card .selector-field {
  flex: 2;
  min-width: 320px;
  margin-bottom: 0;
}

/* ── Mode toggles ── */
#tab-reportes-docente .mode-toggles {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
#tab-reportes-docente .mode-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-subtle);
  background: var(--bg-glass);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all var(--speed) var(--ease);
  font-size: 14px;
  font-weight: 600;
  font-family: var(--font);
  flex: 1;
  min-width: 200px;
  text-align: left;
}
#tab-reportes-docente .mode-btn:hover {
  border-color: var(--border-medium);
  background: rgba(255,255,255,0.05);
}
#tab-reportes-docente .mode-btn.active {
  border-color: var(--blue);
  background: rgba(59,130,246,0.1);
  color: #fff;
}
#tab-reportes-docente .mode-btn small {
  display: block;
  font-size: 11px;
  font-weight: 400;
  color: var(--text-muted);
  margin-top: 2px;
}
#tab-reportes-docente .mode-btn.active small {
  color: var(--text-secondary);
}

/* ── Filters ── */
#tab-reportes-docente .filters-body {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  padding: 0 4px 4px;
}
#tab-reportes-docente .filter-field {
  flex: 1;
  min-width: 160px;
  margin-bottom: 0;
}
#tab-reportes-docente .filter-field label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  font-weight: 700;
}
#tab-reportes-docente .result-count {
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 600;
}

/* ── Card head actions ── */
#tab-reportes-docente .card-head-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* ── Table overrides ── */
#tab-reportes-docente .tbl td {
  vertical-align: middle;
}
#tab-reportes-docente .tbl code {
  font-family: var(--font-mono);
  font-size: 12px;
  background: rgba(255,255,255,0.05);
  padding: 2px 6px;
  border-radius: 4px;
}
#tab-reportes-docente .tbl .rd-row {
  cursor: pointer;
  transition: background var(--speed);
}
#tab-reportes-docente .tbl .rd-row:hover {
  background: rgba(59,130,246,0.04);
}
#tab-reportes-docente .tbl .rd-row td:first-child {
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 600;
}

/* ── Detail expanded row ── */
#tab-reportes-docente .rd-detail-row td {
  padding: 0;
  background: rgba(255,255,255,0.015);
  border-bottom: 1px solid var(--border-subtle);
}
#tab-reportes-docente .rd-detail-inner {
  padding: 20px 24px;
  animation: fadeIn .3s ease;
}

#tab-reportes-docente .rd-detail-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
#tab-reportes-docente .rd-detail-section {
  background: var(--bg-glass);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
}
#tab-reportes-docente .rd-detail-section-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  margin-bottom: 10px;
}

/* ── Competency bars in detail ── */
#tab-reportes-docente .rd-comp-grid {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
#tab-reportes-docente .rd-comp-item {
  display: flex;
  align-items: center;
  gap: 10px;
}
#tab-reportes-docente .rd-comp-label {
  flex: 0 0 160px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-primary);
  text-align: right;
}
#tab-reportes-docente .rd-comp-bar-wrap {
  flex: 1;
  height: 18px;
  background: rgba(255,255,255,0.05);
  border-radius: 4px;
  overflow: hidden;
}
#tab-reportes-docente .rd-comp-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.5s var(--ease);
}
#tab-reportes-docente .rd-comp-val {
  flex: 0 0 40px;
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
  text-align: right;
}

/* ── Small table in detail ── */
#tab-reportes-docente .tbl-sm th {
  font-size: 10px;
  padding: 6px 10px;
}
#tab-reportes-docente .tbl-sm td {
  padding: 6px 10px;
  font-size: 12px;
}

/* ── Modal ── */
#tab-reportes-docente .modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  z-index: 200;
  backdrop-filter: blur(4px);
}
#tab-reportes-docente .modal-overlay.show { display: block; }

#tab-reportes-docente .modal-wrap {
  display: none;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%,-50%);
  z-index: 201;
  width: 90%;
  max-width: 800px;
  max-height: 85vh;
  overflow-y: auto;
}
#tab-reportes-docente .modal-wrap.show { display: block; }

#tab-reportes-docente .modal-box {
  background: var(--bg-card);
  border: 1px solid var(--bg-glass-border);
  border-radius: var(--radius);
  box-shadow: 0 25px 60px rgba(0,0,0,0.5);
  overflow: hidden;
}
#tab-reportes-docente .modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid var(--bg-glass-border);
}
#tab-reportes-docente .modal-title {
  font-family: var(--font-heading);
  font-size: 18px;
  font-weight: 700;
  color: #fff;
}
#tab-reportes-docente .modal-close {
  background: none;
  border: none;
  color: var(--text-muted);
  font-size: 28px;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  transition: color var(--speed);
}
#tab-reportes-docente .modal-close:hover { color: #fff; }
#tab-reportes-docente .modal-body {
  padding: 24px;
}

/* ── Trend colors ── */
#tab-reportes-docente .trend-up { color: #22c55e; }
#tab-reportes-docente .trend-down { color: #ef4444; }
#tab-reportes-docente .trend-flat { color: #eab308; }

/* ── Badge overrides ── */
#tab-reportes-docente .badge-green { background: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.2); }
#tab-reportes-docente .badge-red { background: rgba(244,63,94,0.12); color: #f43f5e; border: 1px solid rgba(244,63,94,0.2); }
#tab-reportes-docente .badge-gold { background: rgba(251,191,36,0.12); color: #eab308; border: 1px solid rgba(251,191,36,0.2); }

/* ── Animations ── */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 768px) {
  #tab-reportes-docente .filters-body { flex-direction: column; }
  #tab-reportes-docente .filter-field { min-width: 0; }
  #tab-reportes-docente .mode-toggles { flex-direction: column; }
  #tab-reportes-docente .mode-btn { min-width: 0; }
  #tab-reportes-docente .rd-comp-label { flex: 0 0 100px; font-size: 11px; }
  #tab-reportes-docente .card-head-actions { flex-wrap: wrap; }
}
</style>
