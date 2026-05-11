<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\CourseTopic;
use App\Models\CourseLearner;
use App\Models\CourseLearnerTopicStatus;
use App\Models\CategoryMaster;
use App\Models\TopicQuestion;
use App\Models\TopicQuestionOption;
use App\Models\TopicQuestionAnswer;
use App\Models\TopicQuestionAnswersList;
use App\Models\CourseFeedbackRating;
use App\Models\CourseFeedbackComment;
use App\Models\CourseFeedbackQuestion;
use App\Models\CourseFeedbackAnswer;
use App\Models\Leaderboard;
use App\Models\LeaderboardLog;
use App\Models\Wishlist;
use App\Models\User;
use App\Models\AppVersion;
use App\Models\PushNotificationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    private function out($data, int $code, string $msg)
    {
        return response()->json(['data' => $data, 'code' => $code, 'message' => $msg]);
    }

    private function userId(Request $request): int
    {
        return $request->user()->id;
    }

    // =========================================================================
    // CATEGORIES
    // =========================================================================

    /** GET /api/categories */
    public function getCategories(Request $request)
    {
        // Admin can pass ?all=1 to include disabled categories
        $query = CategoryMaster::orderBy('name');
        if (!$request->boolean('all')) {
            $query->where('status', 1);
        }
        $rows = $query->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'category_name' => $c->name,
                'image_url'     => $c->image_url,
                'status'        => $c->status,
            ]);
        return $this->out($rows, 1, 'OK');
    }

    /** POST /api/categories/add */
    public function addCategory(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $name = strtolower(strip_tags(trim($request->name)));
        if (CategoryMaster::where('name', $name)->exists()) {
            return $this->out(null, 0, 'Category already exists.');
        }
        $cat = CategoryMaster::create([
            'name'     => $name,
            'image_url'=> $request->image_url ?? '',
            'added_by' => $this->userId($request),
            'added_on' => now(),
            'updated_by' => 0,
            'updated_on' => null,
            'status'   => 1,
        ]);
        return $this->out($cat, 1, 'Category added.');
    }

    /** POST /api/categories/update */
    public function updateCategory(Request $request)
    {
        $request->validate(['id' => 'required|integer', 'name' => 'required|string']);
        CategoryMaster::where('id', $request->id)->update([
            'name'       => strtolower(strip_tags(trim($request->name))),
            'image_url'  => $request->image_url ?? '',
            'updated_by' => $this->userId($request),
            'updated_on' => now(),
        ]);
        return $this->out(null, 1, 'Category updated.');
    }

    // =========================================================================
    // COURSES
    // =========================================================================

    /** GET /api/courses/list  — admin/trainer course management list */
