<?php

use App\Http\Controllers\Payroll\AttendanceDeviceController;
use App\Http\Controllers\Payroll\AttendanceLogController;
use App\Http\Controllers\Payroll\FaceEnrollmentController;
use App\Http\Controllers\Payroll\HolidayController;
use App\Http\Controllers\Payroll\IncidentController;
use App\Http\Controllers\Payroll\KioskController;
use App\Http\Controllers\Payroll\MyAttendanceController;
use App\Http\Controllers\Payroll\PayrollNoteController;
use App\Http\Controllers\Payroll\PayrollPeriodController;
use App\Http\Controllers\Payroll\PayrollSettingController;
use App\Http\Controllers\Payroll\PayslipController;
use App\Http\Controllers\Payroll\ShiftAssignmentController;
use App\Http\Controllers\Payroll\ShiftController;
use App\Http\Controllers\Payroll\VacationAdjustmentController;
use App\Http\Controllers\Payroll\VacationController;
use App\Http\Controllers\Payroll\VacationPeriodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('payroll')->name('payroll.')->group(function () {
    // Payroll module configuration (period, tolerance, multipliers, vacations, face recognition...)
    Route::get('/settings', [PayrollSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [PayrollSettingController::class, 'update'])->name('settings.update');

    // Authorized kiosk devices: list, register the current device and revoke
    Route::get('/devices', [AttendanceDeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices', [AttendanceDeviceController::class, 'store'])->name('devices.store');
    Route::delete('/devices/{device}', [AttendanceDeviceController::class, 'destroy'])->name('devices.destroy');

    // Shifts, schedules and assignments
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');

    Route::post('/shift-assignments', [ShiftAssignmentController::class, 'store'])->name('shift-assignments.store');
    Route::delete('/shift-assignments/{assignment}', [ShiftAssignmentController::class, 'destroy'])->name('shift-assignments.destroy');

    // Holidays catalog (LFT mandatory rest days + manual company days)
    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/sync', [HolidayController::class, 'sync'])->name('holidays.sync');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    // Incidents (absences, medical leaves, permissions...). They are managed
    // from the payroll period detail, so there is no standalone screen.
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::delete('/incidents/{incident}', [IncidentController::class, 'destroy'])->name('incidents.destroy');

    // Vacations: approval flow + self-service requests
    Route::get('/vacations', [VacationController::class, 'index'])->name('vacations.index');
    Route::get('/vacations/balance', [VacationController::class, 'balance'])->name('vacations.balance');
    Route::post('/vacations/requests', [VacationController::class, 'store'])->name('vacations.requests.store');
    Route::delete('/vacations/requests/{vacationRequest}', [VacationController::class, 'cancel'])->name('vacations.requests.cancel');
    Route::post('/vacations/{vacationRequest}/approve', [VacationController::class, 'approve'])->name('vacations.approve');
    Route::post('/vacations/{vacationRequest}/reject', [VacationController::class, 'reject'])->name('vacations.reject');

    // Manual movements of the vacation balance (initial balance, granted days
    // and corrections) registered from the collaborator profile
    Route::post('/users/{user}/vacation-adjustments', [VacationAdjustmentController::class, 'store'])->name('vacations.adjustments.store');
    Route::delete('/vacation-adjustments/{adjustment}', [VacationAdjustmentController::class, 'destroy'])->name('vacations.adjustments.destroy');

    // Stored vacation periods by service year (premium tracking, manual edits
    // and removal of wrong periods)
    Route::post('/users/{user}/vacation-periods', [VacationPeriodController::class, 'store'])->name('vacations.periods.store');
    Route::put('/vacation-periods/{period}', [VacationPeriodController::class, 'update'])->name('vacations.periods.update');
    Route::delete('/vacation-periods/{period}', [VacationPeriodController::class, 'destroy'])->name('vacations.periods.destroy');

    // Payroll periods: pre-payroll, attendance corrections, adjustments and closing
    Route::get('/periods', [PayrollPeriodController::class, 'index'])->name('periods.index');
    Route::post('/periods', [PayrollPeriodController::class, 'store'])->name('periods.store');
    Route::get('/periods/{period}', [PayrollPeriodController::class, 'show'])->name('periods.show');
    Route::get('/periods/{period}/pre-payroll', [PayrollPeriodController::class, 'prePayroll'])->name('periods.pre-payroll');
    Route::get('/periods/{period}/export', [PayrollPeriodController::class, 'export'])->name('periods.export');
    Route::get('/periods/{period}/employees/{user}/days', [PayrollPeriodController::class, 'days'])->name('periods.days');
    Route::put('/periods/{period}/employees/{user}/override', [PayrollPeriodController::class, 'updateOverride'])->name('periods.override');
    Route::post('/periods/{period}/adjustments', [PayrollPeriodController::class, 'storeAdjustment'])->name('periods.adjustments.store');
    Route::post('/periods/{period}/close', [PayrollPeriodController::class, 'close'])->name('periods.close');
    Route::post('/periods/{period}/reopen', [PayrollPeriodController::class, 'reopen'])->name('periods.reopen');
    Route::delete('/adjustments/{adjustment}', [PayrollPeriodController::class, 'destroyAdjustment'])->name('adjustments.destroy');

    // Comments about a collaborator inside a period (bottom of every panel)
    Route::post('/periods/{period}/notes', [PayrollNoteController::class, 'store'])->name('periods.notes.store');
    Route::put('/notes/{note}', [PayrollNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [PayrollNoteController::class, 'destroy'])->name('notes.destroy');

    // Manual attendance corrections (audited)
    Route::post('/attendance-logs', [AttendanceLogController::class, 'store'])->name('attendance-logs.store');
    Route::put('/attendance-logs/{attendanceLog}', [AttendanceLogController::class, 'update'])->name('attendance-logs.update');
    Route::delete('/attendance-logs/{attendanceLog}', [AttendanceLogController::class, 'destroy'])->name('attendance-logs.destroy');

    // Facial enrollment of collaborators (admins enroll; collaborators can enroll themselves)
    Route::post('/users/{user}/faces', [FaceEnrollmentController::class, 'store'])->name('faces.store');
    Route::delete('/users/{user}/faces', [FaceEnrollmentController::class, 'destroy'])->name('faces.destroy');
    Route::post('/my-attendance/faces', [FaceEnrollmentController::class, 'storeSelf'])->name('my-attendance.faces.store');
    Route::delete('/my-attendance/faces', [FaceEnrollmentController::class, 'destroySelf'])->name('my-attendance.faces.destroy');
    Route::get('/my-attendance/faces/status', [FaceEnrollmentController::class, 'status'])->name('my-attendance.faces.status');

    // Self-service attendance portal of a collaborator
    Route::get('/my-attendance', [MyAttendanceController::class, 'index'])->name('my-attendance.index');
    Route::post('/my-attendance/punch', [MyAttendanceController::class, 'punch'])
        ->middleware('throttle:30,1')->name('my-attendance.punch');

    // Printable payslips (4-5 per sheet) of a closed period
    Route::get('/periods/{period}/payslips/print', [PayslipController::class, 'print'])->name('periods.payslips.print');
});

// Public attendance kiosk. Every API call is restricted by the
// "attendance.device" middleware to devices registered inside the ERP.
Route::prefix('attendance/kiosk')->name('attendance.kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'show'])->name('show');

    Route::post('/bootstrap', [KioskController::class, 'bootstrap'])
        ->middleware(['attendance.device', 'throttle:120,1'])->name('bootstrap');

    Route::post('/punch', [KioskController::class, 'punch'])
        ->middleware(['attendance.device', 'throttle:30,1'])->name('punch');

    Route::post('/face-punch', [KioskController::class, 'facePunch'])
        ->middleware(['attendance.device', 'throttle:30,1'])->name('face-punch');
});
