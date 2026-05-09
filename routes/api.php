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
    ['POST', '/api/login', 'AuthController', 'login', []],
    ['POST', '/api/password/reset', 'ProfileController', 'resetPassword', []],
    ['POST', '/api/logout', 'AuthController', 'logout', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/otp/send', 'OtpController', 'send', []],
    ['POST', '/api/otp/verify', 'OtpController', 'verify', []],

    // ----------------------------------------------------------------
    // User Management
    // ----------------------------------------------------------------
    ['GET', '/api/users/count', 'UserController', 'countActive', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/users/birthdays', 'UserController', 'birthdays', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/users/team-leaders', 'UserController', 'teamLeaders', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/users', 'UserController', 'index', ['c_level', 'hrd_manager']],
    ['GET', '/api/users/{id}', 'UserController', 'show', ['c_level', 'hrd_manager']],
    ['POST', '/api/users', 'UserController', 'store', ['c_level', 'hrd_manager']],
    ['PUT', '/api/users/{id}', 'UserController', 'update', ['c_level', 'hrd_manager']],
    ['DELETE', '/api/users/{id}', 'UserController', 'destroy', ['c_level']],

    // ----------------------------------------------------------------
    // Team Management
    // ----------------------------------------------------------------
    ['GET', '/api/teams/count', 'TeamController', 'count', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/teams', 'TeamController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/teams/{id}', 'TeamController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/teams', 'TeamController', 'store', ['c_level', 'hrd_manager', 'technical_manager']],
    ['PUT', '/api/teams/{id}', 'TeamController', 'update', ['c_level', 'hrd_manager', 'technical_manager']],
    ['DELETE', '/api/teams/{id}', 'TeamController', 'destroy', ['c_level', 'hrd_manager', 'technical_manager']],

    // ----------------------------------------------------------------
    // Role Management
    // ----------------------------------------------------------------
    ['GET', '/api/roles/count', 'RoleController', 'count', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/roles', 'RoleController', 'index', ['c_level', 'hrd_manager']],
    ['GET', '/api/roles/{id}', 'RoleController', 'show', ['c_level', 'hrd_manager']],
    ['POST', '/api/roles', 'RoleController', 'store', ['c_level', 'hrd_manager']],
    ['PUT', '/api/roles/{id}', 'RoleController', 'update', ['c_level', 'hrd_manager']],
    ['DELETE', '/api/roles/{id}', 'RoleController', 'destroy', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Attendance
    // ----------------------------------------------------------------
    ['POST', '/api/attendance', 'AttendanceController', 'store', ['hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance', 'AttendanceController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance/today', 'AttendanceController', 'today', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance/subordinates/today', 'AttendanceController', 'subordinatesToday', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader']],
    ['GET', '/api/attendance/summary', 'AttendanceController', 'summary', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Leave
    // ----------------------------------------------------------------
    ['POST', '/api/leave', 'LeaveController', 'store', ['hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/leave', 'LeaveController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/leave/monthly', 'LeaveController', 'monthlyLeaves', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/leave/quota', 'LeaveBalanceController', 'getQuota', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/leave/{id}/approve', 'LeaveController', 'approve', ['c_level', 'hrd_manager']],
    ['PUT', '/api/leave/{id}/reject', 'LeaveController', 'reject', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Shift Schedules
    // ----------------------------------------------------------------
    ['GET', '/api/shifts', 'ShiftController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/shifts/setup', 'ShiftController', 'setup', ['hrd_manager']],
    ['POST', '/api/shifts/generate', 'ShiftController', 'generate', ['hrd_manager']],
    ['POST', '/api/shifts/override', 'ShiftController', 'override', ['hrd_manager']],
    ['POST', '/api/users/{id}/shift-config', 'ShiftConfigController', 'store', ['c_level', 'hrd_manager']],
    ['GET', '/api/users/{id}/shift-config', 'ShiftConfigController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Dashboard
    // ----------------------------------------------------------------
    ['GET', '/api/dashboard/admin', 'DashboardController', 'admin', ['c_level', 'hrd_manager', 'technical_manager']],
    ['GET', '/api/dashboard/team-leader', 'DashboardController', 'teamLeader', ['team_leader']],
    ['GET', '/api/dashboard/staff', 'DashboardController', 'staff', ['team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Report
    // ----------------------------------------------------------------
    ['GET', '/api/report/attendance', 'ReportController', 'attendance', ['c_level', 'hrd_manager']],
    ['GET', '/api/report/leave', 'ReportController', 'leave', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Profile
    // ----------------------------------------------------------------
    ['GET', '/api/profile', 'ProfileController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/profile', 'ProfileController', 'update', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Face Recognition Setup
    // ----------------------------------------------------------------
    ['GET', '/api/face-embeddings', 'FaceEmbeddingController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/face-embeddings', 'FaceEmbeddingController', 'store', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
];
