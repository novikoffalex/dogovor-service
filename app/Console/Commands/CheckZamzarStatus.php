<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Zamzar\ZamzarClient;

class CheckZamzarStatus extends Command
{
    protected $signature = 'zamzar:check-status';
    protected $description = 'Check status of pending Zamzar conversion jobs and download completed PDFs';

    public function handle()
    {
        $jobFile = storage_path('app/zamzar_jobs.json');
        
        if (!file_exists($jobFile)) {
            $this->info('No Zamzar jobs file found.');
            return;
        }

        $jobs = json_decode(file_get_contents($jobFile), true) ?: [];
        $zamzar = new ZamzarClient('4bb76644955076ff4def01f10b50e2ad7c0e4b00');
        
        $updated = false;
        
        foreach ($jobs as $filename => $job) {
            if ($job['status'] === 'processing') {
                try {
                    $zamzarJob = $zamzar->jobs->get($job['job_id']);
                    
                    if ($zamzarJob->getStatus() === 'successful') {
                        // Download PDF
                        $targetFile = $zamzarJob->getTargetFiles()[0];
                        $file = $zamzar->files->get($targetFile->getId());
                        
                        // Save PDF locally
                        $pdfPath = storage_path('app/contracts/' . $filename . '.pdf');
                        @mkdir(dirname($pdfPath), 0775, true);
                        $file->download(dirname($pdfPath));
                        
                        // Rename to correct filename
                        $tempPath = dirname($pdfPath) . '/' . $targetFile->getName();
                        if (file_exists($tempPath)) {
                            rename($tempPath, $pdfPath);
                        }
                        
                        // Update job status
                        $jobs[$filename]['status'] = 'completed';
                        $jobs[$filename]['pdf_path'] = $pdfPath;
                        $jobs[$filename]['completed_at'] = now()->toISOString();
                        
                        $this->info("✅ Downloaded PDF for {$filename}");
                        $updated = true;
                        
                        Log::info('Zamzar PDF downloaded via command', [
                            'filename' => $filename,
                            'job_id' => $job['job_id']
                        ]);
                        
                    } elseif ($zamzarJob->getStatus() === 'failed') {
                        // Update job status to failed
                        $jobs[$filename]['status'] = 'failed';
                        $jobs[$filename]['failed_at'] = now()->toISOString();
                        
                        $this->error("❌ PDF conversion failed for {$filename}");
                        $updated = true;
                        
                        Log::error('Zamzar PDF conversion failed', [
                            'filename' => $filename,
                            'job_id' => $job['job_id']
                        ]);
                    }
                    
                } catch (\Exception $e) {
                    $this->error("Error checking job {$job['job_id']}: " . $e->getMessage());
                    Log::error('Zamzar status check error', [
                        'job_id' => $job['job_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        if ($updated) {
            file_put_contents($jobFile, json_encode($jobs, JSON_PRETTY_PRINT));
            $this->info('✅ Jobs file updated');
        } else {
            $this->info('No updates needed');
        }
    }
}