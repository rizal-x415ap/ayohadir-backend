<?php

use App\Models\Invitation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Automatically shorten any existing tokens longer than 4 characters
        $invitations = Invitation::whereRaw('LENGTH(token) > 4')->get();
        foreach ($invitations as $invitation) {
            $invitation->token = Invitation::generateUniqueToken();
            $invitation->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible migration
    }
};
