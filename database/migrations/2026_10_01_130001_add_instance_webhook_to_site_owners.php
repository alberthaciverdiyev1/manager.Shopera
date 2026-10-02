<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_owners', function (Blueprint $table) {
            $table->string('instance_url')->nullable()->after('api_token');
            $table->string('webhook_secret', 64)->nullable()->after('instance_url');
        });

        DB::table('site_owners')->whereNull('webhook_secret')->orderBy('id')->pluck('id')->each(function ($id) {
            DB::table('site_owners')->where('id', $id)->update(['webhook_secret' => Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::table('site_owners', function (Blueprint $table) {
            $table->dropColumn(['instance_url', 'webhook_secret']);
        });
    }
};
