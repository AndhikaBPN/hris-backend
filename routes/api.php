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
    ['POST', '/api/password/forgot', 'ProfileController', 'forgotPassword', []],
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
    ['DELETE', '/api/users/{id}', 'UserController', 'destroy', ['c_level', 'hrd_manager']],

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
    ['POST', '/api/attendance/clock-in', 'AttendanceController', 'store', ['hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/attendance/clock-out', 'AttendanceController', 'clockOut', ['hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance/my', 'AttendanceController', 'my', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance/{id}/detail', 'AttendanceController', 'detail', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance', 'AttendanceController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance/today', 'AttendanceController', 'today', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/attendance/subordinates/today', 'AttendanceController', 'subordinatesToday', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader']],
    ['GET', '/api/attendance/summary', 'AttendanceController', 'summary', ['c_level', 'hrd_manager', 'technical_manager']],

    // ----------------------------------------------------------------
    // Leave
    // ----------------------------------------------------------------
    ['POST', '/api/leave', 'LeaveController', 'store', ['hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/leave', 'LeaveController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/leave/monthly', 'LeaveController', 'monthlyLeaves', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/leave/quota', 'LeaveBalanceController', 'getQuota', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/leave/quota/generate', 'LeaveBalanceController', 'generateQuota', ['c_level', 'hrd_manager']],
    ['PUT', '/api/leave/{id}/approve', 'LeaveController', 'approve', ['c_level', 'hrd_manager']],
    ['PUT', '/api/leave/{id}/reject', 'LeaveController', 'reject', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Shift Master (CRUD)
    // ----------------------------------------------------------------
    ['GET', '/api/shifts', 'ShiftController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/shifts/import', 'ShiftController', 'import', ['c_level', 'hrd_manager']],
    ['POST', '/api/shifts', 'ShiftController', 'store', ['c_level', 'hrd_manager']],
    ['GET', '/api/shifts/{id}', 'ShiftController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/shifts/{id}', 'ShiftController', 'update', ['c_level', 'hrd_manager']],
    ['DELETE', '/api/shifts/{id}', 'ShiftController', 'destroy', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Shift Schedule (per user per date)
    // ----------------------------------------------------------------
    ['GET', '/api/shift-schedules/upcoming', 'ShiftScheduleController', 'upcoming', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/shift-schedules/my', 'ShiftScheduleController', 'my', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/shift-schedules/my-schedules', 'ShiftScheduleController', 'mySchedules', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/shift-schedules', 'ShiftScheduleController', 'index', ['c_level', 'hrd_manager', 'technical_manager']],
    ['POST', '/api/shift-schedules/import', 'ShiftScheduleController', 'import', ['c_level', 'hrd_manager']],
    ['POST', '/api/shift-schedules/bulk', 'ShiftScheduleController', 'bulkStore', ['c_level', 'hrd_manager']],
    ['PUT', '/api/shift-schedules/bulk', 'ShiftScheduleController', 'bulkUpdate', ['c_level', 'hrd_manager']],
    ['POST', '/api/shift-schedules', 'ShiftScheduleController', 'store', ['c_level', 'hrd_manager']],
    ['GET', '/api/shift-schedules/{id}', 'ShiftScheduleController', 'show', ['c_level', 'hrd_manager', 'technical_manager']],
    ['PUT', '/api/shift-schedules/{id}', 'ShiftScheduleController', 'update', ['c_level', 'hrd_manager']],
    ['DELETE', '/api/shift-schedules/{id}', 'ShiftScheduleController', 'destroy', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Dashboard
    // ----------------------------------------------------------------
    ['GET', '/api/dashboard/admin', 'DashboardController', 'admin', ['c_level', 'hrd_manager', 'technical_manager']],
    ['GET', '/api/dashboard/team-leader', 'DashboardController', 'teamLeader', ['team_leader']],
    ['GET', '/api/dashboard/staff', 'DashboardController', 'staff', ['team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Report
    // ----------------------------------------------------------------
    ['GET', '/api/reports/attendance',    'ReportController', 'attendance', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/reports/leave',         'ReportController', 'leave',      ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/reports/employees',     'ReportController', 'employees',  ['c_level', 'hrd_manager', 'technical_manager', 'team_leader']],
    ['GET', '/api/reports/shifts',        'ReportController', 'shifts',     ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/reports/{type}/export', 'ReportController', 'export',     ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Profile
    // ----------------------------------------------------------------
    ['GET', '/api/profile', 'ProfileController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/profile', 'ProfileController', 'update', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

    // ----------------------------------------------------------------
    // Face Recognition Setup
    // ----------------------------------------------------------------
    ['GET', '/api/face-embeddings', 'FaceEmbeddingController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/face-embeddings/{id}', 'FaceEmbeddingController', 'showByUser', ['c_level', 'hrd_manager']],
    ['POST', '/api/face-embeddings', 'FaceEmbeddingController', 'store', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/face-embeddings/{id}', 'FaceEmbeddingController', 'storeByUser', ['c_level', 'hrd_manager']],
    ['PUT', '/api/face-embeddings', 'FaceEmbeddingController', 'update', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/face-embeddings/{id}', 'FaceEmbeddingController', 'updateByUser', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Office Location
    // ----------------------------------------------------------------
    ['GET', '/api/office-locations', 'OfficeLocationController', 'index', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['GET', '/api/office-locations/{id}', 'OfficeLocationController', 'show', ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['POST', '/api/office-locations', 'OfficeLocationController', 'store', ['c_level', 'hrd_manager']],
    ['PUT', '/api/office-locations/{id}', 'OfficeLocationController', 'update', ['c_level', 'hrd_manager']],
    ['DELETE', '/api/office-locations/{id}', 'OfficeLocationController', 'destroy', ['c_level', 'hrd_manager']],

    // ----------------------------------------------------------------
    // Notifications
    // ----------------------------------------------------------------
    ['GET', '/api/notifications',           'NotificationController', 'index',      ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/notifications/read-all',  'NotificationController', 'markAllRead',['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],
    ['PUT', '/api/notifications/{id}/read', 'NotificationController', 'markRead',   ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff']],

];
