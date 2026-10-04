<?php

use App\Services\Admin\LoginRateLimit;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$app->instance('env', 'testing');
config(['database.default' => 'bootstrap_test', 'database.connections.bootstrap_test' => $input['connection'], 'hashing.bcrypt.rounds' => 4]);
config(['cache.default' => 'database', 'cache.stores.database.connection' => 'bootstrap_test', 'cache.stores.database.lock_connection' => 'bootstrap_test']);
DB::purge();
echo "READY\n";
flush();
try {
    if (($input['operation'] ?? 'bootstrap') === 'login') {
        for ($i = 0; $i < 5; $i++) {
            echo $app->make(LoginRateLimit::class)->retryAfter('admin@example.test', '192.0.2.1') === 0 ? "ADMITTED\n" : "DENIED\n";
        }
        exit(0);
    }
    $app->make(SuperAdminBootstrap::class)->create('Concurrent Administrator', $input['email'], 'Safe-Test-Password9!');
    echo "CREATED\n";
} catch (DomainException) {
    echo "EXISTS\n";
} catch (Throwable) {
    echo "FAILED\n";
    exit(1);
}
