<!-- ═══════ TAB: REPORTES POR ALUMNO ═══════ -->
<section id="tab-reportes" class="tab" style="display:none">
  <div class="page-head">
    <div>
      <h1>Reportes por Alumno</h1>
      <p>Análisis consolidado de evaluaciones, tendencias y métricas de mejora</p>
    </div>
    <a href="<?= base_url('admin/metodologia') ?>" target="_blank" class="btn btn-sm btn-outline-blue" title="Ver metodología de cálculo" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      Metodología
    </a>
  </div>

  <!-- ── Student selector ── -->
  <div class="card selector-card">
    <div class="card-head">
      <span class="card-title">Seleccionar Alumno</span>
    </div>
    <div class="selector-body">
      <div class="fg selector-field">
        <label for="rpAlumno">Alumno</label>
        <div class="fw">
          <select class="fc" id="rpAlumno" onchange="loadReporte(this.value)">
            <option value="">— Seleccione un alumno —</option>
            <?php if (!empty($alumnos)): foreach ($alumnos as $a): ?>
            <option value="<?= $a['id_alumno'] ?>">
              <?= htmlspecialchars($a['matricula'] . ' — ' . $a['nombre_completo']) ?>
              <small>(<?= htmlspecialchars($a['docente_nombre'] ?? 'Sin docente') ?>)</small>
            </option>
            <?php endforeach; endif; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-gold btn-export" id="btnExportExcelRp" style="display:none" onclick="exportExcelReporte()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Exportar Excel
      </button>
    </div>
  </div>

  <!-- ── Report content (hidden until student selected) ── -->
  <div id="rpContent" style="display:none">

    <!-- Summary stats -->
    <div class="stats" id="rpStats"></div>

    <!-- Improvement indices -->
    <div class="card">
      <div class="card-head">
        <span class="card-title">Índices de Mejora</span>
      </div>
      <div class="indices-grid" id="rpIndices"></div>
    </div>

    <!-- Charts row 1 -->
    <div class="grid-2 chart-grid">
      <div class="card chart-card">
        <div class="card-head"><span class="card-title">Evolución de Calificaciones</span></div>
        <div class="chart-wrap"><canvas id="chartEvolucion"></canvas></div>
      </div>
      <div class="card chart-card">
        <div class="card-head"><span class="card-title">Radar de Competencias</span></div>
        <div class="chart-wrap"><canvas id="chartRadar"></canvas></div>
      </div>
    </div>

    <!-- Charts row 2 -->
    <div class="grid-2 chart-grid">
      <div class="card chart-card">
        <div class="card-head"><span class="card-title">Complejidad de Casos</span></div>
        <div class="chart-wrap"><canvas id="chartComplejidad"></canvas></div>
      </div>
      <div class="card chart-card">
        <div class="card-head"><span class="card-title">Tiempos (Observación vs Feedback)</span></div>
        <div class="chart-wrap"><canvas id="chartTiempos"></canvas></div>
      </div>
    </div>

    <!-- Areas to improve -->
    <div class="card">
      <div class="card-head">
        <span class="card-title">Áreas de Mejora Recurrentes</span>
      </div>
      <div class="mejoras-body" id="rpMejorasTags">
        <span class="mejoras-placeholder">Seleccione un alumno para ver sus áreas de mejora.</span>
      </div>
    </div>

    <!-- Detailed evaluations table -->
    <div class="card">
      <div class="card-head">
        <span class="card-title">Detalle de Evaluaciones</span>
        <div class="search-wrap">
          <input type="text" id="searchRpEv" oninput="filterTbl('rpEvTable','searchRpEv',7)" placeholder="Buscar...">
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl" id="rpEvTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Fecha</th>
              <th>Evaluador</th>
              <th>Entorno</th>
              <th>Complejidad</th>
              <th>Puntaje</th>
              <th>Detalles</th>
            </tr>
          </thead>
          <tbody id="rpEvBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════
     SCRIPT
     ═══════════════════════════════════════════════════ -->
<script>
let chartEvolucion = null, chartRadar = null, chartComplejidad = null, chartTiempos = null;
let currentReporteData = null;

function loadReporte(alumnoId) {
  if (!alumnoId) {
    document.getElementById('rpContent').style.display = 'none';
    document.getElementById('btnExportExcelRp').style.display = 'none';
    return;
  }
  showLoader('Cargando reporte...');
  fetch('<?= base_url('admin/reportes/data') ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ alumno_id: parseInt(alumnoId) })
  })
  .then(r => r.json())
  .then(j => {
    hideLoader();
    if (!j.success) { toast(j.message, false); return; }
    currentReporteData = j;
    renderReporte(j);
    document.getElementById('btnExportExcelRp').style.display = '';
  })
  .catch(e => { hideLoader(); toast('Error al cargar reporte.', false); });
}

