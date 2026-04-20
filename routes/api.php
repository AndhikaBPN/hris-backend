<?php

/**
 * API Route Table
 *
 * Format: [ METHOD, pattern, ControllerClass, method, roles[] ]
 * roles[] kosong = tidak butuh auth (public)
 * Roles tersedia: c_level, hrd_manager, technical_manager, team_leader, staff
 */
return [
    // ----------------------------------------------------------------
    // Auth & Password Reset (public)
    // ----------------------------------------------------------------
    ['POST', '/api/login',           'AuthController',    'login',  []],
    ['POST', '/api/password/forgot', 'ProfileController', 'forgotPassword', []],
    ['POST', '/api/password/reset',  'ProfileController', 'resetPassword',  []],
    ['POST', '/api/logout',          'AuthController',    'logout', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

    // ----------------------------------------------------------------
    // User Management
    // ----------------------------------------------------------------
    ['GET',    '/api/users',      'UserController', 'index',   ['c_level', 'hrd_manager']],
    ['POST',   '/api/users',      'UserController', 'store',   ['c_level', 'hrd_manager']],
    ['PUT',    '/api/users/{id}', 'UserController', 'update',  ['c_level', 'hrd_manager']],
    ['DELETE', '/api/users/{id}', 'UserController', 'destroy', ['c_level']],

    // ----------------------------------------------------------------
    // Attendance
    // ----------------------------------------------------------------
    ['POST', '/api/attendance', 'AttendanceController', 'store', ['hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET',  '/api/attendance', 'AttendanceController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Leave
    // ----------------------------------------------------------------
    ['POST', '/api/leave',              'LeaveController', 'store',   ['hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET',  '/api/leave',              'LeaveController', 'index',   ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT',  '/api/leave/{id}/approve', 'LeaveController', 'approve', ['c_level', 'hrd_manager']],
    ['PUT',  '/api/leave/{id}/reject',  'LeaveController', 'reject',  ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Shift Schedules
    // ----------------------------------------------------------------
    ['GET',  '/api/shifts',             'ShiftController', 'index',    ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/shifts/generate',    'ShiftController', 'generate', ['hrd_manager']],
    ['POST', '/api/shifts/override',    'ShiftController', 'override', ['hrd_manager']],

    // ----------------------------------------------------------------
    // Dashboard
    // ----------------------------------------------------------------
    ['GET', '/api/dashboard/admin',       'DashboardController', 'admin',      ['c_level', 'hrd_manager', 'technical_manager']],
    ['GET', '/api/dashboard/team-leader', 'DashboardController', 'teamLeader', ['team_leader']],
    ['GET', '/api/dashboard/staff',       'DashboardController', 'staff',      ['team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Report
    // ----------------------------------------------------------------
    ['GET', '/api/report/attendance', 'ReportController', 'attendance', ['c_level', 'hrd_manager']],
    ['GET', '/api/report/leave',      'ReportController', 'leave',      ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Profile
    // ----------------------------------------------------------------
    ['GET', '/api/profile', 'ProfileController', 'show',   ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/profile', 'ProfileController', 'update', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Face Recognition Setup
    // ----------------------------------------------------------------
    ['GET',  '/api/face-embeddings', 'FaceEmbeddingController', 'show',  ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/face-embeddings', 'FaceEmbeddingController', 'store', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
];
