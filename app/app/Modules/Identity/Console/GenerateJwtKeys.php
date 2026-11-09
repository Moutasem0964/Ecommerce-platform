<?php

namespace App\Modules\Identity\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateJwtKeys extends Command
{
    protected $signature = 'jwt:keys {--force : Overwrite existing keys}';

    protected $description = 'Generate the RSA key pair used to sign and verify JWT access tokens';

    public function handle(): int
    {
        $privatePath = config('jwt.private_key_path');
        $publicPath = config('jwt.public_key_path');

        if (File::exists($privatePath) && ! $this->option('force')) {
            $this->warn('Keys already exist. Use --force to replace them (all issued tokens become invalid).');
            return self::SUCCESS;
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($key, $privatePem);
        $publicPem = openssl_pkey_get_details($key)['key'];

        File::ensureDirectoryExists(dirname($privatePath));
        File::put($privatePath, $privatePem);
        File::put($publicPath, $publicPem);
        chmod($privatePath, 0600);

        $this->info("Private key: {$privatePath}");
        $this->info("Public key:  {$publicPath}");

        return self::SUCCESS;
    }
}
