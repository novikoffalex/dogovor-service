<?php

/**
 * Server Manager for Cursor IDE
 * 
 * Usage:
 * php scripts/server-manager.php status
 * php scripts/server-manager.php setup-counter
 * php scripts/server-manager.php execute "cache:clear"
 */

class ServerManager
{
    private $serverUrl = 'https://dogovor-service-main-srtt1t.laravel.cloud';
    private $apiBase;

    public function __construct()
    {
        $this->apiBase = $this->serverUrl . '/api/server';
    }

    public function run($command, $args = [])
    {
        switch ($command) {
            case 'status':
                $this->showStatus();
                break;
            case 'setup-counter':
                $this->setupCounter();
                break;
            case 'clear-cache':
                $this->clearCache();
                break;
            case 'migrate':
                $this->migrate();
                break;
            case 'check-zamzar':
                $this->checkZamzar();
                break;
            case 'execute':
                $cmd = $args[0] ?? 'cache:clear';
                $this->executeCommand($cmd);
                break;
            default:
                $this->showHelp();
                break;
        }
    }

    private function apiCall($method, $endpoint, $data = null)
    {
        $url = $this->apiBase . $endpoint;
        $caFile = $this->getCaFile();
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => 'Content-Type: application/json',
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        if ($caFile) {
            $options['ssl']['cafile'] = $caFile;
        }

        if ($data && $method === 'POST') {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return $result ? json_decode($result, true) : null;
    }

    private function getCaFile()
    {
        $candidates = [
            '/etc/ssl/cert.pem',
            '/usr/local/etc/openssl@3/cert.pem',
            '/opt/homebrew/etc/openssl@3/cert.pem'
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function showStatus()
    {
        echo "🔍 Checking server status...\n";
        $response = $this->apiCall('GET', '/status');
        
        if ($response && $response['success']) {
            $status = $response['status'];
            
            echo "📊 Server Status:\n";
            echo "  Database: " . $this->formatStatus($status['database']['status'] === 'connected') . "\n";
            echo "  Cache: " . $this->formatStatus($status['cache']['status'] === 'working') . "\n";
            echo "  Storage: " . $this->formatStatus($status['storage']['status'] === 'writable') . "\n";
            
            $counter = $status['contract_counter'];
            echo "  Contract Counter: ";
            if ($counter['status'] === 'database') {
                echo "✅ Database (" . ($counter['counter'] ?? 0) . ")\n";
            } else {
                echo "⚠️  File (" . ($counter['counter'] ?? 0) . ")\n";
            }
            
            $zamzar = $status['zamzar_jobs'];
            echo "  Zamzar Jobs: ";
            if ($zamzar['status'] === 'exists') {
                echo "✅ " . $zamzar['total_jobs'] . " jobs (" . $zamzar['processing'] . " processing)\n";
            } else {
                echo "❌ No jobs\n";
            }

            if (isset($status['template'])) {
                $template = $status['template'];
                $templateStatus = ($template['exists'] ?? false) ? '✅' : '❌';
                $templateHash = $template['sha1'] ?? 'n/a';
                $templateMtime = $template['mtime'] ?? 'n/a';
                echo "  Template: {$templateStatus} sha1={$templateHash} mtime={$templateMtime}\n";
            }
        } else {
            echo "❌ Failed to get server status\n";
        }
    }

    private function setupCounter()
    {
        echo "⚙️  Setting up contract counter...\n";
        $response = $this->apiCall('POST', '/setup-counter');
        
        if ($response && $response['success']) {
            echo "✅ Contract counter setup completed!\n";
            echo "   Today: " . $response['today'] . "\n";
            echo "   Counter: " . $response['counter'] . "\n";
        } else {
            echo "❌ Failed to setup counter: " . ($response['error'] ?? 'Unknown error') . "\n";
        }
    }

    private function clearCache()
    {
        echo "🧹 Clearing all caches...\n";
        $commands = ['cache:clear', 'config:clear', 'route:clear', 'view:clear'];
        
        foreach ($commands as $command) {
            echo "  Executing: $command\n";
            $response = $this->apiCall('POST', '/execute', ['command' => $command]);
            
            if ($response && $response['success']) {
                echo "  ✅ Success\n";
            } else {
                echo "  ❌ Failed\n";
            }
        }
    }

    private function migrate()
    {
        echo "📦 Running migrations...\n";
        $response = $this->apiCall('POST', '/execute', ['command' => 'migrate']);
        
        if ($response && $response['success']) {
            echo "✅ Migrations completed!\n";
            echo "Output:\n" . $response['output'] . "\n";
        } else {
            echo "❌ Migration failed: " . ($response['error'] ?? 'Unknown error') . "\n";
        }
    }

    private function checkZamzar()
    {
        echo "📋 Checking Zamzar jobs...\n";
        $response = $this->apiCall('GET', '/status');
        
        if ($response && $response['success']) {
            $zamzar = $response['status']['zamzar_jobs'];
            if ($zamzar['status'] === 'exists') {
                echo "✅ Zamzar jobs found:\n";
                echo "  Total jobs: " . $zamzar['total_jobs'] . "\n";
                echo "  Processing: " . $zamzar['processing'] . "\n";
            } else {
                echo "❌ No Zamzar jobs found\n";
            }
        } else {
            echo "❌ Failed to check Zamzar jobs\n";
        }
    }

    private function executeCommand($command)
    {
        echo "🚀 Executing: $command\n";
        $response = $this->apiCall('POST', '/execute', ['command' => $command]);
        
        if ($response && $response['success']) {
            echo "✅ Command executed successfully!\n";
            echo "Exit code: " . $response['exit_code'] . "\n";
            echo "Output:\n" . $response['output'] . "\n";
        } else {
            echo "❌ Command failed: " . ($response['error'] ?? 'Unknown error') . "\n";
        }
    }

    private function formatStatus($isGood)
    {
        return $isGood ? '✅ OK' : '❌ Error';
    }

    private function showHelp()
    {
        echo "🛠️  Server Management Commands:\n\n";
        echo "Usage: php scripts/server-manager.php [command]\n\n";
        echo "Commands:\n";
        echo "  status          - Show server status\n";
        echo "  setup-counter   - Setup contract counter\n";
        echo "  clear-cache     - Clear all caches\n";
        echo "  migrate         - Run migrations\n";
        echo "  check-zamzar    - Check Zamzar jobs\n";
        echo "  execute [cmd]   - Execute custom Artisan command\n";
        echo "  help            - Show this help\n\n";
        echo "Examples:\n";
        echo "  php scripts/server-manager.php status\n";
        echo "  php scripts/server-manager.php setup-counter\n";
        echo "  php scripts/server-manager.php execute \"cache:clear\"\n";
    }
}

// Run the script
if ($argc < 2) {
    $manager = new ServerManager();
    $manager->showHelp();
    exit(1);
}

$command = $argv[1];
$args = array_slice($argv, 2);

$manager = new ServerManager();
$manager->run($command, $args);

