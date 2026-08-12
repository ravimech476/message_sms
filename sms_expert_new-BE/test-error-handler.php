<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║        ERROR HANDLER DIAGNOSTIC SCRIPT                     ║\n";
echo "║        Testing Exception Email System                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Test 1: Check if handler exists
echo "TEST 1: Checking Exception Handler\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    $handlerClass = get_class(app(Illuminate\Contracts\Debug\ExceptionHandler::class));
    echo "✓ Handler Class: {$handlerClass}\n";
    echo "✓ Expected: App\\Exceptions\\Handler\n";
    
    if ($handlerClass === 'App\\Exceptions\\Handler') {
        echo "✅ PASS: Custom handler is loaded!\n";
    } else {
        echo "❌ FAIL: Using default handler, not custom handler\n";
        echo "   Run: php artisan config:clear && composer dump-autoload\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL: Could not load handler\n";
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Check configuration
echo "TEST 2: Checking Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$emailEnabled = config('exception.email_enabled');
$recipients = config('exception.email_recipients', []);
$throttle = config('exception.email_throttle');

echo "✓ Email Enabled: " . ($emailEnabled ? 'YES ✅' : 'NO ❌') . "\n";
echo "✓ Recipients: " . (empty($recipients) ? 'NONE ❌' : implode(', ', $recipients) . ' ✅') . "\n";
echo "✓ Throttle Limit: " . ($throttle ?? 'Not Set ❌') . " emails/minute\n";
echo "✓ Environment: " . app()->environment() . "\n";

if ($emailEnabled && !empty($recipients)) {
    echo "✅ PASS: Configuration is correct!\n";
} else {
    echo "❌ FAIL: Configuration issue detected\n";
    if (!$emailEnabled) echo "   - Enable emails in .env: EXCEPTION_EMAIL_ENABLED=true\n";
    if (empty($recipients)) echo "   - Add recipients in .env: EXCEPTION_EMAIL_RECIPIENTS=your@email.com\n";
}
echo "\n";

// Test 3: Check mail configuration
echo "TEST 3: Checking Mail Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ Mail Driver: " . config('mail.default') . "\n";
echo "✓ SMTP Host: " . config('mail.mailers.smtp.host') . "\n";
echo "✓ SMTP Port: " . config('mail.mailers.smtp.port') . "\n";
echo "✓ From Address: " . config('mail.from.address') . "\n";
echo "✓ From Name: " . config('mail.from.name') . "\n";
echo "\n";

// Test 4: Test basic mail sending
echo "TEST 4: Testing Basic Mail Sending\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$testRecipient = $recipients[0] ?? 'test@example.com';
echo "Sending test email to: {$testRecipient}\n";

try {
    Mail::raw('This is a test email from the SMS Expert diagnostic script at ' . now(), function($message) use ($testRecipient) {
        $message->to($testRecipient)
                ->subject('🔧 Test Email - SMS Expert Diagnostic');
    });
    echo "✅ PASS: Basic mail sent successfully!\n";
    echo "   Check inbox: {$testRecipient}\n";
} catch (Exception $e) {
    echo "❌ FAIL: Could not send basic email\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Check your SMTP settings in .env\n";
}
echo "\n";

// Test 5: Test exception email class
echo "TEST 5: Testing Exception Email Class\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $exceptionData = [
        'exception_class' => 'DiagnosticTestException',
        'exception_message' => 'This is a test exception from diagnostic script at ' . now()->toDateTimeString(),
        'exception_code' => 999,
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => [
            ['file' => __FILE__, 'line' => __LINE__, 'function' => 'diagnosticTest'],
            ['file' => __FILE__, 'line' => __LINE__ - 1, 'function' => 'main'],
        ],
        'url' => 'http://localhost:8000/diagnostic-test',
        'method' => 'CLI',
        'ip' => '127.0.0.1',
        'user_agent' => 'Diagnostic Script v1.0',
        'user_id' => null,
        'user_email' => null,
        'input' => ['test' => 'diagnostic'],
        'environment' => app()->environment(),
        'app_name' => config('app.name'),
        'app_url' => config('app.url'),
        'timestamp' => now()->toDateTimeString(),
        'server' => [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => 'CLI Diagnostic'
        ]
    ];
    
    echo "Creating exception email...\n";
    $mail = new \App\Mail\ExceptionNotificationMail($exceptionData);
    
    echo "Sending exception email to: {$testRecipient}\n";
    Mail::to($testRecipient)->send($mail);
    
    echo "✅ PASS: Exception email sent successfully!\n";
    echo "   Check your inbox at: {$testRecipient}\n";
    echo "   You should receive a detailed exception email\n";
} catch (Exception $e) {
    echo "❌ FAIL: Could not send exception email\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
}
echo "\n";

// Test 6: Test exception reporting through handler
echo "TEST 6: Testing Exception Handler Reporting\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    echo "Creating a test exception...\n";
    $testException = new Exception('Test exception for handler reporting at ' . now()->toDateTimeString());
    
    echo "Reporting exception to handler...\n";
    $handler = app(Illuminate\Contracts\Debug\ExceptionHandler::class);
    $handler->report($testException);
    
    echo "✅ PASS: Exception reported to handler!\n";
    echo "   Check your email at: {$testRecipient}\n";
    echo "   Check logs in: storage/logs/" . now()->format('Y-m-d') . "/laravel.log\n";
} catch (Exception $e) {
    echo "❌ FAIL: Could not report exception\n";
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    DIAGNOSTIC SUMMARY                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "If all tests passed:\n";
echo "✅ Your error handling system is working correctly\n";
echo "✅ Check your email inbox: {$testRecipient}\n";
echo "✅ You should have received 2-3 test emails\n";
echo "\n";
echo "If tests failed:\n";
echo "1. Run: php artisan config:clear\n";
echo "2. Run: composer dump-autoload\n";
echo "3. Run: php artisan config:cache\n";
echo "4. Re-run this diagnostic script\n";
echo "\n";
echo "Next step: Test with a web route\n";
echo "Add to routes/web.php:\n";
echo "   Route::get('/test-error', fn() => throw new \\Exception('Test'));\n";
echo "Visit: http://localhost:8000/test-error\n";
echo "\n";
echo "For help, see: TROUBLESHOOTING_EMAIL.md\n";
echo "\n";