public function getCourseList(Request $request)
{
    $courses = Course::with('category')
        ->orderByDesc('added_on')
        ->get()
        ->map(fn($c) => $this->formatCourse($c));
    return response()->json(['data' => $courses, 'code' => 1, 'message' => 'OK'])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
}
    /** GET /api/courses/catalog  — learner public catalog */
    public function getCatalogCourseList(Request $request)
    {
        $uid     = $this->userId($request);
        $courses = Course::with('category')
            ->where('status', 2)
            ->where('visibility', 0)
            ->orderByDesc('featured')
            ->orderBy('name')
            ->get()
            ->map(function ($c) use ($uid) {
                $data                  = $this->formatCourse($c);
                $data['enrolled']      = CourseLearner::where('course_id', $c->id)->where('learner_id', $uid)->exists();
                $data['wishlisted']    = Wishlist::where('course_id', $c->id)->where('user_id', $uid)->exists();
                $data['learner_count'] = CourseLearner::where('course_id', $c->id)->where('status', 1)->count();
                $data['rating']        = round(CourseFeedbackRating::where('course_id', $c->id)->where('status', 1)->avg('star') ?? 0, 1);

                // Progress for enrolled learner
                if ($data['enrolled']) {
                    $learner = CourseLearner::where('course_id', $c->id)->where('learner_id', $uid)->first();
                    $totalTopics     = CourseTopic::where('course_id', $c->id)->where('status', 1)->count();
                    $completedTopics = CourseLearnerTopicStatus::where('course_id', $c->id)->where('user_id', $uid)->where('completed', 1)->count();
                    $data['completed']           = $learner ? $learner->completed : 0;
                    $data['progress_percentage'] = $learner && $learner->completed ? 100 : ($totalTopics > 0 ? round(($completedTopics / $totalTopics) * 100) : 0);
                }
                return $data;
            });
        return $this->out($courses, 1, 'OK');
    }

    /** GET /api/courses/{id}/details */
    public function getCourseDetails(Request $request)
    {
        $courseId = $request->input('courseID');
        $uid      = $this->userId($request);
        $course   = Course::with(['category', 'chapters.topics'])->findOrFail($courseId);

        $data              = $this->formatCourse($course);
        $data['chapters']  = $course->chapters->map(fn($ch) => $this->formatChapter($ch, $uid));
        $data['enrolled']  = CourseLearner::where('course_id', $courseId)->where('learner_id', $uid)->exists();
        $data['wishlisted']= Wishlist::where('course_id', $courseId)->where('user_id', $uid)->exists();

        // added-by employee code
        $addedByUser = User::find($course->added_by);
        $data['addedByEmpCode'] = $addedByUser ? $addedByUser->emp_code : '';

        return $this->out($data, 1, 'OK');
    }

    /** POST /api/courses/add */
    public function addCourse(Request $request)
    {
        $request->validate(['name' => 'required|string', 'category_id' => 'required|integer']);
        $uid = $this->userId($request);

        $course = Course::create([
            'name'                => strip_tags(trim($request->name)),
            'description'         => $request->description ?? '',
            'what_will_you_learn' => $request->what_will_you_learn ?? '',
            'pre_requisites'      => $request->pre_requisites ?? '',
            'trainer_details'     => $request->trainer_details ?? '',
            'image_url'           => $request->image_url ?? '',
            'category_id'         => $request->category_id,
            'type'                => $request->type ?? 1,
            'points'              => $request->points ?? 1,
            'visibility'          => $request->visibility ?? 0,
            'added_by'            => $uid,
            'added_on'            => now(),
            'updated_by'          => 0,
            'status'              => 1, // Drafted
            'featured'            => 0,
            'featured_by'         => 0,
        ]);

        $this->logCourse($course->id, 'ADD_NEW_COURSE', $uid, $request);
        return $this->out(['courseID' => $course->id], 1, 'Course added.');
    }

    /** POST /api/courses/update */
    public function updateCourse(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        $uid = $this->userId($request);

        Course::where('id', $request->courseID)->update(array_filter([
            'name'                => $request->name ? strip_tags(trim($request->name)) : null,
            'description'         => $request->description,
            'what_will_you_learn' => $request->what_will_you_learn,
            'pre_requisites'      => $request->pre_requisites,
            'trainer_details'     => $request->trainer_details,
            'image_url'           => $request->image_url,
            'category_id'         => $request->category_id,
            'type'                => $request->type,
            'points'              => $request->points,
            'visibility'          => $request->visibility,
            'updated_by'          => $uid,
            'updated_on'          => now(),
        ], fn($v) => !is_null($v)));

        $this->logCourse($request->courseID, 'COURSE_UPDATED', $uid, $request);
        return $this->out(null, 1, 'Course updated.');
    }

    /** POST /api/courses/make-live */
    public function makeCourseLive(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        Course::where('id', $request->courseID)->update(['status' => 2, 'updated_by' => $this->userId($request), 'updated_on' => now()]);
        $this->logCourse($request->courseID, 'COURSE_LIVE', $this->userId($request), $request);
        return $this->out(null, 1, 'Course published.');
    }

    /** POST /api/courses/disable */
    public function disableCourse(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        Course::where('id', $request->courseID)->update(['status' => 0, 'updated_by' => $this->userId($request), 'updated_on' => now()]);
        $this->logCourse($request->courseID, 'COURSE_DISABLED', $this->userId($request), $request);
        return $this->out(null, 1, 'Course disabled.');
    }

    // =========================================================================
    // CHAPTERS
    // =========================================================================

    /** POST /api/chapters/add */
    public function addChapter(Request $request)
    {
        $request->validate(['courseID' => 'required|integer', 'name' => 'required|string']);
        $uid = $this->userId($request);
        $ch  = CourseChapter::create([
            'course_id'   => $request->courseID,
            'name'        => strip_tags(trim($request->name)),
            'description' => $request->description ?? '',
            'status'      => 1,
            'added_by'    => $uid,
            'added_on'    => now(),
            'updated_by'  => 0,
        ]);
        $this->logCourse($request->courseID, 'NEW_CHAPTER_ADDED_' . $ch->id, $uid, $request);
        return $this->out(['chapterID' => $ch->id], 1, 'Chapter added.');
    }

    /** POST /api/chapters/update */
    public function updateChapter(Request $request)
    {
        $request->validate(['chapterID' => 'required|integer', 'courseID' => 'required|integer']);
        CourseChapter::where('id', $request->chapterID)->update([
            'name'        => strip_tags(trim($request->name)),
            'description' => $request->description ?? '',
            'updated_by'  => $this->userId($request),
            'updated_on'  => now(),
        ]);
        $this->logCourse($request->courseID, 'CHAPTER_UPDATE_' . $request->chapterID, $this->userId($request), $request);
        return $this->out(null, 1, 'Chapter updated.');
    }

    /** POST /api/chapters/disable */
    public function disableChapter(Request $request)
    {
        $request->validate(['chapterID' => 'required|integer', 'courseID' => 'required|integer']);
        CourseChapter::where('id', $request->chapterID)->update(['status' => 0, 'updated_by' => $this->userId($request), 'updated_on' => now()]);
        $this->logCourse($request->courseID, 'CHAPTER_DISABLED_' . $request->chapterID, $this->userId($request), $request);
        return $this->out(null, 1, 'Chapter disabled.');
    }

    // =========================================================================
    // TOPICS
    // =========================================================================

    /** POST /api/topics/add-video */
    public function newVideoTopic(Request $request)
    {
        return $this->createTopic($request, CourseTopic::TYPE_VIDEO, 'VIDEO');
    }

    /** POST /api/topics/add-pdf */
    public function newPDFTopic(Request $request)
    {
        return $this->createTopic($request, CourseTopic::TYPE_PDF, 'PDF');
    }

    /** POST /api/topics/add-resource */
    public function newResourceTopic(Request $request)
    {
        return $this->createTopic($request, CourseTopic::TYPE_RESOURCE, 'RESLINK');
    }

    /** POST /api/topics/add-test */
    public function newTestTopic(Request $request)
    {
        return $this->createTopic($request, CourseTopic::TYPE_TEST, 'TEST');
    }

    private function createTopic(Request $request, int $type, string $logLabel)
    {
        $request->validate(['courseID' => 'required|integer', 'chapterID' => 'required|integer', 'name' => 'required|string']);
        $uid   = $this->userId($request);
        $topic = CourseTopic::create([
            'course_id'          => $request->courseID,
            'chapter_id'         => $request->chapterID,
            'name'               => strip_tags(trim($request->name)),
            'type'               => $type,
            'description'        => $request->description ?? '',
            'information'        => $request->information ?? '',
            'passing_percentage' => $request->passing_percentage ?? 75,
            'number_of_attempt'  => $request->number_of_attempt ?? 0,
            'duration'           => $request->duration ?? 0,
            'file_url'           => $request->file_url ?? '',
            'video_type'         => $request->video_type ?? 1,
            'status'             => 1,
            'added_by'           => $uid,
            'added_on'           => now(),
            'updated_by'         => 0,
        ]);
        $this->logCourse($request->courseID, "TOPIC_ADDED_CHAP_{$request->chapterID}_TOPIC_{$logLabel}_{$topic->id}", $uid, $request);
        return $this->out(['topicID' => $topic->id], 1, 'Topic added.');
    }

    /** POST /api/topics/remove */
    public function removeTopic(Request $request)
    {
        $request->validate(['topicID' => 'required|integer', 'chapterID' => 'required|integer', 'courseID' => 'required|integer']);
        CourseTopic::where('id', $request->topicID)->update(['status' => 0, 'updated_by' => $this->userId($request), 'updated_on' => now()]);
        $this->logCourse($request->courseID, "TOPIC_REMOVED_CHAP_{$request->chapterID}_TOPIC_{$request->topicID}", $this->userId($request), $request);
        return $this->out(null, 1, 'Topic removed.');
    }

    // =========================================================================
    // LEARNERS
    // =========================================================================

    /** POST /api/courses/enroll-self */
    public function enrollSelf(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        $uid = $this->userId($request);

        $exists = CourseLearner::where('course_id', $request->courseID)->where('learner_id', $uid)->exists();
        if ($exists) {
            return $this->out(null, 2, 'Already enrolled.');
        }
        CourseLearner::create([
            'course_id'  => $request->courseID,
            'learner_id' => $uid,
            'status'     => 1,
            'added_by'   => $uid,
            'added_on'   => now(),
            'started_on' => now(),
            'completed'  => 0,
        ]);
        return $this->out(null, 1, 'Enrolled successfully.');
    }

    /** POST /api/courses/assign-learners */
    public function assignLearnersCourse(Request $request)
    {
        $request->validate(['courseID' => 'required|integer', 'learners' => 'required|array']);
        $uid = $this->userId($request);

        foreach ($request->learners as $learnerId) {
            if (!CourseLearner::where('course_id', $request->courseID)->where('learner_id', $learnerId)->exists()) {
                CourseLearner::create([
                    'course_id'  => $request->courseID,
                    'learner_id' => $learnerId,
                    'status'     => 1,
                    'added_by'   => $uid,
                    'added_on'   => now(),
                    'completed'  => 0,
                ]);
                $this->logCourse($request->courseID, 'LEARNER_ADDED_' . $learnerId, $uid, $request);
            }
        }
        return $this->out(null, 1, 'Learners assigned.');
    }

    /** POST /api/courses/mark-complete */
    public function markAsComplete(Request $request)
    {
        $request->validate(['courseID' => 'required|integer', 'topicID' => 'required|integer']);
        $uid = $this->userId($request);

        CourseLearnerTopicStatus::updateOrCreate(
            ['course_id' => $request->courseID, 'topic_id' => $request->topicID, 'user_id' => $uid],
            ['completed' => 1, 'completed_on' => now(), 'user_marked' => 1]
        );

        // Check if all topics in the course are done
        $totalTopics    = CourseTopic::where('course_id', $request->courseID)->where('status', 1)->count();
        $completedTopics = CourseLearnerTopicStatus::where('course_id', $request->courseID)
            ->where('user_id', $uid)->where('completed', 1)->count();

        if ($totalTopics > 0 && $completedTopics >= $totalTopics) {
            CourseLearner::where('course_id', $request->courseID)
                ->where('learner_id', $uid)
                ->update(['completed' => 1, 'completed_on' => now()]);
        }

        return $this->out(['allDone' => $completedTopics >= $totalTopics], 1, 'Marked complete.');
    }

    /** POST /api/courses/update-topic-time */
    public function updateTopicTime(Request $request)
    {
        // Accept both 'time' (React frontend) and 'timeSpent' (legacy) — merge into 'timeSpent'
        if ($request->filled('time') && !$request->filled('timeSpent')) {
            $request->merge(['timeSpent' => $request->input('time')]);
        }

        $request->validate(['courseID' => 'required|integer', 'topicID' => 'required|integer', 'timeSpent' => 'required|integer']);
        $uid = $this->userId($request);

        CourseLearnerTopicStatus::updateOrCreate(
            ['course_id' => $request->courseID, 'topic_id' => $request->topicID, 'user_id' => $uid],
            ['time_spent' => DB::raw('time_spent + ' . (int)$request->timeSpent), 'started_on' => now()]
        );
        return $this->out(null, 1, 'Time updated.');
    }

    // =========================================================================
    // TEST SUBMIT (in-course test)
    // =========================================================================

    /** POST /api/courses/test-submit */
    public function testSubmit(Request $request)
    {
        $request->validate([
            'courseID'  => 'required|integer',
            'chapterID' => 'required|integer',
            'topicID'   => 'required|integer',
            'answers'   => 'required|array',
        ]);
        $uid = $this->userId($request);

        $questions = TopicQuestion::where('topic_id', $request->topicID)->with('options')->get();
        $points = 0; $total = 0; $correct = 0;
        $listItems = [];

        foreach ($questions as $q) {
            $total += $q->point;
            $selected = $request->answers[$q->id] ?? null;
            $isCorrect = false;

            if ($selected) {
                $option = TopicQuestionOption::find($selected);
                if ($option && $option->answer == 1) {
                    $isCorrect = true;
                    $points   += $q->point;
                    $correct++;
                }
                $listItems[] = ['question_id' => $q->id, 'option_id' => $selected, 'result' => $isCorrect ? 1 : 0];
            }
        }

        $percentage = $total > 0 ? round(($points / $total) * 100, 2) : 0;

        $answer = DB::table('topic_question_answers')->insertGetId([
            'course_id'       => $request->courseID,
            'chapter_id'      => $request->chapterID,
            'topic_id'        => $request->topicID,
            'points'          => $points,
            'total_points'    => $total,
            'correct_answers' => $correct,
            'total_questions' => count($questions),
            'percentage'      => $percentage,
            'answered_by'     => $uid,
            'answered_on'     => now(),
        ]);

        foreach ($listItems as $item) {
            DB::table('topic_question_answers_list')->insert(array_merge($item, ['answer_id' => $answer]));
        }

        $topic = CourseTopic::find($request->topicID);
        $passed = $percentage >= ($topic->passing_percentage ?? 75);

        // If passed, mark topic as complete and update leaderboard
        if ($passed) {
            CourseLearnerTopicStatus::updateOrCreate(
                ['course_id' => $request->courseID, 'topic_id' => $request->topicID, 'user_id' => $uid],
                ['completed' => 1, 'completed_on' => now()]
            );
            $this->updateLeaderboard($uid, $request->courseID);
        }

        return $this->out([
            'points'      => $points,
            'total'       => $total,
            'correct'     => $correct,
            'total_q'     => count($questions),
            'percentage'  => $percentage,
            'passed'      => $passed,
            'answerID'    => $answer,
        ], 1, $passed ? 'Test passed!' : 'Test failed.');
    }

    // =========================================================================
    // FEEDBACK
    // =========================================================================

    /** POST /api/courses/add-feedback */
    public function addFeedback(Request $request)
    {
        $request->validate(['courseID' => 'required|integer', 'star' => 'required|numeric']);
        $uid = $this->userId($request);

        CourseFeedbackRating::create([
            'course_id'  => $request->courseID,
            'user_id'    => $uid,
            'star'       => $request->star,
            'comment'    => $request->comment ?? '',
            'added_on'   => now(),
            'status'     => 1,
            'updated_by' => 0,
        ]);
        return $this->out(null, 1, 'Feedback added.');
    }

    /** POST /api/courses/add-feedback-reply */
    public function addFeedbackReply(Request $request)
    {
        $request->validate(['feedbackID' => 'required|integer', 'reply' => 'required|string']);
        CourseFeedbackComment::create([
            'feedback_id' => $request->feedbackID,
            'reply'       => $request->reply,
            'added_by'    => $this->userId($request),
            'added_on'    => now(),
            'status'      => 1,
        ]);
        return $this->out(null, 1, 'Reply added.');
    }

    /** GET /api/courses/feedback?courseID= */
    public function getAllFeedback(Request $request)
    {
        $rows = CourseFeedbackRating::with('comments')
            ->where('course_id', $request->courseID)
            ->where('status', 1)
            ->orderByDesc('added_on')
            ->get();
        return $this->out($rows, 1, 'OK');
    }

    // =========================================================================
    // FEATURED
    // =========================================================================

    /** POST /api/courses/add-featured */
    public function addToFeatured(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        $uid = $this->userId($request);
        Course::where('id', $request->courseID)->update([
            'featured'    => 1,
            'featured_by' => $uid,
            'featured_on' => now(),
        ]);
        $this->logFeature($request->courseID, 'ADD_COURSE_TO_FEATURE', $uid, $request);
        return $this->out(null, 1, 'Added to featured.');
    }

    /** POST /api/courses/remove-featured */
    public function removeFromFeatured(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        $uid = $this->userId($request);
        Course::where('id', $request->courseID)->update(['featured' => 0]);
        $this->logFeature($request->courseID, 'REMOVED_COURSE_FROM_FEATURE', $uid, $request);
        return $this->out(null, 1, 'Removed from featured.');
    }

    // =========================================================================
    // MY COURSES
    // =========================================================================

    /** GET /api/courses/my-courses */
    public function getMyCourses(Request $request)
    {
        $uid = $this->userId($request);
        $learners = CourseLearner::with('course.category')
            ->where('learner_id', $uid)
            ->where('status', 1)
            ->get();

        $data = $learners->map(function ($l) {
            return array_merge($this->formatCourse($l->course), [
                'started_on'   => $l->started_on,
                'completed'    => $l->completed,
                'completed_on' => $l->completed_on,
            ]);
        });
        return $this->out($data, 1, 'OK');
    }

    /** GET /api/courses/my-courses-inprogress */
 public function getMyCoursesInprogress(Request $request)
{
    $uid = $this->userId($request);
$learners = CourseLearner::with('course')
    ->where('learner_id', $uid)
    ->where('status', 1)
    ->get()
    ->unique('course_id')
    ->values();

    $data = $learners->map(function ($l) {
        $totalTopics     = CourseTopic::where('course_id', $l->course_id)->where('status', 1)->count();
        $completedTopics = CourseLearnerTopicStatus::where('course_id', $l->course_id)
            ->where('user_id', $l->learner_id)->where('completed', 1)->count();
        $progress = $totalTopics > 0 ? round(($completedTopics / $totalTopics) * 100) : 0;

        return [
            'id'                  => $l->course_id,
            'enrollment_id'       => $l->id,
            'course_id'           => $l->course_id,
            'name'                => $l->course ? $l->course->name : '',
            'course_name'         => $l->course ? $l->course->name : '',
            'description'         => $l->course ? $l->course->description : '',
            'image_url'           => $l->course ? $l->course->image_url : '',
            'completed'           => $l->completed,
            'progress_percentage' => $l->completed ? 100 : $progress,
            'started_on'          => $l->started_on,
            'completed_on'        => $l->completed_on,
            'learner_id'          => $l->learner_id,
            'enrolled'            => true,
        ];
    });

    return $this->out($data, 1, 'OK');
}

    // =========================================================================
    // LEADERBOARD
    // =========================================================================

    /** GET /api/leaderboard */
    public function getLeaderboard(Request $request)
    {
        $board = Leaderboard::with('user')
            ->orderByDesc('points')
            ->limit(50)
            ->get()
            ->map(fn($l) => [
                'id'                => $l->id,
                'userID'            => $l->user_id,
                'emp_first_name'    => $l->user ? $l->user->emp_first_name : '',
                'emp_last_name'     => $l->user ? $l->user->emp_last_name : '',
                'emp_code'          => $l->user ? $l->user->emp_code : '',
                'emp_photo'         => $l->user ? $l->user->emp_photo : '',
                'emp_department'    => $l->user ? $l->user->emp_department : '',
                'name'              => $l->user ? trim($l->user->emp_first_name . ' ' . $l->user->emp_last_name) : '',
                'points'            => $l->points,
                'total_points'      => $l->points,
                'completed_courses' => \App\Models\CourseLearner::where('learner_id', $l->user_id)->where('completed', 1)->count(),
                'empClient'         => $l->emp_client,
            ]);
        return $this->out($board, 1, 'OK');
    }

    // =========================================================================
    // WISHLIST
    // =========================================================================

    /** POST /api/wishlist/toggle */
    public function toggleWishlist(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        $uid = $this->userId($request);
        $existing = Wishlist::where('user_id', $uid)->where('course_id', $request->courseID)->first();

        if ($existing) {
            $existing->delete();
            return $this->out(null, 1, 'Removed from wishlist.');
        }
        Wishlist::create(['user_id' => $uid, 'course_id' => $request->courseID, 'added_on' => now()]);
        return $this->out(null, 1, 'Added to wishlist.');
    }

    /** GET /api/wishlist */
    public function getWishlist(Request $request)
    {
        $uid  = $this->userId($request);
        $list = Wishlist::with('course.category')->where('user_id', $uid)->get();
        $data = $list->map(fn($w) => $this->formatCourse($w->course));
        return $this->out($data, 1, 'OK');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function formatCourse(Course $c): array
    {
        $typeMaster = \Illuminate\Support\Facades\DB::table('course_master')->where('id', $c->type)->first();
        return [
            'id'                 => $c->id,
            'name'               => $c->name,
            'description'        => $c->description,
            'whatWillYouLearn'   => $c->what_will_you_learn,
            'what_will_you_learn'=> $c->what_will_you_learn,
            'preRequisites'      => $c->pre_requisites,
            'pre_requisites'     => $c->pre_requisites,
            'trainerDetails'     => $c->trainer_details,
            'trainer_details'    => $c->trainer_details,
            'image_url'          => $c->image_url,
            'imageURL'           => $c->image_url,
            'category_id'        => $c->category_id,
            'categoryID'         => $c->category_id,
            'category_name'      => $c->category ? $c->category->name : '',
            'categoryName'       => $c->category ? $c->category->name : '',
            'type'               => $c->type,
            'type_name'          => $typeMaster ? $typeMaster->name : 'E-Learning',
            'points'             => $c->points,
            'visibility'         => $c->visibility,
            'status'             => $c->status,
            'featured'           => $c->featured,
            'addedBy'            => $c->added_by,
            'added_by'           => $c->added_by,
            'addedOn'            => $c->added_on,
            'added_on'           => $c->added_on,
        ];
    }

    private function formatChapter(CourseChapter $ch, int $uid): array
    {
        return [
            'id'          => $ch->id,
            'name'        => $ch->name,
            'description' => $ch->description,
            'status'      => $ch->status,
            'topics'      => $ch->topics->map(fn($t) => $this->formatTopic($t, $uid)),
        ];
    }

    private function formatTopic(CourseTopic $t, int $uid): array
    {
        $status = CourseLearnerTopicStatus::where('topic_id', $t->id)->where('user_id', $uid)->first();
        return [
            'id'                => $t->id,
            'name'              => $t->name,
            'topic_name'        => $t->name,          // frontend alias
            'type'              => $t->type,
            'topic_type'        => $t->type,           // frontend alias
            'course_id'         => $t->course_id,      // added 24-Apr-2026 for React frontend
            'chapter_id'        => $t->chapter_id,     // added 24-Apr-2026 for React frontend
            'description'       => $t->description,
            'information'       => $t->information,
            'duration'          => $t->duration,
            'file_url'          => $t->file_url,
            'fileURL'           => $t->file_url,       // frontend alias
            'video_type'        => $t->video_type,
            'videoType'         => $t->video_type,     // frontend alias
            'passingPercentage' => $t->passing_percentage,
            'passing_percentage'=> $t->passing_percentage,
            'numberOfAttempt'   => $t->number_of_attempt,
            'number_of_attempt' => $t->number_of_attempt,
            'status'            => $t->status,
            'completed'         => $status ? $status->completed : 0,
            'timeSpent'         => $status ? $status->time_spent : 0,
            'time_spent'        => $status ? $status->time_spent : 0,
        ];
    }

    private function updateLeaderboard(int $userId, int $courseId): void
    {
        $course = Course::find($courseId);
        if (!$course) return;
        $pts = $course->points ?? 1;

        $lb = Leaderboard::firstOrCreate(
            ['user_id' => $userId],
            ['points' => 0, 'emp_client' => 0, 'emp_client_department' => 0, 'added_on' => now()]
        );
        $lb->points     += $pts;
        $lb->updated_on  = now();
        $lb->save();

        DB::table('leaderboard_log')->insert([
            'user_id'   => $userId,
            'points'    => $pts,
            'course_id' => $courseId,
            'added_on'  => now(),
        ]);
    }

    private function logCourse(int $courseId, string $action, int $doneBy, Request $request): void
    {
        DB::table('logs_admin_course')->insert([
            'course_id'  => $courseId,
            'action'     => $action,
            'done_by'    => $doneBy,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent() ?? '',
            'done_on'    => now(),
        ]);
    }

    private function logFeature(int $courseId, string $action, int $doneBy, Request $request): void
    {
        DB::table('logs_admin_feature')->insert([
            'course_id'  => $courseId,
            'action'     => $action,
            'done_by'    => $doneBy,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent() ?? '',
            'done_on'    => now(),
        ]);
    }
public function getDepartmentLeaderboard(Request $request)
{
    $data = \App\Models\User::select(
            'emp_department',
            \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT lb.user_id) as total_learners'),
            \Illuminate\Support\Facades\DB::raw('SUM(lb.points) as total_points'),
            \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT CASE WHEN cl.completed = 1 THEN cl.learner_id END) as completions')
        )
        ->join('leaderboard as lb', 'lb.user_id', '=', 'users.id')
        ->leftJoin('course_learners as cl', 'cl.learner_id', '=', 'users.id')
        ->whereNotNull('emp_department')
        ->where('emp_department', '!=', '')
        ->groupBy('emp_department')
        ->orderByDesc('total_points')
        ->limit(20)
        ->get();

    try {
        $serverName = env('ECR_SQLSRV_HOST', 'tcp:172.16.1.30,1433');
        $config = [
            'Database'               => env('ECR_SQLSRV_DB', 'ECR_New'),
            'Uid'                    => env('ECR_SQLSRV_USER', 'nbg_sa'),
            'PWD'                    => env('ECR_SQLSRV_PASS', ''),
            'TrustServerCertificate' => true,
            'LoginTimeout'           => 5,
        ];
        $conn = @sqlsrv_connect($serverName, $config);
        if ($conn) {
            $sql  = 'SELECT ID, DeptName FROM [ECR_New].[dbo].[Department]';
            $stmt = sqlsrv_query($conn, $sql);
            $deptMap = [];
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $deptMap[(string)$row['ID']] = $row['DeptName'];
                }
            }
            sqlsrv_close($conn);
            $data = $data->map(function ($d) use ($deptMap) {
                $d->dept_name = $deptMap[(string)$d->emp_department] ?? 'Dept ' . $d->emp_department;
                return $d;
            });
        }
    } catch (\Throwable $e) {
        // ECR unreachable — fallback to Dept ID
    }

    return $this->out($data, 1, 'OK');
}

    // =========================================================================
    // CATEGORIES (missing methods)
    // =========================================================================

    /** POST /api/Webservice/disableCategory */
    public function disableCategory(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        CategoryMaster::where('id', $request->id)->update([
            'status'     => 0,
            'updated_by' => $this->userId($request),
            'updated_on' => now(),
        ]);
        return $this->out(null, 1, 'Category disabled.');
    }

    /** POST /api/Webservice/enableCategory */
    public function enableCategory(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        CategoryMaster::where('id', $request->id)->update([
            'status'     => 1,
            'updated_by' => $this->userId($request),
            'updated_on' => now(),
        ]);
        return $this->out(null, 1, 'Category enabled.');
    }

    /** GET /api/Webservice/getCategoryDetails?id= */
    public function getCategoryDetails(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $cat = CategoryMaster::findOrFail($request->id);
        return $this->out($cat, 1, 'OK');
    }

    // =========================================================================
    // COURSES (missing methods)
    // =========================================================================

    /** POST /api/Webservice/visibilityStatus */
    public function visibilityStatus(Request $request)
    {
        $request->validate(['courseID' => 'required|integer', 'visibility' => 'required|integer']);
        Course::where('id', $request->courseID)->update([
            'visibility' => $request->visibility,
            'updated_by' => $this->userId($request),
            'updated_on' => now(),
        ]);
        $this->logCourse($request->courseID, 'VISIBILITY_CHANGED_' . $request->visibility, $this->userId($request), $request);
        return $this->out(null, 1, 'Visibility updated.');
    }

    /** GET /api/Webservice/getCourseType */
    public function getCourseTypes(Request $request)
    {
        $types = DB::table('course_master')->where('status', 1)->orderBy('name')->get();
        return $this->out($types, 1, 'OK');
    }

    /** GET /api/Webservice/getFeaturedCourseList */
    public function getFeaturedList(Request $request)
    {
        $uid     = $this->userId($request);
        $courses = Course::with('category')
            ->where('status', 2)
            ->where('featured', 1)
            ->orderBy('name')
            ->get()
            ->map(function ($c) use ($uid) {
                $data             = $this->formatCourse($c);
                $data['enrolled'] = CourseLearner::where('course_id', $c->id)->where('learner_id', $uid)->exists();
                return $data;
            });
        return $this->out($courses, 1, 'OK');
    }

    /** GET /api/Webservice/getFeaturedListMng */
    public function getFeaturedListAdmin(Request $request)
    {
        $courses = Course::with('category')
            ->where('status', 2)
            ->where('featured', 1)
            ->orderByDesc('featured_on')
            ->get()
            ->map(fn($c) => $this->formatCourse($c));
        return $this->out($courses, 1, 'OK');
    }

    /** GET /api/Webservice/getUnfeaturedList */
    public function getUnfeaturedList(Request $request)
    {
        $courses = Course::with('category')
            ->where('status', 2)
            ->where('featured', 0)
            ->orderBy('name')
            ->get()
            ->map(fn($c) => $this->formatCourse($c));
        return $this->out($courses, 1, 'OK');
    }

    /** GET /api/Webservice/getCourseDetailsWS  (mobile) */
    public function getCourseDetailsMobile(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        $courseId = $request->input('courseID');
        $uid      = $this->userId($request);
        $course   = Course::with(['category', 'chapters.topics'])->findOrFail($courseId);

        $data             = $this->formatCourse($course);
        $data['chapters'] = $course->chapters
            ->where('status', 1)
            ->values()
            ->map(fn($ch) => $this->formatChapter($ch, $uid));
        $learner              = CourseLearner::where('course_id', $courseId)->where('learner_id', $uid)->first();
        $data['enrolled']     = (bool) $learner;
        $data['completed']    = $learner ? $learner->completed : 0;
        $data['wishlisted']   = Wishlist::where('course_id', $courseId)->where('user_id', $uid)->exists();
        return $this->out($data, 1, 'OK');
    }

    /** GET /api/Webservice/getLearnersBoxList?courseID= */
    public function getLearnersBoxList(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);
        $learners = CourseLearner::where('course_id', $request->courseID)
            ->with('learner')
            ->get()
            ->map(fn($l) => [
                'learnerID'   => $l->learner_id,
                'name'        => $l->learner ? trim($l->learner->emp_first_name . ' ' . $l->learner->emp_last_name) : '',
                'empCode'     => $l->learner ? $l->learner->emp_code : '',
                'photo'       => $l->learner ? $l->learner->emp_photo : '',
                'completed'   => $l->completed,
                'completedOn' => $l->completed_on,
                'startedOn'   => $l->started_on,
            ]);
        return $this->out($learners, 1, 'OK');
    }

    /** GET /api/Webservice/assignLearnersModalList?courseID= */
    public function assignLearnersModalList(Request $request)
    {
        $courseId    = $request->input('courseID');
        $enrolledIds = CourseLearner::where('course_id', $courseId)->pluck('learner_id');

        $users = User::where('emp_status', 'A')
            ->where('emp_active', 'A')
            ->whereNotIn('id', $enrolledIds)
            ->select('id', 'emp_first_name', 'emp_last_name', 'emp_code', 'emp_email', 'emp_department', 'emp_photo')
            ->orderBy('emp_first_name')
            ->get();
        return $this->out($users, 1, 'OK');
    }

    // =========================================================================
    // CHAPTERS (missing methods)
    // =========================================================================

    /** POST /api/Webservice/enableChapter */
    public function enableChapter(Request $request)
    {
        $request->validate(['chapterID' => 'required|integer', 'courseID' => 'required|integer']);
        CourseChapter::where('id', $request->chapterID)->update([
            'status'     => 1,
            'updated_by' => $this->userId($request),
            'updated_on' => now(),
        ]);
        $this->logCourse($request->courseID, 'CHAPTER_ENABLED_' . $request->chapterID, $this->userId($request), $request);
        return $this->out(null, 1, 'Chapter enabled.');
    }

    /** GET /api/Webservice/getCourseChapterDetails?courseID=&chapterID= */
    public function getCourseChapterDetails(Request $request)
    {
        $uid = $this->userId($request);

        // If courseID provided → return ALL chapters for that course
        if ($request->filled('courseID')) {
            $chapters = CourseChapter::with('topics')
                ->where('course_id', $request->courseID)
                ->where('status', 1)
                ->orderBy('id')
                ->get()
                ->map(fn($ch) => $this->formatChapter($ch, $uid));
            return $this->out($chapters, 1, 'OK');
        }

        // If chapterID provided → return single chapter
        $request->validate(['chapterID' => 'required|integer']);
        $chapter = CourseChapter::with('topics')->findOrFail($request->chapterID);
        $data    = $this->formatChapter($chapter, $uid);
        return $this->out($data, 1, 'OK');
    }

    /** GET /api/Webservice/getCourseChapterDetailsAdmin?courseID=&chapterID= */
    public function getCourseChapterDetailsAdmin(Request $request)
    {
        $request->validate(['chapterID' => 'required|integer']);
        $chapter = CourseChapter::with(['topics' => fn($q) => $q->orderBy('id')])->findOrFail($request->chapterID);
        $topics  = $chapter->topics->map(fn($t) => [
            'id'                => $t->id,
            'name'              => $t->name,
            'type'              => $t->type,
            'description'       => $t->description,
            'duration'          => $t->duration,
            'fileURL'           => $t->file_url,
            'videoType'         => $t->video_type,
            'passingPercentage' => $t->passing_percentage,
            'numberOfAttempt'   => $t->number_of_attempt,
            'status'            => $t->status,
        ]);
        return $this->out(['chapter' => $chapter, 'topics' => $topics], 1, 'OK');
    }

    /** GET /api/Webservice/courseChapterDetailAdmin?chapterID= */
    public function chapterDetailAdmin(Request $request)
    {
        return $this->getCourseChapterDetailsAdmin($request);
    }

    // =========================================================================
    // TOPICS (missing methods)
    // =========================================================================

    /** GET /api/Webservice/courseTopicDetailAdmin?topicID= */
    public function topicDetailAdmin(Request $request)
    {
        $request->validate(['topicID' => 'required|integer']);
        $topic = CourseTopic::findOrFail($request->topicID);
        $questions = TopicQuestion::with('options')->where('topic_id', $topic->id)->orderBy('question_order')->get();
        $resourceLinks = DB::table('topic_resource_links')->where('topic_id', $topic->id)->where('status', 1)->get();
        return $this->out([
            'topic'         => $topic,
            'questions'     => $questions,
            'resourceLinks' => $resourceLinks,
        ], 1, 'OK');
    }

    /** GET /api/Webservice/courseTopicDetailWS?topicID= (mobile) */
    public function topicDetailMobile(Request $request)
    {
        $request->validate(['topicID' => 'required|integer']);
        $uid   = $this->userId($request);
        $topic = CourseTopic::findOrFail($request->topicID);
        $data  = $this->formatTopic($topic, $uid);

        // Include resource links for resource-type topics
        if ($topic->type == CourseTopic::TYPE_RESOURCE) {
            $data['resourceLinks'] = DB::table('topic_resource_links')
                ->where('topic_id', $topic->id)->where('status', 1)->get();
        }
        return $this->out($data, 1, 'OK');
    }

    /** POST /api/Webservice/updateVideoTopic */
    public function updateVideoTopic(Request $request)
    {
        return $this->updateTopic($request, 'VIDEO');
    }

    /** POST /api/Webservice/updatePDFTopic */
    public function updatePDFTopic(Request $request)
    {
        return $this->updateTopic($request, 'PDF');
    }

    /** POST /api/Webservice/updateResourceTopic */
    public function updateResourceTopic(Request $request)
    {
        return $this->updateTopic($request, 'RESLINK');
    }

    /** POST /api/Webservice/updateTestTopic */
    public function updateTestTopic(Request $request)
    {
        return $this->updateTopic($request, 'TEST');
    }

    private function updateTopic(Request $request, string $logLabel): \Illuminate\Http\JsonResponse
    {
        $request->validate(['topicID' => 'required|integer', 'courseID' => 'required|integer']);
        $uid = $this->userId($request);
        CourseTopic::where('id', $request->topicID)->update(array_filter([
            'name'               => $request->name ? strip_tags(trim($request->name)) : null,
            'description'        => $request->description,
            'information'        => $request->information,
            'passing_percentage' => $request->passing_percentage,
            'number_of_attempt'  => $request->number_of_attempt,
            'duration'           => $request->duration,
            'file_url'           => $request->file_url,
            'video_type'         => $request->video_type,
            'updated_by'         => $uid,
            'updated_on'         => now(),
        ], fn($v) => !is_null($v)));
        $this->logCourse($request->courseID, "TOPIC_UPDATED_{$logLabel}_{$request->topicID}", $uid, $request);
        return $this->out(null, 1, 'Topic updated.');
    }

    // =========================================================================
    // RESOURCE LINKS
    // =========================================================================

    /** POST /api/Webservice/addResourceLinks */
    public function addResourceLinks(Request $request)
    {
        $request->validate([
            'courseID'  => 'required|integer',
            'chapterID' => 'required|integer',
            'topicID'   => 'required|integer',
            'links'     => 'required|array',
        ]);
        $uid  = $this->userId($request);
        $rows = [];
        foreach ($request->links as $link) {
            $rows[] = DB::table('topic_resource_links')->insertGetId([
                'course_id'   => $request->courseID,
                'chapter_id'  => $request->chapterID,
                'topic_id'    => $request->topicID,
                'name'        => $link['name'] ?? '',
                'description' => $link['description'] ?? '',
                'link'        => $link['link'] ?? '',
                'status'      => 1,
                'added_by'    => $uid,
                'added_on'    => now(),
            ]);
        }
        return $this->out(['ids' => $rows], 1, 'Resource links added.');
    }

    /** POST /api/Webservice/removeResourseLink */
    public function removeResourceLink(Request $request)
    {
        $request->validate(['linkID' => 'required|integer']);
        DB::table('topic_resource_links')->where('id', $request->linkID)->update([
            'status'     => 0,
            'updated_on' => now(),
        ]);
        return $this->out(null, 1, 'Resource link removed.');
    }

    /** GET /api/Webservice/getCourseChapterResourceList?topicID= */
    public function getResourceList(Request $request)
    {
        $links = DB::table('topic_resource_links')
            ->where('topic_id', $request->topicID)
            ->where('status', 1)
            ->get();
        return $this->out($links, 1, 'OK');
    }

    /** GET /api/Webservice/getCourseChapterResourceDetail?linkID= */
    public function getResourceDetail(Request $request)
    {
        $link = DB::table('topic_resource_links')->where('id', $request->linkID)->first();
        return $this->out($link, 1, 'OK');
    }

    // =========================================================================
    // TEST QUESTIONS (in-course test)
    // =========================================================================

    /** GET /api/Webservice/getQuestionsListWS?topicID= */
    public function getTestQuestions(Request $request)
    {
        $questions = TopicQuestion::with('options')
            ->where('topic_id', $request->topicID)
            ->orderBy('question_order')
            ->get();
        return $this->out($questions, 1, 'OK');
    }

    /** GET /api/Webservice/getTestQuestion?questionID= */
    public function getTestQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        $q = TopicQuestion::with('options')->findOrFail($request->questionID);
        return $this->out($q, 1, 'OK');
    }

    /** GET /api/Webservice/getTestDetail?topicID= */
    public function getTestDetail(Request $request)
    {
        $request->validate(['topicID' => 'required|integer']);
        $topic     = CourseTopic::findOrFail($request->topicID);
        $questions = TopicQuestion::with('options')
            ->where('topic_id', $request->topicID)
            ->orderBy('question_order')
            ->get();
        return $this->out(['topic' => $topic, 'questions' => $questions], 1, 'OK');
    }

    /** POST /api/Webservice/addTestQuestion */
    public function addTestQuestion(Request $request)
    {
        $request->validate([
            'courseID'      => 'required|integer',
            'chapterID'     => 'required|integer',
            'topicID'       => 'required|integer',
            'question_text' => 'required|string',
        ]);
        $uid = $this->userId($request);
        $q   = TopicQuestion::create([
            'course_id'      => $request->courseID,
            'chapter_id'     => $request->chapterID,
            'topic_id'       => $request->topicID,
            'question_order' => $request->question_order ?? 0,
            'question_text'  => $request->question_text,
            'point'          => $request->point ?? 1,
            'question_type'  => $request->question_type ?? 0,
            'added_by'       => $uid,
            'added_on'       => now(),
            'updated_by'     => 0,
        ]);
        $this->logCourse($request->courseID, 'TEST_QUESTION_ADDED_' . $q->id, $uid, $request);
        return $this->out(['questionID' => $q->id], 1, 'Question added.');
    }

    /** POST /api/Webservice/addTestQuestionOptions */
    public function addTestQuestionOptions(Request $request)
    {
        $request->validate(['questionID' => 'required|integer', 'options' => 'required|array']);
        foreach ($request->options as $opt) {
            TopicQuestionOption::create([
                'question_id' => $request->questionID,
                'option_text' => $opt['option_text'],
                'answer'      => $opt['answer'] ?? 0,
                'value'       => $opt['value'] ?? 0,
            ]);
        }
        return $this->out(null, 1, 'Options added.');
    }

    /** POST /api/Webservice/deleteQuestion */
    public function deleteTestQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        TopicQuestion::where('id', $request->questionID)->delete();
        TopicQuestionOption::where('question_id', $request->questionID)->delete();
        return $this->out(null, 1, 'Question deleted.');
    }

    /** POST /api/Webservice/deleteQuestionOption */
    public function deleteTestQuestionOption(Request $request)
    {
        $request->validate(['optionID' => 'required|integer']);
        TopicQuestionOption::where('id', $request->optionID)->delete();
        return $this->out(null, 1, 'Option deleted.');
    }

    /** POST /api/Webservice/sendTestQuestion  — lock/publish a question */
    public function sendTestQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        // Mark question as published/finalised (question_order update or status flag if needed)
        // Using question_order as a publish signal (set to 1+ if still 0)
        $q = TopicQuestion::findOrFail($request->questionID);
        if ($q->question_order == 0) {
            $max = TopicQuestion::where('topic_id', $q->topic_id)->max('question_order');
            $q->update(['question_order' => $max + 1]);
        }
        return $this->out(null, 1, 'Question published.');
    }

    /** POST /api/Webservice/testDetailUpdate */
    public function updateTestDetail(Request $request)
    {
        $request->validate(['topicID' => 'required|integer', 'courseID' => 'required|integer']);
        CourseTopic::where('id', $request->topicID)->update(array_filter([
            'name'               => $request->name ? strip_tags(trim($request->name)) : null,
            'description'        => $request->description,
            'passing_percentage' => $request->passing_percentage,
            'number_of_attempt'  => $request->number_of_attempt,
            'duration'           => $request->duration,
            'updated_by'         => $this->userId($request),
            'updated_on'         => now(),
        ], fn($v) => !is_null($v)));
        $this->logCourse($request->courseID, 'TEST_DETAIL_UPDATED_' . $request->topicID, $this->userId($request), $request);
        return $this->out(null, 1, 'Test updated.');
    }

    // =========================================================================
    // TEST RESULTS
    // =========================================================================

    /** GET /api/Webservice/getResultUserWS?courseID=&topicID= */
    public function getResultUser(Request $request)
    {
        $uid     = $this->userId($request);
        $answers = DB::table('topic_question_answers')
            ->where('course_id', $request->courseID)
            ->where('topic_id', $request->topicID)
            ->where('answered_by', $uid)
            ->orderByDesc('answered_on')
            ->first();
        return $this->out($answers, $answers ? 1 : 0, $answers ? 'OK' : 'No result found.');
    }

    /** GET /api/Webservice/getResultDetails?answerID= */
    public function getResultDetails(Request $request)
    {
        $answer = DB::table('topic_question_answers')->where('id', $request->answerID)->first();
        $items  = DB::table('topic_question_answers_list')
            ->where('answer_id', $request->answerID)
            ->get();
        return $this->out(['answer' => $answer, 'items' => $items], 1, 'OK');
    }

    /** GET /api/Webservice/getResultSummary?courseID=&topicID= */
    public function getResultSummary(Request $request)
    {
        $qid     = $request->topicID;
        $total   = DB::table('topic_question_answers')->where('topic_id', $qid)->count();
        $avgPct  = DB::table('topic_question_answers')->where('topic_id', $qid)->avg('percentage') ?? 0;
        $topic   = CourseTopic::find($qid);
        $passed  = DB::table('topic_question_answers')
            ->where('topic_id', $qid)
            ->where('percentage', '>=', $topic->passing_percentage ?? 75)
            ->count();
        return $this->out([
            'total'   => $total,
            'passed'  => $passed,
            'failed'  => $total - $passed,
            'avgPct'  => round($avgPct, 2),
        ], 1, 'OK');
    }

    // =========================================================================
    // FEEDBACK QUESTIONS
    // =========================================================================

    /** POST /api/Webservice/addFeedbackQuestion */
    public function addFeedbackQuestion(Request $request)
    {
        $request->validate(['courseID' => 'required|integer', 'question_text' => 'required|string']);
        $uid = $this->userId($request);
        $q   = CourseFeedbackQuestion::create([
            'course_id'     => $request->courseID,
            'question_text' => $request->question_text,
            'status'        => 1,
            'added_by'      => $uid,
            'added_on'      => now(),
            'updated_by'    => 0,
        ]);
        return $this->out(['questionID' => $q->id], 1, 'Feedback question added.');
    }

    /** GET /api/Webservice/getFeedbackQuestion?questionID= */
    public function getFeedbackQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        $q = CourseFeedbackQuestion::findOrFail($request->questionID);
        return $this->out($q, 1, 'OK');
    }

    /** GET /api/Webservice/getFeedbackQuestionsWS?courseID= */
    public function getFeedbackQuestionsWS(Request $request)
    {
        $questions = CourseFeedbackQuestion::where('course_id', $request->courseID)
            ->where('status', 1)
            ->orderBy('id')
            ->get();
        return $this->out($questions, 1, 'OK');
    }

    /** POST /api/Webservice/deleteFeedbackQuestion */
    public function deleteFeedbackQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        CourseFeedbackQuestion::where('id', $request->questionID)->update([
            'status'     => 0,
            'updated_by' => $this->userId($request),
            'updated_on' => now(),
        ]);
        return $this->out(null, 1, 'Feedback question deleted.');
    }

    /** POST /api/Webservice/deleteFeedBackWS */
    public function deleteFeedback(Request $request)
    {
        $request->validate(['feedbackID' => 'required|integer']);
        CourseFeedbackRating::where('id', $request->feedbackID)->update([
            'status'     => 0,
            'updated_by' => $this->userId($request),
        ]);
        return $this->out(null, 1, 'Feedback deleted.');
    }

    // =========================================================================
    // CHARTS / OVERVIEW
    // =========================================================================

    /** GET /api/Webservice/getOverViewCharts?courseID= */
    public function getOverviewCharts(Request $request)
    {
        $cid       = $request->courseID;
        $total     = CourseLearner::where('course_id', $cid)->count();
        $completed = CourseLearner::where('course_id', $cid)->where('completed', 1)->count();
        $inProgress= CourseLearner::where('course_id', $cid)->where('completed', 0)->whereNotNull('started_on')->count();
        $avgRating = CourseFeedbackRating::where('course_id', $cid)->where('status', 1)->avg('star') ?? 0;

        return $this->out([
            'total'      => $total,
            'completed'  => $completed,
            'inProgress' => $inProgress,
            'notStarted' => $total - $completed - $inProgress,
            'avgRating'  => round($avgRating, 2),
        ], 1, 'OK');
    }

    /** GET /api/Webservice/getOverPassViewCharts?courseID= */
    public function getPassRateCharts(Request $request)
    {
        $cid    = $request->courseID;
        $topics = CourseTopic::where('course_id', $cid)->where('type', CourseTopic::TYPE_TEST)->get();

        $chartData = $topics->map(function ($t) {
            $total  = DB::table('topic_question_answers')->where('topic_id', $t->id)->count();
            $passed = DB::table('topic_question_answers')
                ->where('topic_id', $t->id)
                ->where('percentage', '>=', $t->passing_percentage)
                ->count();
            return [
                'topicID'   => $t->id,
                'topicName' => $t->name,
                'total'     => $total,
                'passed'    => $passed,
                'failed'    => $total - $passed,
                'passRate'  => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            ];
        });
        return $this->out($chartData, 1, 'OK');
    }

    // =========================================================================
    // LEADERBOARD — dept (mobile alias)
    // =========================================================================

    /** GET /api/Webservice/getDepartmentLeaderBoardWS */
    public function getDeptLeaderboard(Request $request)
    {
        return $this->getDepartmentLeaderboard($request);
    }

    // =========================================================================
    // APP VERSION
    // =========================================================================

    /** GET /api/Webservice/getAppVersion?device= */
    public function getAppVersion(Request $request)
    {
        $version = AppVersion::where('device', $request->device ?? 1)->first();
        return $this->out($version, 1, 'OK');
    }

    /** POST /api/Webservice/addAppversion */
    public function addAppVersion(Request $request)
    {
        $request->validate(['device' => 'required|integer', 'app_version' => 'required|string']);
        AppVersion::updateOrCreate(
            ['device' => $request->device],
            ['app_version' => $request->app_version, 'updated_date' => now()]
        );
        return $this->out(null, 1, 'App version updated.');
    }

    /** POST /api/Webservice/addAppToken */
    public function addAppToken(Request $request)
    {
        $request->validate(['token' => 'required|string', 'device' => 'required|string']);
        $uid = $this->userId($request);
        PushNotificationToken::updateOrCreate(
            ['emp_id' => $uid],
            [
                'token'       => $request->token,
                'device_type' => $request->device,
                'updated_on'  => now(),
            ]
        );
        return $this->out(null, 1, 'Token registered.');
    }
}
