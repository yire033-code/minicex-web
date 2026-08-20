<!DOCTYPE html>
<html>
<head><title>Exportando...</title><meta charset="utf-8"></head>
<body style="background:#0a1128;color:#e2e8f0;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;flex-direction:column">
<p id="msg" style="font-size:18px">Generando archivo Excel...</p>
<div id="progress" style="width:300px;height:4px;background:#1e293b;border-radius:4px;margin-top:16px;overflow:hidden">
  <div id="bar" style="height:100%;width:0%;background:#4F46E5;border-radius:4px;transition:width .3s"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.min.js"></script>
<script>
(async function() {
  const params = new URLSearchParams(location.search);
  const evaluadorId = params.get('evaluador_id') || 1;
  const modo = params.get('modo') || 'mine';

  document.getElementById('msg').textContent = 'Obteniendo datos...';
  document.getElementById('bar').style.width = '20%';

  try {
    const base = window.location.protocol + '//' + window.location.host;
    const path = window.location.pathname.replace(/\/export-view.*/, '');
    const apiUrl = base + path + '?evaluador_id=' + evaluadorId + '&modo=' + modo;
    const res = await fetch(apiUrl);
    if (!res.ok) {
      document.getElementById('msg').textContent = 'Error HTTP ' + res.status + ': ' + res.statusText;
      document.getElementById('bar').style.width = '0%';
      return;
    }
    const data = await res.json();

    if (!data.success) {
      document.getElementById('msg').textContent = 'Error del servidor: ' + (data.message || 'Sin datos');
      document.getElementById('bar').style.width = '0%';
      return;
    }

    document.getElementById('msg').textContent = 'Datos cargados, generando Excel...';
    document.getElementById('bar').style.width = '50%';

    const doc = data.docente || {};
    const resumen = data.resumen || {};
    const alumnos = data.alumnos || [];

    // ─── Styles ──────────────────────────────────────────────────────────
    const s = {
      header: { fill: { fgColor: { rgb: "4F46E5" } }, font: { bold: true, color: { rgb: "FFFFFF" }, sz: 11 }, alignment: { horizontal: "center", wrapText: true }, border: { bottom: { style: "thin", color: { rgb: "3730A3" } } } },
      subheader: { fill: { fgColor: { rgb: "EEF2FF" } }, font: { bold: true, color: { rgb: "4F46E5" }, sz: 10 }, alignment: { wrapText: true } },
      cell: { font: { sz: 10 }, alignment: { wrapText: true }, border: { top: { style: "thin", color: { rgb: "E2E8F0" } }, bottom: { style: "thin", color: { rgb: "E2E8F0" } } } },
      title: { font: { bold: true, sz: 14, color: { rgb: "4F46E5" } } },
      label: { font: { bold: true, sz: 10, color: { rgb: "64748B" } } },
      scoreGood: { font: { bold: true, sz: 11, color: { rgb: "10B981" } } },
      scoreWarn: { font: { bold: true, sz: 11, color: { rgb: "F59E0B" } } },
      scoreBad: { font: { bold: true, sz: 11, color: { rgb: "EF4444" } } },
    };

    // ─── Sheet 1: Resumen Docente ───────────────────────────────────────
    const ws1Data = [
      [{ v: 'REPORTE POR DOCENTE - MINI-CEX', s: s.title }],
      [],
      [{ v: 'Docente', s: s.label }, doc.nombreCompleto || '—'],
      [{ v: 'Email', s: s.label }, doc.email || '—'],
      [{ v: 'Modo', s: s.label }, modo === 'mine' ? 'Mis evaluaciones' : 'Todos los alumnos'],
      [{ v: 'Fecha del reporte', s: s.label }, new Date().toLocaleDateString('es-MX')],
      [],
      [{ v: 'Total alumnos', s: s.label }, String(resumen.totalAlumnos || 0)],
      [{ v: 'Alumnos con evaluaciones', s: s.label }, String(resumen.alumnosConEvaluaciones || 0)],
      [{ v: 'Total evaluaciones', s: s.label }, String(resumen.totalEvaluaciones || 0)],
      [{ v: 'Promedio general', s: s.label }, (resumen.promedioGeneral || 0) + '/10'],
    ];

    // ─── Sheet 2: Detalle Alumnos ───────────────────────────────────────
    const ws2Headers = ['Alumno', 'Matrícula', 'Semestre', 'Evals', 'Promedio', 'Prom./10', 'Tendencia', 'Consistencia', 'Progreso', 'Comp.Fuerte', 'Comp.Débil'].map(h => ({ v: h, s: s.header }));
    const ws2Data = [ws2Headers];

    alumnos.forEach(al => {
      const idx = al.indices || {};
      const pct = idx.promedioDisplay || 0;
      const sPct = pct >= 8 ? s.scoreGood : (pct >= 5 ? s.scoreWarn : s.scoreBad);
      ws2Data.push([
        { v: al.nombreCompleto || '', s: s.cell },
        { v: al.matricula || '', s: s.cell },
        { v: al.semestreGrupo || '', s: s.cell },
        { v: String(idx.totalEvaluaciones || 0), s: s.cell },
        { v: String(idx.promedio || 0), s: s.cell },
        { v: (idx.promedioDisplay || 0) + '/10', s: sPct },
        { v: idx.trendText || '—', s: s.cell },
        { v: (idx.consistenciaText || '') + ' (σ=' + (idx.consistencia || 0) + ')', s: s.cell },
        { v: (idx.progresoText || '') + ' (' + (idx.progreso >= 0 ? '+' : '') + (idx.progreso || 0) + ')', s: s.cell },
        { v: idx.competenciaFuerte || '—', s: s.cell },
        { v: idx.competenciaDebil || '—', s: s.cell },
      ]);

      // Sub-rows: competencies
      const comps = al.competencias || [];
      if (comps.length) {
        ws2Data.push([{ v: 'Competencias:', s: s.subheader }]);
        comps.forEach(c => {
          ws2Data.push(['', c.competencia || '', (c.promedio || 0) + '/9', String(c.count || 0) + ' eval']);
        });
      }

      // Sub-rows: areas de mejora
      const areas = idx.topAreasMejora || {};
      if (Object.keys(areas).length) {
        ws2Data.push([{ v: 'Áreas de mejora:', s: s.subheader }]);
        Object.entries(areas)
          .sort((a, b) => b[1] - a[1])
          .forEach(([word, freq]) => {
            ws2Data.push(['', word + ' (' + freq + 'x)']);
          });
      }

      ws2Data.push([]);
    });

    // ─── Sheet 3: Evaluaciones ──────────────────────────────────────────
    const ws3Headers = ['Alumno', 'Matrícula', '#', 'Fecha', 'Evaluador', 'Entorno', 'Paciente', 'Asunto', 'Complejidad', 'T.Obs', 'T.Fbk', 'Calificación'].map(h => ({ v: h, s: s.header }));
    const ws3Data = [ws3Headers];
    const ws3DetHeaders = ['Alumno', 'Matrícula', 'Eval#', 'Fecha', 'Competencia', 'Puntaje', 'Notas', 'A Destacar', 'A Mejorar'].map(h => ({ v: h, s: s.header }));
    const ws3DetData = [ws3DetHeaders];

    let evalIdx = 0;
    alumnos.forEach(al => {
      (al.evaluaciones || []).forEach(ev => {
        evalIdx++;
        const cal = ev.calificacionTotal || 0;
        const sCal = cal >= 80 ? s.scoreGood : (cal >= 50 ? s.scoreWarn : s.scoreBad);
        ws3Data.push([
          { v: al.nombreCompleto || '', s: s.cell },
          { v: al.matricula || '', s: s.cell },
          { v: String(evalIdx), s: s.cell },
          { v: (ev.fechaEvaluacion || '').toString().substring(0, 10), s: s.cell },
          { v: ev.evaluadorNombre || '', s: s.cell },
          { v: ev.entornoClinico || '', s: s.cell },
          { v: ev.tipoPaciente || '', s: s.cell },
          { v: ev.asuntoPrincipal || '', s: s.cell },
          { v: ev.complejidad || '', s: s.cell },
          { v: String(ev.tiempoObservacion || 0), s: s.cell },
          { v: String(ev.tiempoFeedback || 0), s: s.cell },
          { v: String(cal), s: sCal },
        ]);

        (ev.detalles || []).forEach(d => {
          ws3DetData.push([
            al.nombreCompleto || '',
            al.matricula || '',
            String(evalIdx),
            (ev.fechaEvaluacion || '').toString().substring(0, 10),
            d.competencia || '',
            (d.puntaje || 0) + '/9',
            d.notas || '',
            d.aDestacar || '',
            d.aMejorar || '',
          ]);
        });
      });
    });

    // ─── Build workbook ──────────────────────────────────────────────────
    const wb = XLSX.utils.book_new();

    const ws1 = XLSX.utils.aoa_to_sheet(ws1Data);
    ws1['!cols'] = [{ wch: 20 }, { wch: 40 }];
    XLSX.utils.book_append_sheet(wb, ws1, 'Resumen Docente');

    const ws2 = XLSX.utils.aoa_to_sheet(ws2Data);
    ws2['!cols'] = [
      { wch: 30 }, { wch: 16 }, { wch: 12 }, { wch: 6 },
      { wch: 10 }, { wch: 10 }, { wch: 18 }, { wch: 24 },
      { wch: 20 }, { wch: 18 }, { wch: 18 }
    ];
    XLSX.utils.book_append_sheet(wb, ws2, 'Detalle Alumnos');

    const ws3 = XLSX.utils.aoa_to_sheet(ws3Data);
    ws3['!cols'] = [
      { wch: 28 }, { wch: 14 }, { wch: 4 }, { wch: 12 },
      { wch: 24 }, { wch: 16 }, { wch: 12 }, { wch: 24 },
      { wch: 12 }, { wch: 6 }, { wch: 6 }, { wch: 10 }
    ];
    XLSX.utils.book_append_sheet(wb, ws3, 'Evaluaciones');

    const ws4 = XLSX.utils.aoa_to_sheet(ws3DetData);
    ws4['!cols'] = [
      { wch: 28 }, { wch: 14 }, { wch: 4 }, { wch: 12 },
      { wch: 20 }, { wch: 8 }, { wch: 20 }, { wch: 20 },
      { wch: 20 }
    ];
    XLSX.utils.book_append_sheet(wb, ws4, 'Detalle Rúbricas');

    document.getElementById('msg').textContent = 'Descargando...';
    document.getElementById('bar').style.width = '90%';

    const fname = 'Reporte_Docente_' + (doc.nombreCompleto || '').replace(/[^a-zA-Z0-9]/g, '_') + '_' +
      new Date().toISOString().substring(0, 10) + '.xlsx';
    XLSX.writeFile(wb, fname);

    document.getElementById('msg').textContent = '¡Descarga completa!';
    document.getElementById('bar').style.width = '100%';

    setTimeout(() => window.close(), 2000);

  } catch (e) {
    document.getElementById('msg').textContent = 'Error: ' + e.message;
    document.getElementById('bar').style.width = '0%';
  }
})();
</script>
</body>
</html>
