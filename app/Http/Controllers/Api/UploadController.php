<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    private function out($data, int $code, string $msg)
    {
        return response()->json(['data' => $data, 'code' => $code, 'message' => $msg]);
    }

    // =========================================================================
    // Upload Category Image
    // =========================================================================

    /** POST /api/upload/category */
    public function uploadCategoryImage(Request $request)
    {
        $request->validate([
            'image'      => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'categoryID' => 'required|integer',
        ]);

        $file     = $request->file('image');
        $ext      = $file->getClientOriginalExtension();
        $filename = $request->categoryID . '.' . $ext;
        $path     = 'categories/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file), 'public');





        $url = config('filesystems.disks.s3.url') . '/' . $path;

        // Update database
        \DB::table('categories_master')
            ->where('id', $request->categoryID)
            ->update(['image_url' => $url, 'updated_by' => $request->user()->id, 'updated_on' => now()]);

        return $this->out(['url' => $url], 1, 'Image uploaded successfully.');
    }

    // =========================================================================
    // Upload Course Image
    // =========================================================================

    /** POST /api/upload/course-image */
    public function uploadCourseImage(Request $request)
    {
        $request->validate([
            'image'    => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'courseID' => 'required|integer',
        ]);

        $file     = $request->file('image');
        $ext      = $file->getClientOriginalExtension();
        $filename = $request->courseID . '.' . $ext;
        $path     = 'course/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file), 'public');

        $url = config('filesystems.disks.s3.url') . '/' . $path;

        \DB::table('courses')
            ->where('id', $request->courseID)
            ->update(['image_url' => $url, 'updated_by' => $request->user()->id, 'updated_on' => now()]);

        return $this->out(['url' => $url], 1, 'Course image uploaded.');
    }

    // =========================================================================
    // Upload PDF Topic
    // =========================================================================

    /** POST /api/upload/pdf */
    public function uploadPDF(Request $request)
    {
        $request->validate([
            'file'      => 'required|mimes:pdf|max:20480',
            'courseID'  => 'required|integer',
            'chapterID' => 'required|integer',
            'topicID'   => 'required|integer',
        ]);

        $file     = $request->file('file');
        $filename = time() . '_' . $request->topicID . '.pdf';
        $path     = 'course/' . $request->courseID . '/chapter/' . $request->chapterID . '/pdf/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file), 'public');

        $url = config('filesystems.disks.s3.url') . '/' . $path;

        \DB::table('course_topics')
            ->where('id', $request->topicID)
            ->update(['file_url' => $url, 'updated_by' => $request->user()->id, 'updated_on' => now()]);

        return $this->out(['url' => $url], 1, 'PDF uploaded successfully.');
    }

    // =========================================================================
    // Upload Video Topic
    // =========================================================================

    /** POST /api/upload/video */
    public function uploadVideo(Request $request)
    {
        $request->validate([
            'file'      => 'required|mimes:mp4,mov,avi|max:512000',
            'courseID'  => 'required|integer',
            'chapterID' => 'required|integer',
            'topicID'   => 'required|integer',
        ]);

        $file     = $request->file('file');
        $filename = time() . '_' . $request->topicID . '.mp4';
        $path     = 'course/' . $request->courseID . '/chapter/' . $request->chapterID . '/video/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file), 'public');

        $url = config('filesystems.disks.s3.url') . '/' . $path;

        \DB::table('course_topics')
            ->where('id', $request->topicID)
            ->update(['file_url' => $url, 'updated_by' => $request->user()->id, 'updated_on' => now()]);

        return $this->out(['url' => $url], 1, 'Video uploaded successfully.');
    }

    // =========================================================================
    // Upload Interview Response Video
    // =========================================================================

    /** POST /api/upload/interview-video */
    public function uploadInterviewVideo(Request $request)
    {
        $request->validate([
            'file'        => 'required|mimes:mp4,mov,avi|max:512000',
            'interviewID' => 'required|integer',
            'inviteID'    => 'required|integer',
            'responseID'  => 'required|integer',
        ]);

        $file     = $request->file('file');
        $attempt  = time();
        $filename = $attempt . '_Attempt1.mp4';
        $path     = 'uploads/interview/' . $request->interviewID . '/' . $request->inviteID . '/' . $request->responseID . '/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file), 'public');

        $url = config('filesystems.disks.s3.url') . '/' . $path;

        \DB::table('interview_response_videos')->insert([
            'response_id' => $request->responseID,
            'src'         => $url,
            'added_on'    => now(),
        ]);

        return $this->out(['url' => $url], 1, 'Interview video uploaded.');
    }

    // =========================================================================
    // Upload User Profile Photo
    // =========================================================================

    /** POST /api/upload/profile-photo */
    public function uploadProfilePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $uid      = $request->user()->id;
        $file     = $request->file('photo');
        $ext      = $file->getClientOriginalExtension();
        $filename = time() . 'profile.' . $ext;
        $path     = 'uploads/photo/' . $filename;

        Storage::disk('s3')->put($path, file_get_contents($file), 'public');

        $url = config('filesystems.disks.s3.url') . '/' . $path;

        \DB::table('users')
            ->where('id', $uid)
            ->update(['emp_photo' => $url, 'updated_on' => now()]);

        return $this->out(['url' => $url], 1, 'Profile photo uploaded.');
    }
}
