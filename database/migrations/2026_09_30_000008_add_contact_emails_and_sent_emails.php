<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where documents go: an email address (and a contact) on customers and
     * vendors, the company's own sending address, and a record of every
     * email sent.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('address');
            $table->string('contact_person')->nullable()->after('email');
        });
        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('address');
            $table->string('contact_person')->nullable()->after('email');
        });
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('address');
        });

        Schema::create('sent_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('about_type')->nullable();
            $table->unsignedBigInteger('about_id')->nullable();
            $table->string('mailable');
            $table->string('subject');
            $table->json('recipients');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['about_type', 'about_id']);
            $table->index(['company_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('email'));
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn(['email', 'contact_person']));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['email', 'contact_person']));
    }
};
