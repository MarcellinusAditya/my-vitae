<?php

use App\Http\Controllers\CareerProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeneratedDocumentController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\ProfileCertificationController;
use App\Http\Controllers\ProfileContactController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileDocumentController;
use App\Http\Controllers\ProfileEducationController;
use App\Http\Controllers\ProfileExperienceController;
use App\Http\Controllers\ProfileProjectController;
use App\Http\Controllers\ProfileSkillController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Career Profile
    Route::get('/career-profile', [CareerProfileController::class, 'show'])->name('career-profile.show');
    Route::patch('/career-profile', [CareerProfileController::class, 'update'])->name('career-profile.update');

    // CV Import
    Route::get('/career-profile/import', [ProfileDocumentController::class, 'index'])->name('career-profile.import.index');
    Route::post('/career-profile/documents', [ProfileDocumentController::class, 'store'])->name('career-profile.documents.store');
    Route::get('/career-profile/documents/{document}', [ProfileDocumentController::class, 'show'])->name('career-profile.import.show');
    Route::post('/career-profile/documents/{document}/confirm', [ProfileDocumentController::class, 'confirm'])->name('career-profile.import.confirm');
    Route::get('/career-profile/documents/{document}/download', [ProfileDocumentController::class, 'download'])->name('career-profile.documents.download');
    Route::delete('/career-profile/documents/{document}', [ProfileDocumentController::class, 'destroy'])->name('career-profile.documents.destroy');

    Route::patch('/career-profile/contact', [ProfileContactController::class, 'update'])->name('career-profile.contact.update');

    Route::post('/career-profile/experiences', [ProfileExperienceController::class, 'store'])->name('career-profile.experiences.store');
    Route::patch('/career-profile/experiences/{experience}', [ProfileExperienceController::class, 'update'])->name('career-profile.experiences.update');
    Route::delete('/career-profile/experiences/{experience}', [ProfileExperienceController::class, 'destroy'])->name('career-profile.experiences.destroy');
    Route::post('/career-profile/experiences/reorder', [ProfileExperienceController::class, 'reorder'])->name('career-profile.experiences.reorder');

    Route::post('/career-profile/educations', [ProfileEducationController::class, 'store'])->name('career-profile.educations.store');
    Route::patch('/career-profile/educations/{education}', [ProfileEducationController::class, 'update'])->name('career-profile.educations.update');
    Route::delete('/career-profile/educations/{education}', [ProfileEducationController::class, 'destroy'])->name('career-profile.educations.destroy');
    Route::post('/career-profile/educations/reorder', [ProfileEducationController::class, 'reorder'])->name('career-profile.educations.reorder');

    Route::post('/career-profile/skills', [ProfileSkillController::class, 'store'])->name('career-profile.skills.store');
    Route::patch('/career-profile/skills/{skill}', [ProfileSkillController::class, 'update'])->name('career-profile.skills.update');
    Route::delete('/career-profile/skills/{skill}', [ProfileSkillController::class, 'destroy'])->name('career-profile.skills.destroy');
    Route::post('/career-profile/skills/reorder', [ProfileSkillController::class, 'reorder'])->name('career-profile.skills.reorder');

    Route::post('/career-profile/projects', [ProfileProjectController::class, 'store'])->name('career-profile.projects.store');
    Route::patch('/career-profile/projects/{project}', [ProfileProjectController::class, 'update'])->name('career-profile.projects.update');
    Route::delete('/career-profile/projects/{project}', [ProfileProjectController::class, 'destroy'])->name('career-profile.projects.destroy');
    Route::post('/career-profile/projects/reorder', [ProfileProjectController::class, 'reorder'])->name('career-profile.projects.reorder');

    Route::post('/career-profile/certifications', [ProfileCertificationController::class, 'store'])->name('career-profile.certifications.store');
    Route::patch('/career-profile/certifications/{certification}', [ProfileCertificationController::class, 'update'])->name('career-profile.certifications.update');
    Route::delete('/career-profile/certifications/{certification}', [ProfileCertificationController::class, 'destroy'])->name('career-profile.certifications.destroy');
    Route::post('/career-profile/certifications/reorder', [ProfileCertificationController::class, 'reorder'])->name('career-profile.certifications.reorder');

    // Job Applications
    Route::get('/applications', [JobApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/create', [JobApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [JobApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}', [JobApplicationController::class, 'show'])->name('applications.show');
    Route::get('/applications/{application}/edit', [JobApplicationController::class, 'edit'])->name('applications.edit');
    Route::patch('/applications/{application}', [JobApplicationController::class, 'update'])->name('applications.update');
    Route::patch('/applications/{application}/status', [JobApplicationController::class, 'updateStatus'])->name('applications.status');
    Route::post('/applications/{application}/analyze', [JobApplicationController::class, 'analyze'])->name('applications.analyze');
    Route::get('/applications/{application}/analysis-status', [JobApplicationController::class, 'analysisStatus'])->name('applications.analysis.status');
    Route::post('/applications/{application}/duplicate', [JobApplicationController::class, 'duplicate'])->name('applications.duplicate');
    Route::delete('/applications/{application}', [JobApplicationController::class, 'destroy'])->name('applications.destroy');

    // Generated documents
    Route::get('/documents', [GeneratedDocumentController::class, 'index'])->name('documents.index');
    Route::post('/applications/{application}/documents', [GeneratedDocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [GeneratedDocumentController::class, 'show'])->name('documents.show');
    Route::patch('/documents/{document}', [GeneratedDocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document}/regenerate', [GeneratedDocumentController::class, 'regenerate'])->name('documents.regenerate');
    Route::get('/documents/{document}/status', [GeneratedDocumentController::class, 'status'])->name('documents.status');
    Route::delete('/documents/{document}', [GeneratedDocumentController::class, 'destroy'])->name('documents.destroy');
});

require __DIR__.'/auth.php';
