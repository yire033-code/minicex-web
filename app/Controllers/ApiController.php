<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class ApiController extends BaseController
{
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Set CORS headers
        $this->response->setHeader("Access-Control-Allow-Origin", "*");
        $this->response->setHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
        $this->response->setHeader("Access-Control-Allow-Headers", "Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'options') {
            $this->response->setStatusCode(200)->send();
            exit();
        }
    }
    private function getDb()
    {
        require_once ROOTPATH . 'config.php';
        $db = getDbConnection();
        // Run migration helper for alumnos.correo
        try { 
            @$db->exec("ALTER TABLE alumnos ADD COLUMN correo VARCHAR(255) DEFAULT ''"); 
        } catch (\PDOException $e) {}
        return $db;
    }

    public function authLogin()
    {
        try {
            $db = $this->getDb();
            $input = $this->request->getJSON(true) ?: [];

            $email = trim($input['email'] ?? '');
            $password = trim($input['password'] ?? '');

            if (empty($email) || empty($password)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Email y contraseña son requeridos.'
                ])->setStatusCode(400);
            }

            $stmt = $db->prepare("SELECT id_usuario, nombre_completo, email, password_hash, rol FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['password_hash'] === $password) {
                return $this->response->setJSON([
                    'success' => true,
                    'user' => [
                        'id_usuario' => intval($user['id_usuario']),
                        'nombre_completo' => $user['nombre_completo'],
                        'email' => $user['email'],
                        'rol' => $user['rol']
                    ]
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Credenciales incorrectas.'
                ])->setStatusCode(401);
            }
        } catch (\Throwable $th) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Exception: ' . $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ])->setStatusCode(500);
        }
    }

    public function getStudents()
    {
        $db = $this->getDb();
        $evaluadorId = $this->request->getGet('evaluador_id') !== null ? intval($this->request->getGet('evaluador_id')) : 1;

        $stmt = $db->prepare("SELECT id_alumno, matricula, nombre_completo, semestre_grupo, correo FROM alumnos WHERE id_docente = ?");
        $stmt->execute([$evaluadorId]);
        $students = $stmt->fetchAll();

        return $this->response->setJSON($students);
    }

    public function syncStudents()
    {
        $db = $this->getDb();
        $input = $this->request->getJSON(true);

        if (!is_array($input)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'El cuerpo de la solicitud debe ser un arreglo JSON de alumnos.'
            ])->setStatusCode(400);
        }

        $synced = [];
        $db->beginTransaction();

        try {
            foreach ($input as $alRaw) {
                $matricula = trim($alRaw['matricula'] ?? '');
                $nombre = trim($alRaw['nombre_completo'] ?? '');
                $semestre = trim($alRaw['semestre_grupo'] ?? '');
                $correo = trim($alRaw['correo'] ?? '');
                $docenteId = intval($alRaw['id_docente'] ?? 1);

                if (empty($matricula) || empty($nombre)) {
                    continue; // Skip invalid
                }

                // Check duplicate by matricula
                $stmtCheck = $db->prepare("SELECT id_alumno FROM alumnos WHERE matricula = ?");
                $stmtCheck->execute([$matricula]);
                if ($stmtCheck->rowCount() > 0) {
                    $idAlumno = intval($stmtCheck->fetchColumn());
                    // Update email if changed or set
                    $stmtUpdate = $db->prepare("UPDATE alumnos SET correo = ? WHERE id_alumno = ?");
                    $stmtUpdate->execute([$correo, $idAlumno]);
                } else {
                    $stmtInsert = $db->prepare("INSERT INTO alumnos (matricula, nombre_completo, semestre_grupo, correo, id_docente) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsert->execute([$matricula, $nombre, $semestre, $correo, $docenteId]);
                    $idAlumno = intval($db->lastInsertId());
                }

                $synced[] = [
                    'matricula' => $matricula,
                    'id_alumno' => $idAlumno
                ];
            }

            $db->commit();
            return $this->response->setJSON([
                'success' => true,
                'synced' => $synced
            ]);

        } catch (\Exception $ex) {
            $db->rollBack();
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error al sincronizar alumnos: ' . $ex->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function syncEvaluations()
    {
        $db = $this->getDb();
        $evaluations = $this->request->getJSON(true);

        if (!is_array($evaluations)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'El cuerpo de la solicitud debe ser un arreglo JSON de evaluaciones.',
                'syncedUuids' => []
            ])->setStatusCode(400);
        }

        $syncedUuids = [];
        $newlySyncedEvaluations = [];
        $db->beginTransaction(); // Start atomic transaction

        try {
            foreach ($evaluations as $item) {
                if (!isset($item['evaluation']) || !isset($item['evaluation']['uuid'])) {
                    continue; // Skip invalid items
                }

                $evalRaw = $item['evaluation'];
                $detailsRaw = $item['details'] ?? [];
                $uuid = $evalRaw['uuid'];

                // 1. Idempotency Check: Verify if uuid already exists
                $stmtCheck = $db->prepare("SELECT id_evaluacion FROM evaluaciones WHERE uuid = ?");
                $stmtCheck->execute([$uuid]);
                if ($stmtCheck->rowCount() > 0) {
                    // If it already exists, add to synced lists so client knows it was handled
                    $syncedUuids[] = $uuid;
                    continue;
                }

                // 2. Map data fields (Kotlin camelCase -> MySQL snake_case)
                $fechaEvaluacion = isset($evalRaw['fechaEvaluacion']) 
                    ? date('Y-m-d', intval($evalRaw['fechaEvaluacion'] / 1000)) 
                    : date('Y-m-d');

                $evalQuery = "INSERT INTO evaluaciones (
                    uuid, id_evaluador, id_alumno, fecha_evaluacion, entorno_clinico, 
                    tipo_paciente, asunto_principal, complejidad, tiempo_observacion, 
                    tiempo_feedback, calificacion_total, firma_evaluador, firma_alumno, is_synced
                ) VALUES (
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, 
                    ?, ?, ?, ?, 1
                )";

                $stmtEval = $db->prepare($evalQuery);
                $stmtEval->execute([
                    $uuid,
                    intval($evalRaw['idEvaluador'] ?? 1),
                    intval($evalRaw['idAlumno'] ?? 1),
                    $fechaEvaluacion,
                    $evalRaw['entornoClinico'] ?? 'Consulta MF',
                    $evalRaw['tipoPaciente'] ?? 'Nuevo',
                    $evalRaw['asuntoPrincipal'] ?? '',
                    $evalRaw['complejidad'] ?? 'Media',
                    intval($evalRaw['tiempoObservacion'] ?? 0),
                    intval($evalRaw['tiempoFeedback'] ?? 0),
                    floatval($evalRaw['calificacionTotal'] ?? 0.0),
                    $evalRaw['firmaEvaluador'] ?? null,
                    $evalRaw['firmaAlumno'] ?? null
                ]);

                $newEvalId = $db->lastInsertId();

                // Insert rubric details
                if (is_array($detailsRaw)) {
                    $detailsQuery = "INSERT INTO detalles_rubrica (
                        id_evaluacion, competencia, puntaje, notas, a_destacar, a_mejorar
                    ) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmtDetails = $db->prepare($detailsQuery);
                    foreach ($detailsRaw as $detail) {
                        $stmtDetails->execute([
                            $newEvalId,
                            $detail['competencia'] ?? '',
                            intval($detail['puntaje'] ?? 0),
                            $detail['notas'] ?? '',
                            $detail['aDestacar'] ?? '',
                            $detail['aMejorar'] ?? ''
                        ]);
                    }
                }

                $newlySyncedEvaluations[] = [
                    'id_evaluacion' => $newEvalId,
                    'asunto_principal' => $evalRaw['asuntoPrincipal'] ?? '',
                    'calificacion_total' => floatval($evalRaw['calificacionTotal'] ?? 0.0),
                    'id_alumno' => intval($evalRaw['idAlumno'] ?? 1)
                ];

                $syncedUuids[] = $uuid;
            }

            $db->commit(); // Commit all inserts atomically

        } catch (\Exception $ex) {
            $db->rollBack();
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error al guardar evaluaciones: ' . $ex->getMessage(),
                'syncedUuids' => $syncedUuids
            ])->setStatusCode(500);
        }

        // Send notification emails after successful commit
        require_once ROOTPATH . 'api/pdf_generator.php';
        require_once ROOTPATH . 'includes/email_sender.php';
        
        foreach ($newlySyncedEvaluations as $newEv) {
            // Fetch student info
            $stmtAl = $db->prepare("SELECT nombre_completo, correo FROM alumnos WHERE id_alumno = ?");
            $stmtAl->execute([$newEv['id_alumno']]);
            $alumnoInfo = $stmtAl->fetch(\PDO::FETCH_ASSOC);
            
            if ($alumnoInfo && !empty($alumnoInfo['correo'])) {
                // Fetch student average
                $stmtAvg = $db->prepare("SELECT AVG(calificacion_total) FROM evaluaciones WHERE id_alumno = ?");
                $stmtAvg->execute([$newEv['id_alumno']]);
                $promedio = floatval($stmtAvg->fetchColumn());
                
                // Generate PDF report
                $pdfContent = generateEvaluationPdf($db, $newEv['id_evaluacion']);
                
                // Send email
                if (!empty($pdfContent)) {
                    sendEvaluationEmail(
                        $alumnoInfo['correo'],
                        $alumnoInfo['nombre_completo'],
                        $promedio,
                        $pdfContent,
                        $newEv['asunto_principal'],
                        $newEv['calificacion_total']
                    );
                }
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Sincronización completada con éxito.',
            'syncedUuids' => $syncedUuids
        ]);
    }

    public function getEvaluations()
    {
        $db = $this->getDb();
        $evaluadorId = $this->request->getGet('evaluador_id') !== null ? intval($this->request->getGet('evaluador_id')) : 1;

        $stmt = $db->prepare("SELECT * FROM evaluaciones WHERE id_evaluador = ?");
        $stmt->execute([$evaluadorId]);
        $evaluations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($evaluations as $eval) {
            $stmtDetails = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
            $stmtDetails->execute([$eval['id_evaluacion']]);
            $details = $stmtDetails->fetchAll(\PDO::FETCH_ASSOC);

            $evalDto = [
                'idEvaluacion' => intval($eval['id_evaluacion']),
                'uuid' => $eval['uuid'],
                'idEvaluador' => intval($eval['id_evaluador']),
                'idAlumno' => intval($eval['id_alumno']),
                'fechaEvaluacion' => strtotime($eval['fecha_evaluacion']) * 1000,
                'entornoClinico' => $eval['entorno_clinico'],
                'tipoPaciente' => $eval['tipo_paciente'],
                'asuntoPrincipal' => $eval['asunto_principal'],
                'complejidad' => $eval['complejidad'],
                'tiempoObservacion' => intval($eval['tiempo_observacion']),
                'tiempoFeedback' => intval($eval['tiempo_feedback']),
                'calificacionTotal' => floatval($eval['calificacion_total']),
                'firmaEvaluador' => $eval['firma_evaluador'],
                'firmaAlumno' => $eval['firma_alumno'],
                'isSynced' => true,
                'createdAt' => strtotime($eval['created_at']) * 1000
            ];

            $detailsDto = [];
            foreach ($details as $detail) {
                $detailsDto[] = [
                    'idDetalle' => intval($detail['id_detalle']),
                    'idEvaluacion' => intval($detail['id_evaluacion']),
                    'competencia' => $detail['competencia'],
                    'puntaje' => intval($detail['puntaje']),
                    'notas' => $detail['notas'],
                    'aDestacar' => $detail['a_destacar'],
                    'aMejorar' => $detail['a_mejorar']
                ];
            }

            $result[] = [
                'evaluation' => $evalDto,
                'details' => $detailsDto
            ];
        }

        return $this->response->setJSON($result);
    }

    public function processQueue()
    {
        $db = $this->getDb();
        $evaluadorId = $this->request->getGet('evaluador_id') !== null ? intval($this->request->getGet('evaluador_id')) : 1;
        $queue = $this->request->getJSON(true) ?: [];

        $processedIds = [];
        $serverActions = [];
        $localToServerStudentIds = []; // Map from App's local idAlumno to Server's id_alumno

        // Verificar inmediatamente si el usuario existe. Si no, retornar acción de borrado y abortar.
        $stmtUserCheck = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_usuario = ?");
        $stmtUserCheck->execute([$evaluadorId]);
        if ($stmtUserCheck->fetchColumn() == 0) {
            return $this->response->setJSON([
                'success' => true,
                'processedIds' => [],
                'serverActions' => [[
                    'action' => 'delete',
                    'tableName' => 'usuarios',
                    'entityUuid' => '',
                    'dataPayload' => '{}',
                    'timestamp' => time() * 1000
                ]]
            ]);
        }

        $db->beginTransaction();

        try {
            // 1. Procesar la cola de entrada (App -> Server)
            foreach ($queue as $item) {
                if (!isset($item['action']) || !isset($item['tableName']) || !isset($item['entityUuid'])) continue;
                
                $action = $item['action'];
                $table = $item['tableName'];
                $uuid = $item['entityUuid'];
                $dataPayloadStr = $item['dataPayload'] ?? '{}';
                $payload = json_decode($dataPayloadStr, true) ?: [];

                // Almacenar en la tabla sync_queue para mantener un historial y permitir que otros dispositivos (si los hay) la descarguen.
                // Aunque en Minicex el maestro es 1-1 con su dispositivo, es útil para recuperar sesión.
                $stmtLog = $db->prepare("INSERT INTO sync_queue (user_id, action, table_name, entity_uuid, data_payload) VALUES (?, ?, ?, ?, ?)");
                $stmtLog->execute([$evaluadorId, $action, $table, $uuid, $dataPayloadStr]);
                
                if ($table === 'alumnos') {
                    if ($action === 'insert' || $action === 'update') {
                        $matricula = $payload['matricula'] ?? '';
                        $nombre = $payload['nombreCompleto'] ?? '';
                        $semestre = $payload['semestreGrupo'] ?? '';
                        $correo = $payload['correo'] ?? '';
                        $localAppId = $payload['idAlumno'] ?? 0;
                        
                        $stmtCheck = $db->prepare("SELECT id_alumno FROM alumnos WHERE uuid = ? OR matricula = ?");
                        $stmtCheck->execute([$uuid, $matricula]);
                        $existing = $stmtCheck->fetch();
                        
                        if ($existing) {
                            $stmtUpdate = $db->prepare("UPDATE alumnos SET nombre_completo=?, semestre_grupo=?, correo=?, uuid=? WHERE id_alumno=?");
                            $stmtUpdate->execute([$nombre, $semestre, $correo, $uuid, $existing['id_alumno']]);
                            if ($localAppId) $localToServerStudentIds[$localAppId] = $existing['id_alumno'];
                        } else {
                            $stmtInsert = $db->prepare("INSERT INTO alumnos (uuid, matricula, nombre_completo, semestre_grupo, correo, id_docente) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmtInsert->execute([$uuid, $matricula, $nombre, $semestre, $correo, $evaluadorId]);
                            if ($localAppId) $localToServerStudentIds[$localAppId] = $db->lastInsertId();
                        }
                    } else if ($action === 'delete') {
                        $stmtDel = $db->prepare("DELETE FROM alumnos WHERE uuid = ?");
                        $stmtDel->execute([$uuid]);
                    }
                } else if ($table === 'evaluaciones') {
                    if ($action === 'insert' || $action === 'update') {
                        $eval = $payload['evaluation'] ?? [];
                        $details = $payload['details'] ?? [];
                        
                        $stmtCheck = $db->prepare("SELECT id_evaluacion FROM evaluaciones WHERE uuid = ?");
                        $stmtCheck->execute([$uuid]);
                        $existing = $stmtCheck->fetch();
                        
                        $fechaEvaluacion = isset($eval['fechaEvaluacion']) ? date('Y-m-d', intval($eval['fechaEvaluacion'] / 1000)) : date('Y-m-d');
                        
                        // Find local student ID
                        $studentUuid = $eval['uuid_alumno'] ?? ''; 
                        $appStudentId = $eval['idAlumno'] ?? 0;
                        
                        if (isset($localToServerStudentIds[$appStudentId])) {
                            $alId = $localToServerStudentIds[$appStudentId];
                        } else {
                            $stmtFindAl = $db->prepare("SELECT id_alumno FROM alumnos WHERE uuid = ? OR matricula = ? OR id_alumno = ?");
                            $stmtFindAl->execute([$studentUuid, "TEMP", $appStudentId]);
                            $alId = $stmtFindAl->fetchColumn() ?: 1;
                        }

                        if (!$existing) {
                            $stmtEval = $db->prepare("INSERT INTO evaluaciones (uuid, id_evaluador, id_alumno, fecha_evaluacion, entorno_clinico, tipo_paciente, asunto_principal, complejidad, tiempo_observacion, tiempo_feedback, calificacion_total, firma_evaluador, firma_alumno, is_synced) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                            $stmtEval->execute([
                                $uuid, $evaluadorId, $alId, $fechaEvaluacion, 
                                $eval['entornoClinico'] ?? '', $eval['tipoPaciente'] ?? '', $eval['asuntoPrincipal'] ?? '',
                                $eval['complejidad'] ?? '', intval($eval['tiempoObservacion'] ?? 0), intval($eval['tiempoFeedback'] ?? 0),
                                floatval($eval['calificacionTotal'] ?? 0), $eval['firmaEvaluador'] ?? null, $eval['firmaAlumno'] ?? null
                            ]);
                            $newId = $db->lastInsertId();
                            
                            if (is_array($details)) {
                                $stmtDet = $db->prepare("INSERT INTO detalles_rubrica (id_evaluacion, competencia, puntaje, notas, a_destacar, a_mejorar) VALUES (?, ?, ?, ?, ?, ?)");
                                foreach ($details as $det) {
                                    $stmtDet->execute([$newId, $det['competencia'] ?? '', intval($det['puntaje'] ?? 0), $det['notas'] ?? '', $det['aDestacar'] ?? '', $det['aMejorar'] ?? '']);
                                }
                            }
                        }
                    }
                }
            }
            
            $db->commit();
        } catch (\Exception $ex) {
            $db->rollBack();
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error al procesar la cola: ' . $ex->getMessage()
            ])->setStatusCode(500);
        }

        // 2. Extraer acciones del servidor (Server -> App) 
        // Enviar todos los usuarios para que la app pueda permitir login offline de cualquier maestro
        try {
            $stmtAllUsers = $db->prepare("SELECT * FROM usuarios");
            $stmtAllUsers->execute();
            $allUsers = $stmtAllUsers->fetchAll(\PDO::FETCH_ASSOC);

            $currentUserFound = false;

            foreach ($allUsers as $u) {
                if ($u['id_usuario'] == $evaluadorId) {
                    $currentUserFound = true;
                }
                $serverActions[] = [
                    'action' => 'update',
                    'tableName' => 'usuarios',
                    'entityUuid' => '',
                    'dataPayload' => json_encode([
                        'id_usuario' => intval($u['id_usuario']),
                        'nombre_completo' => $u['nombre_completo'],
                        'email' => $u['email'],
                        'rol' => $u['rol'],
                        'password_hash' => $u['password_hash']
                    ]),
                    'timestamp' => time() * 1000
                ];
            }

            if (!$currentUserFound) {
                // Usuario logueado actual eliminado en el backend. Mandar acción especial de borrado para cerrar sesión.
                $serverActions[] = [
                    'action' => 'delete',
                    'tableName' => 'usuarios',
                    'entityUuid' => '',
                    'dataPayload' => '{}',
                    'timestamp' => time() * 1000
                ];
            }

            $stmt = $db->prepare("SELECT id_alumno, uuid, matricula, nombre_completo, semestre_grupo, correo, id_docente FROM alumnos WHERE id_docente = ?");
            $stmt->execute([$evaluadorId]);
            $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($students as $st) {
                // Generar action de update implícita para sincronizar a la app
                $serverActions[] = [
                    'action' => 'update',
                    'tableName' => 'alumnos',
                    'entityUuid' => $st['uuid'] ?: '',
                    'dataPayload' => json_encode([
                        'idAlumno' => intval($st['id_alumno']),
                        'uuid' => $st['uuid'] ?: '',
                        'matricula' => $st['matricula'],
                        'nombreCompleto' => $st['nombre_completo'],
                        'semestreGrupo' => $st['semestre_grupo'],
                        'correo' => $st['correo'],
                        'idDocente' => intval($st['id_docente'])
                    ]),
                    'timestamp' => time() * 1000
                ];
            }
        } catch (\Exception $e) {}

        // También enviar las evaluaciones existentes para que la app las recupere si fue reinstalada
        try {
            $stmtEvals = $db->prepare("SELECT e.*, a.matricula as student_matricula FROM evaluaciones e LEFT JOIN alumnos a ON e.id_alumno = a.id_alumno WHERE e.id_evaluador = ?");
            $stmtEvals->execute([$evaluadorId]);
            $evals = $stmtEvals->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($evals as $eval) {
                $stmtDetails = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                $stmtDetails->execute([$eval['id_evaluacion']]);
                $details = $stmtDetails->fetchAll(\PDO::FETCH_ASSOC);

                $evalDto = [
                    'idEvaluacion' => intval($eval['id_evaluacion']),
                    'uuid' => $eval['uuid'],
                    'idEvaluador' => intval($eval['id_evaluador']),
                    'idAlumno' => intval($eval['id_alumno']),
                    'studentMatricula' => $eval['student_matricula'] ?? '',
                    'fechaEvaluacion' => strtotime($eval['fecha_evaluacion']) * 1000,
                    'entornoClinico' => $eval['entorno_clinico'],
                    'tipoPaciente' => $eval['tipo_paciente'],
                    'asuntoPrincipal' => $eval['asunto_principal'],
                    'complejidad' => $eval['complejidad'],
                    'tiempoObservacion' => intval($eval['tiempo_observacion']),
                    'tiempoFeedback' => intval($eval['tiempo_feedback']),
                    'calificacionTotal' => floatval($eval['calificacion_total']),
                    'firmaEvaluador' => $eval['firma_evaluador'],
                    'firmaAlumno' => $eval['firma_alumno'],
                    'isSynced' => true,
                    'createdAt' => strtotime($eval['created_at']) * 1000
                ];

                $detailsDto = [];
                foreach ($details as $detail) {
                    $detailsDto[] = [
                        'idDetalle' => intval($detail['id_detalle']),
                        'idEvaluacion' => intval($detail['id_evaluacion']),
                        'competencia' => $detail['competencia'],
                        'puntaje' => intval($detail['puntaje']),
                        'notas' => $detail['notas'],
                        'aDestacar' => $detail['a_destacar'],
                        'aMejorar' => $detail['a_mejorar']
                    ];
                }

                $serverActions[] = [
                    'action' => 'update',
                    'tableName' => 'evaluaciones',
                    'entityUuid' => $eval['uuid'] ?: '',
                    'dataPayload' => json_encode([
                        'evaluation' => $evalDto,
                        'details' => $detailsDto
                    ]),
                    'timestamp' => time() * 1000
                ];
            }
        } catch (\Exception $e) {}

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Cola procesada correctamente',
            'processedIds' => [], // Local app assumes all sent were processed
            'serverActions' => $serverActions
        ]);
    }

    public function resendEmail()
    {
        $db = $this->getDb();
        $input = $this->request->getJSON(true) ?: [];
        $uuid = trim($input['uuid'] ?? '');

        if (empty($uuid)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'El UUID de la evaluación es requerido.'
            ])->setStatusCode(400);
        }

        $stmt = $db->prepare("SELECT id_evaluacion, id_alumno, asunto_principal, calificacion_total FROM evaluaciones WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $ev = $stmt->fetch();

        if ($ev) {
            $id = intval($ev['id_evaluacion']);
            
            // Fetch student info
            $stmtAl = $db->prepare("SELECT nombre_completo, correo FROM alumnos WHERE id_alumno = ?");
            $stmtAl->execute([$ev['id_alumno']]);
            $alumnoInfo = $stmtAl->fetch(\PDO::FETCH_ASSOC);

            if ($alumnoInfo && !empty($alumnoInfo['correo'])) {
                $stmtAvg = $db->prepare("SELECT AVG(calificacion_total) FROM evaluaciones WHERE id_alumno = ?");
                $stmtAvg->execute([$ev['id_alumno']]);
                $promedio = floatval($stmtAvg->fetchColumn());

                require_once ROOTPATH . 'api/pdf_generator.php';
                $pdfContent = generateEvaluationPdf($db, $id);

                if (!empty($pdfContent)) {
                    require_once ROOTPATH . 'includes/email_sender.php';
                    $sent = sendEvaluationEmail(
                        $alumnoInfo['correo'],
                        $alumnoInfo['nombre_completo'],
                        $promedio,
                        $pdfContent,
                        $ev['asunto_principal'],
                        $ev['calificacion_total']
                    );
                    if ($sent) {
                        return $this->response->setJSON([
                            'success' => true,
                            'message' => 'Correo reenviado con éxito.'
                        ]);
                    } else {
                        return $this->response->setJSON([
                            'success' => false,
                            'message' => 'Error SMTP al reenviar el correo. Verifica los logs del servidor.'
                        ])->setStatusCode(500);
                    }
                } else {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'No se pudo generar el reporte PDF para el envío.'
                    ])->setStatusCode(500);
                }
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'El alumno no tiene un correo electrónico registrado.'
                ])->setStatusCode(400);
            }
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Evaluación no encontrada localmente en el servidor.'
            ])->setStatusCode(404);
        }
    }

    /**
     * GET /api/reports/student/:id
     * Returns aggregated report data for a single student (READ-ONLY, online-only).
     * Android app must check internet before calling this.
     */
    public function getStudentReport($studentId = null)
    {
        if (!$studentId) {
            $studentId = $this->request->getGet('id') ?? $this->request->getGet('student_id');
        }
        $studentId = intval($studentId);
        if (!$studentId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de alumno requerido.'])->setStatusCode(400);
        }

        try {
            $db = $this->getDb();

            // Student info (with evaluator name)
            $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_alumno = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();
            if (!$student) {
                return $this->response->setJSON(['success' => false, 'message' => 'Alumno no encontrado.'])->setStatusCode(404);
            }

            // All evaluations
            $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
            $stmt->execute([$studentId]);
            $evaluaciones = $stmt->fetchAll();

            // Per-evaluation rubric details
            foreach ($evaluaciones as &$ev) {
                $stmt = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                $stmt->execute([$ev['id_evaluacion']]);
                $ev['detalles'] = $stmt->fetchAll();
            }
            unset($ev);

            // Competency averages
            $stmt = $db->prepare("SELECT dr.competencia, AVG(dr.puntaje) as promedio, COUNT(*) as count FROM detalles_rubrica dr JOIN evaluaciones e ON dr.id_evaluacion = e.id_evaluacion WHERE e.id_alumno = ? GROUP BY dr.competencia ORDER BY dr.competencia");
            $stmt->execute([$studentId]);
            $competencias = $stmt->fetchAll();

            // Complexity distribution
            $stmt = $db->prepare("SELECT complejidad, COUNT(*) as count FROM evaluaciones WHERE id_alumno = ? GROUP BY complejidad");
            $stmt->execute([$studentId]);
            $complejidad = $stmt->fetchAll();

            // Areas to improve
            $stmt = $db->prepare("SELECT dr.a_mejorar FROM detalles_rubrica dr JOIN evaluaciones e ON dr.id_evaluacion = e.id_evaluacion WHERE e.id_alumno = ? AND dr.a_mejorar IS NOT NULL AND dr.a_mejorar != ''");
            $stmt->execute([$studentId]);
            $mejorasRaw = $stmt->fetchAll();

            // Compute indices (reuse same logic as admin)
            $total = count($evaluaciones);
            $scores = array_map(function($e) { return floatval($e['calificacion_total']); }, $evaluaciones);
            $avgScore = $total > 0 ? array_sum($scores) / $total : 0;
            $trend = 0;
            if ($total >= 2) {
                $n = $total; $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
                foreach ($scores as $i => $y) { $x = $i + 1; $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumX2 += $x * $x; }
                $trend = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
            }
            $strongest = ''; $weakest = ''; $maxAvg = 0; $minAvg = 999;
            foreach ($competencias as $c) {
                $prom = floatval($c['promedio']);
                if ($prom > $maxAvg) { $maxAvg = $prom; $strongest = $c['competencia']; }
                if ($prom < $minAvg) { $minAvg = $prom; $weakest = $c['competencia']; }
            }
            $stddev = 0;
            if ($total >= 2) {
                $variance = 0;
                foreach ($scores as $s) { $variance += pow($s - $avgScore, 2); }
                $stddev = sqrt($variance / ($total - 1));
            }
            $progress = 0;
            if ($total >= 4) {
                $first3 = array_slice($scores, 0, 3);
                $last3 = array_slice($scores, -3);
                $progress = (array_sum($last3) / 3) - (array_sum($first3) / 3);
            }
            // Word frequency
            $stopWords = ['de', 'la', 'el', 'en', 'y', 'a', 'los', 'del', 'las', 'que', 'por', 'con', 'un', 'una', 'para', 'es', 'al', 'lo', 'su', 'se', 'no', 'más', 'pero', 'sus', 'le', 'ya', 'este', 'entre', 'porque', 'todo', 'esta', 'muy', 'sin', 'como'];
            $wordFreq = [];
            foreach ($mejorasRaw as $row) {
                $text = mb_strtolower(trim($row['a_mejorar']));
                $words = preg_split('/[\s,;.\-!:?()]+/', $text);
                foreach ($words as $w) {
                    $w = trim($w);
                    if (mb_strlen($w) > 2 && !in_array($w, $stopWords) && !is_numeric($w)) {
                        $wordFreq[$w] = ($wordFreq[$w] ?? 0) + 1;
                    }
                }
            }
            arsort($wordFreq);
            $topMejoras = array_slice($wordFreq, 0, 10, true);

            // Format for camelCase Kotlin consumption
            $evaluacionesFormatted = array_map(function($ev) {
                $detalles = array_map(function($d) {
                    return [
                        'idDetalle' => intval($d['id_detalle']),
                        'idEvaluacion' => intval($d['id_evaluacion']),
                        'competencia' => $d['competencia'],
                        'puntaje' => intval($d['puntaje']),
                        'notas' => $d['notas'] ?? '',
                        'aDestacar' => $d['a_destacar'] ?? '',
                        'aMejorar' => $d['a_mejorar'] ?? '',
                    ];
                }, $ev['detalles']);
                return [
                    'idEvaluacion' => intval($ev['id_evaluacion']),
                    'uuid' => $ev['uuid'],
                    'idEvaluador' => intval($ev['id_evaluador']),
                    'idAlumno' => intval($ev['id_alumno']),
                    'fechaEvaluacion' => $ev['fecha_evaluacion'],
                    'entornoClinico' => $ev['entorno_clinico'],
                    'tipoPaciente' => $ev['tipo_paciente'],
                    'asuntoPrincipal' => $ev['asunto_principal'],
                    'complejidad' => $ev['complejidad'],
                    'tiempoObservacion' => intval($ev['tiempo_observacion']),
                    'tiempoFeedback' => intval($ev['tiempo_feedback']),
                    'calificacionTotal' => floatval($ev['calificacion_total']),
                    'evaluadorNombre' => $ev['evaluador_nombre'] ?? '',
                    'detalles' => $detalles,
                ];
            }, $evaluaciones);

            return $this->response->setJSON([
                'success' => true,
                'student' => [
                    'idAlumno' => intval($student['id_alumno']),
                    'uuid' => $student['uuid'],
                    'matricula' => $student['matricula'],
                    'nombreCompleto' => $student['nombre_completo'],
                    'semestreGrupo' => $student['semestre_grupo'],
                    'correo' => $student['correo'] ?? '',
                    'idDocente' => intval($student['id_docente']),
                    'docenteNombre' => $student['docente_nombre'] ?? '',
                ],
                'evaluaciones' => $evaluacionesFormatted,
                'competencias' => array_map(function($c) {
                    return ['competencia' => $c['competencia'], 'promedio' => floatval($c['promedio']), 'count' => intval($c['count'])];
                }, $competencias),
                'complejidad' => array_map(function($c) {
                    return ['complejidad' => $c['complejidad'], 'count' => intval($c['count'])];
                }, $complejidad),
                'indices' => [
                    'totalEvaluaciones' => $total,
                    'promedio' => round($avgScore, 2),
                    'promedioDisplay' => round($avgScore / 10, 1),
                    'trend' => round($trend, 2),
                    'trendText' => $trend > 0.5 ? 'Mejora constante' : ($trend < -0.5 ? 'Requiere atención' : 'Estable'),
                    'competenciaFuerte' => $strongest,
                    'competenciaDebil' => $weakest,
                    'consistencia' => round($stddev, 2),
                    'consistenciaText' => $stddev < 5 ? 'Alta consistencia' : ($stddev < 12 ? 'Consistencia moderada' : 'Variable'),
                    'progreso' => round($progress, 2),
                    'progresoText' => $progress > 2 ? 'Mejorando' : ($progress < -2 ? 'Disminuyendo' : 'Estable'),
                    'topAreasMejora' => $topMejoras,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()])->setStatusCode(500);
        }
    }

    /**
     * GET /api/reports/student/download-xlsx
     * Generates a single-sheet XLSX for a specific student report.
     * Query params: id or student_id (int)
     */
    public function exportStudentReportXlsx()
    {
        $studentId = intval($this->request->getGet('id') ?? $this->request->getGet('student_id') ?? 0);
        if (!$studentId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID de alumno requerido.'])->setStatusCode(400);
        }

        $db = $this->getDb();

        $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_alumno = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) {
            return $this->response->setJSON(['success' => false, 'message' => 'Alumno no encontrado.'])->setStatusCode(404);
        }

        $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
        $stmt->execute([$studentId]);
        $evaluaciones = $stmt->fetchAll();

        foreach ($evaluaciones as &$ev) {
            $stmt = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
            $stmt->execute([$ev['id_evaluacion']]);
            $ev['detalles'] = $stmt->fetchAll();
        }
        unset($ev);

        $stmt = $db->prepare("SELECT dr.competencia, AVG(dr.puntaje) as promedio, COUNT(*) as count FROM detalles_rubrica dr JOIN evaluaciones e ON dr.id_evaluacion = e.id_evaluacion WHERE e.id_alumno = ? GROUP BY dr.competencia ORDER BY dr.competencia");
        $stmt->execute([$studentId]);
        $competencias = $stmt->fetchAll();

        $e = function($s) { return htmlspecialchars($s ?? '', ENT_XML1, 'UTF-8'); };
        $strings = [];
        $si = function($s) use (&$strings) {
            $s = (string)$s;
            if (!in_array($s, $strings)) $strings[] = $s;
            return array_search($s, $strings);
        };

        // Build single-sheet XLSX with sections
        $s1 = '<sheet><sheetData>';

        $r = 1;
        $s1 .= "<row r=\"$r\"><c r=\"A1\" t=\"s\"><v>" . $si('REPORTE INDIVIDUAL - MINI-CEX') . '</v></c></row>';
        $r = 2;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('Alumno') . "</v></c><c r=\"B$r\" t=\"s\"><v>" . $si($student['nombre_completo']) . "</v></c></row>";
        $r = 3;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('Matrícula') . "</v></c><c r=\"B$r\" t=\"s\"><v>" . $si($student['matricula']) . "</v></c></row>";
        $r = 4;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('Semestre/Grupo') . "</v></c><c r=\"B$r\" t=\"s\"><v>" . $si($student['semestre_grupo'] ?? '') . "</v></c></row>";
        $r = 5;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('Docente') . "</v></c><c r=\"B$r\" t=\"s\"><v>" . $si($student['docente_nombre'] ?? '') . "</v></c></row>";
        $r = 6;
        $s1 .= "<row r=\"$r\"/>";

        // Evaluations header
        $r = 7;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('#') . "</v></c><c r=\"B$r\" t=\"s\"><v>" . $si('Fecha') . "</v></c><c r=\"C$r\" t=\"s\"><v>" . $si('Evaluador') . "</v></c><c r=\"D$r\" t=\"s\"><v>" . $si('Entorno') . "</v></c><c r=\"E$r\" t=\"s\"><v>" . $si('Paciente') . "</v></c><c r=\"F$r\" t=\"s\"><v>" . $si('Asunto') . "</v></c><c r=\"G$r\" t=\"s\"><v>" . $si('Complejidad') . "</v></c><c r=\"H$r\" t=\"s\"><v>" . $si('T.Obs') . "</v></c><c r=\"I$r\" t=\"s\"><v>" . $si('T.Fbk') . "</v></c><c r=\"J$r\" t=\"s\"><v>" . $si('Calif.') . "</v></c></row>";

        $evalNum = 0;
        foreach ($evaluaciones as $ev) {
            $evalNum++;
            $r++;
            $s1 .= "<row r=\"$r\">";
            $s1 .= "<c r=\"A$r\" t=\"n\"><v>$evalNum</v></c>";
            $s1 .= "<c r=\"B$r\" t=\"s\"><v>" . $si($ev['fecha_evaluacion']) . "</v></c>";
            $s1 .= "<c r=\"C$r\" t=\"s\"><v>" . $si($ev['evaluador_nombre'] ?? '') . "</v></c>";
            $s1 .= "<c r=\"D$r\" t=\"s\"><v>" . $si($ev['entorno_clinico']) . "</v></c>";
            $s1 .= "<c r=\"E$r\" t=\"s\"><v>" . $si($ev['tipo_paciente']) . "</v></c>";
            $s1 .= "<c r=\"F$r\" t=\"s\"><v>" . $si($ev['asunto_principal']) . "</v></c>";
            $s1 .= "<c r=\"G$r\" t=\"s\"><v>" . $si($ev['complejidad']) . "</v></c>";
            $s1 .= "<c r=\"H$r\" t=\"n\"><v>" . intval($ev['tiempo_observacion']) . "</v></c>";
            $s1 .= "<c r=\"I$r\" t=\"n\"><v>" . intval($ev['tiempo_feedback']) . "</v></c>";
            $s1 .= "<c r=\"J$r\" t=\"n\"><v>" . floatval($ev['calificacion_total']) . "</v></c>";
            $s1 .= '</row>';
        }

        $r++; $s1 .= "<row r=\"$r\"/>";

        // Competencies
        $r++;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('COMPETENCIAS') . "</v></c></row>";
        $r++;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('Competencia') . "</v></c><c r=\"B$r\" t=\"s\"><v>" . $si('Promedio/9') . "</v></c><c r=\"C$r\" t=\"s\"><v>" . $si('Frecuencia') . "</v></c></row>";
        foreach ($competencias as $c) {
            $r++;
            $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si($c['competencia']) . "</v></c><c r=\"B$r\" t=\"n\"><v>" . floatval($c['promedio']) . "</v></c><c r=\"C$r\" t=\"n\"><v>" . intval($c['count']) . "</v></c></row>";
        }

        // Details (rubrics)
        $r++; $s1 .= "<row r=\"$r\"/>";
        $r++;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('RÚBRICAS') . "</v></c></row>";
        $r++;
        $s1 .= "<row r=\"$r\"><c r=\"A$r\" t=\"s\"><v>" . $si('Eval#') . "</v></c><c r=\"B$r\" t=\"s\"><v>" . $si('Competencia') . "</v></c><c r=\"C$r\" t=\"s\"><v>" . $si('Puntaje') . "</v></c><c r=\"D$r\" t=\"s\"><v>" . $si('Notas') . "</v></c><c r=\"E$r\" t=\"s\"><v>" . $si('A Destacar') . "</v></c><c r=\"F$r\" t=\"s\"><v>" . $si('A Mejorar') . "</v></c></row>";
        $evalNum2 = 0;
        foreach ($evaluaciones as $ev) {
            $evalNum2++;
            if (!empty($ev['detalles'])) {
                foreach ($ev['detalles'] as $d) {
                    $r++;
                    $s1 .= "<row r=\"$r\">";
                    $s1 .= "<c r=\"A$r\" t=\"n\"><v>$evalNum2</v></c>";
                    $s1 .= "<c r=\"B$r\" t=\"s\"><v>" . $si($d['competencia']) . "</v></c>";
                    $s1 .= "<c r=\"C$r\" t=\"s\"><v>" . $si($d['puntaje'] . '/9') . "</v></c>";
                    $s1 .= "<c r=\"D$r\" t=\"s\"><v>" . $si($d['notas'] ?? '') . "</v></c>";
                    $s1 .= "<c r=\"E$r\" t=\"s\"><v>" . $si($d['a_destacar'] ?? '') . "</v></c>";
                    $s1 .= "<c r=\"F$r\" t=\"s\"><v>" . $si($d['a_mejorar'] ?? '') . "</v></c>";
                    $s1 .= '</row>';
                }
            }
        }

        $s1 .= '</sheetData></sheet>';

        // Shared strings
        $ss = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $ss .= '<si><t>' . $e($s) . '</t></si>';
        }
        $ss .= '</sst>';

        // Build ZIP
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
            return $this->response->setJSON(['success' => false, 'message' => 'Error al crear XLSX.'])->setStatusCode(500);
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '</Types>'
        );
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>'
        );
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Reporte Individual" sheetId="1" r:id="rId1"/></sheets></workbook>'
        );
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '<Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>'
        );
        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $s1 . '</worksheet>'
        );
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0"/></cellXfs>' .
            '</styleSheet>'
        );
        $zip->addFromString('xl/sharedStrings.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . $ss
        );
        $zip->close();

        $filename = 'reporte_' . preg_replace('/[^a-zA-Z0-9]/', '_', $student['nombre_completo']) . '_' . date('Ymd') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    /**
     * GET /api/reports/teacher-summary
     * Returns aggregated report data for all students of a teacher, with two modes:
     *   mine — only evaluations made BY this teacher
     *   all  — all evaluations for each assigned student
     * Query params: evaluador_id (int), modo (string, "mine"|"all", default "mine")
     */
    public function teacherSummary()
    {
        $db = $this->getDb();
        $evaluadorId = intval($this->request->getGet('evaluador_id') ?? 1);
        $modo = ($this->request->getGet('modo') ?? 'mine') === 'all' ? 'all' : 'mine';

        try {
            // Teacher info
            $stmt = $db->prepare("SELECT id_usuario, nombre_completo, email FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$evaluadorId]);
            $docente = $stmt->fetch();
            if (!$docente) {
                return $this->response->setJSON(['success' => false, 'message' => 'Docente no encontrado.'])->setStatusCode(404);
            }

            $alumnos = [];
            $totalScoreSum = 0;
            $totalEvalCount = 0;

            if ($modo === 'mine') {
                $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre, a.nombre_completo AS alumno_nombre, a.matricula, a.semestre_grupo FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario LEFT JOIN alumnos a ON e.id_alumno = a.id_alumno WHERE e.id_evaluador = ? ORDER BY a.nombre_completo ASC, e.fecha_evaluacion ASC");
                $stmt->execute([$evaluadorId]);
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

                    foreach ($evals as &$ev) {
                        $stmt = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                        $stmt->execute([$ev['id_evaluacion']]);
                        $ev['detalles'] = $stmt->fetchAll();
                    }
                    unset($ev);

                    $alumnos[] = [
                        'idAlumno' => (int)$aid,
                        'matricula' => $sData['matricula'],
                        'nombreCompleto' => $sData['nombre_completo'],
                        'semestreGrupo' => $sData['semestre_grupo'],
                        'indices' => $this->camelizeIndices($indices),
                        'evaluaciones' => $this->formatEvalsCamel($evals),
                        'competencias' => array_map(function($c) {
                            return ['competencia' => $c['competencia'], 'promedio' => floatval($c['promedio']), 'count' => intval($c['count'])];
                        }, $competencias),
                        'complejidad' => array_map(function($c) {
                            return ['complejidad' => $c['complejidad'], 'count' => intval($c['count'])];
                        }, $complejidad),
                    ];
                    $totalScoreSum += $indices['promedio'];
                    $totalEvalCount += $indices['total_evaluaciones'];
                }
            } else {
                $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_docente = ? ORDER BY a.nombre_completo ASC");
                $stmt->execute([$evaluadorId]);
                $students = $stmt->fetchAll();

                foreach ($students as $s) {
                    $aid = (int)$s['id_alumno'];
                    $stmtE = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
                    $stmtE->execute([$aid]);
                    $evals = $stmtE->fetchAll();

                    if (empty($evals)) {
                        $alumnos[] = [
                            'idAlumno' => $aid,
                            'matricula' => $s['matricula'],
                            'nombreCompleto' => $s['nombre_completo'],
                            'semestreGrupo' => $s['semestre_grupo'],
                            'indices' => [
                                'totalEvaluaciones' => 0,
                                'promedio' => 0,
                                'promedioDisplay' => 0,
                                'trend' => 0,
                                'trendText' => 'Sin datos',
                                'competenciaFuerte' => '—',
                                'competenciaDebil' => '—',
                                'consistencia' => 0,
                                'consistenciaText' => 'Sin datos',
                                'progreso' => 0,
                                'progresoText' => 'Sin datos',
                                'topAreasMejora' => [],
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
                        'idAlumno' => $aid,
                        'matricula' => $s['matricula'],
                        'nombreCompleto' => $s['nombre_completo'],
                        'semestreGrupo' => $s['semestre_grupo'],
                        'indices' => $this->camelizeIndices($indices),
                        'evaluaciones' => $this->formatEvalsCamel($evals),
                        'competencias' => array_map(function($c) {
                            return ['competencia' => $c['competencia'], 'promedio' => floatval($c['promedio']), 'count' => intval($c['count'])];
                        }, $competencias),
                        'complejidad' => array_map(function($c) {
                            return ['complejidad' => $c['complejidad'], 'count' => intval($c['count'])];
                        }, $complejidad),
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
                    'idUsuario' => (int)$docente['id_usuario'],
                    'nombreCompleto' => $docente['nombre_completo'],
                    'email' => $docente['email'] ?? '',
                ],
                'modo' => $modo,
                'resumen' => [
                    'totalAlumnos' => $numAlumnos,
                    'alumnosConEvaluaciones' => count(array_filter($alumnos, function($a) { return $a['indices']['totalEvaluaciones'] > 0; })),
                    'totalEvaluaciones' => $totalEvalCount,
                    'promedioGeneral' => $promedioGeneral,
                ],
                'alumnos' => $alumnos,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()])->setStatusCode(500);
        }
    }

    /**
     * GET /api/reports/teacher-summary/export
     * Generates and downloads a CSV file with the full teacher summary report.
     * Query params: evaluador_id (int), modo (string, "mine"|"all", default "mine")
     */
    public function exportTeacherSummary()
    {
        $db = $this->getDb();
        $evaluadorId = intval($this->request->getGet('evaluador_id') ?? 1);
        $modo = ($this->request->getGet('modo') ?? 'mine') === 'all' ? 'all' : 'mine';

        // Get teacher info
        $stmt = $db->prepare("SELECT id_usuario, nombre_completo, email FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$evaluadorId]);
        $docente = $stmt->fetch();
        if (!$docente) {
            return $this->response->setJSON(['success' => false, 'message' => 'Docente no encontrado.'])->setStatusCode(404);
        }

        $filename = 'reporte_docente_' . preg_replace('/[^a-zA-Z0-9]/', '_', $docente['nombre_completo']) . '_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for UTF-8

        // ─── Section 1: Resumen del Docente ───────────────────────────────
        fputcsv($output, ['REPORTE POR DOCENTE - MINI-CEX']);
        fputcsv($output, ['']);
        fputcsv($output, ['Docente', $docente['nombre_completo']]);
        fputcsv($output, ['Email', $docente['email'] ?? '']);
        fputcsv($output, ['Modo', $modo === 'mine' ? 'Mis evaluaciones' : 'Todos los alumnos']);
        fputcsv($output, ['Fecha del reporte', date('d/m/Y H:i')]);
        fputcsv($output, ['']);

        // Collect all data
        $alumnos = [];
        $totalScoreSum = 0;
        $totalEvalCount = 0;

        if ($modo === 'mine') {
            $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre, a.nombre_completo AS alumno_nombre, a.matricula, a.semestre_grupo FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario LEFT JOIN alumnos a ON e.id_alumno = a.id_alumno WHERE e.id_evaluador = ? ORDER BY a.nombre_completo ASC, e.fecha_evaluacion ASC");
            $stmt->execute([$evaluadorId]);
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
                $stmt = $db->prepare("SELECT dr.a_mejorar FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) AND dr.a_mejorar IS NOT NULL AND dr.a_mejorar != ''");
                $stmt->execute($evalIds);
                $mejorasRaw = $stmt->fetchAll();
                $indices = $this->computeIndices($evals, $competencias, $mejorasRaw);
                foreach ($evals as &$ev) {
                    $stmtD = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                    $stmtD->execute([$ev['id_evaluacion']]);
                    $ev['detalles'] = $stmtD->fetchAll();
                }
                unset($ev);
                $alumnos[] = [
                    'matricula' => $sData['matricula'],
                    'nombre_completo' => $sData['nombre_completo'],
                    'semestre_grupo' => $sData['semestre_grupo'],
                    'indices' => $indices,
                    'evaluaciones' => $evals,
                    'competencias' => $competencias,
                    'mejorasRaw' => $mejorasRaw,
                ];
                $totalScoreSum += $indices['promedio'];
                $totalEvalCount += $indices['total_evaluaciones'];
            }
        } else {
            $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_docente = ? ORDER BY a.nombre_completo ASC");
            $stmt->execute([$evaluadorId]);
            $students = $stmt->fetchAll();
            foreach ($students as $s) {
                $aid = (int)$s['id_alumno'];
                $stmtE = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
                $stmtE->execute([$aid]);
                $evals = $stmtE->fetchAll();
                if (empty($evals)) {
                    $alumnos[] = [
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
                        'mejorasRaw' => [],
                    ];
                    continue;
                }
                $evalIds = array_map(function($e) { return $e['id_evaluacion']; }, $evals);
                $placeholders = implode(',', array_fill(0, count($evalIds), '?'));
                $stmtC = $db->prepare("SELECT dr.competencia, AVG(dr.puntaje) as promedio, COUNT(*) as count FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) GROUP BY dr.competencia");
                $stmtC->execute($evalIds);
                $competencias = $stmtC->fetchAll();
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
                    'matricula' => $s['matricula'],
                    'nombre_completo' => $s['nombre_completo'],
                    'semestre_grupo' => $s['semestre_grupo'],
                    'indices' => $indices,
                    'evaluaciones' => $evals,
                    'competencias' => $competencias,
                    'mejorasRaw' => $mejorasRaw,
                ];
                $totalScoreSum += $indices['promedio'];
                $totalEvalCount += $indices['total_evaluaciones'];
            }
        }

        $numAlumnos = count($alumnos);
        $promedioGeneral = $numAlumnos > 0 ? round($totalScoreSum / $numAlumnos, 1) : 0;

        // Summary stats row
        fputcsv($output, ['Total alumnos', $numAlumnos]);
        fputcsv($output, ['Alumnos con evaluaciones', count(array_filter($alumnos, function($a) { return $a['indices']['total_evaluaciones'] > 0; }))]);
        fputcsv($output, ['Total evaluaciones', $totalEvalCount]);
        fputcsv($output, ['Promedio general', number_format($promedioGeneral, 1) . '/10']);
        fputcsv($output, ['']);
        fputcsv($output, ['']);

        // ─── Section 2: Detalle de Alumnos ────────────────────────────────
        fputcsv($output, ['=== DETALLE DE ALUMNOS ===']);
        fputcsv($output, ['']);
        fputcsv($output, ['Alumno', 'Matrícula', 'Semestre', 'Evals', 'Promedio', 'Prom./10', 'Tendencia', 'Consistencia', 'Progreso', 'Comp.Fuerte', 'Comp.Débil']);
        foreach ($alumnos as $al) {
            $idx = $al['indices'];
            fputcsv($output, [
                $al['nombre_completo'],
                $al['matricula'],
                $al['semestre_grupo'] ?? '',
                $idx['total_evaluaciones'],
                number_format($idx['promedio'], 2),
                number_format($idx['promedio_display'], 1) . '/10',
                $idx['trend_text'],
                $idx['consistencia_text'] . ' (σ=' . $idx['consistencia'] . ')',
                $idx['progreso_text'] . ' (' . ($idx['progreso'] >= 0 ? '+' : '') . number_format($idx['progreso'], 1) . ')',
                $idx['competencia_fuerte'] ?: '—',
                $idx['competencia_debil'] ?: '—',
            ]);
            // Competencias per student
            if (!empty($al['competencias'])) {
                fputcsv($output, ['Competencias:']);
                foreach ($al['competencias'] as $c) {
                    fputcsv($output, ['', $c['competencia'], number_format($c['promedio'], 1) . '/9', $c['count'] . ' eval']);
                }
            }
            // Top areas de mejora per student
            if (!empty($idx['top_areas_mejora'])) {
                fputcsv($output, ['Áreas de mejora:']);
                foreach ($idx['top_areas_mejora'] as $word => $freq) {
                    fputcsv($output, ['', $word . ' (' . $freq . 'x)']);
                }
            }
            fputcsv($output, ['']);
        }

        // ─── Section 3: Evaluaciones ──────────────────────────────────────
        fputcsv($output, ['=== EVALUACIONES ===']);
        fputcsv($output, ['']);
        fputcsv($output, ['Alumno', 'Matrícula', '#', 'UUID', 'Fecha', 'Evaluador', 'Entorno', 'Paciente', 'Asunto', 'Complejidad', 'T.Obs', 'T.Fbk', 'Calificación']);
        $evalIdx = 0;
        foreach ($alumnos as $al) {
            foreach ($al['evaluaciones'] as $ev) {
                $evalIdx++;
                fputcsv($output, [
                    $al['nombre_completo'],
                    $al['matricula'],
                    $evalIdx,
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
        }

        fputcsv($output, ['']);
        fputcsv($output, ['=== DETALLE DE RÚBRICAS ===']);
        fputcsv($output, ['']);
        fputcsv($output, ['Alumno', 'Matrícula', 'Eval#', 'Fecha', 'Competencia', 'Puntaje', 'Notas', 'A Destacar', 'A Mejorar']);
        $evalIdx = 0;
        foreach ($alumnos as $al) {
            foreach ($al['evaluaciones'] as $ev) {
                $evalIdx++;
                if (!empty($ev['detalles'])) {
                    foreach ($ev['detalles'] as $d) {
                        fputcsv($output, [
                            $al['nombre_completo'],
                            $al['matricula'],
                            $evalIdx,
                            $ev['fecha_evaluacion'],
                            $d['competencia'],
                            $d['puntaje'] . '/9',
                            $d['notas'] ?? '',
                            $d['a_destacar'] ?? '',
                            $d['a_mejorar'] ?? '',
                        ]);
                    }
                }
            }
        }

        fclose($output);
        exit;
    }

    /**
     * GET /api/reports/teacher-summary/export-view
     * Serves an HTML page that generates an XLSX download client-side via xlsx-js-style.
     */
    public function exportTeacherSummaryView()
    {
        return view('teacher_summary_export');
    }

    /**
     * GET /api/reports/teacher-summary/download-xlsx
     * Generates and returns a proper .xlsx file with 4 sheets built server-side.
     */
    public function exportTeacherSummaryXlsx()
    {
        $db = $this->getDb();
        $evaluadorId = intval($this->request->getGet('evaluador_id') ?? 1);
        $modo = ($this->request->getGet('modo') ?? 'mine') === 'all' ? 'all' : 'mine';

        $stmt = $db->prepare("SELECT id_usuario, nombre_completo, email FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$evaluadorId]);
        $docente = $stmt->fetch();
        if (!$docente) {
            return $this->response->setJSON(['success' => false, 'message' => 'Docente no encontrado.'])->setStatusCode(404);
        }

        // ── Fetch all data (same logic as CSV export) ──────────────────
        $alumnos = [];
        $totalScoreSum = 0;
        $totalEvalCount = 0;

        if ($modo === 'mine') {
            $stmt = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre, a.nombre_completo AS alumno_nombre, a.matricula, a.semestre_grupo FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario LEFT JOIN alumnos a ON e.id_alumno = a.id_alumno WHERE e.id_evaluador = ? ORDER BY a.nombre_completo ASC, e.fecha_evaluacion ASC");
            $stmt->execute([$evaluadorId]);
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
                $stmt = $db->prepare("SELECT dr.a_mejorar FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) AND dr.a_mejorar IS NOT NULL AND dr.a_mejorar != ''");
                $stmt->execute($evalIds);
                $mejorasRaw = $stmt->fetchAll();
                $indices = $this->computeIndices($evals, $competencias, $mejorasRaw);
                foreach ($evals as &$ev) {
                    $stmtD = $db->prepare("SELECT * FROM detalles_rubrica WHERE id_evaluacion = ?");
                    $stmtD->execute([$ev['id_evaluacion']]);
                    $ev['detalles'] = $stmtD->fetchAll();
                }
                unset($ev);
                $alumnos[] = [
                    'matricula' => $sData['matricula'],
                    'nombre_completo' => $sData['nombre_completo'],
                    'semestre_grupo' => $sData['semestre_grupo'],
                    'indices' => $indices,
                    'evaluaciones' => $evals,
                    'competencias' => $competencias,
                    'mejorasRaw' => $mejorasRaw,
                ];
                $totalScoreSum += $indices['promedio'];
                $totalEvalCount += $indices['total_evaluaciones'];
            }
        } else {
            $stmt = $db->prepare("SELECT a.*, u.nombre_completo AS docente_nombre FROM alumnos a LEFT JOIN usuarios u ON a.id_docente = u.id_usuario WHERE a.id_docente = ? ORDER BY a.nombre_completo ASC");
            $stmt->execute([$evaluadorId]);
            $students = $stmt->fetchAll();
            foreach ($students as $s) {
                $aid = (int)$s['id_alumno'];
                $stmtE = $db->prepare("SELECT e.*, u.nombre_completo AS evaluador_nombre FROM evaluaciones e LEFT JOIN usuarios u ON e.id_evaluador = u.id_usuario WHERE e.id_alumno = ? ORDER BY e.fecha_evaluacion ASC");
                $stmtE->execute([$aid]);
                $evals = $stmtE->fetchAll();
                if (empty($evals)) {
                    $alumnos[] = [
                        'matricula' => $s['matricula'],
                        'nombre_completo' => $s['nombre_completo'],
                        'semestre_grupo' => $s['semestre_grupo'],
                        'indices' => [
                            'total_evaluaciones' => 0, 'promedio' => 0, 'promedio_display' => 0,
                            'trend' => 0, 'trend_text' => 'Sin datos', 'competencia_fuerte' => '—',
                            'competencia_debil' => '—', 'consistencia' => 0, 'consistencia_text' => 'Sin datos',
                            'progreso' => 0, 'progreso_text' => 'Sin datos', 'top_areas_mejora' => [],
                        ],
                        'evaluaciones' => [], 'competencias' => [], 'mejorasRaw' => [],
                    ];
                    continue;
                }
                $evalIds = array_map(function($e) { return $e['id_evaluacion']; }, $evals);
                $placeholders = implode(',', array_fill(0, count($evalIds), '?'));
                $stmtC = $db->prepare("SELECT dr.competencia, AVG(dr.puntaje) as promedio, COUNT(*) as count FROM detalles_rubrica dr WHERE dr.id_evaluacion IN ($placeholders) GROUP BY dr.competencia");
                $stmtC->execute($evalIds);
                $competencias = $stmtC->fetchAll();
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
                    'matricula' => $s['matricula'],
                    'nombre_completo' => $s['nombre_completo'],
                    'semestre_grupo' => $s['semestre_grupo'],
                    'indices' => $indices,
                    'evaluaciones' => $evals,
                    'competencias' => $competencias,
                    'mejorasRaw' => $mejorasRaw,
                ];
                $totalScoreSum += $indices['promedio'];
                $totalEvalCount += $indices['total_evaluaciones'];
            }
        }

        $numAlumnos = count($alumnos);
        $promedioGeneral = $numAlumnos > 0 ? round($totalScoreSum / $numAlumnos, 1) : 0;

        // ── Build XLSX ──────────────────────────────────────────────────
        $xml = function($tag, $attrs = [], $content = '') {
            $a = '';
            foreach ($attrs as $k => $v) $a .= " $k=\"$v\"";
            return $content === '' ? "<$tag$a/>" : "<$tag$a>$content</$tag>";
        };

        // Helper: escape XML special chars
        $e = function($s) { return htmlspecialchars($s ?? '', ENT_XML1, 'UTF-8'); };

        // Collect shared strings
        $strings = [];
        $si = function($s) use (&$strings) {
            $s = (string)$s;
            if (!in_array($s, $strings)) $strings[] = $s;
            return array_search($s, $strings);
        };
        $siNum = function($num) { return $num; }; // inline number

        // Sheet 1: Resumen Docente
        $s1 = '';
        $s1 .= '<sheet><sheetData>';
        $s1 .= '<row r="1"><c r="A1" t="s"><v>' . $si('REPORTE POR DOCENTE - MINI-CEX') . '</v></c></row>';
        $s1 .= '<row r="2"><c r="A2" t="s"><v>' . $si('Docente') . '</v></c><c r="B2" t="s"><v>' . $si($docente['nombre_completo']) . '</v></c></row>';
        $s1 .= '<row r="3"><c r="A3" t="s"><v>' . $si('Email') . '</v></c><c r="B3" t="s"><v>' . $si($docente['email'] ?? '') . '</v></c></row>';
        $s1 .= '<row r="4"><c r="A4" t="s"><v>' . $si('Modo') . '</v></c><c r="B4" t="s"><v>' . $si($modo === 'mine' ? 'Mis evaluaciones' : 'Todos los alumnos') . '</v></c></row>';
        $s1 .= '<row r="5"><c r="A5" t="s"><v>' . $si('Fecha del reporte') . '</v></c><c r="B5" t="s"><v>' . $si(date('d/m/Y H:i')) . '</v></c></row>';
        $s1 .= '<row r="6"/>';
        $s1 .= '<row r="7"><c r="A7" t="s"><v>' . $si('Total alumnos') . '</v></c><c r="B7" t="n"><v>' . $numAlumnos . '</v></c></row>';
        $s1 .= '<row r="8"><c r="A8" t="s"><v>' . $si('Alumnos con evaluaciones') . '</v></c><c r="B8" t="n"><v>' . count(array_filter($alumnos, function($a) { return $a['indices']['total_evaluaciones'] > 0; })) . '</v></c></row>';
        $s1 .= '<row r="9"><c r="A9" t="s"><v>' . $si('Total evaluaciones') . '</v></c><c r="B9" t="n"><v>' . $totalEvalCount . '</v></c></row>';
        $s1 .= '<row r="10"><c r="A10" t="s"><v>' . $si('Promedio general') . '</v></c><c r="B10" t="s"><v>' . $si(number_format($promedioGeneral, 1) . '/10') . '</v></c></row>';
        $s1 .= '</sheetData></sheet>';

        // Sheet 2: Detalle Alumnos
        $s2 = '';
        $s2 .= '<sheet><sheetData>';
        $s2 .= '<row r="1"><c r="A1" t="s"><v>' . $si('Alumno') . '</v></c><c r="B1" t="s"><v>' . $si('Matrícula') . '</v></c><c r="C1" t="s"><v>' . $si('Grupo') . '</v></c><c r="D1" t="s"><v>' . $si('Evals') . '</v></c><c r="E1" t="s"><v>' . $si('Promedio') . '</v></c><c r="F1" t="s"><v>' . $si('Tendencia') . '</v></c><c r="G1" t="s"><v>' . $si('σ') . '</v></c><c r="H1" t="s"><v>' . $si('Progreso') . '</v></c><c r="I1" t="s"><v>' . $si('Comp.Fuerte') . '</v></c><c r="J1" t="s"><v>' . $si('Comp.Débil') . '</v></c></row>';
        $r = 1;
        foreach ($alumnos as $al) {
            $r++;
            $idx = $al['indices'];
            $s2 .= '<row r="' . $r . '">';
            $s2 .= '<c r="A' . $r . '" t="s"><v>' . $si($al['nombre_completo']) . '</v></c>';
            $s2 .= '<c r="B' . $r . '" t="s"><v>' . $si($al['matricula']) . '</v></c>';
            $s2 .= '<c r="C' . $r . '" t="s"><v>' . $si($al['semestre_grupo'] ?? '') . '</v></c>';
            $s2 .= '<c r="D' . $r . '" t="n"><v>' . $idx['total_evaluaciones'] . '</v></c>';
            $s2 .= '<c r="E' . $r . '" t="s"><v>' . $si(number_format($idx['promedio_display'], 1) . '/10') . '</v></c>';
            $s2 .= '<c r="F' . $r . '" t="s"><v>' . $si($idx['trend_text']) . '</v></c>';
            $s2 .= '<c r="G' . $r . '" t="n"><v>' . $idx['consistencia'] . '</v></c>';
            $s2 .= '<c r="H' . $r . '" t="s"><v>' . $si($idx['progreso_text'] . ' (' . ($idx['progreso'] >= 0 ? '+' : '') . number_format($idx['progreso'], 1) . ')') . '</v></c>';
            $s2 .= '<c r="I' . $r . '" t="s"><v>' . $si($idx['competencia_fuerte'] ?: '—') . '</v></c>';
            $s2 .= '<c r="J' . $r . '" t="s"><v>' . $si($idx['competencia_debil'] ?: '—') . '</v></c>';
            $s2 .= '</row>';
        }
        $s2 .= '</sheetData></sheet>';

        // Sheet 3: Evaluaciones
        $s3 = '';
        $s3 .= '<sheet><sheetData>';
        $s3 .= '<row r="1"><c r="A1" t="s"><v>' . $si('Alumno') . '</v></c><c r="B1" t="s"><v>' . $si('Matrícula') . '</v></c><c r="C1" t="s"><v>' . $si('#') . '</v></c><c r="D1" t="s"><v>' . $si('Fecha') . '</v></c><c r="E1" t="s"><v>' . $si('Evaluador') . '</v></c><c r="F1" t="s"><v>' . $si('Entorno') . '</v></c><c r="G1" t="s"><v>' . $si('Paciente') . '</v></c><c r="H1" t="s"><v>' . $si('Asunto') . '</v></c><c r="I1" t="s"><v>' . $si('Complejidad') . '</v></c><c r="J1" t="s"><v>' . $si('T.Obs') . '</v></c><c r="K1" t="s"><v>' . $si('T.Fbk') . '</v></c><c r="L1" t="s"><v>' . $si('Calif.') . '</v></c></row>';
        $r = 1;
        $evalIdx = 0;
        foreach ($alumnos as $al) {
            foreach ($al['evaluaciones'] as $ev) {
                $evalIdx++;
                $r++;
                $s3 .= '<row r="' . $r . '">';
                $s3 .= '<c r="A' . $r . '" t="s"><v>' . $si($al['nombre_completo']) . '</v></c>';
                $s3 .= '<c r="B' . $r . '" t="s"><v>' . $si($al['matricula']) . '</v></c>';
                $s3 .= '<c r="C' . $r . '" t="n"><v>' . $evalIdx . '</v></c>';
                $s3 .= '<c r="D' . $r . '" t="s"><v>' . $si($ev['fecha_evaluacion']) . '</v></c>';
                $s3 .= '<c r="E' . $r . '" t="s"><v>' . $si($ev['evaluador_nombre'] ?? '') . '</v></c>';
                $s3 .= '<c r="F' . $r . '" t="s"><v>' . $si($ev['entorno_clinico']) . '</v></c>';
                $s3 .= '<c r="G' . $r . '" t="s"><v>' . $si($ev['tipo_paciente']) . '</v></c>';
                $s3 .= '<c r="H' . $r . '" t="s"><v>' . $si($ev['asunto_principal']) . '</v></c>';
                $s3 .= '<c r="I' . $r . '" t="s"><v>' . $si($ev['complejidad']) . '</v></c>';
                $s3 .= '<c r="J' . $r . '" t="n"><v>' . intval($ev['tiempo_observacion']) . '</v></c>';
                $s3 .= '<c r="K' . $r . '" t="n"><v>' . intval($ev['tiempo_feedback']) . '</v></c>';
                $s3 .= '<c r="L' . $r . '" t="n"><v>' . $ev['calificacion_total'] . '</v></c>';
                $s3 .= '</row>';
            }
        }
        $s3 .= '</sheetData></sheet>';

        // Sheet 4: Rúbricas
        $s4 = '';
        $s4 .= '<sheet><sheetData>';
        $s4 .= '<row r="1"><c r="A1" t="s"><v>' . $si('Alumno') . '</v></c><c r="B1" t="s"><v>' . $si('Matrícula') . '</v></c><c r="C1" t="s"><v>' . $si('Eval#') . '</v></c><c r="D1" t="s"><v>' . $si('Fecha') . '</v></c><c r="E1" t="s"><v>' . $si('Competencia') . '</v></c><c r="F1" t="s"><v>' . $si('Puntaje') . '</v></c><c r="G1" t="s"><v>' . $si('Notas') . '</v></c><c r="H1" t="s"><v>' . $si('A Destacar') . '</v></c><c r="I1" t="s"><v>' . $si('A Mejorar') . '</v></c></row>';
        $r = 1;
        $evalIdx = 0;
        foreach ($alumnos as $al) {
            foreach ($al['evaluaciones'] as $ev) {
                $evalIdx++;
                if (!empty($ev['detalles'])) {
                    foreach ($ev['detalles'] as $d) {
                        $r++;
                        $s4 .= '<row r="' . $r . '">';
                        $s4 .= '<c r="A' . $r . '" t="s"><v>' . $si($al['nombre_completo']) . '</v></c>';
                        $s4 .= '<c r="B' . $r . '" t="s"><v>' . $si($al['matricula']) . '</v></c>';
                        $s4 .= '<c r="C' . $r . '" t="n"><v>' . $evalIdx . '</v></c>';
                        $s4 .= '<c r="D' . $r . '" t="s"><v>' . $si($ev['fecha_evaluacion']) . '</v></c>';
                        $s4 .= '<c r="E' . $r . '" t="s"><v>' . $si($d['competencia']) . '</v></c>';
                        $s4 .= '<c r="F' . $r . '" t="s"><v>' . $si($d['puntaje'] . '/9') . '</v></c>';
                        $s4 .= '<c r="G' . $r . '" t="s"><v>' . $si($d['notas'] ?? '') . '</v></c>';
                        $s4 .= '<c r="H' . $r . '" t="s"><v>' . $si($d['a_destacar'] ?? '') . '</v></c>';
                        $s4 .= '<c r="I' . $r . '" t="s"><v>' . $si($d['a_mejorar'] ?? '') . '</v></c>';
                        $s4 .= '</row>';
                    }
                }
            }
        }
        $s4 .= '</sheetData></sheet>';

        // Build shared strings XML
        $ss = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $ss .= '<si><t>' . $e($s) . '</t></si>';
        }
        $ss .= '</sst>';

        // Build XLSX ZIP
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
            return $this->response->setJSON(['success' => false, 'message' => 'Error al crear XLSX.'])->setStatusCode(500);
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '</Types>'
        );
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>'
        );
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>' .
            '<sheet name="Resumen Docente" sheetId="1" r:id="rId1"/>' .
            '<sheet name="Detalle Alumnos" sheetId="2" r:id="rId2"/>' .
            '<sheet name="Evaluaciones" sheetId="3" r:id="rId3"/>' .
            '<sheet name="Rúbricas" sheetId="4" r:id="rId4"/>' .
            '</sheets></workbook>'
        );
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>' .
            '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>' .
            '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>' .
            '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '<Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>'
        );
        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $s1 . '</worksheet>'
        );
        $zip->addFromString('xl/worksheets/sheet2.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $s2 . '</worksheet>'
        );
        $zip->addFromString('xl/worksheets/sheet3.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $s3 . '</worksheet>'
        );
        $zip->addFromString('xl/worksheets/sheet4.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $s4 . '</worksheet>'
        );
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0"/></cellXfs>' .
            '</styleSheet>'
        );
        $zip->addFromString('xl/sharedStrings.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . $ss
        );

        $zip->close();

        $filename = 'reporte_docente_' . preg_replace('/[^a-zA-Z0-9]/', '_', $docente['nombre_completo']) . '_' . date('Ymd') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    /**
     * Convert snake_case indices from computeIndices() to camelCase for Kotlin.
     */
    private function camelizeIndices($idx)
    {
        return [
            'totalEvaluaciones' => $idx['total_evaluaciones'],
            'promedio' => $idx['promedio'],
            'promedioDisplay' => $idx['promedio_display'],
            'trend' => $idx['trend'],
            'trendText' => $idx['trend_text'],
            'competenciaFuerte' => $idx['competencia_fuerte'],
            'competenciaDebil' => $idx['competencia_debil'],
            'consistencia' => $idx['consistencia'],
            'consistenciaText' => $idx['consistencia_text'],
            'progreso' => $idx['progreso'],
            'progresoText' => $idx['progreso_text'],
            'topAreasMejora' => $idx['top_areas_mejora'],
        ];
    }

    /**
     * Format evaluations array with camelCase keys + detalles for Kotlin.
     */
    private function formatEvalsCamel($evals)
    {
        return array_map(function($ev) {
            $detalles = array_map(function($d) {
                return [
                    'idDetalle' => intval($d['id_detalle']),
                    'idEvaluacion' => intval($d['id_evaluacion']),
                    'competencia' => $d['competencia'],
                    'puntaje' => intval($d['puntaje']),
                    'notas' => $d['notas'] ?? '',
                    'aDestacar' => $d['a_destacar'] ?? '',
                    'aMejorar' => $d['a_mejorar'] ?? '',
                ];
            }, $ev['detalles']);
            return [
                'idEvaluacion' => intval($ev['id_evaluacion']),
                'uuid' => $ev['uuid'],
                'idEvaluador' => intval($ev['id_evaluador']),
                'idAlumno' => intval($ev['id_alumno']),
                'fechaEvaluacion' => $ev['fecha_evaluacion'],
                'entornoClinico' => $ev['entorno_clinico'],
                'tipoPaciente' => $ev['tipo_paciente'],
                'asuntoPrincipal' => $ev['asunto_principal'],
                'complejidad' => $ev['complejidad'],
                'tiempoObservacion' => intval($ev['tiempo_observacion']),
                'tiempoFeedback' => intval($ev['tiempo_feedback']),
                'calificacionTotal' => floatval($ev['calificacion_total']),
                'evaluadorNombre' => $ev['evaluador_nombre'] ?? '',
                'detalles' => $detalles,
            ];
        }, $evals);
    }

    /**
     * Reusable indices computation matching AdminController::computeIndices.
     */
    private function computeIndices($evaluaciones, $competencias, $mejorasRaw)
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
}
