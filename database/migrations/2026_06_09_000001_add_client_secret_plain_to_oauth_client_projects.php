<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('oauth_client_projects', 'client_secret_recoverable')) {
            Schema::table('oauth_client_projects', function (Blueprint $table) {
                $table->text('client_secret_recoverable')->nullable()->after('client_id');
            });
        }

        // Copy existing plain-text secrets from oauth_clients to oauth_client_projects,
        // encrypted at rest via APP_KEY. This must run BEFORE passport:hash, which
        // will permanently hash oauth_clients.secret.
        //
        // If a previous version of this migration ran and stored the value as
        // plain text (pre-encryption), re-encrypt it on this run.
        $rows = DB::table('oauth_clients')
            ->join('oauth_client_projects', 'oauth_client_projects.client_id', '=', 'oauth_clients.id')
            ->whereNotNull('oauth_client_projects.client_secret_recoverable')
            ->select('oauth_client_projects.id as row_id', 'oauth_client_projects.client_secret_recoverable')
            ->cursor();

        foreach ($rows as $row) {
            $value = $this->decryptIfEncrypted($row->client_secret_recoverable);
            DB::table('oauth_client_projects')
                ->where('id', $row->row_id)
                ->update(['client_secret_recoverable' => Crypt::encryptString($value)]);
        }

        // Backfill any remaining rows that have no client_secret_recoverable yet
        // (the original first-time path).
        $missingRows = DB::table('oauth_clients')
            ->join('oauth_client_projects', 'oauth_client_projects.client_id', '=', 'oauth_clients.id')
            ->whereNull('oauth_client_projects.client_secret_recoverable')
            ->select('oauth_client_projects.id as row_id', 'oauth_clients.secret')
            ->cursor();

        foreach ($missingRows as $row) {
            DB::table('oauth_client_projects')
                ->where('id', $row->row_id)
                ->update(['client_secret_recoverable' => Crypt::encryptString($row->secret)]);
        }
    }

    public function down(): void
    {
        // Restore plain-text secrets from oauth_client_projects back to oauth_clients.
        // This is needed because passport:hash may have already hashed oauth_clients.secret.
        // The column may contain either an encrypted payload (new code) or a
        // plain-text value (legacy / pre-encryption migration). Handle both.
        $rows = DB::table('oauth_client_projects')
            ->join('oauth_clients', 'oauth_client_projects.client_id', '=', 'oauth_clients.id')
            ->whereNotNull('oauth_client_projects.client_secret_recoverable')
            ->select('oauth_client_projects.client_id', 'oauth_client_projects.client_secret_recoverable')
            ->cursor();

        foreach ($rows as $row) {
            $plain = $this->decryptIfEncrypted($row->client_secret_recoverable);
            DB::table('oauth_clients')
                ->where('id', $row->client_id)
                ->update(['secret' => $plain]);
        }

        Schema::table('oauth_client_projects', function (Blueprint $table) {
            $table->dropColumn('client_secret_recoverable');
        });
    }

    private function decryptIfEncrypted(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
};
