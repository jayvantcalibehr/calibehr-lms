<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLearner;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizInvite;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    private function out($data, int $code, string $msg)
    {
        return response()->json(['data' => $data, 'code' => $code, 'message' => $msg]);
    }

    private function parseList(Request $request, string $key): array
    {
        $raw = $request->input($key);
        if (is_array($raw)) {
            $list = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $list = explode(',', $raw);
        } else {
            return [];
        }
        return array_values(array_filter(array_map('trim', $list), fn($v) => $v !== ''));
    }

    private function sqlServerQuoteList(array $items): string
    {
        return implode(',', array_map(
            fn($s) => "'" . str_replace("'", "''", (string) $s) . "'",
            $items
        ));
    }

    // =========================================================================
    // COURSE REPORT — JSON data
    // =========================================================================

    /** GET /api/reports/course?courseID= */
    public function getCourseReport(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);

        $course = Course::findOrFail($request->courseID);

        $learners = CourseLearner::with('learner')
            ->where('course_id', $request->courseID)
            ->where('status', 1)
            ->get()
            ->map(function ($l) {
                $user = $l->learner;
                return [
                    'emp_code'    => $user ? $user->emp_code : '',
                    'name'        => $user ? $user->full_name : '',
                    'email'       => $user ? $user->emp_email : '',
                    'department'  => $user ? $user->emp_department : '',
                    'designation' => $user ? $user->emp_designation : '',
                    'enrolled_on' => $l->added_on,
                    'started_on'  => $l->started_on,
                    'completed'   => $l->completed ? 'Yes' : 'No',
                    'completed_on'=> $l->completed_on,
                ];
            });

        return $this->out([
            'course'    => $course->name,
            'total'     => count($learners),
            'completed' => $learners->where('completed', 'Yes')->count(),
            'pending'   => $learners->where('completed', 'No')->count(),
            'learners'  => $learners,
        ], 1, 'OK');
    }

    // =========================================================================
    // COURSE REPORT — Excel Download
    // =========================================================================

    /** GET /api/reports/course/excel?courseID= */
    public function downloadCourseReportExcel(Request $request)
    {
        $request->validate(['courseID' => 'required|integer']);

        $course   = Course::findOrFail($request->courseID);
        $learners = CourseLearner::with('learner')
            ->where('course_id', $request->courseID)
            ->where('status', 1)
            ->get();

        $data = $learners->map(function ($l) {
            $user = $l->learner;
            return [
                'Emp Code'     => $user ? $user->emp_code : '',
                'Name'         => $user ? $user->full_name : '',
                'Email'        => $user ? $user->emp_email : '',
                'Designation'  => $user ? $user->emp_designation : '',
                'Enrolled On'  => $l->added_on    ? date('d-m-Y', strtotime($l->added_on))    : '',
                'Started On'   => $l->started_on  ? date('d-m-Y', strtotime($l->started_on))  : '',
                'Completed'    => $l->completed   ? 'Yes' : 'No',
                'Completed On' => $l->completed_on? date('d-m-Y', strtotime($l->completed_on)): '',
            ];
        })->toArray();

        $filename = 'Course_Report_'.str_replace(' ', '_', $course->name).'_'.date('d-m-Y').'.xlsx';

        return Excel::download(new class($data, $course->name) implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
        {
            private $data;
            private $courseName;
            public function __construct($data, $courseName) { $this->data = $data; $this->courseName = $courseName; }
            public function array(): array { return $this->data; }
            public function headings(): array { return ['Emp Code', 'Name', 'Email', 'Designation', 'Enrolled On', 'Started On', 'Completed', 'Completed On']; }
            public function title(): string { return 'Course Report'; }
            public function styles(Worksheet $sheet) {
                return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E79']]]];
            }
            public function columnWidths(): array { return ['A' => 12, 'B' => 25, 'C' => 30, 'D' => 20, 'E' => 15, 'F' => 15, 'G' => 12, 'H' => 15]; }
        }, $filename);
    }

    // =========================================================================
    // COURSE REPORT — PDF Download
    // =========================================================================

    /** GET /api/reports/course/pdf?courseID= */
    public function downloadCourseReportPdf(Request $request)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        $request->validate(['courseID' => 'required|integer']);

        $course   = Course::findOrFail($request->courseID);
        $learners = CourseLearner::where('course_id', $request->courseID)
            ->where('status', 1)->limit(500)->get()
            ->map(function ($l) {
                $user = User::find($l->learner_id);
                return [
                    'emp_code'    => $user ? $user->emp_code : '',
                    'name'        => $user ? $user->full_name : '',
                    'email'       => $user ? $user->emp_email : '',
                    'designation' => $user ? $user->emp_designation : '',
                    'enrolled_on' => $l->added_on    ? date('d-m-Y', strtotime($l->added_on))    : '',
                    'started_on'  => $l->started_on  ? date('d-m-Y', strtotime($l->started_on))  : '',
                    'completed'   => $l->completed   ? 'Yes' : 'No',
                    'completed_on'=> $l->completed_on? date('d-m-Y', strtotime($l->completed_on)): '',
                ];
            });

        $total     = count($learners);
        $completed = $learners->where('completed', 'Yes')->count();
        $pending   = $learners->where('completed', 'No')->count();

        $html = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h2 { color: #1F4E79; text-align: center; }
            .summary { margin-bottom: 15px; background: #f0f4f8; padding: 10px; border-radius: 5px; }
            .summary span { margin-right: 20px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #1F4E79; color: white; padding: 7px; text-align: left; font-size: 10px; }
            td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 10px; }
            tr:nth-child(even) { background: #f9f9f9; }
            .yes { color: green; font-weight: bold; }
            .no  { color: red; }
            .footer { text-align: center; margin-top: 20px; color: #888; font-size: 9px; }
        </style>
        <h2>Course Report — '.$course->name.'</h2>
        <div class="summary">
            <span>Total Enrolled: '.$total.'</span>
            <span>Completed: '.$completed.'</span>
            <span>Pending: '.$pending.'</span>
            <span>Generated: '.date('d-m-Y H:i').'</span>
        </div>
        <table>
            <tr><th>Emp Code</th><th>Name</th><th>Email</th><th>Designation</th><th>Enrolled On</th><th>Started On</th><th>Completed</th><th>Completed On</th></tr>';

        foreach ($learners as $l) {
            $class = $l['completed'] === 'Yes' ? 'yes' : 'no';
            $html .= '<tr>
                <td>'.$l['emp_code'].'</td><td>'.$l['name'].'</td><td>'.$l['email'].'</td>
                <td>'.$l['designation'].'</td><td>'.$l['enrolled_on'].'</td><td>'.$l['started_on'].'</td>
                <td class="'.$class.'">'.$l['completed'].'</td><td>'.$l['completed_on'].'</td>
            </tr>';
        }
        $html .= '</table><div class="footer">Calibehr LMS — Confidential Report</div>';

        $pdf      = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $filename = 'Course_Report_'.str_replace(' ', '_', $course->name).'_'.date('d-m-Y').'.pdf';
        return $pdf->download($filename);
    }

    // =========================================================================
    // QUIZ REPORT — JSON data
    // =========================================================================

    /** GET /api/reports/quiz?quizID= */
    public function getQuizReport(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $quiz    = Quiz::findOrFail($request->quizID);
        $invites = QuizInvite::where('quiz_id', $request->quizID)->get();

        $data = $invites->map(function ($inv) {
            $answer = QuizAnswer::where('invite_id', $inv->id)->orderByDesc('answered_on')->first();
            return [
                'email'        => $inv->email,
                'sent_on'      => $inv->sent_on      ? date('d-m-Y', strtotime($inv->sent_on))      : '',
                'completed'    => $inv->status       ? 'Yes' : 'No',
                'completed_on' => $inv->completed_on ? date('d-m-Y', strtotime($inv->completed_on)) : '',
                'points'       => $answer ? $answer->points       : 0,
                'total_points' => $answer ? $answer->total_points : 0,
                'percentage'   => $answer ? $answer->percentage   : 0,
                'pass'         => $answer ? ($answer->pass ? 'Pass' : 'Fail') : 'Not Attempted',
                'time_up'      => $answer ? ($answer->time_up == 1 ? 'Yes' : 'No') : '',
            ];
        });

        return $this->out([
            'quiz'      => $quiz->name,
            'total'     => count($data),
            'completed' => $data->where('completed', 'Yes')->count(),
            'passed'    => $data->where('pass', 'Pass')->count(),
            'failed'    => $data->where('pass', 'Fail')->count(),
            'pending'   => count($data) - $data->where('completed', 'Yes')->count(),
            'data'      => $data,
        ], 1, 'OK');
    }

    // =========================================================================
    // QUIZ REPORT — Excel Download
    // =========================================================================

    /** GET /api/reports/quiz/excel?quizID= */
    public function downloadQuizReportExcel(Request $request)
    {
        $request->validate(['quizID' => 'required|integer']);
        $quiz    = Quiz::findOrFail($request->quizID);
        $invites = QuizInvite::where('quiz_id', $request->quizID)->get();

        $data = $invites->map(function ($inv) {
            $answer = QuizAnswer::where('invite_id', $inv->id)->orderByDesc('answered_on')->first();
            return [
                'Email'        => $inv->email,
                'Sent On'      => $inv->sent_on      ? date('d-m-Y', strtotime($inv->sent_on))      : '',
                'Completed'    => $inv->status       ? 'Yes' : 'No',
                'Completed On' => $inv->completed_on ? date('d-m-Y', strtotime($inv->completed_on)) : '',
                'Points'       => $answer ? $answer->points       : 0,
                'Total Points' => $answer ? $answer->total_points : 0,
                'Percentage'   => $answer ? $answer->percentage.'%' : '0%',
                'Result'       => $answer ? ($answer->pass ? 'Pass' : 'Fail') : 'Not Attempted',
                'Time Up'      => $answer ? ($answer->time_up == 1 ? 'Yes' : 'No') : '',
            ];
        })->toArray();

        $filename = 'Quiz_Report_'.str_replace(' ', '_', $quiz->name).'_'.date('d-m-Y').'.xlsx';

        return Excel::download(new class($data, $quiz->name) implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
        {
            private $data; private $quizName;
            public function __construct($data, $quizName) { $this->data = $data; $this->quizName = $quizName; }
            public function array(): array { return $this->data; }
            public function headings(): array { return ['Email', 'Sent On', 'Completed', 'Completed On', 'Points', 'Total Points', 'Percentage', 'Result', 'Time Up']; }
            public function title(): string { return 'Quiz Report'; }
            public function styles(Worksheet $sheet) {
                return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E79']]]];
            }
            public function columnWidths(): array { return ['A' => 30, 'B' => 15, 'C' => 12, 'D' => 15, 'E' => 10, 'F' => 12, 'G' => 12, 'H' => 15, 'I' => 10]; }
        }, $filename);
    }

    // =========================================================================
    // QUIZ REPORT — PDF Download
    // =========================================================================

    /** GET /api/reports/quiz/pdf?quizID= */
    public function downloadQuizReportPdf(Request $request)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        $request->validate(['quizID' => 'required|integer']);

        $quiz    = Quiz::findOrFail($request->quizID);
        $invites = QuizInvite::where('quiz_id', $request->quizID)->limit(500)->get();

        $data = $invites->map(function ($inv) {
            $answer = QuizAnswer::where('invite_id', $inv->id)->orderByDesc('answered_on')->first();
            return [
                'email'        => $inv->email,
                'completed'    => $inv->status       ? 'Yes' : 'No',
                'completed_on' => $inv->completed_on ? date('d-m-Y', strtotime($inv->completed_on)) : '',
                'points'       => $answer ? $answer->points       : 0,
                'total'        => $answer ? $answer->total_points : 0,
                'percentage'   => $answer ? $answer->percentage.'%' : '0%',
                'result'       => $answer ? ($answer->pass ? 'Pass' : 'Fail') : 'Not Attempted',
            ];
        });

        $total     = count($data);
        $completed = $data->where('completed', 'Yes')->count();
        $passed    = $data->where('result', 'Pass')->count();
        $failed    = $data->where('result', 'Fail')->count();

        $html = '<style>
        body{font-family:Arial,sans-serif;font-size:11px}h2{color:#1F4E79;text-align:center}
        .summary{margin-bottom:15px;background:#f0f4f8;padding:10px;border-radius:5px}
        .summary span{margin-right:15px;font-weight:bold}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th{background:#1F4E79;color:white;padding:7px;text-align:left;font-size:10px}
        td{padding:6px;border-bottom:1px solid #ddd;font-size:10px}
        tr:nth-child(even){background:#f9f9f9}
        .pass{color:green;font-weight:bold}.fail{color:red}
        .footer{text-align:center;margin-top:20px;color:#888;font-size:9px}
        </style>
        <h2>Quiz Report — '.$quiz->name.'</h2>
        <div class="summary">
            <span>Total: '.$total.'</span><span>Completed: '.$completed.'</span>
            <span>Passed: '.$passed.'</span><span>Failed: '.$failed.'</span>
            <span>Generated: '.date('d-m-Y H:i').'</span>
        </div>
        <table><tr><th>Email</th><th>Completed</th><th>Completed On</th><th>Points</th><th>Total</th><th>Percentage</th><th>Result</th></tr>';

        foreach ($data as $row) {
            $class = $row['result'] === 'Pass' ? 'pass' : ($row['result'] === 'Fail' ? 'fail' : '');
            $html .= '<tr><td>'.$row['email'].'</td><td>'.$row['completed'].'</td><td>'.$row['completed_on'].'</td>
                <td>'.$row['points'].'</td><td>'.$row['total'].'</td><td>'.$row['percentage'].'</td>
                <td class="'.$class.'">'.$row['result'].'</td></tr>';
        }
        $html .= '</table><div class="footer">Calibehr LMS — Confidential Report</div>';

        $pdf      = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $filename = 'Quiz_Report_'.str_replace(' ', '_', $quiz->name).'_'.date('d-m-Y').'.pdf';
        return $pdf->download($filename);
    }

    // =========================================================================
    // LEARNER REPORT — JSON data
    // =========================================================================

    /** GET /api/reports/learner?userID= */
    public function getLearnerReport(Request $request)
    {
        $request->validate(['userID' => 'required|integer']);
        $user     = User::findOrFail($request->userID);
        $learners = CourseLearner::where('learner_id', $request->userID)->where('status', 1)->get()
            ->map(function ($l) {
                $course = Course::find($l->course_id);
                return [
                    'course'       => $course ? $course->name : '',
                    'enrolled_on'  => $l->added_on    ? date('d-m-Y', strtotime($l->added_on))    : '',
                    'started_on'   => $l->started_on  ? date('d-m-Y', strtotime($l->started_on))  : '',
                    'completed'    => $l->completed   ? 'Yes' : 'No',
                    'completed_on' => $l->completed_on? date('d-m-Y', strtotime($l->completed_on)): '',
                ];
            });

        return $this->out([
            'emp_code'  => $user->emp_code,
            'name'      => $user->full_name,
            'email'     => $user->emp_email,
            'total'     => count($learners),
            'completed' => $learners->where('completed', 'Yes')->count(),
            'pending'   => $learners->where('completed', 'No')->count(),
            'courses'   => $learners,
        ], 1, 'OK');
    }

    // =========================================================================
    // LEARNER REPORT — Excel Download
    // =========================================================================

    /** GET /api/reports/learner/excel?userID= */
    public function downloadLearnerReportExcel(Request $request)
    {
        $request->validate(['userID' => 'required|integer']);
        $user     = User::findOrFail($request->userID);
        $learners = CourseLearner::where('learner_id', $request->userID)->where('status', 1)->get();

        $data = $learners->map(function ($l) {
            $course = Course::find($l->course_id);
            return [
                'Course'       => $course ? $course->name : '',
                'Enrolled On'  => $l->added_on    ? date('d-m-Y', strtotime($l->added_on))    : '',
                'Started On'   => $l->started_on  ? date('d-m-Y', strtotime($l->started_on))  : '',
                'Completed'    => $l->completed   ? 'Yes' : 'No',
                'Completed On' => $l->completed_on? date('d-m-Y', strtotime($l->completed_on)): '',
            ];
        })->toArray();

        $filename = 'Learner_Report_'.$user->emp_code.'_'.date('d-m-Y').'.xlsx';

        return Excel::download(new class($data, $user->full_name, $user->emp_code) implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
        {
            private $data; private $name; private $empCode;
            public function __construct($data, $name, $empCode) { $this->data = $data; $this->name = $name; $this->empCode = $empCode; }
            public function array(): array { return $this->data; }
            public function headings(): array { return ['Course', 'Enrolled On', 'Started On', 'Completed', 'Completed On']; }
            public function title(): string { return 'Learner Report'; }
            public function styles(Worksheet $sheet) {
                return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E79']]]];
            }
            public function columnWidths(): array { return ['A' => 45, 'B' => 15, 'C' => 15, 'D' => 12, 'E' => 15]; }
        }, $filename);
    }

    // =========================================================================
    // LEARNER REPORT — PDF Download
    // =========================================================================

    /** GET /api/reports/learner/pdf?userID= */
    public function downloadLearnerReportPdf(Request $request)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);
        $request->validate(['userID' => 'required|integer']);

        $user     = User::findOrFail($request->userID);
        $learners = CourseLearner::where('learner_id', $request->userID)->where('status', 1)->get()
            ->map(function ($l) {
                $course = Course::find($l->course_id);
                return [
                    'course'       => $course ? $course->name : '',
                    'enrolled_on'  => $l->added_on    ? date('d-m-Y', strtotime($l->added_on))    : '',
                    'started_on'   => $l->started_on  ? date('d-m-Y', strtotime($l->started_on))  : '',
                    'completed'    => $l->completed   ? 'Yes' : 'No',
                    'completed_on' => $l->completed_on? date('d-m-Y', strtotime($l->completed_on)): '',
                ];
            });

        $total     = count($learners);
        $completed = $learners->where('completed', 'Yes')->count();
        $pending   = $learners->where('completed', 'No')->count();

        $html = '<style>
        body{font-family:Arial,sans-serif;font-size:11px}h2{color:#1F4E79;text-align:center}
        .info{margin-bottom:10px}.info span{margin-right:20px;font-weight:bold}
        .summary{margin-bottom:15px;background:#f0f4f8;padding:10px;border-radius:5px}
        .summary span{margin-right:20px;font-weight:bold}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th{background:#1F4E79;color:white;padding:7px;text-align:left;font-size:10px}
        td{padding:6px;border-bottom:1px solid #ddd;font-size:10px}
        tr:nth-child(even){background:#f9f9f9}
        .yes{color:green;font-weight:bold}.no{color:red}
        .footer{text-align:center;margin-top:20px;color:#888;font-size:9px}
        </style>
        <h2>Learner Report</h2>
        <div class="info">
            <span>Name: '.$user->full_name.'</span>
            <span>Emp Code: '.$user->emp_code.'</span>
            <span>Email: '.$user->emp_email.'</span>
        </div>
        <div class="summary">
            <span>Total Courses: '.$total.'</span>
            <span>Completed: '.$completed.'</span>
            <span>Pending: '.$pending.'</span>
            <span>Generated: '.date('d-m-Y H:i').'</span>
        </div>
        <table><tr><th>Course</th><th>Enrolled On</th><th>Started On</th><th>Completed</th><th>Completed On</th></tr>';

        foreach ($learners as $l) {
            $class = $l['completed'] === 'Yes' ? 'yes' : 'no';
            $html .= '<tr><td>'.$l['course'].'</td><td>'.$l['enrolled_on'].'</td><td>'.$l['started_on'].'</td>
                <td class="'.$class.'">'.$l['completed'].'</td><td>'.$l['completed_on'].'</td></tr>';
        }
        $html .= '</table><div class="footer">Calibehr LMS — Confidential Report</div>';

        $pdf      = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $filename = 'Learner_Report_'.$user->emp_code.'_'.date('d-m-Y').'.pdf';
        return $pdf->download($filename);
    }

    // =========================================================================
    // MATRIX REPORT — Excel Download
    // =========================================================================

    /** GET /api/reports/matrix/excel */
    public function downloadMatrixReportExcel(Request $request)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $courseIds = array_filter(array_map('intval', $this->parseList($request, 'courseIDs')));

        $coursesQuery = DB::connection('mysql')
            ->table('courses')
            ->join('course_master', 'courses.type', '=', 'course_master.id')
            ->select('courses.id', 'courses.name as course_name', 'course_master.name as mode')
            ->where('courses.status', 2);

        if (!empty($courseIds)) $coursesQuery->whereIn('courses.id', $courseIds);
        $courses = $coursesQuery->orderBy('courses.id','DESC')->get();

        if ($courses->isEmpty()) return response()->json(['message' => 'No published courses found.'], 404);

        $enrolledUserIds = DB::connection('mysql')->table('course_learners')
            ->whereIn('course_id', $courses->pluck('id')->toArray())->where('status', 1)
            ->distinct()->pluck('learner_id')->toArray();

        if (empty($enrolledUserIds)) return response()->json(['message' => 'No learners found.'], 404);

        $mysqlUsers = DB::connection('mysql')->table('users')
            ->select('id', 'emp_code', 'emp_first_name', 'emp_middle_name', 'emp_last_name', 'emp_email', 'emp_status')
            ->whereIn('id', $enrolledUserIds)->orderBy('emp_code','DESC')->get()->keyBy('emp_code');

        if ($mysqlUsers->isEmpty()) return response()->json(['message' => 'No users found.'], 404);

        $ecrData = collect();
        try {
            $serverName   = env('ECR_SQLSRV_HOST', 'tcp:172.16.1.30,1433');
            $sqlSrvConfig = [
                'Database' => env('ECR_SQLSRV_DB', 'ECR_New'), 'Uid' => env('ECR_SQLSRV_USER', 'nbg_sa'),
                'PWD' => env('ECR_SQLSRV_PASS', ''), 'Encrypt' => 0, 'TrustServerCertificate' => 1,
            ];
            $conn = sqlsrv_connect($serverName, $sqlSrvConfig);
            if ($conn !== false) {
                $empCodeList = "'" . implode("','", $mysqlUsers->keys()->toArray()) . "'";
                $tsql = "SELECT EM.EmployeeCode, EM.OfficeEmail AS EmployeeEmail, EM.ContactNo, EM.Gender, EM.DOJ,
                    CASE WHEN EM.isActive = 1 THEN 'Active' ELSE 'Inactive' END AS Emp_status,
                    C.CompanyName, D.DivisionName AS Vertical, DP.DeptName AS Department,
                    B.BranchName AS Location, DE.DesignationName AS Designation,
                    CONCAT(RM.FirstName,' ',RM.LastName) AS RMName, RM.OfficeEmail AS RMEmail,
                    CONCAT(FH.FirstName,' ',FH.LastName) AS FHName, FH.OfficeEmail AS FHEmail
                FROM [ECR_New].[dbo].[Employee_Master] AS EM
                LEFT JOIN [ECR_New].[dbo].[Company] AS C ON EM.CompanyId=C.ID
                LEFT JOIN [ECR_New].[dbo].[Division] AS D ON EM.DivisionId=D.ID
                LEFT JOIN [ECR_New].[dbo].[Department] AS DP ON EM.DepartmentId=DP.ID
                LEFT JOIN [ECR_New].[dbo].[Branch] AS B ON EM.BranchId=B.ID
                LEFT JOIN [ECR_New].[dbo].[Designation] AS DE ON EM.DesignationId=DE.ID
                LEFT JOIN [ECR_New].[dbo].[Employee_Master] AS RM ON EM.ManagerId=RM.ID
                LEFT JOIN [ECR_New].[dbo].[Employee_Master] AS FH ON EM.FunctionalRoleId=FH.ID
                WHERE EM.EmployeeCode IN ($empCodeList)";

                $companyID = $request->input('companyID'); $deptID = $request->input('departmentID'); $verticalID = $request->input('verticalID');
                if ($companyID  && !is_array($companyID))  $tsql .= " AND EM.CompanyId = "    . intval($companyID);
                if ($deptID     && !is_array($deptID))     $tsql .= " AND EM.DepartmentId = " . intval($deptID);
                if ($verticalID && !is_array($verticalID)) $tsql .= " AND EM.DivisionId = "   . intval($verticalID);

                $companies = $this->parseList($request, 'companies'); $departments = $this->parseList($request, 'departments');
                $verticals = $this->parseList($request, 'verticals'); $branches    = $this->parseList($request, 'branches');
                if (!empty($companies))   $tsql .= " AND C.CompanyName  IN (" . $this->sqlServerQuoteList($companies)   . ")";
                if (!empty($departments)) $tsql .= " AND DP.DeptName    IN (" . $this->sqlServerQuoteList($departments) . ")";
                if (!empty($verticals))   $tsql .= " AND D.DivisionName IN (" . $this->sqlServerQuoteList($verticals)   . ")";
                if (!empty($branches))    $tsql .= " AND B.BranchName   IN (" . $this->sqlServerQuoteList($branches)    . ")";

                $stmt = sqlsrv_query($conn, $tsql);
                if ($stmt !== false) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        if ($row['DOJ'] instanceof \DateTime) $row['DOJ'] = $row['DOJ']->format('d-m-Y');
                        $ecrData[$row['EmployeeCode']] = $row;
                    }
                }
                sqlsrv_close($conn);
            }
        } catch (\Throwable $e) {}

        $allEnrollments = DB::connection('mysql')->table('course_learners')
            ->whereIn('course_id', $courses->pluck('id')->toArray())
            ->whereIn('learner_id', $mysqlUsers->pluck('id')->toArray())
            ->where('status', 1)->get()->groupBy('learner_id');

        $scores = DB::connection('mysql')->table('topic_question_answers')
            ->whereIn('course_id', $courses->pluck('id')->toArray())
            ->whereIn('answered_by', $mysqlUsers->pluck('id')->toArray())
            ->select('course_id', 'answered_by', DB::raw('MAX(percentage) as best_score'))
            ->groupBy('course_id', 'answered_by')->get()->groupBy('answered_by');

        $durations = DB::connection('mysql')->table('course_learner_topic_status')
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereIn('user_id', $mysqlUsers->pluck('id'))
            ->select('course_id', 'user_id', DB::raw('SUM(time_spent) as total_time'))
            ->groupBy('course_id', 'user_id')->get()->groupBy('user_id');

        $lsatData = DB::connection('mysql')->table('course_feedback_ratings')
            ->whereIn('course_id', $courses->pluck('id')->toArray())
            ->whereIn('user_id', $mysqlUsers->pluck('id')->toArray())
            ->select('course_id', 'user_id', DB::raw('AVG(star) as avg_lsat'))
            ->groupBy('course_id', 'user_id')->get()->groupBy('user_id');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matrix Report');

        $fixedHeaders = ['EmpCode','Name','Email','Company','Vertical','Department','Gender','DOJ','Location','Designation','Reporting Manager','RM Email','Functional Head','FH Email','Contact No','Emp Status'];
        $fixedCount   = count($fixedHeaders);

        $courseHeaders = [];
        foreach ($courses as $course) {
            $n = $course->course_name;
            $courseHeaders[] = $n."\nTraining Period"; $courseHeaders[] = $n."\nDuration (Min)";
            $courseHeaders[] = $n."\nCourse Name";     $courseHeaders[] = $n."\nMode of Training";
            $courseHeaders[] = $n."\nCompletion Status"; $courseHeaders[] = $n."\nL-Sat";
            $courseHeaders[] = $n."\nCompletion Date"; $courseHeaders[] = $n."\nAssessment %";
        }

        $summaryHeaders = ['Overall Courses Assigned','Overall Courses Completed','Overall Courses Pending','Overall L-Sat %'];
        $allHeaders     = array_merge($fixedHeaders, $courseHeaders, $summaryHeaders);
        $totalCols      = count($allHeaders);

        $sheet->fromArray([$allHeaders], null, 'A1');
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
        $sheet->getStyle('A1:'.$lastColLetter.'1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(45);
        $sheet->freezePane('A2');

        $rowNum = 2;
        $hasEcrFilter = $request->filled('companyID') || $request->filled('departmentID') || $request->filled('verticalID')
            || !empty($this->parseList($request, 'companies')) || !empty($this->parseList($request, 'departments'))
            || !empty($this->parseList($request, 'verticals')) || !empty($this->parseList($request, 'branches'));

        foreach ($mysqlUsers as $empCode => $mUser) {
            if ($hasEcrFilter && !isset($ecrData[$empCode])) continue;
            $ecr = $ecrData[$empCode] ?? null;

            $row = [
                $mUser->emp_code,
                trim($mUser->emp_first_name.' '.$mUser->emp_middle_name.' '.$mUser->emp_last_name),
                $mUser->emp_email,
                $ecr['CompanyName'] ?? '', $ecr['Vertical'] ?? '', $ecr['Department'] ?? '',
                $ecr['Gender'] ?? '', $ecr['DOJ'] ?? '', $ecr['Location'] ?? '', $ecr['Designation'] ?? '',
                $ecr['RMName'] ?? '', $ecr['RMEmail'] ?? '', $ecr['FHName'] ?? '', $ecr['FHEmail'] ?? '',
                $ecr['ContactNo'] ?? '', $ecr['Emp_status'] ?? ($mUser->emp_status === 'A' ? 'Active' : 'Inactive'),
            ];

            $userEnrollments = $allEnrollments->get($mUser->id, collect())->keyBy('course_id');
            $userScores      = $scores->get($mUser->id, collect())->keyBy('course_id');
            $userLsat        = $lsatData->get($mUser->id, collect())->keyBy('course_id');
            $userDurations   = $durations->get($mUser->id, collect())->keyBy('course_id');
            $totalAssigned = 0; $totalCompleted = 0; $lsatSum = 0; $lsatCount = 0;

            foreach ($courses as $course) {
                $enroll = $userEnrollments->get($course->id);
                if ($enroll) {
                    $totalAssigned++;
                    $trainingPeriod = '';
                    if ($enroll->completed_on) { $month = (int)date('n', strtotime($enroll->completed_on)); $trainingPeriod = 'Q'.ceil($month/3).'-'.date('Y', strtotime($enroll->completed_on)); }
                    $durationRec  = $userDurations->get($course->id);
                    $durationMins = $durationRec ? intval($durationRec->total_time / 60) : 0;
                    $status = $enroll->completed ? 'Completed' : 'Pending';
                    if ($enroll->completed) $totalCompleted++;
                    $completionDate = $enroll->completed_on ? date('d-m-Y', strtotime($enroll->completed_on)) : '-';
                    $lsatRec = $userLsat->get($course->id);
                    $lsat = $lsatRec ? round($lsatRec->avg_lsat, 1) : '-';
                    if ($lsatRec) { $lsatSum += $lsatRec->avg_lsat; $lsatCount++; }
                    $scoreRec   = $userScores->get($course->id);
                    $assessment = $scoreRec ? number_format($scoreRec->best_score, 1).'%' : '-';
                    $row[] = $trainingPeriod; $row[] = $durationMins > 0 ? $durationMins : '-';
                    $row[] = $course->course_name; $row[] = $course->mode; $row[] = $status;
                    $row[] = $lsat; $row[] = $completionDate; $row[] = $assessment;
                } else {
                    $row[] = ''; $row[] = ''; $row[] = $course->course_name; $row[] = '';
                    $row[] = 'Not Assigned'; $row[] = ''; $row[] = ''; $row[] = '';
                }
            }

            $overallLsat = $lsatCount > 0 ? round($lsatSum / $lsatCount, 1) : '-';
            $row[] = $totalAssigned; $row[] = $totalCompleted; $row[] = $totalAssigned - $totalCompleted; $row[] = $overallLsat;
            $sheet->fromArray([$row], null, 'A'.$rowNum);
            $rowNum++;
        }

        $fixedWidths = [12,25,30,18,18,18,8,12,15,20,22,28,22,28,14,12];
        foreach ($fixedWidths as $idx => $w) $sheet->getColumnDimensionByColumn($idx+1)->setWidth($w);
        $courseColCount = count($courses) * 8;
        for ($i = $fixedCount+1; $i <= $fixedCount+$courseColCount; $i++) $sheet->getColumnDimensionByColumn($i)->setWidth(16);
        for ($i = $fixedCount+$courseColCount+1; $i <= $totalCols; $i++) $sheet->getColumnDimensionByColumn($i)->setWidth(18);

        $filename = 'Candidate_Courses_Matrix_Report_'.date('d-m-Y').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: max-age=0'); header('Pragma: no-cache'); header('Expires: 0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save('php://output');
        exit;
    }

    // =========================================================================
    // FEEDBACK REPORT — Excel Download
    // =========================================================================

    /** GET /api/reports/feedback/excel?courseID= */
    public function downloadFeedbackReportExcel(Request $request)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);

        $courseID = $request->input('courseID');
        $query    = \App\Models\CourseFeedback::with(['learner', 'course'])->whereHas('course');
        if ($courseID) $query->where('course_id', $courseID);
        $feedbacks = $query->get();

        $headings = ['Emp Code', 'Name', 'Email', 'Course Name', 'Rating', 'Comment', 'Submitted On'];

        $data = $feedbacks->map(function ($f) {
            $user = $f->learner;
            return [
                $user->emp_code   ?? '-',
                $user->full_name  ?? '-',
                $user->emp_email  ?? '-',
                $f->course->name  ?? '-',
                $f->rating        ?? '-',
                $f->comment       ?? '-',
                $f->added_on ? date('d-m-Y', strtotime($f->added_on)) : '-',  // FIX: was created_at
            ];
        })->toArray();

        $filename = 'Feedback_Report_'.date('d-m-Y').'.xlsx';

        return Excel::download(new class($data, $headings) implements
            \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings,
            \Maatwebsite\Excel\Concerns\WithTitle, \Maatwebsite\Excel\Concerns\WithStyles,
            \Maatwebsite\Excel\Concerns\WithColumnWidths {
            private $data, $headings;
            public function __construct($d, $h) { $this->data = $d; $this->headings = $h; }
            public function array(): array { return $this->data; }
            public function headings(): array { return $this->headings; }
            public function title(): string { return 'Feedback Report'; }
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet) {
                return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E79']]]];
            }
            public function columnWidths(): array { return ['A'=>12,'B'=>25,'C'=>30,'D'=>35,'E'=>8,'F'=>40,'G'=>14]; }
        }, $filename);
    }

    // =========================================================================
    // FEEDBACK REPORT — PDF Download
    // =========================================================================

    /** GET /api/reports/feedback/pdf?courseID= */
    public function downloadFeedbackReportPdf(Request $request)
    {
        ini_set('max_execution_time', 300);
        set_time_limit(300);

        $courseID  = $request->input('courseID');
        $query     = \App\Models\CourseFeedback::with(['learner', 'course'])->whereHas('course');
        if ($courseID) $query->where('course_id', $courseID);
        $feedbacks = $query->limit(300)->get();

        $html = '<style>
        body{font-family:Arial,sans-serif;font-size:9px}h2{color:#1F4E79;text-align:center;font-size:13px}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th{background:#1F4E79;color:#fff;padding:5px;font-size:8px;text-align:left}
        td{padding:4px;border:1px solid #ddd;font-size:8px}tr:nth-child(even){background:#f9f9f9}
        .footer{text-align:center;margin-top:10px;color:#888;font-size:8px}
        </style>
        <h2>Feedback Report</h2>
        <p style="text-align:center;font-size:9px">Generated: '.date('d-m-Y H:i').' | Total: '.count($feedbacks).'</p>
        <table><tr><th>Emp Code</th><th>Name</th><th>Course</th><th>Rating</th><th>Comment</th><th>Date</th></tr>';

        foreach ($feedbacks as $f) {
            $user  = $f->learner;
            $html .= '<tr>
                <td>'.($user->emp_code ?? '-').'</td>
                <td>'.($user->full_name ?? '-').'</td>
                <td>'.($f->course->name ?? '-').'</td>
                <td style="text-align:center">'.($f->rating ?? '-').'</td>
                <td>'.substr($f->comment ?? '-', 0, 80).'</td>
                <td>'.($f->added_on ? date('d-m-Y', strtotime($f->added_on)) : '-').'</td>
            </tr>';  // FIX: was created_at
        }
        $html .= '</table><div class="footer">Calibehr LMS — Confidential</div>';
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->download('Feedback_Report_'.date('d-m-Y').'.pdf');
    }

    // =========================================================================
    // FEEDBACK REPORT — JSON
    // =========================================================================

    /** GET /api/reports/feedback?courseID= */
    public function getFeedbackReport(Request $request)
    {
        $courseID  = $request->input('courseID');
        $query     = \App\Models\CourseFeedback::with(['learner', 'course'])->whereHas('course');
        if ($courseID) $query->where('course_id', $courseID);
        $feedbacks = $query->get();

        $data = $feedbacks->map(function ($f) {
            $user = $f->learner;
            return [
                'emp_code'     => $user->emp_code  ?? '-',
                'name'         => $user->full_name ?? '-',
                'email'        => $user->emp_email ?? '-',
                'course'       => $f->course->name ?? '-',
                'rating'       => $f->rating       ?? '-',
                'comment'      => $f->comment      ?? '-',
                'submitted_on' => $f->added_on ? date('d-m-Y', strtotime($f->added_on)) : '-', // FIX: was created_at
            ];
        });

        return $this->out(['total' => count($data), 'report' => $data], 1, 'OK');
    }

    private function buildMatrixData(Request $request): array
    {
        $courseId   = $request->input('courseID');
        $company    = $request->input('companyCode');
        $department = $request->input('department');
        $ecrData    = [];

        try {
            $ecrRows = \Illuminate\Support\Facades\DB::connection('sqlsrv_ecr')->select("SELECT
                EM.EmployeeCode, C.CompanyName, D.DivisionName, DP.DeptName, B.BranchName,
                DE.DesignationName, EM.DOJ, EM.Gender, EM.ContactNo,
                CONCAT(EM.FirstName,' ',EM.LastName) as Name,
                CONCAT(RM.FirstName,' ',RM.LastName) as RMName, RM.OfficeEmail as RMEmail,
                CONCAT(FH.FirstName,' ',FH.LastName) as FHName, FH.OfficeEmail as FHEmail,
                CASE WHEN EM.isActive=0 THEN 'Inactive' ELSE 'Active' END as Emp_status
            FROM [ECR_New].[dbo].[Employee_Master] as EM
            LEFT JOIN [ECR_New].[dbo].[Company] as C ON EM.CompanyId=C.ID
            LEFT JOIN [ECR_New].[dbo].[Division] as D ON EM.DivisionId=D.ID
            LEFT JOIN [ECR_New].[dbo].[Department] as DP ON EM.DepartmentId=DP.ID
            LEFT JOIN [ECR_New].[dbo].[Branch] as B ON EM.BranchId=B.ID
            LEFT JOIN [ECR_New].[dbo].[Designation] as DE ON EM.DesignationId=DE.ID
            LEFT JOIN [ECR_New].[dbo].[Employee_Master] as FH ON EM.FunctionalRoleId=FH.ID
            LEFT JOIN [ECR_New].[dbo].[Employee_Master] as RM ON EM.ManagerId=RM.ID");
            foreach ($ecrRows as $row) $ecrData[$row->EmployeeCode] = $row;
        } catch (\Exception $e) {}

        $query = \App\Models\CourseLearner::with(['course', 'learner'])->where('status', 1);
        if ($courseId) $query->where('course_id', $courseId);
        $learners = $query->get();
        $data     = [];

        foreach ($learners as $l) {
            $user = $l->learner; $course = $l->course;
            if (!$user || !$course) continue;
            if ($company    && $user->emp_client     != $company)    continue;
            if ($department && $user->emp_department != $department) continue;

            $ecr            = $ecrData[$user->emp_code] ?? null;
            $rm             = $ecr ? null : \App\Models\User::find($user->emp_rm);
            $trainingPeriod = '';
            if ($l->completed_on) { $month = date('n', strtotime($l->completed_on)); $trainingPeriod = 'Q'.ceil($month/3).'-'.date('Y', strtotime($l->completed_on)); }

            $data[] = [
                $user->emp_code,
                $ecr ? $ecr->Name            : trim($user->emp_first_name.' '.$user->emp_last_name),
                $user->emp_email,
                $ecr ? $ecr->CompanyName     : ($user->emp_client      ?? '-'),
                $ecr ? $ecr->DivisionName    : '-',
                $ecr ? $ecr->DeptName        : ($user->emp_department  ?? '-'),
                $ecr ? $ecr->Gender          : '-',
                $ecr ? $ecr->DOJ             : ($user->emp_doj         ?? '-'),
                $ecr ? $ecr->BranchName      : ($user->emp_location    ?? '-'),
                $ecr ? $ecr->DesignationName : ($user->emp_designation ?? '-'),
                $ecr ? $ecr->RMName          : ($rm ? $rm->full_name   : '-'),
                $ecr ? $ecr->RMEmail         : '-',
                $ecr ? $ecr->FHName          : '-',
                $ecr ? $ecr->FHEmail         : '-',
                $ecr ? $ecr->ContactNo       : ($user->emp_phone       ?? '-'),
                $ecr ? $ecr->Emp_status      : ($user->emp_active === 'A' ? 'Active' : 'Inactive'),
                $trainingPeriod, 0, $course->name, 'E-learning',
                $l->completed ? 'Completed' : 'Incomplete', '-',
                $l->completed_on ? date('d-m-Y', strtotime($l->completed_on)) : '-',
            ];
        }
        return $data;
    }

    // =========================================================================
    // MATRIX REPORT — PDF Download
    // =========================================================================

    /** GET /api/reports/matrix/pdf?courseIDs= */
    public function downloadMatrixReportPdf(Request $request)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $courseIds    = array_filter(array_map('intval', $this->parseList($request, 'courseIDs')));
        $coursesQuery = DB::connection('mysql')->table('courses')
            ->join('course_master', 'courses.type', '=', 'course_master.id')
            ->select('courses.id', 'courses.name as course_name', 'course_master.name as mode')
            ->where('courses.status', 2);
        if (!empty($courseIds)) $coursesQuery->whereIn('courses.id', $courseIds);
        $courses = $coursesQuery->orderBy('courses.id', 'DESC')->limit(10)->get();

        if ($courses->isEmpty()) return response()->json(['message' => 'No published courses found.'], 404);

        $cIds            = $courses->pluck('id')->toArray();
        $enrolledUserIds = DB::connection('mysql')->table('course_learners')
            ->whereIn('course_id', $cIds)->where('status', 1)->distinct()->pluck('learner_id')->toArray();
        if (empty($enrolledUserIds)) return response()->json(['message' => 'No learners found.'], 404);

        $users = DB::connection('mysql')->table('users')
            ->select('id', 'emp_code', 'emp_first_name', 'emp_last_name', 'emp_email', 'emp_department', 'emp_designation')
            ->whereIn('id', $enrolledUserIds)->orderBy('emp_code')->limit(300)->get()->keyBy('id');

        $allEnrollments = DB::connection('mysql')->table('course_learners')
            ->whereIn('course_id', $cIds)->whereIn('learner_id', array_keys($users->toArray()))
            ->where('status', 1)->get()->groupBy('learner_id');

        $courseHeaders = '';
        foreach ($courses as $c) {
            $courseHeaders .= '<th>'.htmlspecialchars($c->course_name).'<br><small style="font-weight:normal">'.$c->mode.'</small></th>';
        }

        $rows = '';
        foreach ($users as $user) {
            $name = trim($user->emp_first_name.' '.$user->emp_last_name);
            $row  = '<td>'.htmlspecialchars($user->emp_code).'</td>'
                  . '<td>'.htmlspecialchars($name).'</td>'
                  . '<td>'.htmlspecialchars($user->emp_email).'</td>'
                  . '<td>'.htmlspecialchars($user->emp_department ?? '').'</td>'
                  . '<td>'.htmlspecialchars($user->emp_designation ?? '').'</td>';

            $learnerEnrollments = $allEnrollments->get($user->id, collect())->keyBy('course_id');
            foreach ($courses as $c) {
                $enroll = $learnerEnrollments->get($c->id);
                if (!$enroll) { $row .= '<td style="color:#aaa;text-align:center">-</td>'; }
                elseif ($enroll->completed) { $row .= '<td style="color:green;text-align:center;font-weight:bold">Done<br><small>'.($enroll->completed_on ? date('d-m-Y', strtotime($enroll->completed_on)) : '').'</small></td>'; }
                else { $row .= '<td style="color:#e67e22;text-align:center">In Progress</td>'; }
            }
            $rows .= '<tr>'.$row.'</tr>';
        }

        $html = '<style>
        body{font-family:Arial,sans-serif;font-size:10px}h2{color:#1F4E79;text-align:center;margin-bottom:5px}
        .summary{background:#f0f4f8;padding:8px;margin-bottom:12px;border-radius:4px}
        .summary span{margin-right:20px;font-weight:bold}
        table{width:100%;border-collapse:collapse}
        th{background:#1F4E79;color:white;padding:6px 4px;text-align:left;font-size:9px}
        td{padding:5px 4px;border-bottom:1px solid #ddd;font-size:9px}
        tr:nth-child(even){background:#f9f9f9}
        .footer{text-align:center;margin-top:15px;color:#888;font-size:8px}
        </style>
        <h2>Course Matrix Report</h2>
        <div class="summary">
            <span>Total Learners: '.count($users).'</span>
            <span>Courses: '.count($courses).'</span>
            <span>Generated: '.date('d-m-Y H:i').'</span>
        </div>
        <table><tr><th>Emp Code</th><th>Name</th><th>Email</th><th>Department</th><th>Designation</th>'.$courseHeaders.'</tr>'.$rows.'</table>
        <div class="footer">Calibehr LMS - Confidential - '.date('d-m-Y H:i').'</div>';

        $filename = 'Matrix_Report_'.date('Ymd_His').'.pdf';
        $mpdf     = new \Mpdf\Mpdf(['orientation' => 'L', 'margin_left' => 8, 'margin_right' => 8, 'margin_top' => 8, 'margin_bottom' => 8]);
        $mpdf->SetTitle('Matrix Report');
        $mpdf->WriteHTML($html);
        return response($mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // =========================================================================
    // MATRIX REPORT — ECR Filter Lists
    // =========================================================================

    /** GET /api/reports/matrix/filters */
    public function getMatrixFilters(Request $request)
    {
        $result = ['companies' => [], 'departments' => [], 'verticals' => [], 'source' => 'ecr'];

        try {
            $serverName = env('ECR_SQLSRV_HOST', 'tcp:172.16.1.30,1433');
            $config     = ['Database' => env('ECR_SQLSRV_DB', 'ECR_New'), 'Uid' => env('ECR_SQLSRV_USER', 'nbg_sa'), 'PWD' => env('ECR_SQLSRV_PASS', ''), 'Encrypt' => 0, 'TrustServerCertificate' => 1];
            $conn       = sqlsrv_connect($serverName, $config);
            if ($conn === false) return $this->out($result, 0, 'ECR server unreachable.');

            $stmt = sqlsrv_query($conn, "SELECT ID, CompanyName FROM [ECR_New].[dbo].[Company] WHERE CompanyName IS NOT NULL ORDER BY CompanyName");
            if ($stmt) while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $result['companies'][] = ['id' => $row['ID'], 'name' => $row['CompanyName']];

            $stmt = sqlsrv_query($conn, "SELECT ID, DeptName FROM [ECR_New].[dbo].[Department] WHERE DeptName IS NOT NULL ORDER BY DeptName");
            if ($stmt) while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $result['departments'][] = ['id' => $row['ID'], 'name' => $row['DeptName']];

            $stmt = sqlsrv_query($conn, "SELECT ID, DivisionName FROM [ECR_New].[dbo].[Division] WHERE DivisionName IS NOT NULL ORDER BY DivisionName");
            if ($stmt) while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $result['verticals'][] = ['id' => $row['ID'], 'name' => $row['DivisionName']];

            sqlsrv_close($conn);
        } catch (\Throwable $e) {
            return $this->out($result, 0, 'ECR error: '.$e->getMessage());
        }

        return $this->out($result, 1, 'OK');
    }
}