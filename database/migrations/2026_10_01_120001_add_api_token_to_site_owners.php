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
            $table->string('api_token', 64)->nullable()->unique()->after('theme_id');
        });

        // Give every existing owner a token right away.
        DB::table('site_owners')->whereNull('api_token')->orderBy('id')->pluck('id')->each(function ($id) {
            DB::table('site_owners')->where('id', $id)->update(['api_token' => Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::table('site_owners', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });
    }
};
