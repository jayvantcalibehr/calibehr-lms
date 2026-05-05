<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\QuizInviteMail;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAnswerItem;
use App\Models\QuizFeedbackRating;
use App\Models\QuizInformationQuestion;
use App\Models\QuizInvite;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    private function out($data, int $code, string $msg)
    {
        return response()->json(['data' => $data, 'code' => $code, 'message' => $msg]);
    }

    // =========================================================================
    // QUIZ MANAGEMENT (Admin/Quiz Master)
    // =========================================================================

    /** GET /api/quiz/list */
    public function getAllQuizList(Request $request)
    {
        $uid  = $request->user()->id;
        $user = $request->user();

        $quizzes = Quiz::where('status', '!=', 0)->orderByDesc('added_on')->get()
            ->map(function ($q) use ($uid, $user) {
                // Check if user has an invite & attempt
                $invite = QuizInvite::where('quiz_id', $q->id)
                    ->where('email', $user->emp_email ?: '')
                    ->orderByDesc('id')->first();

                $answer = $invite
                    ? DB::table('quiz_answers')->where('invite_id', $invite->id)->orderByDesc('id')->first()
                    : null;

                return [
                    'id'                 => $q->id,
                    'name'               => $q->name,
                    'title'              => $q->name,
                    'description'        => $q->description,
                    'type'               => $q->type,
                    'points'             => $q->points,
                    'passing_percentage' => $q->passing_percentage,
                    'pass_percentage'    => $q->passing_percentage,
                    'number_of_attempt'  => $q->number_of_attempt,
                    'visibility'         => $q->visibility,
                    'time'               => $q->time,
                    'time_limit'         => $q->time,
                    'show_marks'         => $q->show_marks,
                    'status'             => $q->status,
                    'invite_id'          => $invite ? $invite->id : null,
                    'attempted'          => (bool) $answer,
                    'percentage'         => $answer ? $answer->percentage : null,
                    'passed'             => $answer ? (bool) $answer->pass : null,
                    'added_on'           => $q->added_on,
                ];
            });

        return $this->out($quizzes, 1, 'OK');
    }

    /** POST /api/quiz/add */
    public function addQuiz(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $uid = $request->user()->id;
        $quiz = Quiz::create([
            'name' => strip_tags(trim($request->name)),
            'description' => $request->description ?? '',
            'type' => $request->type ?? 0,
            'points' => $request->points ?? 1,
            'passing_percentage' => $request->passing_percentage ?? 75,
            'number_of_attempt' => $request->number_of_attempt ?? 1,
            'added_by' => $uid,
            'added_on' => now(),
            'updated_by' => 0,
            'visibility' => $request->visibility ?? 0,
            'time' => $request->time ?? 0,
            'show_marks' => $request->show_marks ?? 1,
            'status' => 1,
        ]);
        DB::table('logs_admin_quiz')->insert(['quiz_id' => $quiz->id, 'action' => 'ADD_NEW_QUIZ', 'done_by' => $uid, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'done_on' => now()]);

        return $this->out(['quizID' => $quiz->id], 1, 'Quiz added.');
    }

    /** GET /api/quiz/{id}/details */
    public function getQuizDetails(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $quiz = Quiz::with(['questions.options', 'infoQuestions'])->findOrFail($request->quizID);

        return $this->out($quiz, 1, 'OK');
    }

    /** POST /api/quiz/update */
    public function updateQuizDetail(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $uid = $request->user()->id;
        Quiz::where('id', $request->quizID)->update(array_filter([
            'name' => $request->name ? strip_tags(trim($request->name)) : null,
            'description' => $request->description,
            'type' => $request->type,
            'points' => $request->points,
            'passing_percentage' => $request->passing_percentage,
            'number_of_attempt' => $request->number_of_attempt,
            'visibility' => $request->visibility,
            'time' => $request->time,
            'show_marks' => $request->show_marks,
            'updated_by' => $uid,
            'updated_on' => now(),
        ], fn ($v) => ! is_null($v)));
        DB::table('logs_admin_quiz')->insert(['quiz_id' => $request->quizID, 'action' => 'QUIZ_UPDATED', 'done_by' => $uid, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'done_on' => now()]);

        return $this->out(null, 1, 'Quiz updated.');
    }

    // =========================================================================
    // QUESTIONS
    // =========================================================================

    /** POST /api/quiz/add-question */
    public function addQuizQuestion(Request $request)
    {
        $request->validate(['quizID' => 'required|integer', 'question_text' => 'required|string']);
        $q = QuizQuestion::create([
            'quiz_id' => $request->quizID,
            'question_order' => $request->question_order ?? 0,
            'question_text' => $request->question_text,
            'point' => $request->point ?? 1,
            'question_type' => $request->question_type ?? 0,
            'added_by' => $request->user()->id,
            'added_on' => now(),
            'updated_by' => 0,
        ]);

        return $this->out(['questionID' => $q->id], 1, 'Question added.');
    }

    /** POST /api/quiz/add-question-options */
    public function addQuizQuestionOptions(Request $request)
    {
        $request->validate(['questionID' => 'required|integer', 'options' => 'required|array']);
        foreach ($request->options as $opt) {
            QuizQuestionOption::create([
                'question_id' => $request->questionID,
                'option_text' => $opt['option_text'],
                'answer' => $opt['answer'] ?? 0,
                'value' => $opt['value'] ?? 0,
            ]);
        }

        return $this->out(null, 1, 'Options added.');
    }

    /** DELETE /api/quiz/delete-question */
    public function deleteQuizQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        QuizQuestion::where('id', $request->questionID)->delete();
        QuizQuestionOption::where('question_id', $request->questionID)->delete();

        return $this->out(null, 1, 'Question deleted.');
    }

    // =========================================================================
    // INVITES
    // =========================================================================

    /** POST /api/quiz/send-invites */
    public function sendInviteToQuiz(Request $request)
    {
        $request->validate(['quizID' => 'required|integer', 'emails' => 'required|array']);
        $uid = $request->user()->id;
        $sent = 0;
        $quiz = Quiz::findOrFail($request->quizID);

        foreach ($request->emails as $email) {
            $invite = QuizInvite::create([
                'quiz_id' => $request->quizID,
                'email' => trim($email),
                'added_by' => $uid,
                'added_on' => now(),
                'sent_status' => 0,
                'status' => 0,
            ]);
            $invite = $invite->fresh();

            try {
                // Email sending (configure mailer in .env)
                \Mail::to($email)->send(new QuizInviteMail($invite, $quiz));
                $invite->update(['sent_status' => 1, 'sent_on' => now()]);
                $sent++;
            } catch (\Exception $e) {
                $invite->update(['sent_status' => 2]);
            }
        }
        DB::table('logs_admin_quiz')->insert(['quiz_id' => $request->quizID, 'action' => 'QUIZ_INVITES_SENT_', 'done_by' => $uid, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'done_on' => now()]);

        return $this->out(['sent' => $sent], 1, "Invites sent: $sent");
    }

    /** GET /api/quiz/invited-list?quizID= */
    public function getInvitedList(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $quizId = (int) $request->quizID;

        // Bulk-fetch in 2 queries (no N+1):
        // 1. All invites for this quiz
        // 2. All quiz_answers rows for those invites
        $invites = QuizInvite::where('quiz_id', $quizId)
            ->orderBy('id', 'desc')
            ->get();

        if ($invites->isEmpty()) {
            return $this->out([], 1, 'OK');
        }

        $inviteIds = $invites->pluck('id')->all();
        $answers = DB::table('quiz_answers')
            ->whereIn('invite_id', $inviteIds)
            ->select('invite_id', 'points', 'total_points', 'correct_answers',
                     'total_questions', 'percentage', 'pass', 'answered_on',
                     'time_up', 'switch_tabs')
            ->get()
            ->keyBy('invite_id');

        // Merge: each invite row gets its quiz_answers data attached
        $list = $invites->map(function ($inv) use ($answers) {
            $ans = $answers->get($inv->id);
            return [
                'id'            => $inv->id,
                'quiz_id'       => $inv->quiz_id,
                'email'         => $inv->email,
                'sent_status'   => (int) $inv->sent_status,
                'sent_on'       => $inv->sent_on,
                'status'        => (int) $inv->status,           // 0=pending, 1=completed
                'completed_on'  => $inv->completed_on,
                // Result fields — only present if quiz was actually submitted
                'has_result'    => $ans !== null,
                'points'        => $ans?->points,
                'total_points'  => $ans?->total_points,
                'correct'       => $ans?->correct_answers,
                'total_qs'      => $ans?->total_questions,
                'percentage'    => $ans !== null ? (float) $ans->percentage : null,
                'pass'          => $ans !== null ? (int) $ans->pass : null,
                'answered_on'   => $ans?->answered_on,
                'time_up'       => $ans !== null ? (int) $ans->time_up : null,
                'switch_tabs'   => $ans !== null ? (int) $ans->switch_tabs : null,
            ];
        });

        return $this->out($list, 1, 'OK');
    }

    /**
     * GET /api/quiz/submission/{inviteId}
     * Admin-only — returns full per-question breakdown for a specific candidate's submission.
     * Shows: each question, candidate's selected option, correct option, and result.
     */
    public function getQuizSubmissionDetail(Request $request, $inviteId)
    {
        $inviteId = (int) $inviteId;

        $invite = QuizInvite::find($inviteId);
        if (!$invite) {
            return $this->out(null, 0, 'Invite not found.');
        }

        // Get the quiz_answers row for this invite
        $answer = DB::table('quiz_answers')
            ->where('invite_id', $inviteId)
            ->first();

        if (!$answer) {
            return $this->out([
                'invite' => $invite,
                'status' => 'not_submitted',
            ], 1, 'Quiz not yet submitted by this candidate.');
        }

        // Bulk-fetch all answer items for this submission
        $items = DB::table('quiz_answer_items')
            ->where('answer_id', $answer->id)
            ->get()
            ->keyBy('question_id');

        // Get all questions + options for this quiz
        $quiz = Quiz::with(['questions.options'])->findOrFail($invite->quiz_id);

        $breakdown = $quiz->questions->map(function ($q) use ($items) {
            $item = $items->get($q->id);
            $selectedId = $item?->option_id;

            $correctOption = $q->options->firstWhere('answer', 1);
            $selectedOption = $selectedId ? $q->options->firstWhere('id', $selectedId) : null;

            return [
                'question_id'      => $q->id,
                'question'         => $q->question_text,
                'point'            => $q->point,
                'options'          => $q->options->map(fn($o) => [
                    'id'        => $o->id,
                    'option'    => $o->option_text,
                    'is_correct'=> (int) $o->answer === 1,
                ])->values(),
                'selected_option'  => $selectedOption ? [
                    'id'     => $selectedOption->id,
                    'option' => $selectedOption->option_text,
                ] : null,
                'correct_option'   => $correctOption ? [
                    'id'     => $correctOption->id,
                    'option' => $correctOption->option_text,
                ] : null,
                'result'           => $item ? (int) $item->result : null,    // 1=correct, 0=wrong
                'answered'         => $item !== null,
            ];
        })->values();

        return $this->out([
            'invite' => [
                'id'            => $invite->id,
                'email'         => $invite->email,
                'sent_on'       => $invite->sent_on,
                'completed_on'  => $invite->completed_on,
            ],
            'quiz' => [
                'id'                  => $quiz->id,
                'name'                => $quiz->name,
                'passing_percentage'  => (float) $quiz->passing_percentage,
                'show_marks'          => (int) $quiz->show_marks,
            ],
            'answer' => [
                'id'              => $answer->id,
                'points'          => (int) $answer->points,
                'total_points'    => (int) $answer->total_points,
                'correct'         => (int) $answer->correct_answers,
                'total_qs'        => (int) $answer->total_questions,
                'percentage'      => (float) $answer->percentage,
                'pass'            => (int) $answer->pass,
                'answered_on'     => $answer->answered_on,
                'time_up'         => (int) $answer->time_up,
                'switch_tabs'     => (int) $answer->switch_tabs,
                'ip'              => $answer->ip,
                'user_agent'      => $answer->user_agent,
            ],
            'breakdown' => $breakdown,
            'status'    => 'submitted',
        ], 1, 'OK');
    }

    // =========================================================================
    // QUIZ ATTEMPT (Learner)
    // =========================================================================

    /** GET /api/quiz/questions?inviteID= */
    public function getQuizQuestionsList(Request $request)
    {
        $uid = $request->user()->id;
        $user = $request->user();

        // Accept inviteID OR quizID
        if ($request->filled('quizID')) {
            // Find existing invite for this user+quiz, or create one
            $quizId = $request->quizID;
            $invite = QuizInvite::where('quiz_id', $quizId)
                ->where('email', $user->emp_email)
                ->first();

            if (!$invite) {
                // Auto-create invite for this user
                $invite = QuizInvite::create([
                    'quiz_id'     => $quizId,
                    'email'       => $user->emp_email ?: ($user->emp_code . '@calibehr.com'),
                    'added_by'    => $uid,
                    'added_on'    => now(),
                    'sent_status' => 1,
                    'status'      => 0,
                ]);
            }
        } else {
            $request->validate(['inviteID' => 'required|integer']);
            $invite = QuizInvite::findOrFail($request->inviteID);
        }

        $quiz = Quiz::with(['questions.options', 'infoQuestions'])->findOrFail($invite->quiz_id);

        // Check if already completed
        $alreadyAnswered = DB::table('quiz_answers')
            ->where('invite_id', $invite->id)
            ->orderByDesc('id')
            ->first();

        // Shuffle options for security, hide correct answer flag
        $questions = $quiz->questions->map(function ($q) {
            $opts = $q->options->map(fn ($o) => ['id' => $o->id, 'option_text' => $o->option_text]);
            return [
                'id'            => $q->id,
                'question_text' => $q->question_text,
                'question'      => $q->question_text,
                'point'         => $q->point,
                'question_type' => $q->question_type,
                'options'       => $opts->shuffle(),
            ];
        });

        return $this->out([
            'invite_id'      => $invite->id,
            'inviteID'       => $invite->id,
            'quiz'           => ['id' => $quiz->id, 'name' => $quiz->name, 'time' => $quiz->time, 'passing_percentage' => $quiz->passing_percentage],
            'questions'      => $questions,
            'infoQuestions'  => $quiz->infoQuestions,
            'already_done'   => $invite->status == 1,
            'previous_score' => $alreadyAnswered ? $alreadyAnswered->percentage : null,
        ], 1, 'OK');
    }

    /** POST /api/quiz/submit */
    public function quizSubmit(Request $request)
    {
        $request->validate([
            'inviteID' => 'required|integer',
            'answers' => 'required|array',
        ]);

        $invite = QuizInvite::findOrFail($request->inviteID);
        $quiz = Quiz::with('questions.options')->findOrFail($invite->quiz_id);
        $uid = $request->user()->id ?? 0;

        $points = 0;
        $total = 0;
        $correct = 0;
        $listItems = [];

        foreach ($quiz->questions as $q) {
            $total += $q->point;
            $selected = $request->answers[$q->id] ?? null;
            if ($selected) {
                $option = $q->options->firstWhere('id', $selected);
                $isCorrect = $option && $option->answer == 1;
                if ($isCorrect) {
                    $points += $q->point;
                    $correct++;
                }
                $listItems[] = ['question_id' => $q->id, 'option_id' => $selected, 'result' => $isCorrect ? 1 : 0];
            }
        }

        $percentage = $total > 0 ? round(($points / $total) * 100, 2) : 0;
        $passed = $percentage >= $quiz->passing_percentage;

        $answerId = DB::table('quiz_answers')->insertGetId([
            'quiz_id' => $quiz->id,
            'invite_id' => $invite->id,
            'points' => $points,
            'total_points' => $total,
            'correct_answers' => $correct,
            'total_questions' => $quiz->questions->count(),
            'percentage' => $percentage,
            'pass' => $passed ? 1 : 0,
            'answered_on' => now(),
            'time_up' => $request->time_up ?? 0,
            'switch_tabs' => $request->switch_tabs ?? 0,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent() ?? '',
            'added_on' => now(),
        ]);

        foreach ($listItems as $item) {
            DB::table('quiz_answer_items')->insert(array_merge($item, ['answer_id' => $answerId]));
        }

        $invite->update(['status' => 1, 'completed_on' => now()]);

        return $this->out([
            'answerID' => $answerId,
            'points' => $points,
            'total' => $total,
            'correct' => $correct,
            'percentage' => $percentage,
            'passed' => $passed,
            'show_marks' => $quiz->show_marks,
        ], 1, $passed ? 'Quiz passed!' : 'Quiz failed.');
    }

    /** POST /api/quiz/timeup */
    public function quizTimeup(Request $request)
    {
        $request->validate(['inviteID' => 'required|integer']);
        QuizAnswer::where('invite_id', $request->inviteID)->update(['time_up' => 1]);

        return $this->out(null, 1, 'Recorded.');
    }

    /** POST /api/quiz/switch-tab */
    public function switchTabTooManyTimes(Request $request)
    {
        $request->validate(['inviteID' => 'required|integer']);
        QuizAnswer::where('invite_id', $request->inviteID)->update(['switch_tabs' => 1]);

        return $this->out(null, 1, 'Recorded.');
    }

    // =========================================================================
    // FEEDBACK
    // =========================================================================

    /** POST /api/quiz/add-feedback */
    public function addFeedback(Request $request)
    {
        $request->validate(['quizID' => 'required|integer', 'star' => 'required|numeric']);
        QuizFeedbackRating::create([
            'quiz_id' => $request->quizID,
            'user_id' => $request->user()->id,
            'star' => $request->star,
            'comment' => $request->comment ?? '',
            'added_on' => now(),
            'status' => 1,
            'updated_by' => 0,
        ]);

        return $this->out(null, 1, 'Feedback added.');
    }

    /** GET /api/quiz/feedback?quizID= */
    public function getAllFeedback(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $rows = QuizFeedbackRating::with('comments')
            ->where('quiz_id', $request->quizID)
            ->where('status', 1)
            ->get();

        return $this->out($rows, 1, 'OK');
    }

    // =========================================================================
    // CHARTS / REPORTS
    // =========================================================================

    /** GET /api/quiz/overview-charts?quizID= */
    public function getOverviewCharts(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $qid = $request->quizID;
        $total = QuizInvite::where('quiz_id', $qid)->count();
        $completed = QuizInvite::where('quiz_id', $qid)->where('status', 1)->count();
        $passed = QuizAnswer::where('quiz_id', $qid)->where('pass', 1)->count();
        $failed = QuizAnswer::where('quiz_id', $qid)->where('pass', 0)->count();
        $avgPct = QuizAnswer::where('quiz_id', $qid)->avg('percentage') ?? 0;

        return $this->out(compact('total', 'completed', 'passed', 'failed', 'avgPct'), 1, 'OK');
    }

    // =========================================================================
    // QUIZ — missing methods
    // =========================================================================

    /** POST /api/Webservice/visibilityStatusQuiz */
    public function visibilityStatusQuiz(Request $request)
    {
        $request->validate(['quizID' => 'required|integer', 'visibility' => 'required|integer']);
        $uid = $request->user()->id;
        Quiz::where('id', $request->quizID)->update([
            'visibility' => $request->visibility,
            'updated_by' => $uid,
            'updated_on' => now(),
        ]);
        DB::table('logs_admin_quiz')->insert(['quiz_id' => $request->quizID, 'action' => 'VISIBILITY_CHANGED_' . $request->visibility, 'done_by' => $uid, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'done_on' => now()]);
        return $this->out(null, 1, 'Visibility updated.');
    }

    /** POST /api/Webservice/updateQuizFormSetting */
    public function updateQuizFormSetting(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $uid = $request->user()->id;
        Quiz::where('id', $request->quizID)->update(array_filter([
            'time'               => $request->time,
            'show_marks'         => $request->show_marks,
            'passing_percentage' => $request->passing_percentage,
            'number_of_attempt'  => $request->number_of_attempt,
            'updated_by'         => $uid,
            'updated_on'         => now(),
        ], fn($v) => !is_null($v)));
        return $this->out(null, 1, 'Form settings updated.');
    }

    /** GET /api/Webservice/getQuizQuestion?questionID= */
    public function getQuizQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        $q = QuizQuestion::with('options')->findOrFail($request->questionID);
        return $this->out($q, 1, 'OK');
    }

    /** GET /api/Webservice/getQuizQuestionDetail?questionID= */
    public function getQuizQuestionDetail(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        $q = QuizQuestion::with('options')->findOrFail($request->questionID);
        return $this->out($q, 1, 'OK');
    }

    /** POST /api/Webservice/deleteQuizQuestionOption */
    public function deleteQuizQuestionOption(Request $request)
    {
        $request->validate(['optionID' => 'required|integer']);
        QuizQuestionOption::where('id', $request->optionID)->delete();
        return $this->out(null, 1, 'Option deleted.');
    }

    /** POST /api/Webservice/sendQuizQuestion — publish/finalise a question */
    public function sendQuizQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        $q = QuizQuestion::findOrFail($request->questionID);
        if ($q->question_order == 0) {
            $max = QuizQuestion::where('quiz_id', $q->quiz_id)->max('question_order');
            $q->update(['question_order' => $max + 1, 'updated_by' => $request->user()->id, 'updated_on' => now()]);
        }
        return $this->out(null, 1, 'Question published.');
    }

    /** GET /api/Webservice/getQuizDetailsWS?quizID= (mobile) */
    public function getQuizDetailsMobile(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $quiz = Quiz::with(['questions.options', 'infoQuestions'])->findOrFail($request->quizID);
        $questions = $quiz->questions->map(function ($q) {
            $opts = $q->options->map(fn($o) => ['id' => $o->id, 'option_text' => $o->option_text]);
            return ['id' => $q->id, 'question_text' => $q->question_text, 'point' => $q->point, 'question_type' => $q->question_type, 'options' => $opts];
        });
        return $this->out([
            'quiz'          => ['id' => $quiz->id, 'name' => $quiz->name, 'description' => $quiz->description, 'time' => $quiz->time, 'passing_percentage' => $quiz->passing_percentage, 'show_marks' => $quiz->show_marks],
            'questions'     => $questions,
            'infoQuestions' => $quiz->infoQuestions,
        ], 1, 'OK');
    }

    /** GET /api/Webservice/getOverPassViewChartsQuiz?quizID= */
    public function getPassRateCharts(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $qid    = $request->quizID;
        $total  = QuizAnswer::where('quiz_id', $qid)->count();
        $passed = QuizAnswer::where('quiz_id', $qid)->where('pass', 1)->count();
        $avgPct = QuizAnswer::where('quiz_id', $qid)->avg('percentage') ?? 0;
        return $this->out([
            'total'    => $total,
            'passed'   => $passed,
            'failed'   => $total - $passed,
            'passRate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'avgPct'   => round($avgPct, 2),
        ], 1, 'OK');
    }

    // =========================================================================
    // INFORMATION QUESTIONS
    // =========================================================================

    /** POST /api/Webservice/addQuizInformationQuestion */
    public function addQuizInfoQuestion(Request $request)
    {
        $request->validate(['quizID' => 'required|integer', 'question_text' => 'required|string']);
        $uid = $request->user()->id;
        $q   = QuizInformationQuestion::create([
            'quiz_id'        => $request->quizID,
            'question_order' => $request->question_order ?? 0,
            'question_text'  => $request->question_text,
            'question_type'  => $request->question_type ?? 0,
            'added_by'       => $uid,
            'added_on'       => now(),
            'updated_by'     => 0,
        ]);
        return $this->out(['questionID' => $q->id], 1, 'Info question added.');
    }

    /** POST /api/Webservice/deleteQuizInformationQuestion */
    public function deleteQuizInfoQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        QuizInformationQuestion::where('id', $request->questionID)->delete();
        return $this->out(null, 1, 'Info question deleted.');
    }

    /** GET /api/Webservice/getInformationQuizQuestion?quizID= */
    public function getInfoQuestion(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $questions = QuizInformationQuestion::where('quiz_id', $request->quizID)
            ->orderBy('question_order')
            ->get();
        return $this->out($questions, 1, 'OK');
    }

    /** POST /api/Webservice/sendQuizInformationQuestion — publish an info question */
    public function sendQuizInfoQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        $q = QuizInformationQuestion::findOrFail($request->questionID);
        if ($q->question_order == 0) {
            $max = QuizInformationQuestion::where('quiz_id', $q->quiz_id)->max('question_order');
            $q->update(['question_order' => $max + 1, 'updated_by' => $request->user()->id, 'updated_on' => now()]);
        }
        return $this->out(null, 1, 'Info question published.');
    }

    /** POST /api/Webservice/saveInformationSubmitWS */
    public function saveInfoSubmit(Request $request)
    {
        $request->validate([
            'quizID'    => 'required|integer',
            'answerID'  => 'required|integer',
            'answers'   => 'required|array',
        ]);
        $uid = $request->user()->id;
        foreach ($request->answers as $infoId => $value) {
            DB::table('quiz_information_answers')->insert([
                'quiz_id'        => $request->quizID,
                'information_id' => $infoId,
                'answer_id'      => $request->answerID,
                'value'          => is_array($value) ? json_encode($value) : (string) $value,
                'added_by'       => $uid,
                'added_on'       => now(),
            ]);
        }
        return $this->out(null, 1, 'Info answers saved.');
    }

    /* ═════════════════════════════════════════════════════════════════
       PUBLIC INVITE-BASED QUIZ (no auth required)
       ─────────────────────────────────────────────────────────────────
       Mirror of old project's /ask/<quizID>?i=<base64_inviteID> pattern.
       
       Workflow:
         1. Admin/Quiz Master sends invite via sendInviteToQuiz (auth)
            → row inserted into quiz_invites, email sent with link
         2. Recipient clicks email link → /quiz-take/<inviteId> page
         3. Frontend calls publicGetQuizByInvite (no auth, by inviteId)
         4. Recipient takes quiz, calls publicSubmitQuiz (no auth)
         5. quiz_invites.status set to 1 (completed) — anti-replay
       ═════════════════════════════════════════════════════════════════ */

    /**
     * GET /api/quiz/take/{inviteId}
     * PUBLIC — fetches quiz + questions for an invite. No auth.
     * 
     * Returns:
     *   - { code: 1, status: 'ready',     data: {quiz, questions, infoQuestions, invite} }
     *   - { code: 2, status: 'completed', message: 'Quiz already completed' }
     *   - { code: 3, status: 'invalid',   message: 'Invalid or expired invite' }
     *   - { code: 4, status: 'inactive',  message: 'Quiz no longer available' }
     */
    public function publicGetQuizByInvite($inviteId)
    {
        $inviteId = (int) $inviteId;
        if ($inviteId <= 0) {
            return $this->out(['status' => 'invalid'], 3, 'Invalid invite link.');
        }

        $invite = QuizInvite::find($inviteId);
        if (!$invite) {
            return $this->out(['status' => 'invalid'], 3, 'Invalid or expired invite link.');
        }

        // Anti-replay — already completed
        if ((int) $invite->status === 1) {
            return $this->out([
                'status'        => 'completed',
                'completed_on'  => $invite->completed_on,
                'email'         => $invite->email,
            ], 2, 'You have already completed this quiz.');
        }

        // Load quiz with questions
        $quiz = Quiz::with(['questions.options', 'infoQuestions'])
            ->where('id', $invite->quiz_id)
            ->where('status', '!=', 0)   // exclude soft-deleted
            ->first();

        if (!$quiz) {
            return $this->out(['status' => 'inactive'], 4, 'This quiz is no longer available.');
        }

        // Strip correct-answer flag from options before sending to client
        // (security: prevent client-side answer-peeking via JSON)
        $questions = $quiz->questions->map(function ($q) {
            return [
                'id'             => $q->id,
                'question'       => $q->question_text,
                'point'          => $q->point,
                'question_order' => $q->question_order,
                'options'        => $q->options->map(fn($o) => [
                    'id'     => $o->id,
                    'option' => $o->option_text,
                ])->values(),
            ];
        })->values();

        return $this->out([
            'status' => 'ready',
            'invite' => [
                'id'        => $invite->id,
                'email'     => $invite->email,
                'quiz_id'   => $invite->quiz_id,
                'sent_on'   => $invite->sent_on,
            ],
            'quiz' => [
                'id'                  => $quiz->id,
                'name'                => $quiz->name,
                'description'         => $quiz->description,
                'time'                => (int) $quiz->time,
                'passing_percentage'  => (float) $quiz->passing_percentage,
                'show_marks'          => (int) $quiz->show_marks,
                'number_of_attempt'   => (int) $quiz->number_of_attempt,
                'total_questions'     => $questions->count(),
            ],
            'questions'      => $questions,
            'info_questions' => $quiz->infoQuestions->map(fn($iq) => [
                'id'             => $iq->id,
                'question'       => $iq->question_text,
                'type'           => $iq->question_type,
                'question_order' => $iq->question_order,
            ])->values(),
        ], 1, 'OK');
    }

    /**
     * POST /api/quiz/take/{inviteId}/submit
     * PUBLIC — submits answers for an invite. No auth.
     * 
     * Body: {
     *   answers: { <questionID>: <optionID>, ... },
     *   info_answers: [ { information_id, value }, ... ] (optional),
     *   time_up: 0|1|2,
     *   switch_tabs: 0|1
     * }
     */
    public function publicSubmitQuiz(Request $request, $inviteId)
    {
        $inviteId = (int) $inviteId;
        if ($inviteId <= 0) {
            return $this->out(null, 3, 'Invalid invite.');
        }

        $request->validate([
            'answers' => 'required|array',
        ]);

        $invite = QuizInvite::find($inviteId);
        if (!$invite) {
            return $this->out(null, 3, 'Invalid or expired invite link.');
        }

        // Anti-replay — already completed
        if ((int) $invite->status === 1) {
            return $this->out(null, 2, 'You have already submitted this quiz.');
        }

        $quiz = Quiz::with('questions.options')
            ->where('id', $invite->quiz_id)
            ->where('status', '!=', 0)
            ->first();

        if (!$quiz) {
            return $this->out(null, 4, 'This quiz is no longer available.');
        }

        // Score the quiz
        $points    = 0;
        $total     = 0;
        $correct   = 0;
        $listItems = [];

        foreach ($quiz->questions as $q) {
            $total += $q->point;
            $selected = $request->answers[$q->id] ?? null;
            if ($selected) {
                $option    = $q->options->firstWhere('id', $selected);
                $isCorrect = $option && (int) $option->answer === 1;
                if ($isCorrect) {
                    $points  += $q->point;
                    $correct++;
                }
                $listItems[] = [
                    'question_id' => $q->id,
                    'option_id'   => (int) $selected,
                    'result'      => $isCorrect ? 1 : 0,
                ];
            }
        }

        $percentage = $total > 0 ? round(($points / $total) * 100, 2) : 0;
        $passed     = $percentage >= (float) $quiz->passing_percentage;

        // Persist quiz_answers row + items + mark invite completed
        DB::beginTransaction();
        try {
            $answerId = DB::table('quiz_answers')->insertGetId([
                'quiz_id'         => $quiz->id,
                'invite_id'       => $invite->id,
                'points'          => $points,
                'total_points'    => $total,
                'correct_answers' => $correct,
                'total_questions' => $quiz->questions->count(),
                'percentage'      => $percentage,
                'pass'            => $passed ? 1 : 0,
                'answered_on'     => now(),
                'time_up'         => (int) ($request->time_up ?? 0),
                'switch_tabs'     => (int) ($request->switch_tabs ?? 0),
                'ip'              => $request->ip(),
                'user_agent'      => $request->userAgent() ?? '',
                'added_on'        => now(),
            ]);

            foreach ($listItems as $item) {
                DB::table('quiz_answer_items')->insert(array_merge($item, ['answer_id' => $answerId]));
            }

            // Save information question answers if provided
            if (is_array($request->info_answers)) {
                foreach ($request->info_answers as $ia) {
                    if (!isset($ia['information_id'], $ia['value'])) continue;
                    DB::table('quiz_information_answers')->insert([
                        'quiz_id'        => $quiz->id,
                        'information_id' => (int) $ia['information_id'],
                        'answer_id'      => $answerId,
                        'value'          => (string) $ia['value'],
                        'added_by'       => 0,                  // public — no user
                        'added_on'       => now(),
                    ]);
                }
            }

            $invite->update([
                'status'       => 1,
                'completed_on' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->out(null, 0, 'Failed to save submission. Please try again.');
        }

        return $this->out([
            'answerID'    => $answerId,
            'points'      => $points,
            'total'       => $total,
            'correct'     => $correct,
            'total_qs'    => $quiz->questions->count(),
            'percentage'  => $percentage,
            'passed'      => (bool) $passed,
            'show_marks'  => (int) $quiz->show_marks,
        ], 1, $passed ? 'Quiz submitted successfully — passed!' : 'Quiz submitted.');
    }
}