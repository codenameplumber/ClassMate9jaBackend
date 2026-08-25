<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The whole ClassMate 9ja schema in one migration.
 *
 * Two rules shape it:
 *   1. Anything written by a student's device carries a `client_uuid`, so a
 *      retried sync upserts instead of duplicating. The device can retry
 *      blindly and the server needs no deduplication logic.
 *   2. Study time is stored as events, never as a running counter. Aggregates
 *      are queries, so a bad client build can be reprocessed instead of having
 *      silently corrupted a number we can never recover.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ---------------------------------------------------------------
         | Identity
         ---------------------------------------------------------------- */
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->after('email');
            $table->string('phone')->nullable()->unique()->after('role');
            $table->foreignId('school_id')->nullable()->after('phone');
            $table->string('level')->nullable()->after('school_id');        // JSS1..SS3
            $table->string('locale')->default('en')->after('level');        // en | pcm | both
            $table->string('goal')->nullable()->after('locale');            // waec | jamb | both | school
            $table->unsignedSmallInteger('daily_goal')->default(30)->after('goal');
            $table->string('subject_slug')->nullable()->after('daily_goal'); // teacher's main subject
            $table->string('api_token', 80)->nullable()->unique()->after('remember_token');
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('state')->nullable();
            $table->string('lga')->nullable();
            $table->string('type')->default('public');
            $table->timestamps();
        });

        /* ---------------------------------------------------------------
         | Curriculum — authored once, served to every device
         ---------------------------------------------------------------- */
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->string('emoji', 16)->nullable();
            $table->string('tone', 24)->default('brand');
            $table->boolean('is_core')->default(true);
            $table->unsignedSmallInteger('pack_mb')->default(0);
            $table->unsignedSmallInteger('topic_count')->default(0);
            $table->text('blurb')->nullable();
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('ref', 32);                 // w4t1 — stable id the PWA already uses
            $table->unsignedTinyInteger('week');
            $table->string('week_title');
            $table->string('title');
            $table->unsignedSmallInteger('minutes')->default(25);
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('has_rich_lesson')->default(false);
            $table->timestamps();
            $table->unique(['subject_id', 'ref']);
            $table->index(['subject_id', 'week']);
        });

        // Keyed by (topic, locale) rather than body_en / body_pcm columns, so
        // Pidgin is a first-class locale and adding Hausa never moves the schema.
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);               // en | pcm
            $table->string('title');
            $table->json('objectives');
            $table->json('body');                      // ordered blocks: h / p / f / naija / list
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['topic_id', 'locale']);
        });

        Schema::create('classwork_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->json('options');
            $table->unsignedTinyInteger('answer_index');
            $table->text('explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 32)->unique();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('exam_body', 16);           // WAEC | JAMB | NECO
            $table->unsignedSmallInteger('year');
            $table->string('topic');
            $table->text('prompt');
            $table->json('options');
            $table->unsignedTinyInteger('answer_index');
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->index(['subject_id', 'topic']);
            $table->index(['subject_id', 'exam_body', 'year']);
        });

        /* ---------------------------------------------------------------
         | Learning — written by the student's device
         ---------------------------------------------------------------- */
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'subject_id']);
        });

        // One row per student: the whole progress blob, last-write-wins.
        // Deliberately not an event log — v1 is one student, one device, and
        // this removes about ninety percent of the sync code.
        Schema::create('student_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('device_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid')->unique();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->index(['user_id', 'topic_id']);
        });

        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->date('occurred_on');
            $table->string('source', 24)->default('lesson');
            $table->uuid('client_uuid')->unique();
            $table->timestamps();
            $table->index(['user_id', 'occurred_on']);
        });

        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24)->default('practice');   // practice | mock | scheme_exam
            $table->string('title');
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->boolean('auto_submitted')->default(false);
            $table->uuid('client_uuid')->unique();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['user_id', 'submitted_at']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic')->nullable();
            $table->unsignedTinyInteger('chosen')->nullable();  // null = left blank
            $table->boolean('is_correct')->default(false);
        });

        /* ---------------------------------------------------------------
         | Classroom
         ---------------------------------------------------------------- */
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                    // SS2A
            $table->string('level');                   // SS2
            $table->string('room')->nullable();
            $table->string('join_code', 16)->unique();
            $table->timestamps();
        });

        Schema::create('classroom_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['classroom_id', 'user_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 16)->default('classwork');   // classwork | test
            $table->boolean('ai_generated')->default(false);
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->date('due_on')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('kind', 16)->default('objective');   // objective | written
            $table->text('prompt');
            $table->json('options')->nullable();                // null for written answers
            $table->unsignedTinyInteger('answer_index')->nullable();
            $table->text('expected')->nullable();               // model answer / marking scheme
            $table->unsignedTinyInteger('max_mark')->default(1);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->unsignedSmallInteger('ai_score')->nullable();
            $table->unsignedSmallInteger('final_score')->nullable();
            $table->unsignedSmallInteger('max_score')->default(0);
            $table->string('status', 16)->default('pending');    // pending | marking | marked | approved
            $table->boolean('needs_review')->default(false);     // any answer below the confidence floor
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['assignment_id', 'user_id']);
        });

        Schema::create('submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->unsignedTinyInteger('chosen')->nullable();
            $table->unsignedTinyInteger('ai_mark')->nullable();
            $table->unsignedTinyInteger('ai_confidence')->nullable();   // 0-100
            $table->text('ai_comment')->nullable();
            $table->unsignedTinyInteger('teacher_mark')->nullable();
            $table->text('teacher_comment')->nullable();
            $table->timestamps();
        });

        /* ---------------------------------------------------------------
         | Parents
         ---------------------------------------------------------------- */
        Schema::create('parent_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('report_day', 12)->default('sunday');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['parent_id', 'student_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'parent_links', 'submission_answers', 'submissions', 'assignment_questions',
            'assignments', 'classroom_student', 'classrooms', 'attempt_answers', 'attempts',
            'study_sessions', 'lesson_completions', 'student_states', 'enrollments',
            'questions', 'classwork_questions', 'lessons', 'topics', 'subjects', 'schools',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'phone', 'school_id', 'level', 'locale', 'goal',
                'daily_goal', 'subject_slug', 'api_token', 'onboarded_at', 'last_seen_at',
            ]);
        });
    }
};
