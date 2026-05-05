<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\InterviewInvite;
use App\Models\InterviewQuestion;
use App\Models\InterviewResponse;
use App\Models\InterviewResponseVideo;
use App\Mail\InterviewInviteMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class InterviewController extends Controller
{
    private function out($data, int $code, string $msg)
    {
        return response()->json(['data' => $data, 'code' => $code, 'message' => $msg]);
    }

    /** GET /api/interviews/list */
    public function getAllInterviewList(Request $request)
    {
        return $this->out(Interview::where('status','!=',0)->orderByDesc('added_on')->get(), 1, 'OK');
    }

    /** POST /api/interviews/add */
    public function addInterview(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $uid = $request->user()->id;
        $iv  = Interview::create([
            'name'        => strip_tags(trim($request->name)),
            'description' => $request->description ?? '',
            'type'        => $request->type ?? 0,
            'added_by'    => $uid,
            'added_on'    => now(),
            'updated_by'  => 0,
            'visibility'  => $request->visibility ?? 0,
            'time'        => $request->time ?? 0,
            'show_marks'  => $request->show_marks ?? 1,
            'status'      => 1,
        ]);
        DB::table('logs_admin_interview')->insert(['interview_id'=>$iv->id,'action'=>'ADD_NEW_INTERVIEW','done_by'=>$uid,'ip'=>$request->ip(),'user_agent'=>$request->userAgent(),'done_on'=>now()]);
        return $this->out(['interviewID' => $iv->id], 1, 'Interview added.');
    }

    /** GET /api/interviews/{id}/details */
    public function getInterviewDetails(Request $request)
    {
        $request->validate(['interviewID' => 'required|integer']);
        $iv = Interview::with('questions')->findOrFail($request->interviewID);
        return $this->out($iv, 1, 'OK');
    }

    /** POST /api/interviews/update */
    public function updateInterviewDetail(Request $request)
    {
        $request->validate(['interviewID' => 'required|integer']);
        Interview::where('id', $request->interviewID)->update(array_filter([
            'name'        => $request->name ? strip_tags(trim($request->name)) : null,
            'description' => $request->description,
            'updated_by'  => $request->user()->id,
            'updated_on'  => now(),
        ], fn($v) => !is_null($v)));
        return $this->out(null, 1, 'Updated.');
    }

    /** POST /api/interviews/add-question */
    public function addInterviewQuestion(Request $request)
    {
        $request->validate(['interviewID' => 'required|integer', 'question_text' => 'required|string']);
        $uid = $request->user()->id;
        $q   = InterviewQuestion::create([
            'interview_id'       => $request->interviewID,
            'question_text'      => $request->question_text,
            'question_time'      => $request->question_time ?? 1,
            'question_view_time' => $request->question_view_time ?? 0,
            'status'             => 1,
            'added_by'           => $uid,
            'added_on'           => now(),
            'updated_by'         => 0,
        ]);
        DB::table('logs_admin_interview')->insert(['interview_id'=>$request->interviewID,'action'=>'INTERVIEW_QUESTION_ADDED_'.$q->id,'done_by'=>$uid,'ip'=>$request->ip(),'user_agent'=>$request->userAgent(),'done_on'=>now()]);
        return $this->out(['questionID' => $q->id], 1, 'Question added.');
    }

    /** DELETE /api/interviews/delete-question */
    public function deleteInterviewQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        InterviewQuestion::where('id', $request->questionID)->update(['status' => 0, 'updated_by' => $request->user()->id, 'updated_on' => now()]);
        return $this->out(null, 1, 'Deleted.');
    }

    /** POST /api/interviews/share-invite */public function shareInterviewInvite(Request $request)
{
    $request->validate([
        'interviewID' => 'required|integer',
        'emails'      => 'required|array',
        'expiry_days' => 'integer'
    ]);

    $uid       = $request->user()->id;
    $expiry    = now()->addDays($request->expiry_days ?? 7);
    $interview = Interview::findOrFail($request->interviewID);

    foreach ($request->emails as $email) {
        $invite = InterviewInvite::create([
            'unique_id'     => Str::uuid(),
            'interview_id'  => $request->interviewID,
            'email'         => trim($email),
            'completed'     => 0,
            'expire_on'     => $expiry,
            'invite_status' => 0,
            'added_by'      => $uid,
            'added_on'      => now(),
        ]);
        $invite = $invite->fresh();

        try {
            Mail::to($email)->send(new InterviewInviteMail($invite, $interview));
            $invite->update(['invite_status' => 1, 'invited_on' => now()]);
        } catch (\Exception $e) {
            $invite->update(['invite_status' => 2]);
        }
    }

    return $this->out(null, 1, 'Invites created.');
}
    /** GET /api/interviews/invited-list?interviewID= */
    public function getInterviewInvitedList(Request $request)
    {
        $request->validate(['interviewID' => 'required|integer']);
        return $this->out(InterviewInvite::where('interview_id', $request->interviewID)->get(), 1, 'OK');
    }

    // =========================================================================
    // Candidate-facing endpoints
    // =========================================================================

    /** GET /api/interviews/take?uniqueID= */
    public function getInterview(Request $request)
    {
        $invite = InterviewInvite::where('unique_id', $request->uniqueID)->firstOrFail();

        if ($invite->expire_on && now()->gt($invite->expire_on)) {
            return $this->out(null, 0, 'Invite has expired.');
        }
        if ($invite->completed == 2) {
            return $this->out(null, 0, 'Interview already completed.');
        }

        $interview = Interview::with('questions')->findOrFail($invite->interview_id);
        return $this->out([
            'invite'    => $invite,
            'interview' => $interview,
        ], 1, 'OK');
    }

    /** POST /api/interviews/submit-question */
    public function sendInterviewQuestion(Request $request)
    {
        $request->validate(['inviteID' => 'required|integer', 'questionID' => 'required|integer']);

        $invite = InterviewInvite::findOrFail($request->inviteID);
        if ($invite->completed == 0) {
            $invite->update(['completed' => 1, 'started_on' => now()]);
        }

        $resp = InterviewResponse::firstOrCreate(
            ['interview_id' => $invite->interview_id, 'invite_id' => $invite->id, 'question_id' => $request->questionID],
            ['started_on' => now(), 'submitted' => 0]
        );

        return $this->out(['responseID' => $resp->id], 1, 'Ready to record.');
    }

    /** POST /api/interviews/save-video */
    public function saveInterviewVideo(Request $request)
    {
        $request->validate(['responseID' => 'required|integer', 'src' => 'required|string']);
        InterviewResponseVideo::create([
            'response_id' => $request->responseID,
            'src'         => $request->src,
            'added_on'    => now(),
        ]);
        InterviewResponse::where('id', $request->responseID)->update(['submitted' => 1, 'submitted_on' => now()]);

        // Check if all questions answered for this invite
        $resp    = InterviewResponse::find($request->responseID);
        $invite  = InterviewInvite::find($resp->invite_id);
        $total   = InterviewQuestion::where('interview_id', $invite->interview_id)->where('status', 1)->count();
        $done    = InterviewResponse::where('invite_id', $invite->id)->where('submitted', 1)->count();

        if ($done >= $total) {
            $invite->update(['completed' => 2, 'complete_on' => now()]);
        }
        return $this->out(['allDone' => $done >= $total], 1, 'Video saved.');
    }

    /** GET /api/interviews/overview-charts?interviewID= */
    public function getOverviewCharts(Request $request)
    {
        $request->validate(['interviewID' => 'required|integer']);
        $iid      = $request->interviewID;
        $total    = InterviewInvite::where('interview_id', $iid)->count();
        $started  = InterviewInvite::where('interview_id', $iid)->where('completed', '>=', 1)->count();
        $done     = InterviewInvite::where('interview_id', $iid)->where('completed', 2)->count();
        return $this->out(compact('total','started','done'), 1, 'OK');
    }

    // =========================================================================
    // INTERVIEW — missing methods
    // =========================================================================

    /** GET /api/Webservice/getInterviewQuestions?interviewID= */
    public function getInterviewQuestions(Request $request)
    {
        $request->validate(['interviewID' => 'required|integer']);
        $questions = InterviewQuestion::where('interview_id', $request->interviewID)
            ->where('status', 1)
            ->orderBy('id')
            ->get();
        return $this->out($questions, 1, 'OK');
    }

    /** GET /api/Webservice/getInterviewQuestionDetail?questionID= */
    public function getInterviewQuestionDetail(Request $request)
    {
        $request->validate(['questionID' => 'required|integer']);
        $q = InterviewQuestion::findOrFail($request->questionID);
        return $this->out($q, 1, 'OK');
    }

    /** POST /api/Webservice/updateInterviewQuestion */
    public function updateInterviewQuestion(Request $request)
    {
        $request->validate(['questionID' => 'required|integer', 'interviewID' => 'required|integer']);
        $uid = $request->user()->id;
        InterviewQuestion::where('id', $request->questionID)->update(array_filter([
            'question_text'      => $request->question_text,
            'question_time'      => $request->question_time,
            'question_view_time' => $request->question_view_time,
            'updated_by'         => $uid,
            'updated_on'         => now(),
        ], fn($v) => !is_null($v)));
        DB::table('logs_admin_interview')->insert(['interview_id' => $request->interviewID, 'action' => 'INTERVIEW_QUESTION_UPDATED_' . $request->questionID, 'done_by' => $uid, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'done_on' => now()]);
        return $this->out(null, 1, 'Question updated.');
    }

    /**
     * GET /api/Webservice/getInterviewSubmissions?interviewID=
     *
     * Returns ALL completed candidates' submissions for an interview, with
     * their video responses nested under each question.
     *
     * Response shape:
     *   [
     *     {
     *       invite_id, email, completed, completed_on, started_on,
     *       responses: [
     *         { question_id, question_text, submitted_on, videos: [{ id, src, added_on }] }
     *       ]
     *     },
     *     ...
     *   ]
     */
    public function getInterviewSubmissions(Request $request)
    {
        $request->validate(['interviewID' => 'required|integer']);
        $iid = (int) $request->interviewID;

        // Fetch all questions for this interview (for ordering + text)
        $questions = InterviewQuestion::where('interview_id', $iid)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'question_text', 'question_time'])
            ->keyBy('id');

        // Fetch all invites that have at least started OR completed
        $invites = InterviewInvite::where('interview_id', $iid)
            ->where('completed', '>=', 1)
            ->orderByDesc('complete_on')
            ->orderByDesc('started_on')
            ->get();

        if ($invites->isEmpty()) {
            return $this->out([], 1, 'No submissions yet.');
        }

        $inviteIds = $invites->pluck('id')->toArray();

        // Bulk fetch all responses for these invites
        $responses = InterviewResponse::whereIn('invite_id', $inviteIds)
            ->where('submitted', 1)
            ->orderBy('submitted_on')
            ->get()
            ->groupBy('invite_id');

        // Bulk fetch videos for all those responses
        $allResponseIds = InterviewResponse::whereIn('invite_id', $inviteIds)
            ->where('submitted', 1)
            ->pluck('id')
            ->toArray();

        $videos = InterviewResponseVideo::whereIn('response_id', $allResponseIds)
            ->orderBy('added_on')
            ->get(['id', 'response_id', 'src', 'added_on'])
            ->groupBy('response_id');

        // Build response payload
        $data = $invites->map(function ($invite) use ($responses, $videos, $questions) {
            $invResps = $responses->get($invite->id, collect());

            $respList = $invResps->map(function ($resp) use ($videos, $questions) {
                $q = $questions->get($resp->question_id);
                return [
                    'response_id'    => $resp->id,
                    'question_id'    => $resp->question_id,
                    'question_text'  => $q->question_text ?? 'Question removed',
                    'question_time'  => $q->question_time ?? 0,
                    'started_on'     => $resp->started_on,
                    'submitted_on'   => $resp->submitted_on,
                    'videos'         => $videos->get($resp->id, collect())->values(),
                ];
            })->values();

            return [
                'invite_id'    => $invite->id,
                'email'        => $invite->email,
                'completed'    => $invite->completed,
                'started_on'   => $invite->started_on,
                'complete_on'  => $invite->complete_on,
                'invited_on'   => $invite->invited_on,
                'response_count' => $respList->count(),
                'responses'    => $respList,
            ];
        })->values();

        return $this->out($data, 1, 'OK');
    }

}
