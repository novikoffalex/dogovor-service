<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Management Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Server Management Panel</h1>
        
        <!-- Server Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Server Status</h2>
            <div id="server-status" class="space-y-2">
                <div class="flex justify-between">
                    <span>Loading...</span>
                    <span class="text-blue-600">🔄</span>
                </div>
            </div>
            <button onclick="refreshStatus()" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Refresh Status
            </button>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 gap-4">
                <button onclick="setupCounter()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Setup Contract Counter
                </button>
                <button onclick="clearCache()" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                    Clear Cache
                </button>
                <button onclick="migrate()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    Run Migrations
                </button>
                <button onclick="checkZamzar()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Check Zamzar Jobs
                </button>
            </div>
        </div>

        <!-- Command Execution -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Execute Command</h2>
            <div class="space-y-4">
                <select id="command-select" class="w-full p-2 border rounded">
                    <option value="cache:clear">Clear Cache</option>
                    <option value="config:clear">Clear Config</option>
                    <option value="route:clear">Clear Routes</option>
                    <option value="view:clear">Clear Views</option>
                    <option value="migrate">Run Migrations</option>
                    <option value="contract:setup-counter">Setup Contract Counter</option>
                </select>
                <button onclick="executeCommand()" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    Execute Command
                </button>
            </div>
            <div id="command-output" class="mt-4 p-4 bg-gray-100 rounded hidden">
                <pre class="text-sm"></pre>
            </div>
        </div>
    </div>

    <script>
        // Load server status on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshStatus();
        });

        async function refreshStatus() {
            try {
                const response = await fetch('/api/server/status');
                const data = await response.json();
                
                if (data.success) {
                    displayStatus(data.status);
                } else {
                    document.getElementById('server-status').innerHTML = 
                        '<div class="text-red-600">Error: ' + data.error + '</div>';
                }
            } catch (error) {
                document.getElementById('server-status').innerHTML = 
                    '<div class="text-red-600">Error: ' + error.message + '</div>';
            }
        }

        function displayStatus(status) {
            const statusHtml = `
                <div class="flex justify-between">
                    <span>Database</span>
                    <span class="${status.database.status === 'connected' ? 'text-green-600' : 'text-red-600'}">
                        ${status.database.status === 'connected' ? '✅ Connected' : '❌ Error'}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Cache</span>
                    <span class="${status.cache.status === 'working' ? 'text-green-600' : 'text-red-600'}">
                        ${status.cache.status === 'working' ? '✅ Working' : '❌ Error'}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Storage</span>
                    <span class="${status.storage.status === 'writable' ? 'text-green-600' : 'text-red-600'}">
                        ${status.storage.status === 'writable' ? '✅ Writable' : '❌ Readonly'}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Contract Counter</span>
                    <span class="${status.contract_counter.status === 'database' ? 'text-green-600' : 'text-yellow-600'}">
                        ${status.contract_counter.status === 'database' ? '✅ Database' : '⚠️ File'}
                        ${status.contract_counter.counter !== undefined ? '(' + status.contract_counter.counter + ')' : ''}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Zamzar Jobs</span>
                    <span class="${status.zamzar_jobs.status === 'exists' ? 'text-green-600' : 'text-gray-600'}">
                        ${status.zamzar_jobs.status === 'exists' ? '✅ ' + status.zamzar_jobs.total_jobs + ' jobs' : '❌ No jobs'}
                    </span>
                </div>
            `;
            document.getElementById('server-status').innerHTML = statusHtml;
        }

        async function setupCounter() {
            try {
                const response = await fetch('/api/server/setup-counter', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Contract counter setup completed!');
                    refreshStatus();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function clearCache() {
            await executeCommand('cache:clear');
        }

        async function migrate() {
            await executeCommand('migrate');
        }

        async function checkZamzar() {
            refreshStatus();
        }

        async function executeCommand(command = null) {
            const cmd = command || document.getElementById('command-select').value;
            
            try {
                const response = await fetch('/api/server/execute', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    },
                    body: JSON.stringify({ command: cmd })
                });
                const data = await response.json();
                
                const outputDiv = document.getElementById('command-output');
                const outputPre = outputDiv.querySelector('pre');
                
                if (data.success) {
                    outputPre.textContent = `Command: ${data.command}\nExit Code: ${data.exit_code}\nOutput:\n${data.output}`;
                    outputDiv.className = 'mt-4 p-4 bg-green-100 rounded';
                } else {
                    outputPre.textContent = `Command: ${data.command}\nError: ${data.error}`;
                    outputDiv.className = 'mt-4 p-4 bg-red-100 rounded';
                }
                
                outputDiv.classList.remove('hidden');
                refreshStatus();
            } catch (error) {
                const outputDiv = document.getElementById('command-output');
                const outputPre = outputDiv.querySelector('pre');
                outputPre.textContent = `Error: ${error.message}`;
                outputDiv.className = 'mt-4 p-4 bg-red-100 rounded';
                outputDiv.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
