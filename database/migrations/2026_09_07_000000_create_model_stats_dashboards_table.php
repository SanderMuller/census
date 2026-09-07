<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('model_stats_dashboards', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('audiences');
            $table->json('stats');

            // No foreign key on purpose. A dashboard belongs to its audience rather than its author,
            // so deleting the author must not cascade, and a host is free to use any user-key type.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_stats_dashboards');
    }
};
