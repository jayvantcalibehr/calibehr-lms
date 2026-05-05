<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MigrationSeeder
 *
 * Reads from:
 *   - calibehr_mitra  (old Mitra database)
 *   - calibehr_lms    (old LMS database)
 *
 * Writes into:
 *   - calibehr_lms_new  (new merged Laravel database, configured in .env as DB_DATABASE)
 *
 * Run: php artisan db:seed --class=MigrationSeeder
 *
 * IMPORTANT: Configure two extra DB connections in config/database.php:
 *   'mitra_old' => connects to calibehr_mitra
 *   'lms_old'   => connects to calibehr_lms
 */
class MigrationSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== Starting Full Data Migration ===');
        $this->command->info('Source 1: calibehr_mitra (Mitra DB)');
        $this->command->info('Source 2: calibehr_lms   (LMS DB)');
        $this->command->newLine();

        // Disable FK checks for clean bulk insert
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->migrateUsers();
        $this->migrateRoles();
        $this->migrateCategories();
        $this->migrateCourseMaster();
        $this->migrateCourses();
        $this->migrateCourseChapters();
        $this->migrateTopicTypeMaster();
        $this->migrateCourseTopics();
        $this->migrateTopicResourceLinks();
        $this->migrateTopicQuestions();
        $this->migrateTopicQuestionOptions();
        $this->migrateTopicQuestionAnswers();
        $this->migrateTopicQuestionAnswersList();
        $this->migrateCourseLearnersAndStatus();
        $this->migrateCourseFeedback();
        $this->migrateQuizzes();
        $this->migrateInterviews();
        $this->migrateCandidates();
        $this->migrateLeaderboard();
        $this->migrateWishlists();
        $this->migrateNotifications();
        $this->migrateLdapConfigs();
        $this->migrateAppVersions();
        $this->migrateLogs();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->newLine();
        $this->command->info('=== Migration Complete ===');
    }

    // -------------------------------------------------------------------------
    // USERS  (cm_login + lms_login merged)
    // -------------------------------------------------------------------------
    private function migrateUsers(): void
    {
        $this->command->info('[1/24] Migrating users (cm_login + lms_login)...');

        $mitraUsers = DB::connection('mitra_old')->table('cm_login')->get();

        // Build lms_login index for lastVisitedOn
        $lmsLogins = DB::connection('lms_old')->table('lms_login')
            ->get()->keyBy('mitraID');

        $chunk = [];
        foreach ($mitraUsers as $u) {
            $lms = $lmsLogins->get($u->id);

            $lastVisited = null;
            if ($lms && $lms->lastVisitedOn && $lms->lastVisitedOn !== '0000-00-00 00:00:00') {
                $lastVisited = $lms->lastVisitedOn;
            }

            $chunk[] = [
                'id'                 => $u->id,
                'on_roll'            => $u->onRoll,
                'emp_first_name'     => $u->emp_first_name,
                'emp_middle_name'    => $u->emp_middle_name ?? '',
                'emp_last_name'      => $u->emp_last_name ?? '',
                'emp_username'       => $u->emp_username ?? '',
                'emp_department'     => $u->emp_department ?? 0,
                'emp_phone'          => $u->emp_phone ?? '',
                'emp_aadhar'         => $u->emp_aadhar ?? '',
                'emp_aadhar_status'  => $u->emp_aadhar_status ?? 0,
                'emp_dob'            => $u->emp_dob ?? '',
                'emp_pan'            => $u->emp_pan ?? '',
                'emp_photo'          => $u->emp_photo ?? '',
                'emp_email'          => $u->emp_email ?? '',
                'emp_code'           => $u->emp_code,
                'emp_designation'    => $u->emp_designation ?? '',
                'emp_doj'            => $u->emp_doj ?? '',
                'emp_location'       => $u->emp_location ?? '',
                'emp_pfno'           => $u->emp_pfno ?? '',
                'emp_uanno'          => $u->emp_uanno ?? '',
                'emp_esicno'         => $u->emp_esicno ?? '',
                'emp_status_job'     => $u->emp_status_job ?? '',
                'esic_tic_uploade'   => $u->esic_tic_uploade ?? '',
                'emp_password'       => $u->emp_password,
                'password_changed'   => $u->password_changed ?? 0,
                'emp_type'           => $u->emp_type ?? 'A',
                'emp_role'           => $u->emp_role ?? 0,
                'emp_rm'             => $u->emp_rm ?? 0,
                'emp_rm_role'        => $u->emp_rm_role ?? 0,
                'emp_status'         => $u->emp_status ?? 'A',
                'emp_active'         => $u->emp_active ?? 'A',
                'emp_client'         => $u->emp_client ?? 0,
                'emp_client_department' => $u->emp_client_department ?? 0,
                'mitra_status'       => $lms ? $lms->mitraStatus : 0,
                'mitra_active'       => $lms ? $lms->mitraActive : 0,
                'last_visited_on'    => $lastVisited,
                'emp_add_date'       => $u->emp_add_date ?? now(),
                'updated_on'         => null,
            ];

            if (count($chunk) >= 500) {
                DB::table('users')->insertOrIgnore($chunk);
                $chunk = [];
            }
        }
        if ($chunk) {
            DB::table('users')->insertOrIgnore($chunk);
        }

        $this->command->info('   → ' . count($mitraUsers) . ' users migrated.');
    }

    // -------------------------------------------------------------------------
    // ROLES
    // -------------------------------------------------------------------------
    private function migrateRoles(): void
    {
        $this->command->info('[2/24] Migrating roles_master + user_roles...');

        $roles = DB::connection('lms_old')->table('lms_roles_master')->get();
        foreach ($roles as $r) {
            DB::table('roles_master')->insertOrIgnore([
                'id'       => $r->id,
                'name'     => $r->name,
                'status'   => $r->status,
                'added_on' => $r->addedOn !== '0000-00-00 00:00:00' ? $r->addedOn : null,
            ]);
        }

        $userRoles = DB::connection('lms_old')->table('lms_login_roles')->get();
        foreach ($userRoles as $lr) {
            DB::table('user_roles')->insertOrIgnore([
                'id'       => $lr->id,
                'user_id'  => $lr->mitraID,
                'role_id'  => $lr->roleID,
                'added_by' => $lr->addedBy,
                'added_on' => $lr->addedOn !== '0000-00-00 00:00:00' ? $lr->addedOn : null,
            ]);
        }
        $this->command->info('   → ' . count($roles) . ' roles, ' . count($userRoles) . ' user_roles migrated.');
    }

    // -------------------------------------------------------------------------
    // CATEGORIES
    // -------------------------------------------------------------------------
    private function migrateCategories(): void
    {
        $this->command->info('[3/24] Migrating categories_master...');
        $rows = DB::connection('lms_old')->table('lms_categories_master')->get();
        foreach ($rows as $r) {
            DB::table('categories_master')->insertOrIgnore([
                'id'         => $r->id,
                'name'       => $r->name,
                'image_url'  => $r->imageUrl,
                'added_by'   => $r->addedBy,
                'added_on'   => $this->ts($r->addedOn),
                'updated_by' => $r->updatedBy,
                'updated_on' => $this->ts($r->updatedOn),
                'status'     => $r->status,
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' categories migrated.');
    }

    // -------------------------------------------------------------------------
    // COURSE MASTER
    // -------------------------------------------------------------------------
    private function migrateCourseMaster(): void
    {
        $this->command->info('[4/24] Migrating course_master...');
        $rows = DB::connection('lms_old')->table('lms_course_master')->get();
        foreach ($rows as $r) {
            DB::table('course_master')->insertOrIgnore([
                'id'     => $r->id,
                'name'   => $r->name,
                'status' => $r->status,
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' course types migrated.');
    }

    // -------------------------------------------------------------------------
    // COURSES
    // -------------------------------------------------------------------------
    private function migrateCourses(): void
    {
        $this->command->info('[5/24] Migrating courses...');
        $rows = DB::connection('lms_old')->table('lms_course')->get();
        foreach ($rows as $r) {
            DB::table('courses')->insertOrIgnore([
                'id'                  => $r->id,
                'name'                => $r->name,
                'description'         => $r->description,
                'what_will_you_learn' => $r->whatWillYouLearn,
                'pre_requisites'      => $r->preRequisites,
                'trainer_details'     => $r->trainerDetails,
                'image_url'           => $r->imageURL,
                'category_id'         => $r->categoryID,
                'type'                => $r->type,
                'points'              => $r->points,
                'added_by'            => $r->addedBy,
                'added_on'            => $this->ts($r->addedOn),
                'updated_on'          => $this->ts($r->updatedOn),
                'updated_by'          => $r->updatedBy,
                'visibility'          => $r->visibility,
                'status'              => $r->status,
                'featured'            => $r->featured,
                'featured_by'         => $r->featuredBy,
                'featured_on'         => $this->ts($r->featuredOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' courses migrated.');
    }

    // -------------------------------------------------------------------------
    // COURSE CHAPTERS
    // -------------------------------------------------------------------------
    private function migrateCourseChapters(): void
    {
        $this->command->info('[6/24] Migrating course_chapters...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters')->get();
        foreach ($rows as $r) {
            DB::table('course_chapters')->insertOrIgnore([
                'id'          => $r->id,
                'course_id'   => $r->courseID,
                'name'        => $r->name,
                'description' => $r->description,
                'status'      => $r->status,
                'added_by'    => $r->addedBy,
                'added_on'    => $this->ts($r->addedOn),
                'updated_by'  => $r->updatedBy,
                'updated_on'  => $this->ts($r->updatedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' chapters migrated.');
    }

    // -------------------------------------------------------------------------
    // TOPIC TYPE MASTER
    // -------------------------------------------------------------------------
    private function migrateTopicTypeMaster(): void
    {
        $this->command->info('[7/24] Migrating topic_type_master...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters_topics_type_master')->get();
        foreach ($rows as $r) {
            DB::table('topic_type_master')->insertOrIgnore([
                'id'         => $r->id,
                'name'       => $r->name,
                'icon'       => $r->icon,
                'status'     => $r->status,
                'added_on'   => $this->ts($r->addedOn),
                'updated_on' => $this->ts($r->updatedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' topic types migrated.');
    }

    // -------------------------------------------------------------------------
    // COURSE TOPICS
    // -------------------------------------------------------------------------
    private function migrateCourseTopics(): void
    {
        $this->command->info('[8/24] Migrating course_topics...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters_topics')->get();
        foreach ($rows as $r) {
            DB::table('course_topics')->insertOrIgnore([
                'id'                  => $r->id,
                'course_id'           => $r->courseID,
                'chapter_id'          => $r->chapterID,
                'name'                => $r->name,
                'type'                => $r->type,
                'description'         => $r->description,
                'information'         => $r->information,
                'passing_percentage'  => $r->passingPercentage,
                'number_of_attempt'   => $r->numberOfAttempt,
                'duration'            => $r->duration,
                'file_url'            => $r->fileURL,
                'video_type'          => $r->videoType,
                'status'              => $r->status,
                'added_by'            => $r->addedBy,
                'added_on'            => $this->ts($r->addedOn),
                'updated_by'          => $r->updatedBy,
                'updated_on'          => $this->ts($r->updatedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' topics migrated.');
    }

    // -------------------------------------------------------------------------
    // TOPIC RESOURCE LINKS
    // -------------------------------------------------------------------------
    private function migrateTopicResourceLinks(): void
    {
        $this->command->info('[9/24] Migrating topic_resource_links...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters_topics_resource_link')->get();
        foreach ($rows as $r) {
            DB::table('topic_resource_links')->insertOrIgnore([
                'id'          => $r->id,
                'course_id'   => $r->courseID,
                'chapter_id'  => $r->chapterID,
                'topic_id'    => $r->topicID,
                'name'        => $r->name,
                'description' => $r->description,
                'link'        => $r->link,
                'status'      => $r->status,
                'updated_on'  => $this->ts($r->updatedOn),
                'added_by'    => $r->addedBy,
                'added_on'    => $this->ts($r->addedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' resource links migrated.');
    }

    // -------------------------------------------------------------------------
    // TOPIC QUESTIONS
    // -------------------------------------------------------------------------
    private function migrateTopicQuestions(): void
    {
        $this->command->info('[10/24] Migrating topic_questions...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters_topics_questions')->get();
        foreach ($rows as $r) {
            DB::table('topic_questions')->insertOrIgnore([
                'id'             => $r->id,
                'course_id'      => $r->courseID,
                'chapter_id'     => $r->chapterID,
                'topic_id'       => $r->topicID,
                'question_order' => $r->question_order,
                'question_text'  => $r->questions_text,
                'point'          => $r->point,
                'question_type'  => $r->questionType,
                'added_by'       => $r->addedBy,
                'added_on'       => $this->ts($r->addedOn),
                'updated_by'     => $r->updatedBy,
                'updated_on'     => $this->ts($r->updatedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' topic questions migrated.');
    }

    private function migrateTopicQuestionOptions(): void
    {
        $this->command->info('[11/24] Migrating topic_question_options...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters_topics_questions_options')->get();
        foreach ($rows as $r) {
            DB::table('topic_question_options')->insertOrIgnore([
                'id'          => $r->id,
                'question_id' => $r->questionID,
                'option_text' => $r->optionText,
                'answer'      => $r->answer,
                'value'       => $r->value,
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' topic question options migrated.');
    }

    private function migrateTopicQuestionAnswers(): void
    {
        $this->command->info('[12/24] Migrating topic_question_answers...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters_topics_questions_answers')->get();
        foreach ($rows as $r) {
            DB::table('topic_question_answers')->insertOrIgnore([
                'id'              => $r->id,
                'course_id'       => $r->courseID,
                'chapter_id'      => $r->chapterID,
                'topic_id'        => $r->topicID,
                'points'          => $r->points,
                'total_points'    => $r->totalPoints,
                'correct_answers' => $r->correctAnswers,
                'total_questions' => $r->totalQuestions,
                'percentage'      => $r->percentage,
                'answered_by'     => $r->answeredBy,
                'answered_on'     => $this->ts($r->answeredOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' topic answers migrated.');
    }

    private function migrateTopicQuestionAnswersList(): void
    {
        $this->command->info('[13/24] Migrating topic_question_answers_list...');
        $rows = DB::connection('lms_old')->table('lms_course_chapters_topics_questions_answers_list')->get();
        foreach ($rows as $r) {
            DB::table('topic_question_answers_list')->insertOrIgnore([
                'id'          => $r->id,
                'answer_id'   => $r->answerID,
                'question_id' => $r->questionID,
                'option_id'   => $r->optionID,
                'result'      => $r->result,
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' answer list items migrated.');
    }

    // -------------------------------------------------------------------------
    // COURSE LEARNERS + TOPIC STATUS
    // -------------------------------------------------------------------------
    private function migrateCourseLearnersAndStatus(): void
    {
        $this->command->info('[14/24] Migrating course_learners...');
        $rows = DB::connection('lms_old')->table('lms_course_learners')->get();
        foreach ($rows as $r) {
            DB::table('course_learners')->insertOrIgnore([
                'id'           => $r->id,
                'course_id'    => $r->courseID,
                'learner_id'   => $r->learnerID,
                'status'       => $r->status,
                'added_by'     => $r->addedBy,
                'added_on'     => $this->ts($r->addedOn),
                'started_on'   => $this->ts($r->startedOn),
                'completed'    => $r->completed,
                'completed_on' => $this->ts($r->completedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' course learners migrated.');

        $this->command->info('[14b] Migrating course_learner_topic_status...');
        $rows2 = DB::connection('lms_old')->table('lms_course_learners_status')->get();
        foreach ($rows2 as $r) {
            DB::table('course_learner_topic_status')->insertOrIgnore([
                'id'           => $r->id,
                'course_id'    => $r->courseID,
                'topic_id'     => $r->topicID,
                'user_id'      => $r->userID,
                'time_spent'   => $r->timeSpent,
                'started_on'   => $this->ts($r->startedOn),
                'completed'    => $r->completed,
                'completed_on' => $this->ts($r->completedOn),
                'user_marked'  => $r->userMarked,
            ]);
        }
        $this->command->info('   → ' . count($rows2) . ' topic status rows migrated.');
    }

    // -------------------------------------------------------------------------
    // COURSE FEEDBACK
    // -------------------------------------------------------------------------
    private function migrateCourseFeedback(): void
    {
        $this->command->info('[15/24] Migrating course feedback tables...');

        $fq = DB::connection('lms_old')->table('lms_course_feedback_questions')->get();
        foreach ($fq as $r) {
            DB::table('course_feedback_questions')->insertOrIgnore([
                'id'            => $r->id,
                'course_id'     => $r->courseID,
                'question_text' => $r->questionText,
                'status'        => $r->status,
                'added_by'      => $r->addedBy,
                'added_on'      => $this->dt($r->addedOn),
                'updated_by'    => $r->updatedBy,
                'updated_on'    => $this->dt($r->updatedOn),
            ]);
        }

        $fa = DB::connection('lms_old')->table('lms_course_feedback_questions_answer')->get();
        foreach ($fa as $r) {
            DB::table('course_feedback_answers')->insertOrIgnore([
                'id'                   => $r->id,
                'course_id'            => $r->courseID,
                'user_id'              => $r->userID,
                'feedback_question_id' => $r->feedbackQuestionID,
                'rating'               => $r->rating,
                'added_on'             => $this->dt($r->addedOn),
            ]);
        }

        $fr = DB::connection('lms_old')->table('lms_feedback')->get();
        foreach ($fr as $r) {
            DB::table('course_feedback_ratings')->insertOrIgnore([
                'id'         => $r->id,
                'course_id'  => $r->courseID,
                'user_id'    => $r->userID,
                'star'       => $r->star,
                'comment'    => $r->comment,
                'added_on'   => $this->ts($r->addedOn),
                'status'     => $r->status,
                'updated_by' => $r->updatedBy,
                'updated_on' => $this->ts($r->updatedOn),
            ]);
        }

        $fc = DB::connection('lms_old')->table('lms_feedback_comment')->get();
        foreach ($fc as $r) {
            DB::table('course_feedback_comments')->insertOrIgnore([
                'id'          => $r->id,
                'feedback_id' => $r->feedbackID,
                'reply'       => $r->reply,
                'added_by'    => $r->addedBy,
                'added_on'    => $this->ts($r->addedOn),
                'status'      => $r->status,
            ]);
        }

        $this->command->info('   → ' . count($fq) . ' feedback questions, ' . count($fa) . ' answers, ' . count($fr) . ' ratings, ' . count($fc) . ' comments.');
    }

    // -------------------------------------------------------------------------
    // QUIZZES
    // -------------------------------------------------------------------------
    private function migrateQuizzes(): void
    {
        $this->command->info('[16/24] Migrating quiz tables...');

        $quizzes = DB::connection('lms_old')->table('lms_quiz')->get();
        foreach ($quizzes as $r) {
            DB::table('quizzes')->insertOrIgnore([
                'id'                => $r->id,
                'name'              => $r->name,
                'description'       => $r->description,
                'type'              => $r->type,
                'points'            => $r->points,
                'passing_percentage'=> $r->passingPercentage,
                'number_of_attempt' => $r->numberOfAttempt,
                'added_by'          => $r->addedBy,
                'added_on'          => $this->ts($r->addedOn),
                'updated_on'        => $this->ts($r->updatedOn),
                'updated_by'        => $r->updatedBy,
                'visibility'        => $r->visibility,
                'time'              => $r->time,
                'show_marks'        => $r->showMarks,
                'status'            => $r->status,
            ]);
        }

        $qq = DB::connection('lms_old')->table('lms_quiz_questions')->get();
        foreach ($qq as $r) {
            DB::table('quiz_questions')->insertOrIgnore([
                'id'             => $r->id,
                'quiz_id'        => $r->quizID,
                'question_order' => $r->question_order,
                'question_text'  => $r->questions_text,
                'point'          => $r->point,
                'question_type'  => $r->questionType,
                'added_by'       => $r->addedBy,
                'added_on'       => $this->ts($r->addedOn),
                'updated_by'     => $r->updatedBy,
                'updated_on'     => $this->ts($r->updatedOn),
            ]);
        }

        $qo = DB::connection('lms_old')->table('lms_quiz_questions_options')->get();
        foreach ($qo as $r) {
            DB::table('quiz_question_options')->insertOrIgnore([
                'id'          => $r->id,
                'question_id' => $r->questionID,
                'option_text' => $r->optionText,
                'answer'      => $r->answer,
                'value'       => $r->value,
            ]);
        }

        $qi = DB::connection('lms_old')->table('lms_quiz_information')->get();
        foreach ($qi as $r) {
            DB::table('quiz_information_questions')->insertOrIgnore([
                'id'             => $r->id,
                'quiz_id'        => $r->quizID,
                'question_order' => $r->question_order,
                'question_text'  => $r->questions_text,
                'question_type'  => $r->questionType,
                'added_by'       => $r->addedBy,
                'added_on'       => $this->ts($r->addedOn),
                'updated_by'     => $r->updatedBy,
                'updated_on'     => $this->ts($r->updatedOn),
            ]);
        }

        $qia = DB::connection('lms_old')->table('lms_quiz_information_answers')->get();
        foreach ($qia as $r) {
            DB::table('quiz_information_answers')->insertOrIgnore([
                'id'             => $r->id,
                'quiz_id'        => $r->quizID,
                'information_id' => $r->informationID,
                'answer_id'      => $r->answerID,
                'value'          => $r->value,
                'added_by'       => $r->added_by,
                'added_on'       => $this->ts($r->added_on),
            ]);
        }

        $qinv = DB::connection('lms_old')->table('lms_quiz_invites')->get();
        foreach ($qinv as $r) {
            DB::table('quiz_invites')->insertOrIgnore([
                'id'           => $r->id,
                'quiz_id'      => $r->quizID,
                'email'        => $r->email,
                'added_by'     => $r->added_by,
                'added_on'     => $this->ts($r->added_on),
                'sent_status'  => $r->sentStaus,
                'sent_on'      => $this->ts($r->sent_on),
                'status'       => $r->status,
                'completed_on' => $this->ts($r->completed_on),
            ]);
        }

        $qa = DB::connection('lms_old')->table('lms_quiz_answers')->get();
        foreach ($qa as $r) {
            DB::table('quiz_answers')->insertOrIgnore([
                'id'              => $r->id,
                'quiz_id'         => $r->quizID,
                'invite_id'       => $r->inviteID,
                'points'          => $r->points,
                'total_points'    => $r->totalPoints,
                'correct_answers' => $r->correctAnswers,
                'total_questions' => $r->totalQuestions,
                'percentage'      => $r->percentage,
                'pass'            => $r->pass,
                'answered_on'     => $this->ts($r->answeredOn),
                'time_up'         => $r->timeUp,
                'switch_tabs'     => $r->switchTabs,
                'ip'              => $r->ip,
                'user_agent'      => $r->userAgent,
                'added_on'        => $this->ts($r->added_on),
            ]);
        }

        $qal = DB::connection('lms_old')->table('lms_quiz_answers_list')->get();
        foreach ($qal as $r) {
            DB::table('quiz_answer_items')->insertOrIgnore([
                'id'          => $r->id,
                'answer_id'   => $r->answerID,
                'question_id' => $r->questionID,
                'option_id'   => $r->optionID,
                'result'      => $r->result,
            ]);
        }

        $qfr = DB::connection('lms_old')->table('lms_quiz_feedback')->get();
        foreach ($qfr as $r) {
            DB::table('quiz_feedback_ratings')->insertOrIgnore([
                'id'         => $r->id,
                'quiz_id'    => $r->quizID,
                'user_id'    => $r->userID,
                'star'       => $r->star,
                'comment'    => $r->comment,
                'added_on'   => $this->ts($r->addedOn),
                'status'     => $r->status,
                'updated_by' => $r->updatedBy,
                'updated_on' => $this->ts($r->updatedOn),
            ]);
        }

        $qfc = DB::connection('lms_old')->table('lms_quiz_feedback_comment')->get();
        foreach ($qfc as $r) {
            DB::table('quiz_feedback_comments')->insertOrIgnore([
                'id'          => $r->id,
                'feedback_id' => $r->feedbackID,
                'reply'       => $r->reply,
                'added_by'    => $r->addedBy,
                'added_on'    => $this->ts($r->addedOn),
                'status'      => $r->status,
            ]);
        }

        $this->command->info('   → ' . count($quizzes) . ' quizzes, ' . count($qq) . ' questions, ' . count($qinv) . ' invites, ' . count($qa) . ' answers.');
    }

    // -------------------------------------------------------------------------
    // INTERVIEWS
    // -------------------------------------------------------------------------
    private function migrateInterviews(): void
    {
        $this->command->info('[17/24] Migrating interview tables...');

        $iv = DB::connection('lms_old')->table('lms_interview')->get();
        foreach ($iv as $r) {
            DB::table('interviews')->insertOrIgnore([
                'id'          => $r->id,
                'name'        => $r->name,
                'description' => $r->description,
                'type'        => $r->type,
                'added_by'    => $r->addedBy,
                'added_on'    => $this->ts($r->addedOn),
                'updated_on'  => $this->ts($r->updatedOn),
                'updated_by'  => $r->updatedBy,
                'visibility'  => $r->visibility,
                'time'        => $r->time,
                'show_marks'  => $r->showMarks,
                'status'      => $r->status,
            ]);
        }

        $ii = DB::connection('lms_old')->table('lms_interview_invite')->get();
        foreach ($ii as $r) {
            DB::table('interview_invites')->insertOrIgnore([
                'id'            => $r->id,
                'unique_id'     => $r->uniqueID,
                'interview_id'  => $r->interviewID,
                'email'         => $r->email,
                'completed'     => $r->completed,
                'complete_on'   => $this->ts($r->completeOn),
                'started_on'    => $this->ts($r->startedOn),
                'expire_on'     => $this->ts($r->expireOn),
                'invite_status' => $r->inviteStatus,
                'invited_on'    => $this->ts($r->invitedOn),
                'added_by'      => $r->addedBy,
                'added_on'      => $this->ts($r->addedOn),
            ]);
        }

        $iq = DB::connection('lms_old')->table('lms_interview_questions')->get();
        foreach ($iq as $r) {
            DB::table('interview_questions')->insertOrIgnore([
                'id'                 => $r->id,
                'interview_id'       => $r->interviewID,
                'question_text'      => $r->questionText,
                'question_time'      => $r->questionTime,
                'question_view_time' => $r->questionViewTime,
                'status'             => $r->status,
                'added_by'           => $r->addedBy,
                'added_on'           => $this->ts($r->addedOn),
                'updated_by'         => $r->updatedBy,
                'updated_on'         => $this->ts($r->updatedOn),
            ]);
        }

        $ir = DB::connection('lms_old')->table('lms_interview_response')->get();
        foreach ($ir as $r) {
            DB::table('interview_responses')->insertOrIgnore([
                'id'           => $r->id,
                'interview_id' => $r->interviewID,
                'invite_id'    => $r->inviteID,
                'question_id'  => $r->questionID,
                'started_on'   => $this->ts($r->startedOn),
                'submitted'    => $r->submitted,
                'submitted_on' => $this->ts($r->submittedOn),
            ]);
        }

        $irv = DB::connection('lms_old')->table('lms_interview_response_video')->get();
        foreach ($irv as $r) {
            DB::table('interview_response_videos')->insertOrIgnore([
                'id'          => $r->id,
                'response_id' => $r->responseID,
                'src'         => $r->src,
                'added_on'    => $this->ts($r->addedOn),
            ]);
        }
        $this->command->info('   → ' . count($iv) . ' interviews, ' . count($ii) . ' invites, ' . count($iq) . ' questions.');
    }

    // -------------------------------------------------------------------------
    // CANDIDATES
    // -------------------------------------------------------------------------
    private function migrateCandidates(): void
    {
        $this->command->info('[18/24] Migrating candidates...');
        $rows = DB::connection('lms_old')->table('lms_candidate')->get();
        foreach ($rows as $r) {
            DB::table('candidates')->insertOrIgnore([
                'id'       => $r->id,
                'email'    => $r->email,
                'added_by' => $r->addedBy,
                'added_on' => $this->ts($r->addedOn),
            ]);
        }

        $ci = DB::connection('lms_old')->table('lms_candidate_invites')->get();
        foreach ($ci as $r) {
            DB::table('candidate_invites')->insertOrIgnore([
                'id'           => $r->id,
                'candidate_id' => $r->candidateID,
                'invite_id'    => $r->inviteID,
                'type'         => $r->type,
                'added_by'     => $r->addedBy,
                'added_on'     => $this->ts($r->addedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' candidates, ' . count($ci) . ' invites.');
    }

    // -------------------------------------------------------------------------
    // LEADERBOARD
    // -------------------------------------------------------------------------
    private function migrateLeaderboard(): void
    {
        $this->command->info('[19/24] Migrating leaderboard...');
        $rows = DB::connection('lms_old')->table('lms_leaderboard')->get();
        foreach ($rows as $r) {
            DB::table('leaderboard')->insertOrIgnore([
                'id'                     => $r->id,
                'user_id'                => $r->userID,
                'points'                 => $r->points,
                'emp_client'             => $r->emp_client,
                'emp_client_department'  => $r->emp_client_department,
                'added_on'               => $this->ts($r->addedOn),
                'updated_on'             => $this->ts($r->updatedOn),
            ]);
        }

        $log = DB::connection('lms_old')->table('lms_leaderboard_log')->get();
        foreach ($log as $r) {
            DB::table('leaderboard_log')->insertOrIgnore([
                'id'        => $r->id,
                'user_id'   => $r->userid,
                'points'    => $r->points,
                'course_id' => $r->courseID,
                'added_on'  => $this->ts($r->addedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' leaderboard entries, ' . count($log) . ' log rows.');
    }

    // -------------------------------------------------------------------------
    // WISHLISTS
    // -------------------------------------------------------------------------
    private function migrateWishlists(): void
    {
        $this->command->info('[20/24] Migrating wishlists...');
        $rows = DB::connection('lms_old')->table('lms_wishlist')->get();
        foreach ($rows as $r) {
            DB::table('wishlists')->insertOrIgnore([
                'id'        => $r->id,
                'user_id'   => $r->userID,
                'course_id' => $r->courseID,
                'added_on'  => $this->ts($r->addedOn),
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' wishlist items migrated.');
    }

    // -------------------------------------------------------------------------
    // NOTIFICATIONS
    // -------------------------------------------------------------------------
    private function migrateNotifications(): void
    {
        $this->command->info('[21/24] Migrating push_notification_tokens...');
        $rows = DB::connection('lms_old')->table('lms_notification')->get();
        foreach ($rows as $r) {
            DB::table('push_notification_tokens')->insertOrIgnore([
                'id'          => $r->id,
                'emp_id'      => $r->emp_id,
                'device_type' => $r->device_type,
                'token'       => $r->token,
                'added_on'    => $r->added_on,
                'updated_on'  => $r->updated_on,
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' tokens migrated.');
    }

    // -------------------------------------------------------------------------
    // LDAP CONFIGS
    // -------------------------------------------------------------------------
    private function migrateLdapConfigs(): void
    {
        $this->command->info('[22/24] Migrating ldap_configs...');
        $rows = DB::connection('lms_old')->table('lms_ldap')->get();
        foreach ($rows as $r) {
            DB::table('ldap_configs')->insertOrIgnore([
                'id'         => $r->id,
                'comment'    => $r->comment,
                'host'       => $r->host,
                'domain'     => $r->domain,
                'port'       => $r->port,
                'group'      => $r->group,
                'added_by'   => $r->addedBy,
                'added_on'   => $r->addedOn,
                'updated_by' => $r->updatedBy,
                'updated_on' => $r->updatedOn,
                'deleted_by' => $r->deletedBy,
                'deleted_on' => $r->deletedOn,
                'status'     => $r->status,
                'is_deleted' => $r->isDeleted,
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' LDAP configs migrated.');
    }

    // -------------------------------------------------------------------------
    // APP VERSIONS
    // -------------------------------------------------------------------------
    private function migrateAppVersions(): void
    {
        $this->command->info('[23/24] Migrating app_versions...');
        $rows = DB::connection('lms_old')->table('lms_appversion')->get();
        foreach ($rows as $r) {
            DB::table('app_versions')->insertOrIgnore([
                'id'           => $r->id,
                'device'       => $r->device,
                'app_version'  => $r->app_version,
                'updated_date' => $r->updated_date,
            ]);
        }
        $this->command->info('   → ' . count($rows) . ' app versions migrated.');
    }

    // -------------------------------------------------------------------------
    // ADMIN AUDIT LOGS
    // -------------------------------------------------------------------------
    private function migrateLogs(): void
    {
        $this->command->info('[24/24] Migrating admin audit logs...');

        $lc = DB::connection('lms_old')->table('lms_logs_admin_course')->get();
        foreach ($lc as $r) {
            DB::table('logs_admin_course')->insert([
                'course_id'  => $r->courseID,
                'action'     => $r->action,
                'done_by'    => $r->doneBy,
                'ip'         => $r->ip,
                'user_agent' => $r->userAgent,
                'done_on'    => $this->ts($r->doneOn),
            ]);
        }

        $lf = DB::connection('lms_old')->table('lms_logs_admin_feature')->get();
        foreach ($lf as $r) {
            DB::table('logs_admin_feature')->insert([
                'course_id'  => $r->courseID,
                'action'     => $r->action,
                'done_by'    => $r->doneBy,
                'ip'         => $r->ip,
                'user_agent' => $r->userAgent,
                'done_on'    => $this->ts($r->doneOn),
            ]);
        }

        $li = DB::connection('lms_old')->table('lms_logs_admin_interview')->get();
        foreach ($li as $r) {
            DB::table('logs_admin_interview')->insert([
                'interview_id' => $r->interviewID,
                'action'       => $r->action,
                'done_by'      => $r->doneBy,
                'ip'           => $r->ip,
                'user_agent'   => $r->userAgent,
                'done_on'      => $this->ts($r->doneOn),
            ]);
        }

        $lq = DB::connection('lms_old')->table('lms_logs_admin_quiz')->get();
        foreach ($lq as $r) {
            DB::table('logs_admin_quiz')->insert([
                'quiz_id'    => $r->quizID,
                'action'     => $r->action,
                'done_by'    => $r->doneBy,
                'ip'         => $r->ip,
                'user_agent' => $r->userAgent,
                'done_on'    => $this->ts($r->doneOn),
            ]);
        }

        $this->command->info('   → ' . (count($lc) + count($lf) + count($li) + count($lq)) . ' log rows migrated.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Null-safe timestamp: returns null for MySQL zero dates */
    private function ts(?string $value): ?string
    {
        if (!$value || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
            return null;
        }
        return $value;
    }

    /** Null-safe datetime */
    private function dt(?string $value): ?string
    {
        return $this->ts($value);
    }
}
