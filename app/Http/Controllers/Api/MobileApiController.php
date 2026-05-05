<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ════════════════════════════════════════════════════════════════════════════════
 * MOBILE API CONTROLLER  —  All 29 endpoints for mobile app v5.4
 * ────────────────────────────────────────────────────────────────────────────────
 * Mobile app (com.calibehr.lms v5.4, versionCode 21) calls these endpoints.
 * APK is hardcoded with BASE_URL = https://lms.calibehr.com/Webservice/
 *
 * This controller mirrors the OLD PHP backend's Processing.php response format
 * exactly — same JSON shape, same field names, same status codes.
 * Mobile app stays untouched. No APK rebuild needed.
 *
 * Response envelope: { "data": <obj|array|null>, "code": <int>, "message": <str> }
 *
 * Status codes (matching old project Constants):
 *   1 = SUCCESS
 *   0 = ERROR / EMPTY
 *   2 = ACC_DISABLED
 *   3 = USER_NOT_FOUND
 *   4 = INVALID_INPUT
 *   5 = NO_DATA
 *
 * Endpoint inventory (29 total):
 *   AUTH:        loginLdap, loginWS, getAppVersion, addAppToken, underMaintenance
 *   ACCOUNT:     getAccountInfoWS
 *   COURSES:     getCourseCategory, getCatalogCourseList, getFeaturedCourseList,
 *                getAllCoursesList, getCourseDetailsWS, courseTopicDetailWS,
 *                updateTopicTimeWS, enrollSelf
 *   QUIZ:        getQuestionsListWS, testSubmitMobileWS
 *   FEEDBACK:    getFeedbackQuestionsWS, addFeedBackWS, addFeedBackV2WS,
 *                addFeedBackReplyWS, getAllFeedbackWS
 *   WISHLIST:    getWishlistWS, anrToWishlistWS
 *   LEADERBOARD: getLeaderBoardWS
 *   INTERVIEW:   getInterviewWS, getInterviewQuestionWS, sendInterviewQuestionWS
 *
 * ⚠️  TESTING NOTE: Each endpoint has been written based on:
 *     - Mobile's expected JSON shape (Kotlin model classes)
 *     - Old PHP backend's Processing.php response logic
 *     - New Laravel DB schema
 * Field-by-field bugs likely exist — test via APK and fix iteratively.
 * Marked uncertain spots with [VERIFY] comments.
 * ════════════════════════════════════════════════════════════════════════════════
 */
class MobileApiController extends Controller
{
    /* ════════════════════════════════════════════════════════════════════════
       BASE HELPERS
       ════════════════════════════════════════════════════════════════════════ */

    /**
     * Standard mobile response envelope — never change shape.
     * Old PHP equivalent: makeOutput($data, $code, $message)
     */
    private function out($data, int $code, string $message)
    {
        return response()->json([
            'data'    => $data,
            'code'    => $code,
            'message' => $message,
        ]);
    }

    /**
     * Build a 45-field LoginData object exactly matching mobile's LoginData.kt.
     * All values cast to string ("" not null) — mobile Gson expects strings.
     */
    private function buildLoginData(User $user, int $primaryRoleId): array
    {
        return [
            'id'                      => (string) $user->id,
            'emp_code'                => (string) ($user->emp_code ?? ''),
            'emp_first_name'          => (string) ($user->emp_first_name ?? ''),
            'emp_middle_name'         => (string) ($user->emp_middle_name ?? ''),
            'emp_last_name'           => (string) ($user->emp_last_name ?? ''),
            'emp_username'            => (string) ($user->emp_username ?? ''),
            'emp_email'               => (string) ($user->emp_email ?? ''),
            'emp_phone'               => (string) ($user->emp_phone ?? ''),
            'emp_photo'               => (string) ($user->emp_photo ?? ''),
            'emp_dob'                 => (string) ($user->emp_dob ?? ''),
            'emp_doj'                 => (string) ($user->emp_doj ?? ''),
            'emp_designation'         => (string) ($user->emp_designation ?? ''),
            'emp_department'          => (string) ($user->emp_department ?? ''),
            'emp_location'            => (string) ($user->emp_location ?? ''),
            'emp_aadhar'              => (string) ($user->emp_aadhar ?? ''),
            'emp_aadhar_status'       => (string) ($user->emp_aadhar_status ?? ''),
            'emp_pan'                 => (string) ($user->emp_pan ?? ''),
            'emp_pfno'                => (string) ($user->emp_pfno ?? ''),
            'emp_uanno'               => (string) ($user->emp_uanno ?? ''),
            'emp_esicno'              => (string) ($user->emp_esicno ?? ''),
            'esic_tic_uploade'        => (string) ($user->esic_tic_uploade ?? ''),
            'emp_password'            => (string) ($user->emp_password ?? ''),
            'password_changed'        => (string) ($user->password_changed ?? '0'),
            'emp_role'                => (string) $primaryRoleId,
            'emp_rm'                  => (string) ($user->emp_rm ?? ''),
            'emp_rm_role'             => (string) ($user->emp_rm_role ?? ''),
            'emp_type'                => (string) ($user->emp_type ?? ''),
            'emp_status'              => (string) ($user->emp_status ?? ''),
            'emp_active'              => (string) ($user->emp_active ?? ''),
            'emp_status_job'          => (string) ($user->emp_status_job ?? ''),
            'emp_client'              => (string) ($user->emp_client ?? ''),
            'emp_client_department'   => (string) ($user->emp_client_department ?? ''),
            'emp_client_name'         => '',
            'emp_otp'                 => '',
            'emp_otp_date'            => '',
            'emp_otp_email'           => '',
            'emp_otp_mobile'          => '',
            'emp_otp_status'          => '',
            'emp_sms_status'          => '',
            'emp_sms_status_second'   => '',
            'salarySlipSent'          => '',
            'isActiveOnce'            => $user->last_visited_on ? '1' : '0',
            'lastActive_on'           => $user->last_visited_on ? Carbon::parse($user->last_visited_on)->format('Y-m-d H:i:s') : '',
            'emp_add_date'            => $user->emp_add_date ? Carbon::parse($user->emp_add_date)->format('Y-m-d H:i:s') : '',
            'emp_updated_date'        => $user->updated_on ? Carbon::parse($user->updated_on)->format('Y-m-d H:i:s') : '',
        ];
    }

