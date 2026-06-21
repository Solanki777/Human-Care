<?php
/**
 * =====================================================================
 * Nexora Auto-Trigger
 * =====================================================================
 * Called by security_functions.php after every login attempt is logged.
 * Runs login_threat_detector.py in the background so PHP never waits
 * for it to finish (non-blocking, fire-and-forget).
 *
 * FAIL-SAFE: if Python or the script is not found, this silently logs
 * the error and returns — it NEVER breaks the login flow.
 *
 * PLACEMENT: C:\xampp\htdocs\vscode\security\nexora_trigger.php
 * =====================================================================
 */

function trigger_nexora_analysis(): void {
    try {
        // Absolute path to the detector script (adjust if project moves)
        $script = realpath(__DIR__ . '/login_threat_detector.py');

        if (!$script || !file_exists($script)) {
            error_log('[nexora_trigger] login_threat_detector.py not found at: ' . __DIR__);
            return;
        }

        // Prefer venv python; fall back to system python
        $pythonCandidates = [
            __DIR__ . '/../../venv/Scripts/python.exe',   // Windows venv
            __DIR__ . '/../../venv/bin/python3',           // Unix venv
            'python',                                       // system PATH
            'python3',
        ];

        $python = 'python'; // default
        foreach ($pythonCandidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved && file_exists($resolved)) {
                $python = $resolved;
                break;
            }
        }

        // Escape arguments for safe shell execution
        $pythonEsc = escapeshellarg($python);
        $scriptEsc  = escapeshellarg($script);

        // Fire-and-forget: redirect stdout+stderr to a log file,
        // background the process so login.php does NOT wait.
        $logDir = __DIR__ . '/../../security_logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $nexoraLog = escapeshellarg(realpath($logDir) . '/nexora_run.log');

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: start /B runs detached
            $cmd = "start /B {$pythonEsc} {$scriptEsc} >> {$nexoraLog} 2>&1";
            pclose(popen($cmd, 'r'));
        } else {
            // Unix/Linux: & backgrounds the process
            $cmd = "{$pythonEsc} {$scriptEsc} >> {$nexoraLog} 2>&1 &";
            exec($cmd);
        }

    } catch (\Throwable $e) {
        error_log('[nexora_trigger] Exception: ' . $e->getMessage());
        // Swallow — never affect login
    }
}