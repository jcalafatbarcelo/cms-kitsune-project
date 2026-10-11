<?php

use App\Models\User;
use App\Services\Admin\AdminCredentials;
use App\Services\Admin\AdminPresentation;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Template\Enums\CmsPresentation;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$app->instance('env', 'local');
config(['database.default' => 'bootstrap_test', 'database.connections.bootstrap_test' => $input['connection'], 'hashing.bcrypt.rounds' => 4]);
config([
    'cache.default' => 'database',
    'cache.stores.database.connection' => 'bootstrap_test',
    'cache.stores.database.lock_connection' => 'bootstrap_test',
    'session.driver' => 'database',
    'session.connection' => 'bootstrap_test',
]);
DB::purge();
echo "READY\n";
flush();
try {
    if (($input['operation'] ?? null) === 'reset-login') {
        RateLimiter::clear('admin-login:'.hash('sha256', AdminCredentials::email($input['email'])).'|192.0.2.1');

        exit(0);
    }
    if (in_array($input['operation'] ?? 'bootstrap', ['login', 'slow-login', 'csrf-invalid'], true)) {
        if (in_array($input['operation'] ?? null, ['login', 'slow-login'], true)) {
            $log = $input['log'] ?? null;
            $label = $input['label'] ?? null;
            $verificationLog = $input['verification_log'] ?? null;
            Auth::provider('instrumented-eloquent', function () use ($app, $log, $label, $verificationLog, $input): EloquentUserProvider {
                return new class($app['hash'], User::class, $log, $label, $verificationLog, ($input['operation'] ?? null) === 'slow-login') extends EloquentUserProvider
                {
                    public function __construct($hasher, string $model, private ?string $log, private ?string $label, private ?string $verificationLog, private bool $slow)
                    {
                        parent::__construct($hasher, $model);
                    }

                    public function validateCredentials(UserContract $user, #[SensitiveParameter] array $credentials)
                    {
                        if ($this->verificationLog !== null) {
                            file_put_contents($this->verificationLog, "verified\n", FILE_APPEND | LOCK_EX);
                        }
                        if ($this->slow) {
                            file_put_contents($this->log, $this->label.':starts:'.microtime(true).PHP_EOL, FILE_APPEND | LOCK_EX);
                            echo "VERIFYING\n";
                            flush();
                            sleep(11);
                            file_put_contents($this->log, $this->label.':ends:'.microtime(true).PHP_EOL, FILE_APPEND | LOCK_EX);
                        }

                        return parent::validateCredentials($user, $credentials);
                    }
                };
            });
            config(['auth.providers.instrumented' => ['driver' => 'instrumented-eloquent', 'model' => User::class], 'auth.guards.web.provider' => 'instrumented']);
            Auth::forgetGuards();
        }
        $app->instance(AdminPresentation::class, new class extends AdminPresentation
        {
            public function __construct() {}

            public function render(CmsPresentation $key, int $status = 200, array $data = []): Response
            {
                return response('', $status);
            }
        });
        $kernel = $app->make(HttpKernel::class);
        $get = Request::create('/admin/login', 'GET', server: ['REMOTE_ADDR' => '192.0.2.1']);
        $getResponse = $kernel->handle($get);
        $sessionCookie = collect($getResponse->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        if ($sessionCookie === null || $sessionCookie->getValue() === $get->session()->getId()) {
            throw new RuntimeException('The login form did not issue an encrypted session cookie.');
        }
        $token = ($input['operation'] ?? null) === 'csrf-invalid' ? 'invalid-csrf-token' : $get->session()->token();
        $request = Request::create('/admin/login', 'POST', [
            'email' => $input['email'],
            'password' => $input['password'],
            '_token' => $token,
        ], server: ['REMOTE_ADDR' => '192.0.2.1']);
        $request->cookies->set(config('session.cookie'), $sessionCookie->getValue());
        if (isset($input['barrier'])) {
            file_put_contents($input['barrier'], "ready\n", FILE_APPEND | LOCK_EX);
            while (trim((string) file_get_contents($input['barrier'])) !== 'release') {
                usleep(1000);
            }
        }
        $response = $kernel->handle($request);
        echo 'STATUS:'.$response->getStatusCode()."\n";
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
