<?php

// If the profiler is enabled, set it up now:
$vufindProfiler = getenv('VUFIND_PROFILER_XHPROF');
if (!empty($vufindProfiler)) {
    include __DIR__ . '/../module/VuFind/functions/profiler.php';
    enableVuFindProfiling($vufindProfiler);
}

use Laminas\Db\Adapter\Exception\RuntimeException as DbRuntimeException;
use Laminas\Mvc\Application;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;

$maxRetries = 3; // Maximum number of times to retry the request
$currentRetry = 0;
$requestSucceeded = false;

do {
    $deadlockDetected = false; // Flag to indicate if a deadlock was the reason for failure
    $exceptionDuringRun = null; // To store the exception if one occurs

    try {
        // Reload the application configuration and re-initialize the application
        // This effectively restarts the entire Laminas application bootstrap
        // for the current HTTP request attempt.
        $app = include __DIR__ . '/../config/application.php';

        if (PHP_SAPI === 'cli') {
            return $app->getServiceManager()
                ->get(\VuFindConsole\ConsoleRunner::class)->run();
        } else {
            // Setup remote code coverage if enabled (keep this inside the try block)
            if (getenv('VUFIND_CODE_COVERAGE')) {
                $modules = $app->getServiceManager()
                    ->get(\Laminas\ModuleManager\ModuleManager::class)->getModules();
                include __DIR__ . '/../module/VuFind/functions/codecoverage.php';
                setupVuFindRemoteCodeCoverage($modules);
            }
            // Run the application for a web request
            $app->run();
        }

        // If app->run() completes without throwing, the request was successful
        $requestSucceeded = true;

    } catch (ServiceNotCreatedException $e) {
        $exceptionDuringRun = $e;
        $innerException = $e->getPrevious();
        // Traverse the exception chain to find the root cause
        while ($innerException !== null) {
            // Check for specific database runtime exceptions or mysqli_sql_exception (for direct mysqli errors)
            if (($innerException instanceof DbRuntimeException || $innerException instanceof \mysqli_sql_exception)) {
                // MySQL/MariaDB deadlock error code is 1213
                // TODO for a PR we will need to have it check more codes and messages
                if ($innerException->getCode() == 1213 || str_contains($innerException->getMessage(), 'Deadlock found')) {
                    $deadlockDetected = true;
                    break; // Found the deadlock, no need to check further
                }
            }
            $innerException = $innerException->getPrevious();
        }

        if (!$deadlockDetected) {
            // If the ServiceNotCreatedException was NOT due to a deadlock, re-throw it
            throw $exceptionDuringRun;
        }

    } catch (\Exception $e) {
        // Catch any other general exceptions not specifically handled as deadlocks
        $exceptionDuringRun = $e;
        error_log(
            '[' . date('Y-m-d H:i:s') . '] APPLICATION_ERROR: Unhandled exception during application run. ' .
            'Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . "\n" .
            'Trace: ' . $e->getTraceAsString() .
            ' URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A')
        );
        throw $e; // Re-throw to show typical error page/log
    }

    $currentRetry++;

    // Add a small delay before retrying, only if a deadlock was detected
    if ($deadlockDetected && $currentRetry < $maxRetries) {
        // Exponential backoff: 10ms, 20ms, 40ms, etc.
        // Adjust these values as needed. A very small initial delay is usually sufficient.
        usleep(2 ** $currentRetry * 10000);
    }

} while (!$requestSucceeded && $deadlockDetected && $currentRetry < $maxRetries);

// If we exit the loop and the request was not successful (i.e., retries exhausted
// due to persistent deadlocks), handle the final failure.
if (!$requestSucceeded && $deadlockDetected) {
    error_log(
        '[' . date('Y-m-d H:i:s') . '] CRITICAL_DEADLOCK_FAILURE: Application failed after ' . $maxRetries . ' retries ' .
        'due to persistent deadlocks for URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A')
    );
    // Display a user-friendly error page or redirect
    http_response_code(503); // Service Unavailable
    echo "<h1>Service Temporarily Unavailable</h1><p>We're experiencing heavy load. Please try again shortly.</p>";
    // If the exception was captured, log its full trace here if not already.
    if ($exceptionDuringRun) {
        error_log("Last caught exception trace:\n" . $exceptionDuringRun->getTraceAsString());
    }
    exit();
}
