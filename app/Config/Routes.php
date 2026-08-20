<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'HomeController::index');

// Admin Panel Routes
$routes->get('admin', 'AdminController::index');
$routes->post('admin/login', 'AdminController::login');
$routes->get('admin/logout', 'AdminController::logout');
$routes->post('admin/action', 'AdminController::action');

// API Routes
$routes->post('api/auth/login', 'ApiController::authLogin');
$routes->get('api/students', 'ApiController::getStudents');
$routes->post('api/sync/students', 'ApiController::syncStudents');
$routes->post('api/sync/evaluations', 'ApiController::syncEvaluations');
$routes->get('api/sync/evaluations', 'ApiController::getEvaluations');
$routes->post('api/sync/resend-email', 'ApiController::resendEmail');
$routes->post('api/sync/process_queue', 'ApiController::processQueue');

// Student Reports (read-only, online-only for Android)
$routes->get('api/reports/student/(:num)', 'ApiController::getStudentReport/$1');
$routes->get('api/reports/student', 'ApiController::getStudentReport');
$routes->get('api/reports/teacher-summary', 'ApiController::teacherSummary');
$routes->get('api/reports/teacher-summary/export', 'ApiController::exportTeacherSummary');
$routes->get('api/reports/teacher-summary/export-view', 'ApiController::exportTeacherSummaryView');
$routes->get('api/reports/teacher-summary/download-xlsx', 'ApiController::exportTeacherSummaryXlsx');
$routes->get('api/reports/student/download-xlsx', 'ApiController::exportStudentReportXlsx');

// Report Routes
$routes->get('generate_report.php', 'ReportController::generate');
$routes->post('admin/reportes/data', 'AdminController::reportesData');
$routes->post('admin/reportes/docente-data', 'AdminController::reportesDocenteData');
$routes->get('admin/reportes/export-excel', 'AdminController::exportExcel');

// API Documentation
$routes->get('api/docs', 'ApiDocsController::index');

// Admin Guide
$routes->get('admin/guide', 'AdminController::guide');

// Calculation Methodology
$routes->get('admin/metodologia', 'AdminController::metodologia');
