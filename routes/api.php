<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Middleware\CheckLmsAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LMS API Routes
|--------------------------------------------------------------------------
| All routes match original CodeIgniter endpoint paths where possible.
| Auth uses Laravel Sanctum tokens (Bearer).
|
| Public:  /api/auth/*
| Protected: everything else (requires valid Bearer token)
|--------------------------------------------------------------------------
*/

// ============================================================
// PUBLIC — Auth (no token required)
// ============================================================
Route::prefix('auth')->group(function () {
    // Web + mobile login — matches original:f
    // POST Webservice/login         → MitraConnect/loginWeb
    // POST Webservice/loginWS       → MitraConnect/loginMobile
    Route::post('login', [AuthController::class, 'login']);
    Route::post('loginWS', [AuthController::class, 'login']);
    Route::post('loginLdap', [AuthController::class, 'loginLdap']);
    Route::post('loginLdapWeb', [AuthController::class, 'loginLdap']); // mobile alias
});

// ============================================================
// PUBLIC — Quiz invite link (no auth required)
// ─────────────────────────────────────────────────────────────
// Mirrors old project's /ask/<quizID>?i=<inviteID> pattern.
// Recipients of email invites take quiz here without logging in.
// ============================================================
Route::get ('quiz/take/{inviteId}',         [QuizController::class, 'publicGetQuizByInvite'])
    ->where('inviteId', '[0-9]+');
Route::post('quiz/take/{inviteId}/submit',  [QuizController::class, 'publicSubmitQuiz'])
    ->where('inviteId', '[0-9]+');

// ============================================================
// PUBLIC — Interview invite link (no auth required)
// ─────────────────────────────────────────────────────────────
// Recipients of email invites record video responses here.
// ============================================================
Route::get ('interview-take/{uniqueId}',              [InterviewController::class, 'publicGetInterview'])
    ->where('uniqueId', '[a-f0-9\-]{36}');  // UUID format
Route::post('interview-take/{uniqueId}/start',       [InterviewController::class, 'publicStartInterview'])
    ->where('uniqueId', '[a-f0-9\-]{36}');
Route::post('interview-take/{uniqueId}/submit-video',[InterviewController::class, 'publicSubmitVideo'])
    ->where('uniqueId', '[a-f0-9\-]{36}');
Route::post('interview-take/{uniqueId}/complete',    [InterviewController::class, 'publicCompleteInterview'])
    ->where('uniqueId', '[a-f0-9\-]{36}');

