<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mission = \App\Models\Mission::find(23);
$user = \App\Models\User::where('email', 'rrh@example.com')->first();

$controller = app(\App\Http\Controllers\MissionController::class);
$r = new ReflectionClass($controller);

$fakeSig = 'data:image/png;base64,' . base64_encode(str_repeat('x', 1000));

try {
    $gen = $r->getMethod('genererPdfMission');
    $gen->setAccessible(true);
    $filename = $gen->invoke($controller, $mission, $fakeSig, $user);
    echo "PDF OK: $filename\n";
} catch (Throwable $e) {
    echo "PDF FAIL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

try {
    $log = $r->getMethod('enregistrerLogValidation');
    $log->setAccessible(true);
    $log->invoke($controller, $mission, $user, 'test', $fakeSig, 'test comment');
    echo "LOG OK\n";
} catch (Throwable $e) {
    echo "LOG FAIL: " . $e->getMessage() . "\n";
}

// Test with large signature like real canvas
$largeSig = 'data:image/png;base64,' . base64_encode(random_bytes(50000));
try {
    $log->invoke($controller, $mission, $user, 'test2', $largeSig, 'test');
    echo "LARGE LOG OK len=" . strlen($largeSig) . "\n";
    \App\Models\MissionLog::where('mission_id', 23)->where('etape_concernee', 'test2')->delete();
} catch (Throwable $e) {
    echo "LARGE LOG FAIL len=" . strlen($largeSig) . ": " . $e->getMessage() . "\n";
}
