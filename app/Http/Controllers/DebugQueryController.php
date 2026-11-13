<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class DebugQueryController extends Controller
{
    // Secure keys passed inside URL path
    private string $dbKey = 'cctv_debug_s8u2w4t9_db';

    private string $logKey = 'cctv_debug_s8u2w4t9_log';

    /**
     * Verify database key.
     */
    private function checkDbKey(string $key): void
    {
        if ($key !== $this->dbKey) {
            abort(404);
        }
    }

    /**
     * Verify log key.
     */
    private function checkLogKey(string $key): void
    {
        if ($key !== $this->logKey) {
            abort(404);
        }
    }

    /**
     * Resolve Eloquent Model class name.
     */
    private function resolveModelClass(string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $input = trim($input);

        // Try exact casing match as fully qualified name or under App\Models\
        $possibleClasses = [
            $input,
            'App\\'.$input,
            'App\\Models\\'.$input,
        ];

        foreach ($possibleClasses as $className) {
            if (class_exists($className)) {
                return $className;
            }
        }

        // Recursively scan the app/ directory for modular models (e.g. App\Modules\...)
        $appPath = app_path();
        if (is_dir($appPath)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === $input.'.php') {
                    $content = file_get_contents($file->getPathname());
                    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                        $namespace = trim($matches[1]);
                        $className = $namespace.'\\'.$input;
                        if (class_exists($className)) {
                            return $className;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Resolve Eloquent Model or table name.
     */
    private function resolveTable(string $input): ?string
    {
        $modelClass = $this->resolveModelClass($input);
        if ($modelClass) {
            try {
                $modelInstance = new $modelClass;
                if (method_exists($modelInstance, 'getTable')) {
                    return $modelInstance->getTable();
                }
            } catch (Throwable $e) {
                // Fallback
            }
        }

        return trim($input);
    }

    /**
     * Database viewer handler.
     */
    public function dbQuery(Request $request, string $key)
    {
        $this->checkDbKey($key);

        $action = 'table';
        $data = [];
        $error = null;
        $columns = [];
        $total = 0;
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 50);
        $sortBy = $request->get('sort_by', 'id');
        $sortDir = $request->get('sort_dir', 'desc');

        if ($page < 1) {
            $page = 1;
        }

        try {
            $tableInput = $request->get('table');
            if ($tableInput) {
                $modelClass = $this->resolveModelClass($tableInput);
                if ($modelClass) {
                    $query = $modelClass::query();
                } else {
                    $table = $this->resolveTable($tableInput);
                    $query = DB::table($table);
                }

                // Dynamic filters from query params
                $filters = $request->except(['action', 'table', 'limit', 'sort_by', 'sort_dir', 'page', 'format', 'json']);
                foreach ($filters as $col => $val) {
                    if ($val !== null && $val !== '') {
                        if (is_string($val) && str_contains($val, ',')) {
                            $valsArray = array_map('trim', explode(',', $val));
                            $query->whereIn($col, $valsArray);
                        } elseif (is_string($val) && str_contains($val, '%')) {
                            $query->where($col, 'like', $val);
                        } else {
                            $query->where($col, $val);
                        }
                    }
                }

                $total = $query->count();

                try {
                    $query->orderBy($sortBy, $sortDir);
                } catch (Throwable $e) {
                    // Ignore sorting errors if column is not found
                }

                $offset = ($page - 1) * $limit;
                $data = $query->offset($offset)->limit($limit)->get()->toArray();

                if (! empty($data)) {
                    $columns = array_keys((array) $data[0]);
                }
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            Log::error('Debug Query Tool Error: '.$error);
        }

        $dbKey = $this->dbKey;
        $logKey = $this->logKey;

        if ($request->wantsJson() || $request->get('format') === 'json' || $request->get('json') == 1) {
            if ($error) {
                return response()->json([
                    'success' => false,
                    'error' => $error,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'columns' => $columns,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);
        }

        return view('debug.query', compact('data', 'columns', 'error', 'action', 'key', 'total', 'page', 'limit', 'dbKey', 'logKey'));
    }

    /**
     * Server log viewer.
     */
    public function viewLogs(Request $request, string $key)
    {
        $this->checkLogKey($key);

        $action = 'logs';
        $error = null;
        $logLines = [];
        $selectedFile = $request->get('file');

        $logFiles = [];
        $logDir = storage_path('logs');

        if (File::exists($logDir)) {
            $files = File::files($logDir);
            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['log', 'txt'])) {
                    $logFiles[] = [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime(),
                    ];
                }
            }

            usort($logFiles, fn ($a, $b) => $b['modified'] <=> $a['modified']);
        }

        if (empty($selectedFile) && ! empty($logFiles)) {
            $selectedFile = $logFiles[0]['name'];
        }

        try {
            $lines = (int) $request->get('lines', 100);

            if ($selectedFile) {
                $logPath = storage_path('logs/'.$selectedFile);

                if (realpath(dirname($logPath)) !== realpath($logDir)) {
                    throw new Exception('Security Error: Invalid log file path access.');
                }

                if (File::exists($logPath)) {
                    if (File::size($logPath) === 0) {
                        $logLines = ['--- THE SELECTED LOG FILE IS CURRENTLY EMPTY (0 BYTES) ---'];
                    } else {
                        $logLines = $this->readLogsBackwards($logPath, $lines);
                        if (empty($logLines)) {
                            $logLines = ['--- NO LOGS FOUND IN THIS FILE ---'];
                        }
                    }
                } else {
                    $error = 'Log file not found.';
                }
            } else {
                $error = 'No log files found in storage/logs/.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            Log::error('Debug Log Viewer Tool Error: '.$error);
        }

        $dbKey = $this->dbKey;
        $logKey = $this->logKey;

        if ($request->wantsJson() || $request->get('format') === 'json' || $request->get('json') == 1) {
            if ($error) {
                return response()->json([
                    'success' => false,
                    'error' => $error,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'log_files' => $logFiles,
                'selected_file' => $selectedFile,
                'data' => $logLines,
            ]);
        }

        return view('debug.query', compact('logLines', 'logFiles', 'selectedFile', 'error', 'action', 'key', 'dbKey', 'logKey'));
    }

    /**
     * Backward-reading tail parser.
     */
    private function readLogsBackwards(string $filePath, int $targetLinesCount): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        $lines = [];
        $lineCount = 0;

        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);

        $buffer = '';
        $timestampFound = false;

        while ($pos > 0) {
            $readSize = min(4096, $pos);
            $pos -= $readSize;
            fseek($handle, $pos, SEEK_SET);
            $chunk = fread($handle, $readSize);

            $buffer = $chunk.$buffer;
            $parts = explode("\n", $buffer);
            $buffer = array_shift($parts);

            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $line = $parts[$i];

                $isTimestamp = preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', trim($line));
                if ($isTimestamp) {
                    $timestampFound = true;
                }

                $lines[] = $line;
                $lineCount++;

                if ($lineCount >= $targetLinesCount) {
                    if ($isTimestamp) {
                        break 2;
                    }
                    if ($lineCount >= $targetLinesCount + 1000) {
                        break 2;
                    }
                    if ($lineCount >= $targetLinesCount + 100 && ! $timestampFound) {
                        break 2;
                    }
                }
            }
        }

        if ($pos == 0 && $buffer !== '') {
            $lines[] = $buffer;
        }

        fclose($handle);

        $reversed = array_reverse($lines);

        if (! $timestampFound && count($reversed) > $targetLinesCount) {
            $reversed = array_slice($reversed, count($reversed) - $targetLinesCount);
        }

        return $reversed;
    }
}
