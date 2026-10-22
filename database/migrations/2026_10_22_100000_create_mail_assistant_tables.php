<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mailassistent v1
 * - mail_assistant_settings: mailbox (IMAP/SMTP), toon, handtekening, bronnen per tenant
 * - mail_assistant_messages: binnengekomen mails + voorgesteld/verstuurd antwoord
 * - mail_assistant_faqs: kennisbank (vraag/antwoord) per tenant
 *
 * Geen foreign key constraints (zoals de rest van het project).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->boolean('enabled')->default(false);

            // IMAP (ophalen)
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 10)->default('ssl'); // ssl | tls | none
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();             // encrypted cast
            $table->string('imap_folder')->default('INBOX');
            $table->string('sent_folder')->nullable();             // leeg = automatisch zoeken

            // SMTP (versturen)
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();             // encrypted cast; leeg = IMAP-wachtwoord
            $table->string('from_address')->nullable();            // leeg = IMAP-gebruikersnaam
            $table->string('from_name')->nullable();               // leeg = naam van de zaak

            // Antwoorden
            $table->string('tone', 20)->default('informal');       // informal | formal | follow
            $table->string('language', 20)->default('customer');   // customer | nl
            $table->text('signature')->nullable();
            $table->text('instructions')->nullable();              // extra richtlijnen van de zaak
            $table->json('sources')->nullable();                   // {"opening_hours":true,...}
            $table->unsignedTinyInteger('auto_suggest_after')->default(5);
            $table->unsignedSmallInteger('fetch_since_days')->default(3);

            // Status van het ophalen
            $table->unsignedBigInteger('uid_validity')->nullable();
            $table->unsignedBigInteger('last_uid')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->timestamps();
        });

        Schema::create('mail_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('uid')->nullable();
            $table->unsignedBigInteger('uid_validity')->nullable();
            $table->string('message_id', 500)->nullable();
            $table->string('in_reply_to', 500)->nullable();
            $table->text('references')->nullable();

            $table->string('from_email')->index();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('subject', 500)->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('received_at')->nullable();

            // new | draft | manual | ignored | sent | done | error
            $table->string('status', 20)->default('new')->index();
            $table->string('ignore_reason')->nullable();
            $table->string('category', 40)->nullable()->index();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('language', 5)->nullable();
            $table->text('summary')->nullable();
            $table->boolean('needs_human')->default(false);
            $table->text('needs_human_reason')->nullable();

            $table->longText('draft_body')->nullable();
            $table->longText('draft_original')->nullable();        // eerste versie van de AI (om aanpassingen te tellen)
            $table->json('draft_sources')->nullable();
            $table->json('draft_warnings')->nullable();
            $table->timestamp('drafted_at')->nullable();
            $table->unsignedInteger('draft_tokens')->nullable();

            $table->unsignedBigInteger('account_id')->nullable();

            $table->longText('sent_body')->nullable();
            $table->boolean('sent_edited')->nullable();            // aangepast t.o.v. het AI-voorstel?
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'uid_validity', 'uid']);
            $table->index(['tenant_id', 'status', 'received_at']);
        });

        Schema::create('mail_assistant_faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('question', 500);
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_assistant_faqs');
        Schema::dropIfExists('mail_assistant_messages');
        Schema::dropIfExists('mail_assistant_settings');
    }
};