    /**
     * Decode mobile's base64-encoded password (mobile uses base64 wrap).
     * Returns plaintext password.
     */
    private function decodePassword(string $password): string
    {
        $decoded = @base64_decode($password, true);
        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }
        return $password;  // not base64, return as-is
    }

    /**
     * Get user's primary role (lowest role_id = highest priority).
     * Mobile expects single string, defaults to "3" (Employee).
     */
    private function getUserPrimaryRole(int $userId): int
    {
        $roleId = UserRole::where('user_id', $userId)
            ->orderBy('role_id')
            ->value('role_id');
        return $roleId ?: 3;
    }

    /**
     * Generate AWS S3 pre-signed URL (or return as-is if not S3).
     * Mobile expects fully accessible URLs for images, PDFs, videos.
     * [VERIFY] Adjust this if your S3 setup differs from old project.
     */
    private function s3Url(?string $path): string
    {
        if (empty($path)) return '';
        // If already a full URL, return as-is
        if (preg_match('#^https?://#', $path)) return $path;
        // Generate presigned URL via Laravel's Storage facade (S3 driver)
        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addHours(2));
        } catch (\Exception $e) {
            // Fall back to direct URL using AWS_URL env var
            $base = rtrim(config('filesystems.disks.s3.url') ?? env('AWS_URL', ''), '/');
            return $base ? "$base/$path" : $path;
        }
    }


    /* ════════════════════════════════════════════════════════════════════════
       AUTH SECTION (5 endpoints)
       ════════════════════════════════════════════════════════════════════════ */

    /**
     * POST /Webservice/loginLdap
     * Mobile: empCode + base64(password)
     * Old project: MitraConnect/loginMobileLdap
     */
    public function loginLdap(Request $request)
    {
        $empCode  = trim((string) $request->input('empCode'));
        $password = (string) $request->input('password');

        if ($empCode === '' || $password === '') {
            return $this->out(null, 0, 'Empty fields.');
        }

        $user = User::where('emp_code', $empCode)->first();
        if (!$user) {
            return $this->out(null, 3, 'User not found.');
        }
        if ($user->emp_status === 'D') {
            return $this->out([], 2, 'Account is disabled.');
        }

        $password = $this->decodePassword($password);

        // LDAP auth (or fallback to MD5 if no LDAP configured)
        $authenticated = $this->authenticateUser($user, $password);
        if (!$authenticated) {
            return $this->out(null, 0, 'Invalid credentials.');
        }

        $primaryRole = $this->getUserPrimaryRole($user->id);

        $user->last_visited_on = now();
        $user->save();

        return $this->out(
            $this->buildLoginData($user, $primaryRole),
            1,
            'Login successful.'
        );
    }

    /**
     * POST /Webservice/loginWS
     * Old project: MitraConnect/loginMobile (non-LDAP variant)
     * Mobile sends: empCode + base64(password) — same as loginLdap but uses MD5
     */
    public function loginWS(Request $request)
    {
        $empCode  = trim((string) $request->input('empCode'));
        $password = (string) $request->input('password');

        if ($empCode === '' || $password === '') {
            return $this->out(null, 0, 'Empty fields.');
        }

        $user = User::where('emp_code', $empCode)->first();
        if (!$user) {
            return $this->out(null, 3, 'User not found.');
        }
        if ($user->emp_status === 'D') {
            return $this->out([], 2, 'Account is disabled.');
        }

        $password = $this->decodePassword($password);

        // Direct MD5 match (no LDAP for this endpoint)
        if ($user->emp_password !== md5($password)) {
            return $this->out(null, 0, 'Invalid credentials.');
        }

        $primaryRole = $this->getUserPrimaryRole($user->id);

        $user->last_visited_on = now();
        $user->save();

        return $this->out(
            $this->buildLoginData($user, $primaryRole),
            1,
            'Login successful.'
        );
    }

    /**
     * Internal: authenticate via LDAP (with MD5 fallback if no LDAP config).
     */
    private function authenticateUser(User $user, string $password): bool
    {
        $ldapConfigs = DB::table('ldap_configs')
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->get();

        if ($ldapConfigs->isEmpty()) {
            // No LDAP configured → MD5 fallback (legacy)
            return $user->emp_password === md5($password);
        }

        foreach ($ldapConfigs as $ldap) {
            try {
                $conn = @ldap_connect($ldap->host, (int) $ldap->port);
                if (!$conn) continue;

                @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
                @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
                @ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);

                $bind = @ldap_bind($conn, $user->emp_code . '@' . $ldap->domain, $password);
                @ldap_close($conn);

                if ($bind) return true;
            } catch (\Exception $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * POST /Webservice/getAppVersion
     * Mobile sends: device (string)
     */
    public function getAppVersion(Request $request)
    {
        return $this->out([
            'version'      => '5.4',
            'versionCode'  => 21,
            'forceUpdate'  => '0',    // '1' to force-update users
            'message'      => '',
        ], 1, 'OK');
    }

    /**
     * POST /Webservice/addAppToken
     * Mobile sends: userID, token, device — for FCM push notifications
     */
    public function addAppToken(Request $request)
    {
        $userID = (int) $request->input('userID');
        $token  = trim((string) $request->input('token'));
        $device = (string) $request->input('device', '1');

        if ($userID <= 0 || $token === '') {
            return $this->out(null, 0, 'Empty fields.');
        }

        try {
            DB::table('app_tokens')->updateOrInsert(
                ['user_id' => $userID, 'device' => $device],
                ['token' => $token, 'updated_on' => now()]
            );
        } catch (\Exception $e) {
            // Silently succeed — old behavior was tolerant
        }

        return $this->out(null, 1, 'Token saved.');
    }

    /**
     * GET /underMaintenance  (no Webservice prefix)
     * Mobile checks on startup. code=1 → show maintenance screen.
     */
    public function underMaintenance()
    {
        return $this->out(null, 0, 'OK');
    }


    /* ════════════════════════════════════════════════════════════════════════
       ACCOUNT SECTION (1 endpoint)
       ════════════════════════════════════════════════════════════════════════ */

    /**
     * POST /Webservice/getAccountInfoWS
     * Mobile expects AccountResponse { data: AccountData, code, message }
     * AccountData fields (6): name, courses_completed, courses_inprogress,
     *   total_points, leaderboard_rank, photo (verify in AccountData.kt)
     */
    public function getAccountInfoWS(Request $request)
    {
        $userID = (int) $request->input('userID');
        if ($userID <= 0) return $this->out(null, 0, 'Empty fields.');

        $user = User::find($userID);
        if (!$user) return $this->out(null, 3, 'User not found.');

        // Course completion stats from course_learners
        $coursesCompleted  = DB::table('course_learners')
            ->where('learner_id', $userID)
            ->where('completed', 1)
            ->count();

        $coursesInprogress = DB::table('course_learners')
            ->where('learner_id', $userID)
            ->where('completed', 0)
            ->count();

        // Leaderboard points + rank
        $points = (int) DB::table('leaderboard')->where('user_id', $userID)->value('points');
        $rank = DB::table('leaderboard')
            ->where('points', '>', $points)
            ->count() + 1;

        return $this->out([
            'name'               => trim($user->emp_first_name . ' ' . $user->emp_last_name),
            'photo'              => $this->s3Url($user->emp_photo),
            'emp_code'           => (string) $user->emp_code,
            'emp_designation'    => (string) ($user->emp_designation ?? ''),
            'courses_completed'  => $coursesCompleted,
            'courses_inprogress' => $coursesInprogress,
            'total_points'       => $points,
            'leaderboard_rank'   => $rank,
        ], 1, 'OK');
    }


    /* ════════════════════════════════════════════════════════════════════════
       COURSES SECTION (8 endpoints)
       ════════════════════════════════════════════════════════════════════════ */

    /**
     * POST /Webservice/getCourseCategory
     * Returns list of categories. CategoryData fields (3): id, name, courseCount
     */
    public function getCourseCategory(Request $request)
    {
        $rows = DB::table('categories_master')
            ->where('status', 1)
            ->select('id', 'name')
            ->get();

        // Add course count per category
        $list = $rows->map(function ($cat) {
            $count = DB::table('courses')
                ->where('category_id', $cat->id)
                ->where('status', 2)  // 2 = published
                ->count();
            return [
                'id'    => $cat->id,
                'name'  => $cat->name,
                'count' => $count,    // [VERIFY] mobile field name might be 'courseCount'
            ];
        });

        return $this->out($list, 1, 'OK');
    }

    /**
     * POST /Webservice/getCatalogCourseList
     * User's assigned courses. AssignCourseData fields (8):
     *   id, name, description, imageURL, categoryName, status, addedToWishlist, type
     */
    public function getCatalogCourseList(Request $request)
    {
        $userID   = (int) $request->input('userID');
        $category = $request->input('category', 0);
        $search   = trim((string) $request->input('search', ''));

        if ($userID <= 0) return $this->out(null, 0, 'Empty fields.');

        $q = DB::table('courses')
            ->join('course_learners', function ($j) use ($userID) {
                $j->on('course_learners.course_id', '=', 'courses.id')
                  ->where('course_learners.learner_id', '=', $userID);
            })
            ->join('categories_master', 'categories_master.id', '=', 'courses.category_id')
            ->leftJoin('wishlist', function ($j) use ($userID) {
                $j->on('wishlist.course_id', '=', 'courses.id')
                  ->where('wishlist.user_id', '=', $userID);
            })
            ->where('courses.status', 2)    // published
            ->select(
                'courses.id',
                'courses.name',
                'courses.description',
                'courses.image_url as imageURL',
                'categories_master.name as categoryName',
                'course_learners.completed as status',
                DB::raw('CASE WHEN wishlist.id IS NULL THEN 0 ELSE 1 END as addedToWishlist'),
                'courses.type'
            );

        if ($category != 0 && $category != '') {
            $q->where('courses.category_id', $category);
        }
        if ($search !== '') {
            $q->where('courses.name', 'like', "%$search%");
        }

        $list = $q->get()->map(function ($r) {
            return [
                'id'              => $r->id,
                'name'            => strip_tags($r->name),
                'description'     => strip_tags($r->description),
                'imageURL'        => $this->s3Url($r->imageURL),
                'categoryName'    => $r->categoryName,
                'status'          => (int) $r->status,
                'addedToWishlist' => (int) $r->addedToWishlist,
                'type'            => $r->type,
            ];
        });

        return $this->out($list, 1, 'OK');
    }

    /**
     * POST /Webservice/getFeaturedCourseList
     * FeaturedCourseResponse { data: [FeaturedCourseData], code, message }
     */
    public function getFeaturedCourseList(Request $request)
    {
        $userID = (int) $request->input('userID');

        $q = DB::table('courses')
            ->leftJoin('wishlist', function ($j) use ($userID) {
                $j->on('wishlist.course_id', '=', 'courses.id')
                  ->where('wishlist.user_id', '=', $userID);
            })
            ->where('courses.featured', 1)
            ->where('courses.status', 2)
            ->select(
                'courses.id',
                'courses.name',
                'courses.description',
                'courses.image_url as imageURL',
                'courses.type',
                DB::raw('CASE WHEN wishlist.id IS NULL THEN 0 ELSE 1 END as addedToWishlist')
            );

        $list = $q->get()->map(function ($r) {
            return [
                'id'              => $r->id,
                'name'            => $r->name,
                'description'     => strip_tags($r->description),
                'imageURL'        => $this->s3Url($r->imageURL),
                'type'            => $r->type,
                'addedToWishlist' => (int) $r->addedToWishlist,
            ];
        });

        return $this->out($list, 1, 'OK');
    }

    /**
     * POST /Webservice/getAllCoursesList
     * All published courses. AllCoursesData fields (8 — like assigned but not learner-bound)
     */
    public function getAllCoursesList(Request $request)
    {
        $userID   = (int) $request->input('userID');
        $category = $request->input('category', 0);
        $search   = trim((string) $request->input('search', ''));

        $q = DB::table('courses')
            ->join('categories_master', 'categories_master.id', '=', 'courses.category_id')
            ->leftJoin('course_learners', function ($j) use ($userID) {
                $j->on('course_learners.course_id', '=', 'courses.id')
                  ->where('course_learners.learner_id', '=', $userID);
            })
            ->leftJoin('wishlist', function ($j) use ($userID) {
                $j->on('wishlist.course_id', '=', 'courses.id')
                  ->where('wishlist.user_id', '=', $userID);
            })
            ->where('courses.status', 2)
            ->where('courses.visibility', 0)  // public courses only
            ->select(
                'courses.id',
                'courses.name',
                'courses.description',
                'courses.image_url as imageURL',
                'categories_master.name as categoryName',
                DB::raw('IFNULL(course_learners.completed, -1) as status'),
                DB::raw('CASE WHEN wishlist.id IS NULL THEN 0 ELSE 1 END as addedToWishlist'),
                'courses.type'
            );

        if ($category != 0 && $category != '') {
            $q->where('courses.category_id', $category);
        }
        if ($search !== '') {
            $q->where('courses.name', 'like', "%$search%");
        }

        $list = $q->get()->map(function ($r) {
            return [
                'id'              => $r->id,
                'name'            => $r->name,
                'description'     => strip_tags($r->description),
                'imageURL'        => $this->s3Url($r->imageURL),
                'categoryName'    => $r->categoryName,
                'status'          => (int) $r->status,
                'addedToWishlist' => (int) $r->addedToWishlist,
                'type'            => $r->type,
            ];
        });

        return $this->out($list, 1, 'OK');
    }

    /**
     * POST /Webservice/getCourseDetailsWS
     * Single course with chapters + topics. CourseDetailData fields (21+).
     */
    public function getCourseDetailsWS(Request $request)
    {
        $courseID = (int) $request->input('courseID');
        $userID   = (int) $request->input('userID');

        if ($courseID <= 0 || $userID <= 0) return $this->out(null, 0, 'Empty fields.');

        $course = DB::table('courses')
            ->leftJoin('users', 'users.id', '=', 'courses.added_by')
            ->where('courses.id', $courseID)
            ->select(
                'courses.*',
                'users.emp_first_name',
                'users.emp_last_name',
                'users.emp_code as addedByEmpCode'
            )
            ->first();

        if (!$course) return $this->out(null, 5, 'Course not found.');

        // Chapters with their topics
        $chapters = DB::table('course_chapters')
            ->where('course_id', $courseID)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name']);

        $chaptersList = $chapters->map(function ($ch) use ($courseID, $userID) {
            $topics = DB::table('course_topics')
                ->leftJoin('course_topic_types', 'course_topic_types.id', '=', 'course_topics.type')
                ->leftJoin('course_learners_status', function ($j) use ($userID) {
                    $j->on('course_learners_status.topic_id', '=', 'course_topics.id')
                      ->where('course_learners_status.user_id', '=', $userID);
                })
                ->where('course_topics.course_id', $courseID)
                ->where('course_topics.chapter_id', $ch->id)
                ->where('course_topics.status', 1)
                ->orderBy('course_topics.id')
                ->select(
                    'course_topics.id',
                    'course_topics.name',
                    'course_topics.type',
                    'course_topics.duration',
                    'course_topics.file_url as fileURL',
                    'course_topic_types.icon',
                    'course_topic_types.name as TypeName',
                    DB::raw('IFNULL(course_learners_status.completed, 0) as completed'),
                    DB::raw('IFNULL(course_learners_status.completed, 0) as topicLearnerStatus')
                )
                ->get()
                ->map(function ($t) {
                    return [
                        'id'                 => $t->id,
                        'name'               => $t->name,
                        'type'               => $t->type,
                        'duration'           => $t->duration,
                        'fileURL'            => $this->s3Url($t->fileURL),
                        'icon'               => $this->s3Url($t->icon),
                        'TypeName'           => $t->TypeName,
                        'completed'          => (int) $t->completed,
                        'topicLearnerStatus' => (int) $t->topicLearnerStatus,
                    ];
                });

            return [
                'id'     => $ch->id,
                'name'   => $ch->name,
                'topics' => $topics,
            ];
        });

        $addedByName = trim(($course->emp_first_name ?? '') . ' ' . ($course->emp_last_name ?? ''));

        return $this->out([
            'id'                  => $course->id,
            'name'                => $course->name,
            'description'         => strip_tags($course->description ?? ''),
            'what_will_you_learn' => $course->what_will_you_learn ?? '',
            'pre_requisites'      => $course->pre_requisites ?? '',
            'trainer_details'     => $course->trainer_details ?? '',
            'imageURL'            => $this->s3Url($course->image_url),
            'categoryID'          => $course->category_id,
            'type'                => $course->type,
            'points'              => $course->points,
            'visibility'          => $course->visibility,
            'status'              => $course->status,
            'featured'            => $course->featured,
            'addedBy'             => $course->added_by,
            'addedByName'         => $addedByName,
            'addedByEmpCode'      => $course->addedByEmpCode ?? '',
            'addedOn'             => $course->added_on,
            'chaptersList'        => $chaptersList,
        ], 1, 'OK');
    }

    /**
     * POST /Webservice/courseTopicDetailWS
     * Returns topic content based on type (video/pdf/link/quiz).
     * Mobile parses different response types:
     *   type=1 (video) → VideoResponse
     *   type=2 (pdf)   → PdfTopicResponse
     *   type=3 (link)  → LinkTopicResponse
     *   type=4 (quiz)  → QuizDetailResponse (with PastTestAttempts)
     */
    public function courseTopicDetailWS(Request $request)
    {
        $topicID = (int) $request->input('topicID');
        $userID  = (int) $request->input('userID');

        if ($topicID <= 0 || $userID <= 0) return $this->out(null, 0, 'Empty fields.');

        $topic = DB::table('course_topics')
            ->where('id', $topicID)
            ->first();

        if (!$topic) return $this->out(null, 5, 'Topic not found.');

        $base = [
            'id'        => $topic->id,
            'name'      => $topic->name,
            'type'      => $topic->type,
            'duration'  => $topic->duration,
            'fileURL'   => $this->s3Url($topic->file_url),
            'courseID'  => $topic->course_id,
            'chapterID' => $topic->chapter_id,
        ];

        // Mark started if not already
        DB::table('course_learners_status')->updateOrInsert(
            ['user_id' => $userID, 'topic_id' => $topicID],
            ['course_id' => $topic->course_id, 'completed' => 0, 'updated_on' => now()]
        );

        // Type-specific extras
        switch ((int) $topic->type) {
            case 3: // Link / Resource
                $links = DB::table('topic_resource_links')
                    ->where('topic_id', $topicID)
                    ->get();
                $base['links'] = $links->map(fn($l) => [
                    'id'         => $l->id,
                    'name'       => $l->name,
                    'description'=> $l->description,
                    'fileURL'    => $this->s3Url($l->file_url),
                    'type'       => $l->type,
                ])->toArray();
                break;

            case 4: // Quiz
                $quiz = DB::table('quizzes')->where('topic_id', $topicID)->first();
                if ($quiz) {
                    $base['quizID']        = $quiz->id;
                    $base['quizName']      = $quiz->name;
                    $base['time']          = $quiz->time;
                    $base['passing']       = $quiz->passing_percentage;
                    $base['attemptsLimit'] = $quiz->number_of_attempt;

                    // Past attempts by this user
                    $past = DB::table('quiz_answers')
                        ->where('quiz_id', $quiz->id)
                        ->where('added_by', $userID)
                        ->orderBy('id', 'desc')
                        ->get();
                    $base['pastAttempts'] = $past->map(fn($a) => [
                        'id'         => $a->id,
                        'percentage' => $a->percentage,
                        'pass'       => $a->pass,
                        'answeredOn' => $a->answered_on,
                    ])->toArray();
                }
                break;
        }

        return $this->out($base, 1, 'OK');
    }

    /**
     * POST /Webservice/updateTopicTimeWS
     * Mobile reports time spent on topic + completion status.
     */
    public function updateTopicTimeWS(Request $request)
    {
        $userID    = (int) $request->input('userID');
        $topicID   = (int) $request->input('topicID');
        $courseID  = (int) $request->input('courseID');
        $time      = (int) $request->input('time', 0);
        $completed = (int) $request->input('completed', 0);

        if ($userID <= 0 || $topicID <= 0) return $this->out(null, 0, 'Empty fields.');

        DB::table('course_learners_status')->updateOrInsert(
            ['user_id' => $userID, 'topic_id' => $topicID],
            [
                'course_id'    => $courseID,
                'time_spent'   => DB::raw("IFNULL(time_spent, 0) + $time"),
                'completed'    => $completed,
                'updated_on'   => now(),
            ]
        );

        // Check if all topics completed → mark course complete
        if ($completed === 1 && $courseID > 0) {
            $totalTopics = DB::table('course_topics')
                ->where('course_id', $courseID)
                ->where('status', 1)
                ->count();
            $completedTopics = DB::table('course_learners_status')
                ->where('user_id', $userID)
                ->where('course_id', $courseID)
                ->where('completed', 1)
                ->count();

            if ($totalTopics > 0 && $completedTopics >= $totalTopics) {
                DB::table('course_learners')
                    ->where('learner_id', $userID)
                    ->where('course_id', $courseID)
                    ->update(['completed' => 1, 'completed_on' => now()]);

                // Award points to leaderboard
                $coursePts = (int) DB::table('courses')->where('id', $courseID)->value('points');
                if ($coursePts > 0) {
                    DB::table('leaderboard')->updateOrInsert(
                        ['user_id' => $userID],
                        [
                            'points'     => DB::raw("IFNULL(points, 0) + $coursePts"),
                            'updated_on' => now(),
                        ]
                    );
                }
            }
        }

        return $this->out(null, 1, 'Updated.');
    }

    /**
     * POST /Webservice/enrollSelf
     * User self-enrolls in a public course.
     */
    public function enrollSelf(Request $request)
    {
        $userID   = (int) $request->input('userID');
        $courseID = (int) $request->input('courseID');

        if ($userID <= 0 || $courseID <= 0) return $this->out(null, 0, 'Empty fields.');

        $exists = DB::table('course_learners')
            ->where('learner_id', $userID)
            ->where('course_id', $courseID)
            ->exists();

        if ($exists) {
            return $this->out(null, 1, 'Already enrolled.');
        }

        DB::table('course_learners')->insert([
            'learner_id' => $userID,
            'course_id'  => $courseID,
            'completed'  => 0,
            'assigned_on'=> now(),
        ]);

        return $this->out(null, 1, 'Enrolled successfully.');
    }


    /* ════════════════════════════════════════════════════════════════════════
       QUIZ SECTION (2 endpoints)
       ════════════════════════════════════════════════════════════════════════ */

    /**
     * POST /Webservice/getQuestionsListWS
     * Returns quiz questions + options (without correct-answer flag).
     * QuestionData fields: id, question_text, question_type, options [{id, option_text}]
     */
    public function getQuestionsListWS(Request $request)
    {
        $quizID = (int) $request->input('quizID');
        if ($quizID <= 0) return $this->out(null, 0, 'Empty fields.');

        $questions = DB::table('quiz_questions')
            ->where('quiz_id', $quizID)
            ->orderBy('question_order')
            ->get();

        $list = $questions->map(function ($q) {
            $options = DB::table('quiz_question_options')
                ->where('question_id', $q->id)
                ->get()
                ->map(fn($o) => [
                    'id'         => $o->id,
                    'optionText' => $o->option_text,    // mobile expects camelCase
                ]);

            return [
                'id'             => $q->id,
                'questionsText'  => $q->question_text,  // [VERIFY] mobile uses 'questionsText' with 's'
                'questionType'   => $q->question_type,
                'point'          => $q->point,
                'options'        => $options,
            ];
        });

        return $this->out($list, 1, 'OK');
    }

    /**
     * POST /Webservice/testSubmitMobileWS
     * Mobile submits quiz answers as JSON in 'answer' form field.
     * Body example: userID=1&quizID=5&topicID=10&time_up=2&switch_tabs=0&answer=[{"questionID":1,"optionID":11}, ...]
     */
    public function testSubmitMobileWS(Request $request)
    {
        $userID    = (int) $request->input('userID');
        $quizID    = (int) $request->input('quizID');
        $topicID   = (int) $request->input('topicID', 0);
        $answerRaw = $request->input('answer', '[]');
        $timeUp    = (int) $request->input('time_up', 2);
        $switchTabs = (int) $request->input('switch_tabs', 0);

        if ($userID <= 0 || $quizID <= 0) return $this->out(null, 0, 'Empty fields.');

        $answers = is_string($answerRaw) ? json_decode($answerRaw, true) : $answerRaw;
        if (!is_array($answers)) $answers = [];

        $quiz = Quiz::with('questions.options')->find($quizID);
        if (!$quiz) return $this->out(null, 5, 'Quiz not found.');

        // Score the quiz
        $points = 0; $total = 0; $correct = 0; $items = [];
        foreach ($quiz->questions as $q) {
            $total += $q->point;
            $selected = null;
            foreach ($answers as $a) {
                if (($a['questionID'] ?? 0) == $q->id) {
                    $selected = (int) ($a['optionID'] ?? 0);
                    break;
                }
            }
            if ($selected) {
                $opt = $q->options->firstWhere('id', $selected);
                $isCorrect = $opt && (int) $opt->answer === 1;
                if ($isCorrect) { $points += $q->point; $correct++; }
                $items[] = [
                    'question_id' => $q->id,
                    'option_id'   => $selected,
                    'result'      => $isCorrect ? 1 : 0,
                ];
            }
        }

        $percentage = $total > 0 ? round(($points / $total) * 100, 2) : 0;
        $passed     = $percentage >= (float) $quiz->passing_percentage;

        DB::beginTransaction();
        try {
            $answerId = DB::table('quiz_answers')->insertGetId([
                'quiz_id'         => $quizID,
                'topic_id'        => $topicID,
                'invite_id'       => 0,
                'points'          => $points,
                'total_points'    => $total,
                'correct_answers' => $correct,
                'total_questions' => $quiz->questions->count(),
                'percentage'      => $percentage,
                'pass'            => $passed ? 1 : 0,
                'time_up'         => $timeUp,
                'switch_tabs'     => $switchTabs,
                'ip'              => $request->ip(),
                'user_agent'      => $request->userAgent() ?? '',
                'added_by'        => $userID,
                'added_on'        => now(),
                'answered_on'     => now(),
            ]);

            foreach ($items as $it) {
                DB::table('quiz_answer_items')->insert(array_merge($it, ['answer_id' => $answerId]));
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('testSubmitMobileWS error: ' . $e->getMessage());
            return $this->out(null, 0, 'Submission failed.');
        }

        return $this->out([
            'answerID'   => $answerId,
            'points'     => $points,
            'total'      => $total,
            'correct'    => $correct,
            'total_qs'   => $quiz->questions->count(),
            'percentage' => $percentage,
            'pass'       => $passed ? 1 : 0,
        ], 1, $passed ? 'Quiz passed.' : 'Quiz submitted.');
    }


    /* ════════════════════════════════════════════════════════════════════════
       FEEDBACK SECTION (5 endpoints)
       ════════════════════════════════════════════════════════════════════════ */

    public function getFeedbackQuestionsWS(Request $request)
    {
        $courseID = (int) $request->input('courseID');
        if ($courseID <= 0) return $this->out(null, 0, 'Empty fields.');

        $list = DB::table('course_feedback_questions')
            ->where('course_id', $courseID)
            ->orderBy('id')
            ->get();

        return $this->out($list, 1, 'OK');
    }

    public function addFeedBackWS(Request $request)
    {
        $userID    = (int) $request->input('userID');
        $courseID  = (int) $request->input('courseID');
        $rating    = (int) $request->input('rating', 0);
        $message   = trim((string) $request->input('message', ''));

        if ($userID <= 0 || $courseID <= 0) return $this->out(null, 0, 'Empty fields.');

        DB::table('course_feedback')->insert([
            'course_id'  => $courseID,
            'user_id'    => $userID,
            'rating'     => $rating,
            'message'    => $message,
            'added_on'   => now(),
        ]);

        return $this->out(null, 1, 'Feedback saved.');
    }

    public function addFeedBackV2WS(Request $request)
    {
        $userID    = (int) $request->input('userID');
        $courseID  = (int) $request->input('courseID');
        $answers   = $request->input('answers', []);   // array of {question_id, value}

        if ($userID <= 0 || $courseID <= 0) return $this->out(null, 0, 'Empty fields.');

        if (is_string($answers)) $answers = json_decode($answers, true) ?: [];

        DB::beginTransaction();
        try {
            $feedbackId = DB::table('course_feedback')->insertGetId([
                'course_id'  => $courseID,
                'user_id'    => $userID,
                'rating'     => 0,
                'message'    => '',
                'added_on'   => now(),
            ]);

            foreach ($answers as $a) {
                if (!isset($a['question_id'])) continue;
                DB::table('course_feedback_answers')->insert([
                    'feedback_id' => $feedbackId,
                    'question_id' => (int) $a['question_id'],
                    'answer'      => (string) ($a['value'] ?? ''),
                    'added_on'    => now(),
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('addFeedBackV2WS: ' . $e->getMessage());
            return $this->out(null, 0, 'Save failed.');
        }

        return $this->out(null, 1, 'Feedback saved.');
    }

    public function addFeedBackReplyWS(Request $request)
    {
        $userID     = (int) $request->input('userID');
        $feedbackID = (int) $request->input('feedbackID');
        $message    = trim((string) $request->input('message'));

        if ($userID <= 0 || $feedbackID <= 0 || $message === '') {
            return $this->out(null, 0, 'Empty fields.');
        }

        DB::table('course_feedback_replies')->insert([
            'feedback_id' => $feedbackID,
            'user_id'     => $userID,
            'message'     => $message,
            'added_on'    => now(),
        ]);

        return $this->out(null, 1, 'Reply added.');
    }

    public function getAllFeedbackWS(Request $request)
    {
        $courseID = (int) $request->input('courseID');
        if ($courseID <= 0) return $this->out(null, 0, 'Empty fields.');

        $feedbacks = DB::table('course_feedback')
            ->leftJoin('users', 'users.id', '=', 'course_feedback.user_id')
            ->where('course_feedback.course_id', $courseID)
            ->orderBy('course_feedback.id', 'desc')
            ->select(
                'course_feedback.*',
                'users.emp_first_name',
                'users.emp_last_name',
                'users.emp_photo'
            )
            ->get();

        $list = $feedbacks->map(function ($fb) {
            $replies = DB::table('course_feedback_replies')
                ->leftJoin('users', 'users.id', '=', 'course_feedback_replies.user_id')
                ->where('course_feedback_replies.feedback_id', $fb->id)
                ->orderBy('course_feedback_replies.id')
                ->select(
                    'course_feedback_replies.*',
                    'users.emp_first_name',
                    'users.emp_last_name',
                    'users.emp_photo'
                )
                ->get()
                ->map(fn($r) => [
                    'id'         => $r->id,
                    'message'    => $r->message,
                    'userName'   => trim($r->emp_first_name . ' ' . $r->emp_last_name),
                    'userPhoto'  => $this->s3Url($r->emp_photo),
                    'addedOn'    => $r->added_on,
                ])->toArray();

            return [
                'id'          => $fb->id,
                'rating'      => $fb->rating,
                'message'     => $fb->message,
                'userName'    => trim($fb->emp_first_name . ' ' . $fb->emp_last_name),
                'userPhoto'   => $this->s3Url($fb->emp_photo),
                'addedOn'     => $fb->added_on,
                'replies'     => $replies,
            ];
        });

        return $this->out($list, 1, 'OK');
    }


    /* ════════════════════════════════════════════════════════════════════════
       WISHLIST SECTION (2 endpoints)
       ════════════════════════════════════════════════════════════════════════ */

    public function getWishlistWS(Request $request)
    {
        $userID = (int) $request->input('userID');
        if ($userID <= 0) return $this->out(null, 0, 'Empty fields.');

        $list = DB::table('wishlist')
            ->join('courses', 'courses.id', '=', 'wishlist.course_id')
            ->join('categories_master', 'categories_master.id', '=', 'courses.category_id')
            ->where('wishlist.user_id', $userID)
            ->where('courses.status', 2)
            ->orderBy('wishlist.id', 'desc')
            ->select(
                'courses.id',
                'courses.name',
                'courses.description',
                'courses.image_url as imageURL',
                'categories_master.name as categoryName',
                'courses.type'
            )
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'name'         => $r->name,
                'description'  => strip_tags($r->description),
                'imageURL'     => $this->s3Url($r->imageURL),
                'categoryName' => $r->categoryName,
                'type'         => $r->type,
            ]);

        return $this->out($list, 1, 'OK');
    }

    /**
     * POST /Webservice/anrToWishlistWS
     * Add or remove from wishlist (toggle).
     */
    public function anrToWishlistWS(Request $request)
    {
        $userID   = (int) $request->input('userID');
        $courseID = (int) $request->input('courseID');
        $action   = $request->input('action', 'add');  // 'add' or 'remove'

        if ($userID <= 0 || $courseID <= 0) return $this->out(null, 0, 'Empty fields.');

        if ($action === 'remove') {
            DB::table('wishlist')
                ->where('user_id', $userID)
                ->where('course_id', $courseID)
                ->delete();
            return $this->out(null, 1, 'Removed from wishlist.');
        }

        // Add (idempotent)
        $exists = DB::table('wishlist')
            ->where('user_id', $userID)
            ->where('course_id', $courseID)
            ->exists();
        if (!$exists) {
            DB::table('wishlist')->insert([
                'user_id'   => $userID,
                'course_id' => $courseID,
                'added_on'  => now(),
            ]);
        }
        return $this->out(null, 1, 'Added to wishlist.');
    }


    /* ════════════════════════════════════════════════════════════════════════
       LEADERBOARD SECTION (1 endpoint)
       ════════════════════════════════════════════════════════════════════════ */

    /**
     * POST /Webservice/getLeaderBoardWS
     * Mobile params: userID, leaderBoardType (0=all,1=client,2=dept), value, length, start
     * Mobile expects: { topThree: [...], list: [...], userRank: int, userPoints: int }
     */
    public function getLeaderBoardWS(Request $request)
    {
        $userID = (int) $request->input('userID');
        $type   = (int) $request->input('leaderBoardType', 0);
        $value  = $request->input('leaderBoardValue', 0);
        $length = (int) $request->input('length', 50);
        $start  = (int) $request->input('start', 0);

        $q = DB::table('leaderboard')
            ->join('users', 'users.id', '=', 'leaderboard.user_id')
            ->select(
                'leaderboard.id',
                'leaderboard.user_id',
                'leaderboard.points',
                'users.emp_first_name',
                'users.emp_last_name',
                'users.emp_code',
                'users.emp_photo',
                'users.emp_designation',
                'users.emp_department'
            );

        if ($type === 1) $q->where('leaderboard.emp_client', $value);
        elseif ($type === 2) $q->where('leaderboard.emp_client_department', $value);

        $q->orderBy('leaderboard.points', 'desc')
          ->orderBy('leaderboard.updated_on', 'asc');

        $rows = $q->offset($start)->limit($length)->get();
        $list = $rows->map(fn($r) => [
            'id'             => $r->id,
            'userID'         => $r->user_id,
            'points'         => $r->points,
            'name'           => trim($r->emp_first_name . ' ' . $r->emp_last_name),
            'empCode'        => $r->emp_code,
            'photo'          => $this->s3Url($r->emp_photo),
            'designation'    => $r->emp_designation,
            'department'     => $r->emp_department,
        ]);

        // Top 3 (always returned regardless of pagination)
        $topThree = (clone $q)->offset(0)->limit(3)->get()
            ->map(fn($r) => [
                'userID'  => $r->user_id,
                'points'  => $r->points,
                'name'    => trim($r->emp_first_name . ' ' . $r->emp_last_name),
                'photo'   => $this->s3Url($r->emp_photo),
            ]);

        // Current user's rank + points
        $userPoints = (int) DB::table('leaderboard')->where('user_id', $userID)->value('points');
        $userRank = DB::table('leaderboard')
            ->where('points', '>', $userPoints)
            ->count() + 1;

        return $this->out([
            'list'        => $list,
            'topThree'    => $topThree,
            'userRank'    => $userRank,
            'userPoints'  => $userPoints,
        ], 1, 'OK');
    }


    /* ════════════════════════════════════════════════════════════════════════
       INTERVIEW SECTION (3 endpoints)
       ════════════════════════════════════════════════════════════════════════ */

    /**
     * POST /Webservice/getInterviewWS
     * Mobile sends: interviewCode (UUID-like string)
     * Returns: interview details + invite info
     */
    public function getInterviewWS(Request $request)
    {
        $code = trim((string) $request->input('interviewCode'));
        if ($code === '') return $this->out(null, 0, 'Empty fields.');

        $invite = DB::table('interview_invites')
            ->where('unique_id', $code)
            ->first();

        if (!$invite) return $this->out(null, 5, 'Invalid interview code.');
        if ((int) $invite->completed === 2) return $this->out([], 2, 'Already completed.');

        // Check expiry
        if (!empty($invite->expire_on) && strtotime($invite->expire_on) < time()) {
            return $this->out(null, 5, 'This interview link has expired.');
        }

        $interview = DB::table('interviews')
            ->where('id', $invite->interview_id)
            ->first();

        if (!$interview) return $this->out(null, 5, 'Interview not found.');

        // Mark started
        if ((int) $invite->completed === 0) {
            DB::table('interview_invites')
                ->where('id', $invite->id)
                ->update(['completed' => 1, 'updated_on' => now()]);
        }

        return $this->out([
            'interviewID'    => $interview->id,
            'inviteID'       => $invite->id,
            'name'           => $interview->name,
            'description'    => $interview->description ?? '',
            'questions_count'=> (int) DB::table('interview_questions')
                                  ->where('interview_id', $interview->id)
                                  ->count(),
            'time_per_qs'    => $interview->question_time ?? 60,
            'candidateEmail' => $invite->email,
        ], 1, 'OK');
    }

    /**
     * POST /Webservice/getInterviewQuestionWS
     * Mobile sends: interviewID
     * Returns list of questions for the interview.
     */
    public function getInterviewQuestionWS(Request $request)
    {
        $interviewID = (int) $request->input('interviewID');
        if ($interviewID <= 0) return $this->out(null, 0, 'Empty fields.');

        $questions = DB::table('interview_questions')
            ->where('interview_id', $interviewID)
            ->orderBy('question_order')
            ->get();

        $list = $questions->map(fn($q) => [
            'id'             => $q->id,
            'question'       => $q->question_text ?? $q->question ?? '',
            'questionType'   => $q->question_type ?? 0,
            'questionOrder'  => $q->question_order,
            'time'           => $q->time ?? 60,
        ]);

        return $this->out($list, 1, 'OK');
    }

    /**
     * POST /Webservice/sendInterviewQuestionWS
     * MULTIPART upload — mobile sends video file + metadata.
     * Body: inviteID, questionID, video (file)
     */
    public function sendInterviewQuestionWS(Request $request)
    {
        $inviteID   = (int) $request->input('inviteID');
        $questionID = (int) $request->input('questionID');
        $video      = $request->file('video');

        if ($inviteID <= 0 || $questionID <= 0 || !$video) {
            return $this->out(null, 0, 'Missing video or invite.');
        }

        $invite = DB::table('interview_invites')->where('id', $inviteID)->first();
        if (!$invite) return $this->out(null, 5, 'Invalid invite.');

        try {
            // Upload to S3 under interview_responses/{inviteID}/{questionID}_{timestamp}.{ext}
            $ext = $video->getClientOriginalExtension() ?: 'mp4';
            $path = sprintf(
                'interview_responses/%d/%d_%d.%s',
                $inviteID, $questionID, time(), $ext
            );
            Storage::disk('s3')->put($path, file_get_contents($video->getRealPath()));

            // Save record
            DB::table('interview_responses')->updateOrInsert(
                ['invite_id' => $inviteID, 'question_id' => $questionID],
                [
                    'video_url'    => $path,
                    'submitted_on' => now(),
                ]
            );

            return $this->out([
                'video_url' => $this->s3Url($path),
            ], 1, 'Video uploaded.');
        } catch (\Exception $e) {
            Log::error('sendInterviewQuestionWS: ' . $e->getMessage());
            return $this->out(null, 0, 'Upload failed.');
        }
    }
}