function renderReporte(d) {
  const s = d.student, evs = d.evaluaciones, comps = d.competencias, idx = d.indices;

  // ── Stats cards ──
  const trendIcon = idx.trend > 0
    ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>'
    : idx.trend < 0
    ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>'
    : '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>';

  document.getElementById('rpStats').innerHTML = `
    <div class="stat">
      <div class="stat-info">
        <span class="stat-label">Evaluaciones</span>
        <span class="stat-num">${idx.total_evaluaciones}</span>
      </div>
      <div class="stat-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
      </div>
    </div>
    <div class="stat gold">
      <div class="stat-info">
        <span class="stat-label">Promedio General</span>
        <span class="stat-num">${idx.promedio_display}<small style="font-size:14px;color:var(--slate);font-weight:600">/10</small></span>
      </div>
      <div class="stat-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
      </div>
    </div>
    <div class="stat">
      <div class="stat-info">
        <span class="stat-label">Tendencia</span>
        <span class="stat-num trend-badge ${idx.trend > 0 ? 'trend-up' : idx.trend < 0 ? 'trend-down' : 'trend-flat'}">
          ${idx.trend > 0 ? '↑' : idx.trend < 0 ? '↓' : '→'}
        </span>
      </div>
      <div class="stat-icon trend-icon">${trendIcon}</div>
    </div>
    <div class="stat gold">
      <div class="stat-info">
        <span class="stat-label">${idx.consistencia_text || 'Consistencia'}</span>
        <span class="stat-num" style="font-size:20px;letter-spacing:-0.5px">σ = ${idx.consistencia}</span>
      </div>
      <div class="stat-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
    </div>
  `;

  // ── Indices grid ──
  const progresoColor = idx.progreso > 0 ? '#22c55e' : (idx.progreso < 0 ? '#ef4444' : '#eab308');
  const progresoSigno = idx.progreso > 0 ? '+' : '';

  document.getElementById('rpIndices').innerHTML = `
    <div class="indice-card">
      <div class="indice-icon indice-trend">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="indice-label">Tendencia</div>
      <div class="indice-val">${idx.trend_text}</div>
      <div class="indice-sub">Pendiente: ${idx.trend > 0 ? '+' : ''}${typeof idx.trend === 'number' ? idx.trend.toFixed(3) : idx.trend}</div>
    </div>
    <div class="indice-card">
      <div class="indice-icon indice-strong">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/><polyline points="9 10 4 10 3 20 7 20"/></svg>
      </div>
      <div class="indice-label">Competencia más fuerte</div>
      <div class="indice-val strong-text">${idx.competencia_fuerte || '—'}</div>
    </div>
    <div class="indice-card">
      <div class="indice-icon indice-weak">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3H10z"/><polyline points="15 14 20 14 21 4 17 4"/></svg>
      </div>
      <div class="indice-label">Competencia más débil</div>
      <div class="indice-val weak-text">${idx.competencia_debil || '—'}</div>
    </div>
    <div class="indice-card">
      <div class="indice-icon indice-progress">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
      </div>
      <div class="indice-label">Progreso (últ.3 vs prim.3)</div>
      <div class="indice-val" style="color:${progresoColor}">${idx.progreso_text} (${progresoSigno}${typeof idx.progreso === 'number' ? idx.progreso.toFixed(1) : idx.progreso})</div>
    </div>
  `;

  // ── Chart: Evolution ──
  if (chartEvolucion) chartEvolucion.destroy();
  const evLabels = evs.map((e, i) => '#' + (i + 1) + ' ' + (e.fecha_evaluacion || '').substring(0, 10));
  const evScores = evs.map(e => parseFloat(e.calificacion_total) / 10);
  chartEvolucion = new Chart(document.getElementById('chartEvolucion'), {
    type: 'line',
    data: {
      labels: evLabels,
      datasets: [{
        label: 'Calificación /10',
        data: evScores,
        borderColor: '#1B5E96',
        backgroundColor: 'rgba(27,94,150,0.12)',
        fill: true,
        tension: 0.35,
        pointBackgroundColor: '#B8860B',
        pointBorderColor: '#B8860B',
        pointRadius: 5,
        pointHoverRadius: 7,
        borderWidth: 2.5,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#e2e8f0', font: { family: "'Inter', system-ui, sans-serif" } } },
        tooltip: {
          backgroundColor: 'rgba(10,17,40,0.9)',
          titleColor: '#e2e8f0',
          bodyColor: '#94a3b8',
          borderColor: 'rgba(255,255,255,0.06)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 8,
        }
      },
      scales: {
        x: {
          ticks: { color: '#94a3b8', maxRotation: 45, font: { size: 10 } },
          grid: { color: 'rgba(148,163,184,0.08)' }
        },
        y: {
          min: 0, max: 10,
          ticks: { color: '#94a3b8', font: { size: 10 } },
          grid: { color: 'rgba(148,163,184,0.08)' }
        }
      },
      interaction: { intersect: false, mode: 'index' }
    }
  });

  // ── Chart: Radar ──
  if (chartRadar) chartRadar.destroy();
  const compLabels = comps.map(c => c.competencia);
  const compValues = comps.map(c => parseFloat(c.promedio));
  chartRadar = new Chart(document.getElementById('chartRadar'), {
    type: 'radar',
    data: {
      labels: compLabels,
      datasets: [{
        label: 'Promedio por competencia',
        data: compValues,
        backgroundColor: 'rgba(27,94,150,0.2)',
        borderColor: '#1B5E96',
        pointBackgroundColor: '#B8860B',
        pointBorderColor: '#B8860B',
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#e2e8f0', font: { family: "'Inter', system-ui, sans-serif" } } },
        tooltip: {
          backgroundColor: 'rgba(10,17,40,0.9)',
          titleColor: '#e2e8f0',
          bodyColor: '#94a3b8',
          borderColor: 'rgba(255,255,255,0.06)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 8,
        }
      },
      scales: {
        r: {
          min: 0, max: 9,
          ticks: {
            color: '#94a3b8',
            backdropColor: 'transparent',
            font: { size: 10 },
            stepSize: 3,
          },
          grid: { color: 'rgba(148,163,184,0.15)' },
          angleLines: { color: 'rgba(148,163,184,0.15)' },
          pointLabels: {
            color: '#e2e8f0',
            font: { size: 11, family: "'Inter', system-ui, sans-serif", weight: '600' }
          }
        }
      },
      interaction: { intersect: false, mode: 'index' }
    }
  });

  // ── Chart: Complexity doughnut ──
  if (chartComplejidad) chartComplejidad.destroy();
  const compMap = { 'Baja': 0, 'Media': 0, 'Alta': 0 };
  (d.complejidad || []).forEach(c => { compMap[c.complejidad] = parseInt(c.count); });
  chartComplejidad = new Chart(document.getElementById('chartComplejidad'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(compMap),
      datasets: [{
        data: Object.values(compMap),
        backgroundColor: ['#22c55e', '#eab308', '#ef4444'],
        borderWidth: 0,
        hoverOffset: 8,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: '#e2e8f0',
            padding: 16,
            font: { size: 12, family: "'Inter', system-ui, sans-serif", weight: '600' },
            usePointStyle: true,
            pointStyle: 'circle',
          }
        },
        tooltip: {
          backgroundColor: 'rgba(10,17,40,0.9)',
          titleColor: '#e2e8f0',
          bodyColor: '#94a3b8',
          borderColor: 'rgba(255,255,255,0.06)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label: function(ctx) {
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
              const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
              return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
            }
          }
        }
      }
    }
  });

  // ── Chart: Times bar ──
  if (chartTiempos) chartTiempos.destroy();
  const tLabels = evs.map((e, i) => '#' + (i + 1));
  const tObs = evs.map(e => parseInt(e.tiempo_observacion) || 0);
  const tFbk = evs.map(e => parseInt(e.tiempo_feedback) || 0);
  chartTiempos = new Chart(document.getElementById('chartTiempos'), {
    type: 'bar',
    data: {
      labels: tLabels,
      datasets: [
        {
          label: 'Observación (min)',
          data: tObs,
          backgroundColor: '#1B5E96',
          borderRadius: 4,
          borderSkipped: false,
        },
        {
          label: 'Feedback (min)',
          data: tFbk,
          backgroundColor: '#B8860B',
          borderRadius: 4,
          borderSkipped: false,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: { color: '#e2e8f0', font: { family: "'Inter', system-ui, sans-serif" } }
        },
        tooltip: {
          backgroundColor: 'rgba(10,17,40,0.9)',
          titleColor: '#e2e8f0',
          bodyColor: '#94a3b8',
          borderColor: 'rgba(255,255,255,0.06)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 8,
        }
      },
      scales: {
        x: {
          ticks: { color: '#94a3b8', font: { size: 10 } },
          grid: { color: 'rgba(148,163,184,0.08)' },
          stacked: false,
        },
        y: {
          ticks: { color: '#94a3b8', font: { size: 10 } },
          grid: { color: 'rgba(148,163,184,0.08)' },
          beginAtZero: true,
        }
      },
      interaction: { intersect: false, mode: 'index' }
    }
  });

  // ── Areas to improve ──
  const mejTags = document.getElementById('rpMejorasTags');
  mejTags.innerHTML = '';
  const areas = idx.top_areas_mejora || {};
  const areaKeys = Object.keys(areas);

  if (areaKeys.length === 0) {
    mejTags.innerHTML = '<span class="mejoras-placeholder">Sin áreas de mejora registradas.</span>';
  } else {
    const maxFreq = Math.max(...Object.values(areas), 1);
    areaKeys.sort((a, b) => areas[b] - areas[a]);

    areaKeys.forEach((word, i) => {
      const freq = areas[word];
      const size = 0.75 + (freq / maxFreq) * 0.75;
      const tag = document.createElement('span');
      tag.className = 'mejora-tag';
      tag.style.fontSize = (size * 14) + 'px';
      tag.style.opacity = 0.55 + (freq / maxFreq) * 0.45;
      if (i === 0 && freq > 1) tag.classList.add('mejora-tag-top');
      tag.textContent = word + ' (' + freq + ')';
      tag.title = word + ': ' + freq + ' veces mencionado';
      mejTags.appendChild(tag);
    });
  }

  // ── Evaluations table ──
  const tbody = document.getElementById('rpEvBody');
  tbody.innerHTML = '';

  if (evs.length === 0) {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="7">Sin evaluaciones registradas para este alumno.</td></tr>';
  } else {
    evs.forEach((ev, i) => {
      const tr = document.createElement('tr');
      const badgeClass = ev.complejidad === 'Alta' ? 'badge-red'
        : ev.complejidad === 'Media' ? 'badge-gold' : 'badge-green';
      const score = (parseFloat(ev.calificacion_total) / 10).toFixed(1);
      tr.innerHTML = `
        <td data-label="#">${i + 1}</td>
        <td data-label="Fecha">${ev.fecha_evaluacion || '—'}</td>
        <td data-label="Evaluador">${htmlspecialchars(ev.evaluador_nombre || '—')}</td>
        <td data-label="Entorno">${htmlspecialchars(ev.entorno_clinico || '—')}</td>
        <td data-label="Complejidad"><span class="badge ${badgeClass}">${htmlspecialchars(ev.complejidad || '—')}</span></td>
        <td data-label="Puntaje"><span class="badge badge-score">${score}/10</span></td>
        <td data-label="Detalles">
          <button class="btn btn-sm btn-outline-blue" onclick='openModal(${JSON.stringify(ev).replace(/'/g, "\\'")})'>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Ver
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  document.getElementById('rpContent').style.display = 'block';
  document.getElementById('rpContent').style.animation = 'fadeIn .4s ease';
}

function exportExcelReporte() {
  if (!currentReporteData) {
    toast('No hay datos para exportar. Seleccione un alumno primero.', false);
    return;
  }
  showLoader('Generando archivo Excel con información detallada…');

  // Fallback: if xlsx-js-style not loaded, use server-side CSV
  if (typeof XLSX === 'undefined' || !XLSX.utils || !XLSX.utils.aoa_to_sheet) {
    hideLoader();
    const id = document.getElementById('rpAlumno').value;
    if (id) window.location.href = '<?= base_url('admin/reportes/export-excel') ?>?alumno_id=' + id;
    return;
  }

  try {
    const startTime = Date.now();
    const d = currentReporteData;
    const s = d.student;
    const evs = d.evaluaciones || [];
    const comps = d.competencias || [];
    const idx = d.indices || {};
    const complejidad = d.complejidad || [];

    const wb = XLSX.utils.book_new();

    // ═══════════════════════════════════════════════════
    // STYLE DEFINITIONS
    // ═══════════════════════════════════════════════════
    const TITLE_STYLE = {
      font: { bold: true, sz: 18, color: { rgb: 'FFFFFF' }, name: 'Calibri' },
      fill: { fgColor: { rgb: '1B5E96' } },
      alignment: { horizontal: 'center', vertical: 'center' },
      border: {
        top: { style: 'thin', color: { rgb: '1B5E96' } },
        bottom: { style: 'thin', color: { rgb: '1B5E96' } },
        left: { style: 'thin', color: { rgb: '1B5E96' } },
        right: { style: 'thin', color: { rgb: '1B5E96' } },
      },
    };
    const SUBTITLE_STYLE = {
      font: { bold: true, sz: 13, color: { rgb: 'FFFFFF' }, name: 'Calibri' },
      fill: { fgColor: { rgb: 'B8860B' } },
      alignment: { horizontal: 'left', vertical: 'center' },
    };
    const HEADER_STYLE = {
      font: { bold: true, sz: 11, color: { rgb: 'FFFFFF' }, name: 'Calibri' },
      fill: { fgColor: { rgb: '2C3E50' } },
      alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
      border: {
        top: { style: 'thin', color: { rgb: 'CCCCCC' } },
        bottom: { style: 'thin', color: { rgb: 'CCCCCC' } },
        left: { style: 'thin', color: { rgb: 'CCCCCC' } },
        right: { style: 'thin', color: { rgb: 'CCCCCC' } },
      },
    };
    const LABEL_STYLE = {
      font: { bold: true, sz: 11, color: { rgb: '333333' }, name: 'Calibri' },
      fill: { fgColor: { rgb: 'F5F5F5' } },
      alignment: { horizontal: 'left', vertical: 'center' },
      border: {
        top: { style: 'thin', color: { rgb: 'E0E0E0' } },
        bottom: { style: 'thin', color: { rgb: 'E0E0E0' } },
        left: { style: 'thin', color: { rgb: 'E0E0E0' } },
        right: { style: 'thin', color: { rgb: 'E0E0E0' } },
      },
    };
    const VALUE_STYLE = {
      font: { sz: 11, color: { rgb: '333333' }, name: 'Calibri' },
      alignment: { horizontal: 'left', vertical: 'center' },
      border: {
        top: { style: 'thin', color: { rgb: 'E0E0E0' } },
        bottom: { style: 'thin', color: { rgb: 'E0E0E0' } },
        left: { style: 'thin', color: { rgb: 'E0E0E0' } },
        right: { style: 'thin', color: { rgb: 'E0E0E0' } },
      },
    };
    const CELL_STYLE = {
      font: { sz: 10, color: { rgb: '333333' }, name: 'Calibri' },
      alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
      border: {
        top: { style: 'thin', color: { rgb: 'E0E0E0' } },
        bottom: { style: 'thin', color: { rgb: 'E0E0E0' } },
        left: { style: 'thin', color: { rgb: 'E0E0E0' } },
        right: { style: 'thin', color: { rgb: 'E0E0E0' } },
      },
    };
    const CELL_LEFT_STYLE = Object.assign({}, CELL_STYLE, {
      alignment: { horizontal: 'left', vertical: 'center', wrapText: true },
    });
    const GOLD_BADGE_STYLE = {
      font: { bold: true, sz: 11, color: { rgb: 'B8860B' }, name: 'Calibri' },
      fill: { fgColor: { rgb: 'FFF8E1' } },
      alignment: { horizontal: 'center', vertical: 'center' },
    };
    const GREEN_BADGE_STYLE = {
      font: { bold: true, sz: 11, color: { rgb: '22C55E' }, name: 'Calibri' },
      fill: { fgColor: { rgb: 'F0FFF4' } },
      alignment: { horizontal: 'center', vertical: 'center' },
    };
    const RED_BADGE_STYLE = {
      font: { bold: true, sz: 11, color: { rgb: 'EF4444' }, name: 'Calibri' },
      fill: { fgColor: { rgb: 'FFF0F0' } },
      alignment: { horizontal: 'center', vertical: 'center' },
    };
    const EMPTY_STYLE = {
      alignment: { horizontal: 'center', vertical: 'center' },
    };

    // ═══════════════════════════════════════════════════════
    // SHEET 1: RESUMEN GENERAL
    // ═══════════════════════════════════════════════════════
    const totalEvaluaciones = Number(idx.total_evaluaciones) || 0;
    const tendenciaClinica = totalEvaluaciones < 2
      ? 'Se requieren al menos 2 evaluaciones para identificar cambios en el desempeño.'
      : idx.trend > 0.5
        ? 'El alumno tiende a mejorar su puntaje con cada evaluación.'
        : idx.trend < -0.5
          ? 'El alumno tiende a bajar su puntaje con cada evaluación.'
          : 'El alumno mantiene su desempeño sin cambios significativos.';
    const consistenciaClinica = totalEvaluaciones < 2
      ? 'Se requieren al menos 2 evaluaciones para valorar la regularidad del desempeño.'
      : idx.consistencia < 5
        ? 'El alumno mantiene un nivel de desempeño estable, sin variaciones importantes.'
        : idx.consistencia < 12
          ? 'El alumno muestra cierta variabilidad en su desempeño, posiblemente relacionada con el tipo de caso.'
          : 'El rendimiento fluctúa significativamente y puede requerir atención pedagógica.';
    const progresoClinico = totalEvaluaciones < 4
      ? 'Se requieren al menos 4 evaluaciones para comparar el desempeño actual con el inicial.'
      : idx.progreso > 2
        ? 'El alumno rinde mejor ahora que al inicio.'
        : idx.progreso < -2
          ? 'El alumno rinde peor ahora que al inicio y puede requerir atención.'
          : 'No hay cambios significativos en el rendimiento respecto al inicio.';
    const rData = [
      ['REPORTE INDIVIDUAL — MINI-CEX'],
      [],
      ['DATOS DEL ALUMNO'],
      ['Matrícula', s.matricula],
      ['Nombre Completo', s.nombre_completo],
      ['Semestre / Grupo', s.semestre_grupo],
      ['Correo Electrónico', s.correo || '—'],
      ['Docente Asignado', s.docente_nombre || '—'],
      ['Fecha de Reporte', new Date().toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' })],
      [],
      ['ESTADÍSTICAS GENERALES'],
      ['Total de Evaluaciones', String(idx.total_evaluaciones)],
      ['Promedio General', idx.promedio_display + ' / 10'],
      ['Evolución del desempeño', tendenciaClinica],
      ['Regularidad del desempeño', consistenciaClinica],
      ['Competencia más Fuerte', idx.competencia_fuerte || '—'],
      ['Competencia más Débil', idx.competencia_debil || '—'],
      [],
      ['ÍNDICES DE MEJORA'],
      ['Cambio respecto al inicio', progresoClinico],
      ['Distribución de Complejidad',
        complejidad.map(function(c) { return c.complejidad + ': ' + c.count; }).join(' | ') || '—'],
      ['Principales Áreas de Mejora',
        (function() {
          var areas = idx.top_areas_mejora || {};
          var keys = Object.keys(areas);
          if (keys.length === 0) return 'Sin áreas de mejora registradas.';
          return keys.slice(0, 8).map(function(k) { return k + ' (' + areas[k] + ')'; }).join(', ');
        })()],
    ];

    const ws1 = XLSX.utils.aoa_to_sheet(rData);
    // Apply styles
    ws1['A1'].s = TITLE_STYLE;
    ws1['A3'].s = SUBTITLE_STYLE;
    ws1['A11'].s = SUBTITLE_STYLE;
    ws1['A19'].s = SUBTITLE_STYLE;
    for (var i = 3; i <= 8; i++) {
      var labelCell = XLSX.utils.encode_cell({ r: i, c: 0 });
      var valCell = XLSX.utils.encode_cell({ r: i, c: 1 });
      if (ws1[labelCell]) ws1[labelCell].s = LABEL_STYLE;
      if (ws1[valCell]) ws1[valCell].s = VALUE_STYLE;
    }
    for (var i = 11; i <= 16; i++) {
      var labelCell = XLSX.utils.encode_cell({ r: i, c: 0 });
      var valCell = XLSX.utils.encode_cell({ r: i, c: 1 });
      if (ws1[labelCell]) ws1[labelCell].s = LABEL_STYLE;
      if (ws1[valCell]) ws1[valCell].s = VALUE_STYLE;
    }
    for (var i = 19; i <= 21; i++) {
      var labelCell = XLSX.utils.encode_cell({ r: i, c: 0 });
      var valCell = XLSX.utils.encode_cell({ r: i, c: 1 });
      if (ws1[labelCell]) ws1[labelCell].s = LABEL_STYLE;
      if (ws1[valCell]) ws1[valCell].s = VALUE_STYLE;
    }
    // Merges
    ws1['!merges'] = [
      { s: { r: 0, c: 0 }, e: { r: 0, c: 7 } },
      { s: { r: 2, c: 0 }, e: { r: 2, c: 7 } },
      { s: { r: 10, c: 0 }, e: { r: 10, c: 7 } },
      { s: { r: 18, c: 0 }, e: { r: 18, c: 7 } },
    ];
    // Column widths
    ws1['!cols'] = [
      { wch: 32 }, { wch: 60 }, { wch: 14 }, { wch: 14 },
      { wch: 14 }, { wch: 14 }, { wch: 14 }, { wch: 14 },
    ];
    XLSX.utils.book_append_sheet(wb, ws1, 'Resumen General');

    // ═══════════════════════════════════════════════════════
    // SHEET 2: EVALUACIONES
    // ═══════════════════════════════════════════════════════
    var evData = [
      ['DETALLE DE EVALUACIONES'],
      [],
      ['#', 'Fecha', 'Evaluador', 'Entorno Clínico', 'Tipo Paciente', 'Asunto Principal', 'Complejidad', 'T.Observación', 'T.Feedback', 'Calificación Total'],
    ];
    evs.forEach(function(ev, i) {
      evData.push([
        String(i + 1),
        ev.fecha_evaluacion || '—',
        ev.evaluador_nombre || '—',
        ev.entorno_clinico || '—',
        ev.tipo_paciente || '—',
        ev.asunto_principal || '—',
        ev.complejidad || '—',
        ev.tiempo_observacion ? ev.tiempo_observacion + ' min' : '—',
        ev.tiempo_feedback ? ev.tiempo_feedback + ' min' : '—',
        (parseFloat(ev.calificacion_total) / 10).toFixed(1) + ' / 10',
      ]);
    });
    var ws2 = XLSX.utils.aoa_to_sheet(evData);
    // Title
    ws2['A1'].s = TITLE_STYLE;
    // Header row
    for (var c = 0; c < 10; c++) {
      var hCell = XLSX.utils.encode_cell({ r: 2, c: c });
      if (ws2[hCell]) ws2[hCell].s = HEADER_STYLE;
    }
    // Data rows
    for (var r = 3; r < evData.length; r++) {
      for (var c = 0; c < 10; c++) {
        var cellRef = XLSX.utils.encode_cell({ r: r, c: c });
        if (ws2[cellRef]) {
          ws2[cellRef].s = (c === 6) ? CELL_STYLE : (c <= 1 || c >= 7 ? CELL_STYLE : CELL_LEFT_STYLE);
          // Color-code complexity
          if (c === 6 && ws2[cellRef]) {
            var v = ws2[cellRef].v;
            if (v === 'Alta') ws2[cellRef].s = RED_BADGE_STYLE;
            else if (v === 'Media') ws2[cellRef].s = GOLD_BADGE_STYLE;
            else if (v === 'Baja') ws2[cellRef].s = GREEN_BADGE_STYLE;
          }
        }
      }
    }
    ws2['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 9 } }];
    ws2['!cols'] = [
      { wch: 6 }, { wch: 14 }, { wch: 28 }, { wch: 22 },
      { wch: 18 }, { wch: 32 }, { wch: 14 }, { wch: 14 },
      { wch: 14 }, { wch: 14 },
    ];
    XLSX.utils.book_append_sheet(wb, ws2, 'Evaluaciones');

    // ═══════════════════════════════════════════════════════
    // SHEET 3: DETALLE DE RÚBRICAS
    // ═══════════════════════════════════════════════════════
    var rubData = [
      ['DETALLE DE RÚBRICAS POR EVALUACIÓN'],
      [],
      ['Evaluación #', 'Fecha', 'Competencia', 'Puntaje', 'Notas', 'A Destacar', 'A Mejorar'],
    ];
    evs.forEach(function(ev, i) {
      var detalles = ev.detalles || [];
      if (detalles.length === 0) {
        rubData.push([String(i + 1), ev.fecha_evaluacion || '—', '(sin registros)', '—', '—', '—', '—']);
      } else {
        detalles.forEach(function(d) {
          rubData.push([
            String(i + 1),
            ev.fecha_evaluacion || '—',
            d.competencia || '—',
            String(d.puntaje),
            d.notas || '—',
            d.a_destacar || '—',
            d.a_mejorar || '—',
          ]);
        });
      }
    });
    var ws3 = XLSX.utils.aoa_to_sheet(rubData);
    ws3['A1'].s = TITLE_STYLE;
    for (var c = 0; c < 7; c++) {
      var hCell = XLSX.utils.encode_cell({ r: 2, c: c });
      if (ws3[hCell]) ws3[hCell].s = HEADER_STYLE;
    }
    for (var r = 3; r < rubData.length; r++) {
      for (var c = 0; c < 7; c++) {
        var cellRef = XLSX.utils.encode_cell({ r: r, c: c });
        if (ws3[cellRef]) {
          ws3[cellRef].s = (c <= 1 || c === 3) ? CELL_STYLE : CELL_LEFT_STYLE;
        }
      }
    }
    ws3['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 6 } }];
    ws3['!cols'] = [
      { wch: 14 }, { wch: 14 }, { wch: 30 }, { wch: 10 },
      { wch: 40 }, { wch: 40 }, { wch: 40 },
    ];
    XLSX.utils.book_append_sheet(wb, ws3, 'Detalle Rúbricas');

    // ═══════════════════════════════════════════════════════
    // SHEET 4: COMPETENCIAS
    // ═══════════════════════════════════════════════════════
    var compData = [
      ['COMPETENCIAS — PROMEDIO POR ÁREA'],
      [],
      ['Competencia', 'Promedio (0-9)', 'Evaluaciones'],
    ];
    comps.forEach(function(c) {
      compData.push([
        c.competencia || '—',
        parseFloat(c.promedio).toFixed(2),
        String(c.count),
      ]);
    });
    var ws4 = XLSX.utils.aoa_to_sheet(compData);
    ws4['A1'].s = TITLE_STYLE;
    for (var c = 0; c < 3; c++) {
      var hCell = XLSX.utils.encode_cell({ r: 2, c: c });
      if (ws4[hCell]) ws4[hCell].s = HEADER_STYLE;
    }
    for (var r = 3; r < compData.length; r++) {
      for (var c = 0; c < 3; c++) {
        var cellRef = XLSX.utils.encode_cell({ r: r, c: c });
        if (ws4[cellRef]) ws4[cellRef].s = CELL_STYLE;
      }
    }
    ws4['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 2 } }];
    ws4['!cols'] = [{ wch: 36 }, { wch: 20 }, { wch: 16 }];
    XLSX.utils.book_append_sheet(wb, ws4, 'Competencias');

    // ═══════════════════════════════════════════════════════
    // SHEET 5: ÁREAS DE MEJORA
    // ═══════════════════════════════════════════════════════
    var areas = idx.top_areas_mejora || {};
    var areaKeys = Object.keys(areas);
    var mejData = [
      ['ÁREAS DE MEJORA RECURRENTES'],
      [],
      ['Área de Mejora', 'Frecuencia', 'Prioridad'],
    ];
    if (areaKeys.length === 0) {
      mejData.push(['Sin áreas de mejora registradas.', '', '']);
    } else {
      var maxFreq = Math.max.apply(null, Object.values(areas));
      areaKeys.sort(function(a, b) { return areas[b] - areas[a]; });
      areaKeys.forEach(function(word) {
        var freq = areas[word];
        var priority = freq >= maxFreq * 0.7 ? 'Alta' : (freq >= maxFreq * 0.4 ? 'Media' : 'Baja');
        mejData.push([word, String(freq), priority]);
      });
    }
    var ws5 = XLSX.utils.aoa_to_sheet(mejData);
    ws5['A1'].s = TITLE_STYLE;
    for (var c = 0; c < 3; c++) {
      var hCell = XLSX.utils.encode_cell({ r: 2, c: c });
      if (ws5[hCell]) ws5[hCell].s = HEADER_STYLE;
    }
    for (var r = 3; r < mejData.length; r++) {
      for (var c = 0; c < 3; c++) {
        var cellRef = XLSX.utils.encode_cell({ r: r, c: c });
        if (ws5[cellRef]) {
          ws5[cellRef].s = (c === 2) ? CELL_STYLE : CELL_LEFT_STYLE;
          // Color-code priority
          if (c === 2 && ws5[cellRef]) {
            var pv = ws5[cellRef].v;
            if (pv === 'Alta') ws5[cellRef].s = RED_BADGE_STYLE;
            else if (pv === 'Media') ws5[cellRef].s = GOLD_BADGE_STYLE;
            else if (pv === 'Baja') ws5[cellRef].s = GREEN_BADGE_STYLE;
            else ws5[cellRef].s = CELL_STYLE;
          }
        }
      }
    }
    ws5['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 2 } }];
    ws5['!cols'] = [{ wch: 36 }, { wch: 14 }, { wch: 14 }];
    XLSX.utils.book_append_sheet(wb, ws5, 'Áreas de Mejora');

    // ═══════════════════════════════════════════════════════
    // WRITE FILE
    // ═══════════════════════════════════════════════════════
    var filename = 'reporte_' + s.matricula.replace(/[^a-zA-Z0-9]/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.xlsx';
    XLSX.writeFile(wb, filename);

    var elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
    hideLoader();
    toast('Archivo XLSX descargado correctamente en ' + elapsed + 's.', true);
  } catch (e) {
    hideLoader();
    toast('Error al generar Excel: ' + e.message + '. Use el botón de respaldo CSV.', false);
    console.error('XLSX export error:', e);
  }
}

function htmlspecialchars(str) {
  if (!str) return '';
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
</script>

<!-- ═══════════════════════════════════════════════════
     STYLE (scoped to this tab)
     ═══════════════════════════════════════════════════ -->
<style>
/* ── Selector card ── */
.selector-card .selector-body {
  display: flex;
  gap: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
  padding: 0 4px 4px;
}
.selector-card .selector-field {
  flex: 2;
  min-width: 280px;
  margin-bottom: 0;
}
.selector-card .btn-export {
  width: auto;
  margin-top: 0;
  white-space: nowrap;
  padding: 12px 24px;
}

/* ── Indices grid ── */
.indices-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  padding: 6px 4px 4px;
}
.indice-card {
  background: var(--bg-glass);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 20px 18px;
  text-align: center;
  transition: all var(--speed) var(--ease);
  position: relative;
  overflow: hidden;
}
.indice-card:hover {
  transform: translateY(-2px);
  border-color: var(--border-medium);
  box-shadow: var(--shadow);
}
.indice-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 10px;
  transition: transform var(--speed);
}
.indice-card:hover .indice-icon {
  transform: scale(1.1) rotate(-4deg);
}
.indice-trend { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.indice-strong { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
.indice-weak { background: rgba(244, 63, 94, 0.12); color: #f43f5e; }
.indice-progress { background: rgba(234, 179, 8, 0.12); color: #eab308; }

.indice-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 6px;
}
.indice-val {
  font-size: 17px;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.3;
}
.indice-sub {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 6px;
  font-weight: 500;
}
.strong-text { color: #22c55e; }
.weak-text { color: #f43f5e; }

/* ── Trend badges in stats ── */
.trend-badge {
  font-size: 28px !important;
  line-height: 1;
}
.trend-up { color: #22c55e; }
.trend-down { color: #ef4444; }
.trend-flat { color: #eab308; }

.stat .trend-icon {
  background: rgba(59, 130, 246, 0.08);
  color: #3b82f6;
}

/* ── Chart cards ── */
.chart-grid {
  margin-bottom: 24px;
}
.chart-card .chart-wrap {
  padding: 8px 12px 12px;
  position: relative;
  height: 300px;
}
.chart-card .chart-wrap canvas {
  max-height: 100%;
  max-width: 100%;
}

/* ── Areas de mejora ── */
.mejoras-body {
  padding: 8px 4px 4px;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  min-height: 52px;
  align-items: center;
}
.mejoras-placeholder {
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 500;
  padding: 8px 0;
}
.mejora-tag {
  display: inline-block;
  padding: 7px 18px;
  border-radius: 24px;
  background: rgba(184, 134, 11, 0.12);
  color: #eab308;
  border: 1px solid rgba(184, 134, 11, 0.25);
  font-weight: 600;
  transition: all 0.25s var(--ease);
  cursor: default;
  letter-spacing: 0.2px;
}
.mejora-tag:hover {
  background: rgba(184, 134, 11, 0.22);
  transform: translateY(-1px) scale(1.03);
  box-shadow: 0 4px 12px rgba(184, 134, 11, 0.15);
}
.mejora-tag-top {
  background: rgba(244, 63, 94, 0.12);
  color: #f43f5e;
  border-color: rgba(244, 63, 94, 0.25);
}
.mejora-tag-top:hover {
  background: rgba(244, 63, 94, 0.22);
  box-shadow: 0 4px 12px rgba(244, 63, 94, 0.15);
}

/* ── Table enhancements ── */
.tbl td:last-child {
  text-align: center;
}
.tbl td:last-child .btn {
  margin-top: 0;
}

/* ── Badge overrides for table ── */
.badge-green {
  background: rgba(34, 197, 94, 0.12);
  color: #22c55e;
  border: 1px solid rgba(34, 197, 94, 0.2);
}
.badge-red {
  background: rgba(244, 63, 94, 0.12);
  color: #f43f5e;
  border: 1px solid rgba(244, 63, 94, 0.2);
}

/* ── Animations ── */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .selector-card .selector-body {
    flex-direction: column;
    align-items: stretch;
  }
  .selector-card .selector-field {
    min-width: 0;
  }
  .selector-card .btn-export {
    width: 100%;
  }
  .chart-card .chart-wrap {
    height: 260px;
  }
  .indices-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  .indice-card {
    padding: 16px 14px;
  }
}

@media (max-width: 480px) {
  .indices-grid {
    grid-template-columns: 1fr;
  }
  .chart-card .chart-wrap {
    height: 220px;
  }
  .mejoras-body {
    gap: 8px;
  }
  .mejora-tag {
    font-size: 12px !important;
    padding: 5px 14px;
  }
}
</style>
