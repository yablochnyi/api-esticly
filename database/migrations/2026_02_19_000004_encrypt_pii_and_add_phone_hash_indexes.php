<?php

use App\Support\PhoneIndex;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone_hash')) {
                $table->string('phone_hash', 64)->nullable()->after('phone');
            }
        });

        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'phone_hash')) {
                $table->string('phone_hash', 64)->nullable()->after('phone');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'phone_hash')) {
                $table->string('phone_hash', 64)->nullable()->after('phone');
            }
        });

        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'client_phone_hash')) {
                $table->string('client_phone_hash', 64)->nullable()->after('client_phone');
            }
        });

        try {
            DB::statement('ALTER TABLE users DROP INDEX users_phone_unique');
        } catch (\Throwable) {
            // Index may already be removed.
        }

        try {
            DB::statement('ALTER TABLE clients DROP INDEX clients_user_id_phone_index');
        } catch (\Throwable) {
            // Index may already be removed.
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone_hash', 'users_phone_hash_unique');
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->index(['user_id', 'phone_hash'], 'staff_user_id_phone_hash_index');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['user_id', 'phone_hash'], 'clients_user_id_phone_hash_index');
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->index(['user_id', 'client_phone_hash'], 'visits_user_id_client_phone_hash_index');
        });

        DB::table('users')
            ->select(['id', 'phone', 'address', 'description'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $phonePlain = $this->decryptMaybe($row->phone);
                    $phoneNorm = PhoneIndex::normalize($phonePlain);
                    $addressPlain = $this->decryptMaybe($row->address);
                    $descriptionPlain = $this->decryptMaybe($row->description);

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->encryptNullable($phoneNorm),
                            'phone_hash' => PhoneIndex::hash($phoneNorm),
                            'address' => $this->encryptNullable($addressPlain),
                            'description' => $this->encryptNullable($descriptionPlain),
                        ]);
                }
            });

        DB::table('staff')
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $phonePlain = $this->decryptMaybe($row->phone);
                    $phoneNorm = PhoneIndex::normalize($phonePlain);
                    DB::table('staff')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->encryptNullable($phoneNorm),
                            'phone_hash' => PhoneIndex::hash($phoneNorm),
                        ]);
                }
            });

        DB::table('clients')
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $phonePlain = $this->decryptMaybe($row->phone);
                    $phoneNorm = PhoneIndex::normalize($phonePlain);
                    DB::table('clients')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->encryptNullable($phoneNorm),
                            'phone_hash' => PhoneIndex::hash($phoneNorm),
                        ]);
                }
            });

        DB::table('visits')
            ->select(['id', 'client_phone'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $phonePlain = $this->decryptMaybe($row->client_phone);
                    $phoneNorm = PhoneIndex::normalize($phonePlain);
                    DB::table('visits')
                        ->where('id', $row->id)
                        ->update([
                            'client_phone' => $this->encryptNullable($phoneNorm),
                            'client_phone_hash' => PhoneIndex::hash($phoneNorm),
                        ]);
                }
            });

        DB::table('client_notes')
            ->select(['id', 'text'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $plain = $this->decryptMaybe($row->text);
                    DB::table('client_notes')
                        ->where('id', $row->id)
                        ->update([
                            'text' => $this->encryptNullable($plain),
                        ]);
                }
            });

        DB::table('visit_agreements')
            ->select(['id', 'agreement_text'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $plain = $this->decryptMaybe($row->agreement_text);
                    DB::table('visit_agreements')
                        ->where('id', $row->id)
                        ->update([
                            'agreement_text' => $this->encryptNullable($plain),
                        ]);
                }
            });

        DB::table('services')
            ->select(['id', 'agreement_text'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $plain = $this->decryptMaybe($row->agreement_text);
                    DB::table('services')
                        ->where('id', $row->id)
                        ->update([
                            'agreement_text' => $this->encryptNullable($plain),
                        ]);
                }
            });
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE users DROP INDEX users_phone_hash_unique');
        } catch (\Throwable) {
        }
        try {
            DB::statement('ALTER TABLE staff DROP INDEX staff_user_id_phone_hash_index');
        } catch (\Throwable) {
        }
        try {
            DB::statement('ALTER TABLE clients DROP INDEX clients_user_id_phone_hash_index');
        } catch (\Throwable) {
        }
        try {
            DB::statement('ALTER TABLE visits DROP INDEX visits_user_id_client_phone_hash_index');
        } catch (\Throwable) {
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone_hash')) {
                $table->dropColumn('phone_hash');
            }
        });
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'phone_hash')) {
                $table->dropColumn('phone_hash');
            }
        });
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'phone_hash')) {
                $table->dropColumn('phone_hash');
            }
        });
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'client_phone_hash')) {
                $table->dropColumn('client_phone_hash');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['user_id', 'phone'], 'clients_user_id_phone_index');
        });
    }

    private function decryptMaybe(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    private function encryptNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plain = trim($value);
        if ($plain === '') {
            return null;
        }

        return Crypt::encryptString($plain);
    }
};

