<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_boards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('ticket_board_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_board_id')->index()->constrained('ticket_boards')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('wip_limit')->nullable();
            $table->boolean('is_done')->default(false)->index();
            $table->timestampsTz();

            $table->unique(['ticket_board_id', 'slug']);
            $table->unique(['ticket_board_id', 'position']);
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ticket_number')->unique();
            $table->foreignId('ticket_board_id')->nullable()->index()->constrained('ticket_boards')->nullOnDelete();
            $table->foreignId('ticket_board_column_id')->nullable()->index()->constrained('ticket_board_columns')->nullOnDelete();
            $table->foreignId('reporter_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->enum('type', ['question', 'help', 'feature'])->default('question')->index();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->index();
            $table->enum('status', ['open', 'triaged', 'planned', 'in_progress', 'waiting_client', 'resolved', 'closed'])->default('open')->index();
            $table->unsignedSmallInteger('effort_points')->nullable()->index();
            $table->timestampTz('due_at')->nullable()->index();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('resolved_at')->nullable()->index();
            $table->timestampTz('closed_at')->nullable()->index();
            $table->timestampTz('first_response_at')->nullable()->index();
            $table->timestampTz('last_activity_at')->nullable()->index();
            $table->foreignId('closed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('sla_notified_at')->nullable()->index();
            $table->boolean('is_public')->default(false)->index();
            $table->string('dampak_bisnis')->nullable()->index();
            $table->string('modul_terkait')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'priority', 'created_at'], 'tickets_status_priority_created_at_idx');
            $table->index(['assignee_id', 'status'], 'tickets_assignee_status_idx');
            $table->index(['reporter_id', 'status'], 'tickets_reporter_status_idx');
        });

        Schema::create('ticket_watchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->index()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->index()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false)->index();
            $table->timestampTz('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_work_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->index()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('minutes_spent');
            $table->text('notes');
            $table->timestampTz('logged_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['ticket_id', 'logged_at']);
        });

        Schema::create('ticket_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color_hex', 7)->default('#64748B');
            $table->timestampsTz();
        });

        Schema::create('ticket_label_ticket', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->index()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('ticket_label_id')->index()->constrained('ticket_labels')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['ticket_id', 'ticket_label_id']);
        });

        Schema::create('ticket_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->index()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'done'])->default('todo')->index();
            $table->unsignedInteger('position')->default(1);
            $table->timestampTz('due_at')->nullable()->index();
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['ticket_id', 'position']);
            $table->index(['ticket_id', 'status'], 'ticket_tasks_ticket_status_idx');
        });

        Schema::create('ticket_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->index()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('related_ticket_id')->index()->constrained('tickets')->cascadeOnDelete();
            $table->enum('relation_type', ['duplicate', 'blocked_by', 'related_to', 'dependency'])->index();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['ticket_id', 'related_ticket_id', 'relation_type'], 'ticket_rel_ticket_related_type_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_relations');
        Schema::dropIfExists('ticket_work_logs');
        Schema::dropIfExists('ticket_tasks');
        Schema::dropIfExists('ticket_label_ticket');
        Schema::dropIfExists('ticket_labels');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_watchers');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_board_columns');
        Schema::dropIfExists('ticket_boards');
    }
};
