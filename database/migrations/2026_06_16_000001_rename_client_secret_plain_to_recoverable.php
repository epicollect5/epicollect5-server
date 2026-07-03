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
        // Rename the column for environments that already applied the original
        // migration (which used the misleading name "client_secret_plain").
        // Fresh deployments get the new name directly from the original migration.
        if (
            Schema::hasColumn('oauth_client_projects', 'client_secret_plain')
            && !Schema::hasColumn('oauth_client_projects', 'client_secret_recoverable')
        ) {
            // Older versions of the original migration stored the value as plain
            // text. Encrypt any plaintext values before the model's `encrypted`
            // cast starts reading the column.
            $rows = DB::table('oauth_client_projects')
                ->whereNotNull('client_secret_plain')
                ->select('id', 'client_secret_plain')
                ->cursor();

            foreach ($rows as $row) {
                if (!$this->isEncrypted($row->client_secret_plain)) {
                    DB::table('oauth_client_projects')
                        ->where('id', $row->id)
                        ->update([
                            'client_secret_plain' => Crypt::encryptString($row->client_secret_plain),
                        ]);
                }
            }

            Schema::table('oauth_client_projects', function (Blueprint $table) {
                $table->renameColumn('client_secret_plain', 'client_secret_recoverable');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('oauth_client_projects', 'client_secret_recoverable')
            && !Schema::hasColumn('oauth_client_projects', 'client_secret_plain')
        ) {
            Schema::table('oauth_client_projects', function (Blueprint $table) {
                $table->renameColumn('client_secret_recoverable', 'client_secret_plain');
            });
        }
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