// ============================================================
// PROTECTED — All endpoints below require valid Sanctum token
// ============================================================
Route::middleware(['auth:sanctum', CheckLmsAuth::class])->group(function () {

    // --- Auth ---
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::get('account-info', [AuthController::class, 'accountInfo']);  // mobile: getAccountInfoWS

    });

    // --- Categories ---
    Route::prefix('Webservice')->group(function () {
        Route::get('getCategories', [CourseController::class, 'getCategories']);
        Route::post('addCategory', [CourseController::class, 'addCategory']);
        Route::post('updateCategory', [CourseController::class, 'updateCategory']);
        Route::post('disableCategory', [CourseController::class, 'disableCategory']);
        Route::post('enableCategory', [CourseController::class, 'enableCategory']);
        Route::get('getCategoryDetails', [CourseController::class, 'getCategoryDetails']);
        Route::get('cc', [CourseController::class, 'getCategories']);  // alias

        // --- Courses ---
        Route::post('addCourse', [CourseController::class, 'addCourse']);
        Route::get('getCourseList', [CourseController::class, 'getCourseList']);
        Route::get('getCourseDetails', [CourseController::class, 'getCourseDetails']);
        Route::post('updateCourseDetail', [CourseController::class, 'updateCourse']);
        Route::post('makeCourseLive', [CourseController::class, 'makeCourseLive']);
        Route::post('disableCourse', [CourseController::class, 'disableCourse']);
        Route::post('visibilityStatus', [CourseController::class, 'visibilityStatus']);
        Route::get('getCatalogCourseList', [CourseController::class, 'getCatalogCourseList']);
        Route::get('getAllCoursesList', [CourseController::class, 'getCourseList']);
        Route::get('getCoursesList', [CourseController::class, 'getCourseList']);

        // Featured
        Route::post('addToFeaturedCourse', [CourseController::class, 'addToFeatured']);
        Route::post('removeFromFeature', [CourseController::class, 'removeFromFeatured']);
        Route::get('getFeaturedCourseList', [CourseController::class, 'getFeaturedList']);
        Route::get('getFeaturedListMng', [CourseController::class, 'getFeaturedListAdmin']);
        Route::get('getUnfeaturedList', [CourseController::class, 'getUnfeaturedList']);

        // Chapters
        Route::post('addChapter', [CourseController::class, 'addChapter']);
        Route::get('getCourseChapterDetails', [CourseController::class, 'getCourseChapterDetails']);
        Route::get('getCourseChapterDetailsAdmin', [CourseController::class, 'getCourseChapterDetailsAdmin']);
        Route::post('updateChapter', [CourseController::class, 'updateChapter']);
        Route::post('disableChapter', [CourseController::class, 'disableChapter']);
        Route::post('enableChapter', [CourseController::class, 'enableChapter']);

        // Topics
        Route::post('newVideoTopic', [CourseController::class, 'newVideoTopic']);
        Route::post('newPDFTopic', [CourseController::class, 'newPDFTopic']);
        Route::post('newResourceTopic', [CourseController::class, 'newResourceTopic']);
        Route::post('newTestTopic', [CourseController::class, 'newTestTopic']);
        Route::post('updateVideoTopic', [CourseController::class, 'updateVideoTopic']);
        Route::post('updatePDFTopic', [CourseController::class, 'updatePDFTopic']);
        Route::post('updateResourceTopic', [CourseController::class, 'updateResourceTopic']);
        Route::post('updateTestTopic', [CourseController::class, 'updateTestTopic']);
        Route::post('removeTopic', [CourseController::class, 'removeTopic']);
        Route::get('courseTopicDetailAdmin', [CourseController::class, 'topicDetailAdmin']);
        Route::get('courseChapterDetailAdmin', [CourseController::class, 'chapterDetailAdmin']);

        // Resource links
        Route::post('addResourceLinks', [CourseController::class, 'addResourceLinks']);
        Route::post('removeResourseLink', [CourseController::class, 'removeResourceLink']);
        Route::get('getCourseChapterResourceList', [CourseController::class, 'getResourceList']);
        Route::get('getCourseChapterResourceDetail', [CourseController::class, 'getResourceDetail']);

        // Test questions (in-course)
        Route::get('getQuestionsListWS', [CourseController::class, 'getTestQuestions']);
        Route::post('sendTestQuestion', [CourseController::class, 'sendTestQuestion']);
        Route::post('addTestQuestion', [CourseController::class, 'addTestQuestion']);
        Route::post('deleteQuestion', [CourseController::class, 'deleteTestQuestion']);
        Route::post('addTestQuestionOptions', [CourseController::class, 'addTestQuestionOptions']);
        Route::post('deleteQuestionOption', [CourseController::class, 'deleteTestQuestionOption']);
        Route::get('getTestDetail', [CourseController::class, 'getTestDetail']);
        Route::post('testDetailUpdate', [CourseController::class, 'updateTestDetail']);
        Route::get('getTestQuestion', [CourseController::class, 'getTestQuestion']);

        // Learners
        Route::post('enrollSelf', [CourseController::class, 'enrollSelf']);
        Route::post('assignLearnersCourse', [CourseController::class, 'assignLearnersCourse']);
        Route::get('assignLearnersModalList', [CourseController::class, 'assignLearnersModalList']);
        Route::get('getLearnersBoxList', [CourseController::class, 'getLearnersBoxList']);

        // Progress pending
        Route::post('markAsComplete', [CourseController::class, 'markAsComplete']);
        Route::post('updateTopicTimeWS', [CourseController::class, 'updateTopicTime']);
        Route::post('testSubmitWS', [CourseController::class, 'testSubmit']);
        Route::post('testSubmitMobileWS', [CourseController::class, 'testSubmit']);
        Route::get('getResultUserWS', [CourseController::class, 'getResultUser']);
        Route::get('getResultDetails', [CourseController::class, 'getResultDetails']);
        Route::get('getResultSummary', [CourseController::class, 'getResultSummary']);

        // Course details (mobile)
        Route::get('getCourseDetailsWS', [CourseController::class, 'getCourseDetailsMobile']);
        Route::get('courseTopicDetailWS', [CourseController::class, 'topicDetailMobile']);
        Route::get('getMycoursesInprogress', [CourseController::class, 'getMyCoursesInprogress']);

        // Feedback
        Route::post('addFeedBackWS', [CourseController::class, 'addFeedback']);
        Route::post('addFeedBackV2WS', [CourseController::class, 'addFeedback']);
        Route::post('addFeedBackReplyWS', [CourseController::class, 'addFeedbackReply']);
        Route::get('getAllFeedbackWS', [CourseController::class, 'getAllFeedback']);
        Route::post('deleteFeedBackWS', [CourseController::class, 'deleteFeedback']);

        // ─── React Frontend Aliases (new naming convention) ─────────────
        // Added: 24-Apr-2026 — aligns frontend CourseDetail.jsx endpoints
        Route::post('addCourseFeedback',           [CourseController::class, 'addFeedback']);
        Route::get('getCourseTopicQuestions',      [CourseController::class, 'getTestQuestions']);
        Route::get('getCourseTopicResourceLinks',  [CourseController::class, 'getResourceList']);
        Route::post('submitTopicTest',             [CourseController::class, 'testSubmit']);
        Route::post('updateTopicStatus',           [CourseController::class, 'markAsComplete']);
        // Additional aliases for CourseDetail + Interview pages
        Route::get('getCourseDetailsMobile',       [CourseController::class, 'getCourseDetailsMobile']);
        Route::post('toggleWishlist',              [CourseController::class, 'toggleWishlist']);
        Route::post('saveInterviewVideo',          [UploadController::class, 'uploadInterviewVideo']);
        // ────────────────────────────────────────────────────────────────

        // Feedback Questions (per course)
        Route::post('addFeedbackQuestion', [CourseController::class, 'addFeedbackQuestion']);
        Route::get('getFeedbackQuestion', [CourseController::class, 'getFeedbackQuestion']);
        Route::post('deleteFeedbackQuestion', [CourseController::class, 'deleteFeedbackQuestion']);
        Route::get('getFeedbackQuestionsWS', [CourseController::class, 'getFeedbackQuestionsWS']);

        // Wishlist
        Route::post('anrToWishlistWS', [CourseController::class, 'toggleWishlist']);
        Route::get('getWishlistWS', [CourseController::class, 'getWishlist']);

        // Leaderboard
        Route::get('getLeaderBoardWS', [CourseController::class, 'getLeaderboard']);
        Route::get('getLeaderBoardWeb', [CourseController::class, 'getLeaderboard']);
        Route::get('getDepartmentLeaderBoardWS', [CourseController::class, 'getDeptLeaderboard']);

        // Charts
        Route::get('getOverViewCharts', [CourseController::class, 'getOverviewCharts']);
        Route::get('getOverPassViewCharts', [CourseController::class, 'getPassRateCharts']);
        Route::get('getCourseType', [CourseController::class, 'getCourseTypes']);

        // App version
        Route::get('getAppVersion', [CourseController::class, 'getAppVersion']);
        Route::post('addAppversion', [CourseController::class, 'addAppVersion']);
        Route::post('addAppToken', [CourseController::class, 'addAppToken']);

        // --- Quiz ---
        Route::post('addQuiz', [QuizController::class, 'addQuiz']);
        Route::get('getQuizDetails', [QuizController::class, 'getQuizDetails']);
        Route::post('updateQuizDetail', [QuizController::class, 'updateQuizDetail']);
        Route::post('quizDetailUpdate', [QuizController::class, 'updateQuizDetail']);
        Route::post('visibilityStatusQuiz', [QuizController::class, 'visibilityStatusQuiz']);
        Route::post('updateQuizFormSetting', [QuizController::class, 'updateQuizFormSetting']);
        Route::post('addQuizQuestion', [QuizController::class, 'addQuizQuestion']);
        Route::post('deleteQuizQuestion', [QuizController::class, 'deleteQuizQuestion']);
        Route::post('addQuizQuestionOptions', [QuizController::class, 'addQuizQuestionOptions']);
        Route::post('deleteQuizQuestionOption', [QuizController::class, 'deleteQuizQuestionOption']);
        Route::get('getQuizQuestion', [QuizController::class, 'getQuizQuestion']);
        Route::get('getQuizQuestionDetail', [QuizController::class, 'getQuizQuestionDetail']);
        Route::post('sendQuizQuestion', [QuizController::class, 'sendQuizQuestion']);
        Route::post('sendInviteToQuiz', [QuizController::class, 'sendInviteToQuiz']);
        Route::get('getInvitedList', [QuizController::class, 'getInvitedList']);
        Route::get('quiz/submission/{inviteId}', [QuizController::class, 'getQuizSubmissionDetail'])
            ->where('inviteId', '[0-9]+');
        Route::post('addQuizInformationQuestion', [QuizController::class, 'addQuizInfoQuestion']);
        Route::post('deleteQuizInformationQuestion', [QuizController::class, 'deleteQuizInfoQuestion']);
        Route::get('getInformationQuizQuestion', [QuizController::class, 'getInfoQuestion']);
        Route::post('sendQuizInformationQuestion', [QuizController::class, 'sendQuizInfoQuestion']);
        Route::post('saveInformationSubmitWS', [QuizController::class, 'saveInfoSubmit']);
        Route::post('quizSubmitWS', [QuizController::class, 'quizSubmit']);
        Route::post('quizTimeup', [QuizController::class, 'quizTimeup']);
        Route::post('switchTabTooManyTimes', [QuizController::class, 'switchTabTooManyTimes']);
        Route::get('getQuizDetailsWS', [QuizController::class, 'getQuizDetailsMobile']);
        Route::get('getQuestionsListQuizWS', [QuizController::class, 'getQuizQuestionsList']);
        Route::get('getQuizQuestionsListWS', [QuizController::class, 'getQuizQuestionsList']);
        Route::get('getInformationQuizQuestion', [QuizController::class, 'getInfoQuestion']);
        Route::get('getAllFeedbackQuizWS', [QuizController::class, 'getAllFeedback']);
        Route::get('getOverViewChartsQuiz', [QuizController::class, 'getOverviewCharts']);
        Route::get('getOverPassViewChartsQuiz', [QuizController::class, 'getPassRateCharts']);

        // --- Interview ---
        Route::post('addInterview', [InterviewController::class, 'addInterview']);
        Route::get('getInterviewDetails', [InterviewController::class, 'getInterviewDetails']);
        Route::post('updateInterviewDetail', [InterviewController::class, 'updateInterviewDetail']);
        Route::get('getInterviewQuestions', [InterviewController::class, 'getInterviewQuestions']);
        Route::post('addInterviewQuestion', [InterviewController::class, 'addInterviewQuestion']);
        Route::get('getInterviewQuestionDetail', [InterviewController::class, 'getInterviewQuestionDetail']);
        Route::post('updateInterviewQuestion', [InterviewController::class, 'updateInterviewQuestion']);
        Route::post('deleteInterviewQuestion', [InterviewController::class, 'deleteInterviewQuestion']);
        Route::post('shareInterviewInvite', [InterviewController::class, 'shareInterviewInvite']);
        Route::get('getInterviewInvitedList', [InterviewController::class, 'getInterviewInvitedList']);
        Route::get('getInterviewSubmissions', [InterviewController::class, 'getInterviewSubmissions']);
        Route::get('getInterviewWS', [InterviewController::class, 'getInterview']);
        Route::get('getInterviewQuestionWS', [InterviewController::class, 'getInterviewQuestions']);
        Route::post('sendInterviewQuestionWS', [InterviewController::class, 'sendInterviewQuestion']);
        Route::get('getOverViewChartsInterview', [InterviewController::class, 'getOverviewCharts']);
    });

    // Webservices prefix (for settings — note different capitalization from original)
    Route::prefix('Webservices')->group(function () {
        Route::get('getAllUserList', [SettingController::class, 'getAllUserList']);
        Route::get('getUserDetailList', [SettingController::class, 'getUserDetail']);
        Route::get('getAllRoles', [SettingController::class, 'getAllRoles']);
        Route::post('setAdminRoles', [SettingController::class, 'setAdminRoles']);
        Route::get('getAllQuizList', [QuizController::class, 'getAllQuizList']);
        Route::get('getAllInterviewList', [InterviewController::class, 'getAllInterviewList']);


      Route::get('getCompanyList',      [SettingController::class, 'getCompanyList']);
    Route::get('getDepartmentList',   [SettingController::class, 'getDepartmentList']);
    Route::get('getVerticalList',     [SettingController::class, 'getVerticalList']);
    Route::get('getBranchList',       [SettingController::class, 'getBranchList']);

    });
    // --- Reports ---
    Route::get('reports/course', [ReportController::class, 'getCourseReport']);
    Route::get('reports/course/excel', [ReportController::class, 'downloadCourseReportExcel']);
    Route::get('reports/course/pdf', [ReportController::class, 'downloadCourseReportPdf']);

    // --- Quiz Reports ---
    Route::get('reports/quiz', [ReportController::class, 'getQuizReport']);
    Route::get('reports/quiz/excel', [ReportController::class, 'downloadQuizReportExcel']);
    Route::get('reports/quiz/pdf', [ReportController::class, 'downloadQuizReportPdf']);

    // --- Learner Reports --
    Route::get('reports/learner', [ReportController::class, 'getLearnerReport']);
    Route::get('reports/learner/excel', [ReportController::class, 'downloadLearnerReportExcel']);
    Route::get('reports/learner/pdf', [ReportController::class, 'downloadLearnerReportPdf']);

    // --- Matrix Report ---
    Route::get('reports/matrix/filters',       [ReportController::class, 'getMatrixFilters']);
    Route::get('reports/matrix/courses/excel', [ReportController::class, 'downloadMatrixReportExcel']);
    Route::get('reports/matrix/pdf',           [ReportController::class, 'downloadMatrixReportPdf']);


    // --- File Uploads ---
    Route::post('upload/category-image', [UploadController::class, 'uploadCategoryImage']);
    Route::post('upload/course-image', [UploadController::class, 'uploadCourseImage']);
    Route::post('upload/pdf', [UploadController::class, 'uploadPDF']);
    Route::post('upload/video', [UploadController::class, 'uploadVideo']);
    Route::post('upload/interview-video', [UploadController::class, 'uploadInterviewVideo']);
    Route::post('upload/profile-photo', [UploadController::class, 'uploadProfilePhoto']);

    // --- Push Notifications ---
    Route::post('notifications/register-token', [NotificationController::class, 'registerToken']);
    Route::post('notifications/send', [NotificationController::class, 'sendNotification']);
    Route::post('notifications/send-all', [NotificationController::class, 'sendToAll']);
    Route::post('notifications/new-course', [NotificationController::class, 'newCourseNotification']);
    Route::post('notifications/course-assigned', [NotificationController::class, 'courseAssignedNotification']);
// --- Feedback Report ---
Route::get('reports/feedback',       [ReportController::class, 'getFeedbackReport']);
Route::get('reports/feedback/excel', [ReportController::class, 'downloadFeedbackReportExcel']);
Route::get('reports/feedback/pdf',   [ReportController::class, 'downloadFeedbackReportPdf']);

// --- Department Leaderboard ---
Route::get('leaderboard/department', [CourseController::class, 'getDepartmentLeaderboard']);

});
