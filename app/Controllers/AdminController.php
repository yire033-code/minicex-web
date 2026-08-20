<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class AdminController extends BaseController
{
    public function index()
    {
        $session = session();
        $isLoggedIn = $session->get('admin_logged_in') === true;

        if (!$isLoggedIn) {
            return view('admin/login');
        }

        // Fetch data using getDbConnection()
        require_once ROOTPATH . 'config.php';
        $db = getDbConnection();

        // Run migration helper for alumnos.correo
        try { 
            @$db->exec("ALTER TABLE alumnos ADD COLUMN correo VARCHAR(255) DEFAULT ''"); 
        } catch (\PDOException $e) {}

        try {
            $statDocentes     = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            $statAlumnos      = $db->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
            $statEvaluaciones = $db->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
            $statPromedio     = $db->query("SELECT AVG(calificacion_total) FROM evaluaciones")->fetchColumn();
            $statPromedio     = $statPromedio ? number_format($statPromedio, 2) : '0.00';

            $docentes = $db->query("SELECT * FROM usuarios ORDER BY id_usuario DESC")->fetchAll();
            $alumnos  = $db->query("SELECT a.*,u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente=u.id_usuario ORDER BY a.id_alumno DESC")->fetchAll();
            $evaluaciones = $db->query("SELECT e.*,u.nombre_completo AS evaluador_nombre,a.nombre_completo AS alumno_nombre,a.matricula AS alumno_matricula FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador=u.id_usuario LEFT JOIN alumnos a ON e.id_alumno=a.id_alumno ORDER BY e.id_evaluacion DESC")->fetchAll();

            $data = [
                'statDocentes'     => $statDocentes,
                'statAlumnos'      => $statAlumnos,
                'statEvaluaciones' => $statEvaluaciones,
                'statPromedio'     => $statPromedio,
                'docentes'         => $docentes,
                'alumnos'          => $alumnos,
                'evaluaciones'     => $evaluaciones,
            ];
        } catch (\Exception $e) {
            die("Error de datos: " . $e->getMessage());
        }

        return view('admin/dashboard', $data);
    }

    public function login()
    {
        require_once ROOTPATH . 'config.php';

        $json = $this->request->getJSON(true) ?: [];
        $username = trim($json['username'] ?? '');
        $password = trim($json['password'] ?? '');

        $configuredUsername = defined('ADMIN_USERNAME') ? (string) ADMIN_USERNAME : '';
        $configuredHash = defined('ADMIN_PASSWORD_HASH') ? (string) ADMIN_PASSWORD_HASH : '';
        $success = $configuredUsername !== ''
            && $configuredHash !== ''
            && hash_equals($configuredUsername, $username)
            && password_verify($password, $configuredHash);

        if ($success) {
            $session = session();
            $session->set('admin_logged_in', true);
        }

        return $this->response->setJSON([
            'success' => $success,
            'message' => $success ? 'Acceso concedido. Redirigiendo...' : 'Usuario o contraseña incorrectos.'
        ]);
    }

    public function logout()
    {
        $session = session();
        $session->destroy();
        return redirect()->to(base_url('admin'));
    }

    public function action()
    {
        $session = session();
        if ($session->get('admin_logged_in') !== true) {
            return $this->response->setJSON(['success' => false, 'message' => 'No autorizado.'])->setStatusCode(401);
        }

        require_once ROOTPATH . 'config.php';
        $db = getDbConnection();

        $inputData = $this->request->getJSON(true) ?: [];
        $action = $inputData['action'] ?? '';

        switch ($action) {
            case 'add_alumnos_ajax':
                $did = intval($inputData['id_docente'] ?? 0);
                $rows = $inputData['data'] ?? [];
                if (!$did || empty($rows)) { 
                    return $this->response->setJSON(['success' => false, 'message' => 'Faltan datos.']);
                }
                $ok = 0; $err = 0;
                $db->beginTransaction();
                foreach ($rows as $r) {
                    $m = trim($r['matricula'] ?? ''); 
                    $n = trim($r['nombre'] ?? ''); 
                    $s = trim($r['semestre'] ?? '');
                    $c = trim($r['correo'] ?? '');
                    if ($m && $n && $s) {
                        try { 
                            $db->prepare("INSERT INTO alumnos (uuid,matricula,nombre_completo,semestre_grupo,correo,id_docente) VALUES (UUID(),?,?,?,?,?)")->execute([$m,$n,$s,$c,$did]); 
                            $ok++; 
                        } catch (\PDOException $e) { 
                            $err++; 
                        }
                    }
                }
                $db->commit();
                return $this->response->setJSON(['success' => true, 'message' => "$ok alumnos agregados." . ($err ? " ($err omitidos)" : "")]);

            case 'add_docente':
                $nombre = trim($inputData['nombre_completo'] ?? '');
                $email  = trim($inputData['email'] ?? '');
                $pass   = trim($inputData['password'] ?? '');
                $rol    = $inputData['rol'] ?? 'Docente';
                if (!$nombre || !$email || !$pass) { 
                    return $this->response->setJSON(['success' => false, 'message' => 'Todos los campos son requeridos.']);
                }
                $db->prepare("INSERT INTO usuarios (nombre_completo,email,password_hash,rol) VALUES (?,?,?,?)")->execute([$nombre,$email,$pass,$rol]);
                return $this->response->setJSON(['success' => true, 'message' => "Docente registrado con éxito."]);

            case 'add_alumno':
                $mat = trim($inputData['matricula'] ?? ''); 
                $nom = trim($inputData['nombre_completo'] ?? '');
                $sem = trim($inputData['semestre_grupo'] ?? ''); 
                $did = intval($inputData['id_docente'] ?? 0);
                $correo = trim($inputData['correo'] ?? '');
                if (!$mat || !$nom || !$sem || !$did) { 
                    return $this->response->setJSON(['success' => false, 'message' => 'Complete todos los campos.']);
                }
                try {
                    $db->prepare("INSERT INTO alumnos (uuid,matricula,nombre_completo,semestre_grupo,correo,id_docente) VALUES (UUID(),?,?,?,?,?)")->execute([$mat,$nom,$sem,$correo,$did]);
                    return $this->response->setJSON(['success' => true, 'message' => "Alumno registrado con éxito."]);
                } catch (\PDOException $e) {
                    $msg = $e->getCode() == 23000 ? 'Matrícula duplicada.' : 'Error: ' . $e->getMessage();
                    return $this->response->setJSON(['success' => false, 'message' => $msg]);
                }

            case 'add_alumnos_bulk_text':
                $did = intval($inputData['id_docente'] ?? 0); 
                $text = trim($inputData['alumnos_text'] ?? '');
                if (!$did || !$text) { 
                    return $this->response->setJSON(['success' => false, 'message' => 'Complete todos los campos.']);
                }
                $ok = 0; $err = 0;
                $db->beginTransaction();
                foreach (explode("\n", $text) as $ln) {
                    $ln = trim($ln); 
                    if (!$ln) continue;
                    $p = explode(",", $ln);
                    if (count($p) >= 3) {
                        $m = trim($p[0]);
                        $n = trim($p[1]);
                        $s = trim($p[2]);
                        $c = isset($p[3]) ? trim($p[3]) : '';
                        if ($m && $n && $s) {
                            try {
                                $db->prepare("INSERT INTO alumnos (uuid,matricula,nombre_completo,semestre_grupo,correo,id_docente) VALUES (UUID(),?,?,?,?,?)")->execute([$m,$n,$s,$c,$did]);
                                $ok++;
                            } catch (\PDOException $e) {
                                $err++;
                            }
                        }
                    } else {
                        $err++;
                    }
                }
                $db->commit();
                return $this->response->setJSON(['success' => true, 'message' => "$ok alumnos agregados." . ($err ? " ($err omitidos)" : "")]);

            case 'delete_docente':
                $id = intval($inputData['id_docente'] ?? 0);
                if ($id) {
                    $db->beginTransaction();
                    try {
                        $db->prepare("DELETE FROM detalles_rubrica WHERE id_evaluacion IN (SELECT id_evaluacion FROM evaluaciones WHERE id_evaluador = ?)")->execute([$id]);
                        $db->prepare("DELETE FROM evaluaciones WHERE id_evaluador = ?")->execute([$id]);
                        $db->prepare("DELETE FROM alumnos WHERE id_docente = ?")->execute([$id]);
                        $db->prepare("DELETE FROM usuarios WHERE id_usuario=?")->execute([$id]);
                        $db->commit();
                        return $this->response->setJSON(['success' => true, 'message' => "Docente eliminado."]);
                    } catch (\Exception $e) {
                        $db->rollBack();
                        return $this->response->setJSON(['success' => false, 'message' => "Error al eliminar docente."]);
                    }
                }
                return $this->response->setJSON(['success' => false, 'message' => "ID no válido."]);

            case 'delete_alumno':
                $id = intval($inputData['id_alumno'] ?? 0);
                if ($id) {
                    $db->beginTransaction();
                    try {
                        $db->prepare("DELETE FROM detalles_rubrica WHERE id_evaluacion IN (SELECT id_evaluacion FROM evaluaciones WHERE id_alumno = ?)")->execute([$id]);
                        $db->prepare("DELETE FROM evaluaciones WHERE id_alumno = ?")->execute([$id]);
                        $db->prepare("DELETE FROM alumnos WHERE id_alumno=?")->execute([$id]);
                        $db->commit();
                        return $this->response->setJSON(['success' => true, 'message' => "Alumno eliminado."]);
                    } catch (\Exception $e) {
                        $db->rollBack();
                        return $this->response->setJSON(['success' => false, 'message' => "Error al eliminar alumno."]);
                    }
                }
                return $this->response->setJSON(['success' => false, 'message' => "ID no válido."]);

            case 'resend_email':
                $id = intval($inputData['id_evaluacion'] ?? 0);
                if (!$id) {
                    return $this->response->setJSON(['success' => false, 'message' => "ID de evaluación no válido."]);
                }

                require_once ROOTPATH . 'includes/email_sender.php';
                require_once ROOTPATH . 'api/pdf_generator.php';
                
                $stmt = $db->prepare("SELECT id_alumno, asunto_principal, calificacion_total FROM evaluaciones WHERE id_evaluacion = ?");
                $stmt->execute([$id]);
                $ev = $stmt->fetch();
                
                if ($ev) {
                    $stmtAl = $db->prepare("SELECT nombre_completo, correo FROM alumnos WHERE id_alumno = ?");
                    $stmtAl->execute([$ev['id_alumno']]);
                    $alumnoInfo = $stmtAl->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($alumnoInfo && !empty($alumnoInfo['correo'])) {
                        $stmtAvg = $db->prepare("SELECT AVG(calificacion_total) FROM evaluaciones WHERE id_alumno = ?");
                        $stmtAvg->execute([$ev['id_alumno']]);
                        $promedio = floatval($stmtAvg->fetchColumn());
                        
                        $pdfContent = generateEvaluationPdf($db, $id);
                        
                        if (!empty($pdfContent)) {
                            $sent = sendEvaluationEmail(
                                $alumnoInfo['correo'],
                                $alumnoInfo['nombre_completo'],
                                $promedio,
                                $pdfContent,
                                $ev['asunto_principal'],
                                $ev['calificacion_total']
                            );
                            if ($sent) {
                                return $this->response->setJSON(['success' => true, 'message' => "Reporte enviado al correo del alumno exitosamente."]);
                            } else {
                                return $this->response->setJSON(['success' => false, 'message' => "El SMTP está configurado, pero falló el envío del correo. Revisa email_log.log para más detalles."]);
                            }
                        } else {
                            return $this->response->setJSON(['success' => false, 'message' => "No se pudo generar el reporte PDF."]);
                        }
                    } else {
                        return $this->response->setJSON(['success' => false, 'message' => "El alumno no tiene un correo electrónico registrado."]);
                    }
                } else {
                    return $this->response->setJSON(['success' => false, 'message' => "Evaluación no encontrada."]);
                }

            case 'reset_db':
                try {
                    ob_start();
                    require ROOTPATH . 'setup.php';
                    $output = ob_get_clean();
                    return $this->response->setJSON(['success' => true, 'message' => "Base de datos reconstruida con éxito. Por favor inicia sesión nuevamente."]);
                } catch (\Exception $e) {
                    if (ob_get_level() > 0) ob_end_clean();
                    return $this->response->setJSON(['success' => false, 'message' => "Error al reconstruir la base de datos: " . $e->getMessage()]);
                }

            default:
                return $this->response->setJSON(['success' => false, 'message' => "Acción desconocida."]);
        }
    }

    /**
     * AJAX: Returns aggregated report data for a single student (JSON).
     */
    public function reportesData()
    {
        $session = session();
        if ($session->get('admin_logged_in') !== true) {
            return $this->response->setJSON(['success' => false, 'message' => 'No autorizado.'])->setStatusCode(401);
        }

        require_once ROOTPATH . 'config.php';
        $db = getDbConnection();

        $input = $this->request->getJSON(true) ?: [];
        $alumnoId = intval($input['alumno_id'] ?? 0);
        if (!$alumnoId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de alumno requerido.']);
        }

        try {
            // Student info
            $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_alumno = ?");
            $stmt->execute([$alumnoId]);
            $student = $stmt->fetch();
            if (!$student) {
                return $this->response->setJSON(['success' => false, 'message' => 'Alumno no encontrado.']);
            }

            // All evaluations for this student (ordered by date)
            $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
            $stmt->execute([$alumnoId]);
            $evaluaciones = $stmt->fetchAll();

            // Competency averages across all evaluations
            $stmt = $db->prepare("SELECT dr.competencia, AVG(dr.puntaje) as promedio, COUNT(*) as count FROM detalles_rubrica dr JOIN evaluaciones e ON dr.id_evaluacion = e.id_evaluacion WHERE e.id_alumno = ? GROUP BY dr.competencia ORDER BY dr.competencia");
            $stmt->execute([$alumnoId]);
            $competencias = $stmt->fetchAll();

            // Complexity distribution
            $stmt = $db->prepare("SELECT complejidad, COUNT(*) as count FROM evaluaciones WHERE id_alumno = ? GROUP BY complejidad");
            $stmt->execute([$alumnoId]);
            $complejidad = $stmt->fetchAll();

            // Areas to improve (word frequency from a_mejorar)
            $stmt = $db->prepare("SELECT dr.a_mejorar FROM detalles_rubrica dr JOIN evaluaciones e ON dr.id_evaluacion = e.id_evaluacion WHERE e.id_alumno = ? AND dr.a_mejorar IS NOT NULL AND dr.a_mejorar != ''");
            $stmt->execute([$alumnoId]);
            $mejorasRaw = $stmt->fetchAll();

            // Compute improvement indices
            $indices = $this->computeIndices($evaluaciones, $competencias, $mejorasRaw);

            // Per-evaluation detail with rubric breakdown
            $evalDetalles = [];
            foreach ($evaluaciones as &$ev) {
                $stmt = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                $stmt->execute([$ev['id_evaluacion']]);
                $ev['detalles'] = $stmt->fetchAll();
            }
            unset($ev);

            return $this->response->setJSON([
                'success' => true,
                'student' => $student,
                'evaluaciones' => $evaluaciones,
                'competencias' => $competencias,
                'complejidad' => $complejidad,
                'indices' => $indices,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Error al cargar reporte: ' . $e->getMessage()]);
        }
    }

    /**
     * Computes improvement indices from evaluation data.
     */
    public function computeIndices($evaluaciones, $competencias, $mejorasRaw)
    {
        $total = count($evaluaciones);
        $scores = array_map(function($e) { return floatval($e['calificacion_total']); }, $evaluaciones);

        // 1. Average score
        $avgScore = $total > 0 ? array_sum($scores) / $total : 0;

        // 2. Trend (linear regression slope)
        $trend = 0;
        if ($total >= 2) {
            $n = $total;
            $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
            foreach ($scores as $i => $y) {
                $x = $i + 1;
                $sumX += $x; $sumY += $y;
                $sumXY += $x * $y; $sumX2 += $x * $x;
            }
            $trend = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        }

        // 3. Strongest / weakest competency
        $strongest = ''; $weakest = '';
        $maxAvg = 0; $minAvg = 999;
        foreach ($competencias as $c) {
            $prom = floatval($c['promedio']);
            if ($prom > $maxAvg) { $maxAvg = $prom; $strongest = $c['competencia']; }
            if ($prom < $minAvg) { $minAvg = $prom; $weakest = $c['competencia']; }
        }

        // 4. Consistency (standard deviation)
        $stddev = 0;
        if ($total >= 2) {
            $variance = 0;
            foreach ($scores as $s) { $variance += pow($s - $avgScore, 2); }
            $stddev = sqrt($variance / ($total - 1));
        }

        // 5. Progress (last 3 vs first 3)
        $progress = 0;
        if ($total >= 4) {
            $first3 = array_slice($scores, 0, 3);
            $last3 = array_slice($scores, -3);
            $avgFirst = array_sum($first3) / 3;
            $avgLast = array_sum($last3) / 3;
            $progress = $avgLast - $avgFirst;
        }

        // 6. Word frequency from "a_mejorar"
        $stopWords = ['de', 'la', 'el', 'en', 'y', 'a', 'los', 'del', 'las', 'que', 'por', 'con', 'un', 'una', 'para', 'es', 'al', 'lo', 'su', 'se', 'no', 'más', 'pero', 'sus', 'le', 'ya', 'este', 'entre', 'porque', 'todo', 'esta', 'muy', 'sin', 'como'];
        $wordFreq = [];
        foreach ($mejorasRaw as $row) {
            $text = mb_strtolower(trim($row['a_mejorar']));
            $words = preg_split('/[\s,;.\-!:?()]+/', $text);
            foreach ($words as $w) {
                $w = trim($w);
                if (mb_strlen($w) > 2 && !in_array($w, $stopWords) && is_numeric($w) === false) {
                    $wordFreq[$w] = ($wordFreq[$w] ?? 0) + 1;
                }
            }
        }
        arsort($wordFreq);
        $topMejoras = array_slice($wordFreq, 0, 10, true);

        return [
            'total_evaluaciones' => $total,
            'promedio' => round($avgScore, 2),
            'promedio_display' => round($avgScore / 10, 1),
            'trend' => round($trend, 2),
            'trend_text' => $trend > 0.5 ? 'Mejora constante' : ($trend < -0.5 ? 'Requiere atención' : 'Estable'),
            'competencia_fuerte' => $strongest,
            'competencia_debil' => $weakest,
            'consistencia' => round($stddev, 2),
            'consistencia_text' => $stddev < 5 ? 'Alta consistencia' : ($stddev < 12 ? 'Consistencia moderada' : 'Variable'),
            'progreso' => round($progress, 2),
            'progreso_text' => $progress > 2 ? 'Mejorando' : ($progress < -2 ? 'Disminuyendo' : 'Estable'),
            'top_areas_mejora' => $topMejoras,
        ];
    }

    /**
     * Generates and downloads an Excel file with the student report.
     */
    public function exportExcel()
    {
        $session = session();
        if ($session->get('admin_logged_in') !== true) {
            return $this->response->setJSON(['success' => false, 'message' => 'No autorizado.'])->setStatusCode(401);
        }

        require_once ROOTPATH . 'config.php';
        $db = getDbConnection();

        $alumnoId = intval($this->request->getGet('alumno_id') ?? 0);
        if (!$alumnoId) {
            die('ID de alumno requerido.');
        }

        // Get student + evaluations (same queries as reportesData)
        $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_alumno = ?");
        $stmt->execute([$alumnoId]);
        $student = $stmt->fetch();
        if (!$student) die('Alumno no encontrado.');

        $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
        $stmt->execute([$alumnoId]);
        $evaluaciones = $stmt->fetchAll();

        // Build CSV with multiple sections
        $filename = 'reporte_' . preg_replace('/[^a-zA-Z0-9]/', '_', $student['matricula']) . '_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for UTF-8

        // Section 1: Student info
        fputcsv($output, ['REPORTE INDIVIDUAL DE ALUMNO - MINI-CEX']);
        fputcsv($output, ['']);
        fputcsv($output, ['Matrícula', $student['matricula']]);
        fputcsv($output, ['Nombre', $student['nombre_completo']]);
        fputcsv($output, ['Semestre/Grupo', $student['semestre_grupo']]);
        fputcsv($output, ['Correo', $student['correo'] ?? '']);
        fputcsv($output, ['Docente', $student['docente_nombre'] ?? '']);
        fputcsv($output, ['Fecha del reporte', date('d/m/Y H:i')]);
        fputcsv($output, ['']);

        // Section 2: All evaluations
        fputcsv($output, ['=== EVALUACIONES ===']);
        fputcsv($output, ['#', 'UUID', 'Fecha', 'Evaluador', 'Entorno', 'Paciente', 'Asunto Principal', 'Complejidad', 'T.Observación', 'T.Feedback', 'Calificación']);
        foreach ($evaluaciones as $i => $ev) {
            fputcsv($output, [
                $i + 1,
                $ev['uuid'],
                $ev['fecha_evaluacion'],
                $ev['evaluador_nombre'] ?? '',
                $ev['entorno_clinico'],
                $ev['tipo_paciente'],
                $ev['asunto_principal'],
                $ev['complejidad'],
                $ev['tiempo_observacion'],
                $ev['tiempo_feedback'],
                number_format($ev['calificacion_total'], 2),
            ]);
        }
        fputcsv($output, ['']);

        // Section 3: Rubric details for each evaluation
        fputcsv($output, ['=== DETALLE DE RÚBRICAS ===']);
        fputcsv($output, ['Evaluación #', 'Fecha', 'Competencia', 'Puntaje', 'Notas', 'A Destacar', 'A Mejorar']);
        foreach ($evaluaciones as $i => $ev) {
            $stmt = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
            $stmt->execute([$ev['id_evaluacion']]);
            $detalles = $stmt->fetchAll();
            foreach ($detalles as $d) {
                fputcsv($output, [
                    $i + 1,
                    $ev['fecha_evaluacion'],
                    $d['competencia'],
                    $d['puntaje'],
                    $d['notas'] ?? '',
                    $d['a_destacar'] ?? '',
                    $d['a_mejorar'] ?? '',
                ]);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX: Returns aggregated report data for all students of a teacher (JSON).
     */
    public function reportesDocenteData()
    {
        $session = session();
        if ($session->get('admin_logged_in') !== true) {
            return $this->response->setJSON(['success' => false, 'message' => 'No autorizado.'])->setStatusCode(401);
        }

        require_once ROOTPATH . 'config.php';
        $db = getDbConnection();

        $input = $this->request->getJSON(true) ?: [];
        $docenteId = intval($input['docente_id'] ?? 0);
        $modo = ($input['modo'] ?? 'mine') === 'all' ? 'all' : 'mine';
        if (!$docenteId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de docente requerido.']);
        }

        try {
            // Teacher info
            $stmt = $db->prepare("SELECT id_usuario, nombre_completo, email FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$docenteId]);
            $docente = $stmt->fetch();
            if (!$docente) {
                return $this->response->setJSON(['success' => false, 'message' => 'Docente no encontrado.']);
            }

            $alumnos = [];
            $totalScoreSum = 0;
            $totalEvalCount = 0;

            if ($modo === 'mine') {
                // ── Mode "mine": evaluations made BY this teacher, grouped by student ──
                $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre, a.nombre_completo AS alumno_nombre, a.matricula, a.semestre_grupo FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario LEFT JOIN alumnos a ON e.id_alumno = a.id_alumno WHERE e.id_evaluador = ? ORDER BY a.nombre_completo ASC, e.fecha_evaluacion ASC");
                $stmt->execute([$docenteId]);
                $allEvals = $stmt->fetchAll();

                $studentGroups = [];
                foreach ($allEvals as $ev) {
                    $aid = $ev['id_alumno'];
                    if (!isset($studentGroups[$aid])) {
                        $studentGroups[$aid] = [
                            'id_alumno' => $aid,
                            'matricula' => $ev['matricula'] ?? '—',
                            'nombre_completo' => $ev['alumno_nombre'] ?? '—',
                            'semestre_grupo' => $ev['semestre_grupo'] ?? '—',
                            'evaluaciones' => [],
                        ];
                    }
                    $studentGroups[$aid]['evaluaciones'][] = $ev;
                }

                foreach ($studentGroups as $aid => $sData) {
                    $evals = $sData['evaluaciones'];
                    $evalIds = array_map(function($e) { return $e['id_evaluacion']; }, $evals);

                    if (empty($evalIds)) continue;
                    $placeholders = implode(',', array_fill(0, count($evalIds), '?'));

                    $stmt = $db->prepare("SELECT dr.competencia, AVG(dr.puntaje) as promedio, COUNT(*) as count FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) GROUP BY dr.competencia");
                    $stmt->execute($evalIds);
                    $competencias = $stmt->fetchAll();

                    $stmt = $db->prepare("SELECT complejidad, COUNT(*) as count FROM evaluaciones WHERE id_evaluacion IN ($placeholders) GROUP BY complejidad");
                    $stmt->execute($evalIds);
                    $complejidad = $stmt->fetchAll();

                    $stmt = $db->prepare("SELECT dr.a_mejorar FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) AND dr.a_mejorar IS NOT NULL AND dr.a_mejorar != ''");
                    $stmt->execute($evalIds);
                    $mejorasRaw = $stmt->fetchAll();

                    $indices = $this->computeIndices($evals, $competencias, $mejorasRaw);

                    // Rubric details per evaluation
                    foreach ($evals as &$ev) {
                        $stmt = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                        $stmt->execute([$ev['id_evaluacion']]);
                        $ev['detalles'] = $stmt->fetchAll();
                    }
                    unset($ev);

                    $alumnos[] = [
                        'id_alumno' => (int)$aid,
                        'matricula' => $sData['matricula'],
                        'nombre_completo' => $sData['nombre_completo'],
                        'semestre_grupo' => $sData['semestre_grupo'],
                        'indices' => $indices,
                        'evaluaciones' => $evals,
                        'competencias' => $competencias,
                        'complejidad' => $complejidad,
                    ];
                    $totalScoreSum += $indices['promedio'];
                    $totalEvalCount += $indices['total_evaluaciones'];
                }
            } else {
                // ── Mode "all": all students assigned to this teacher, ALL their evaluations ──
                $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_docente = ? ORDER BY a.nombre_completo ASC");
                $stmt->execute([$docenteId]);
                $students = $stmt->fetchAll();

                foreach ($students as $s) {
                    $aid = (int)$s['id_alumno'];

                    $stmtE = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
                    $stmtE->execute([$aid]);
                    $evals = $stmtE->fetchAll();

                    if (empty($evals)) {
                        $alumnos[] = [
                            'id_alumno' => $aid,
                            'matricula' => $s['matricula'],
                            'nombre_completo' => $s['nombre_completo'],
                            'semestre_grupo' => $s['semestre_grupo'],
                            'indices' => [
                                'total_evaluaciones' => 0,
                                'promedio' => 0,
                                'promedio_display' => 0,
                                'trend' => 0,
                                'trend_text' => 'Sin datos',
                                'competencia_fuerte' => '—',
                                'competencia_debil' => '—',
                                'consistencia' => 0,
                                'consistencia_text' => 'Sin datos',
                                'progreso' => 0,
                                'progreso_text' => 'Sin datos',
                                'top_areas_mejora' => [],
                            ],
                            'evaluaciones' => [],
                            'competencias' => [],
                            'complejidad' => [],
                        ];
                        continue;
                    }

                    $evalIds = array_map(function($e) { return $e['id_evaluacion']; }, $evals);
                    $placeholders = implode(',', array_fill(0, count($evalIds), '?'));

                    $stmtC = $db->prepare("SELECT dr.competencia, AVG(dr.puntaje) as promedio, COUNT(*) as count FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) GROUP BY dr.competencia");
                    $stmtC->execute($evalIds);
                    $competencias = $stmtC->fetchAll();

                    $stmtC2 = $db->prepare("SELECT complejidad, COUNT(*) as count FROM evaluaciones WHERE id_alumno = ? GROUP BY complejidad");
                    $stmtC2->execute([$aid]);
                    $complejidad = $stmtC2->fetchAll();

                    $stmtC3 = $db->prepare("SELECT dr.a_mejorar FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) AND dr.a_mejorar IS NOT NULL AND dr.a_mejorar != ''");
                    $stmtC3->execute($evalIds);
                    $mejorasRaw = $stmtC3->fetchAll();

                    $indices = $this->computeIndices($evals, $competencias, $mejorasRaw);

                    foreach ($evals as &$ev) {
                        $stmtD = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                        $stmtD->execute([$ev['id_evaluacion']]);
                        $ev['detalles'] = $stmtD->fetchAll();
                    }
                    unset($ev);

                    $alumnos[] = [
                        'id_alumno' => $aid,
                        'matricula' => $s['matricula'],
                        'nombre_completo' => $s['nombre_completo'],
                        'semestre_grupo' => $s['semestre_grupo'],
                        'indices' => $indices,
                        'evaluaciones' => $evals,
                        'competencias' => $competencias,
                        'complejidad' => $complejidad,
                    ];
                    $totalScoreSum += $indices['promedio'];
                    $totalEvalCount += $indices['total_evaluaciones'];
                }
            }

            $numAlumnos = count($alumnos);
            $promedioGeneral = $numAlumnos > 0 ? round($totalScoreSum / $numAlumnos, 1) : 0;

            return $this->response->setJSON([
                'success' => true,
                'docente' => [
                    'id_usuario' => (int)$docente['id_usuario'],
                    'nombre_completo' => $docente['nombre_completo'],
                    'email' => $docente['email'] ?? '',
                ],
                'modo' => $modo,
                'resumen' => [
                    'total_alumnos' => $numAlumnos,
                    'alumnos_con_evaluaciones' => count(array_filter($alumnos, function($a) { return $a['indices']['total_evaluaciones'] > 0; })),
                    'total_evaluaciones' => $totalEvalCount,
                    'promedio_general' => $promedioGeneral,
                ],
                'alumnos' => $alumnos,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Error al cargar reporte por docente: ' . $e->getMessage()]);
        }
    }

    public function guide()
    {
        return view('admin_guide');
    }

    /**
     * Muestra la página de metodología de cálculo con todas las fórmulas
     * e indicadores utilizados en los reportes por alumno.
     */
    public function metodologia()
    {
        return view('metodologia');
    }
}
