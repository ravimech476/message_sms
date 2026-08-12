<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class VirtualNumberExpiryReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:virtual-number-expiry {--debug : Run in debug mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send virtual number and keyword expiry report to care team';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $debug = $this->option('debug');
        
        $this->info('Starting Virtual Number Expiry Report...');

        try {
            $virtsDoDay = Carbon::now()->format('D jS M');
            $virtsStartDate = Carbon::now()->subDays(30)->format('Y-m-d');
            $virtsStartDate2 = Carbon::now()->subDays(30)->format('d/m/Y');
            $virtsEndDate = Carbon::now()->addDays(14)->format('Y-m-d');
            $virtsEndDate2 = Carbon::now()->addDays(14)->format('d/m/Y');

            // Get expiring virtual numbers and keywords
            // Only process users migrated to new system (migration_flag = 'new')
            $expiries = DB::table('itagg_instance as i')
                ->join('smsshortcodes as s', 'i.smsshortcodes_id', '=', 's.id')
                ->join('users as u', 'u.bigid', '=', 'i.users_bigid')
                ->select(
                    'u.id',
                    'u.uname',
                    'u.contactname',
                    'u.busname',
                    'u.contactemail',
                    'i.expiry'
                )
                ->selectRaw("IF(s.number = '60300', CONCAT(i.keyword, '/60300'), s.number) as virtkey")
                ->where('u.bit_disabled', 0)
                ->where('u.migration_flag', 'new') // Only process users migrated to new system
                ->whereBetween('i.expiry', [$virtsStartDate, $virtsEndDate])
                ->orderBy('i.expiry')
                ->orderBy('u.contactname')
                ->get();

            // Build email content
            $subject = "Number & keywords expiry report, {$virtsDoDay}";
            $virtsStr = "<b>Expiries between {$virtsStartDate2} and {$virtsEndDate2}...</b><br><br>";

            if ($expiries->isEmpty()) {
                $virtsStr .= "No expiries found for this period.<br>";
            } else {
                foreach ($expiries as $expiry) {
                    $contactname = trim(urldecode($expiry->contactname ?? ''));
                    $busname = trim(urldecode($expiry->busname ?? ''));
                    $contactemail = trim(urldecode($expiry->contactemail ?? ''));
                    
                    $expiryDate = Carbon::parse($expiry->expiry)->format('d/m/Y');
                    
                    // Build admin URL — points at the NEW SYSTEM customer
                    // detail page (admin.user.show). The OLD SYSTEM URL it
                    // used to point to (secure.itagg.com/...) only opens for
                    // pre-migration customers; this command only emails for
                    // migration_flag='new', so the new admin is correct.
                    $url = route('admin.user.show', ['id' => $expiry->id]);
                    
                    $customerName = $busname ?: $contactname ?: 'Unknown';
                    
                    $virtsStr .= "{$expiryDate}: {$expiry->virtkey}, <a href='{$url}'>{$contactname}, {$busname}</a><br><br>";
                }
            }

            // Add summary
            $virtsStr .= "<br><hr><br>";
            $virtsStr .= "<b>Summary:</b> " . $expiries->count() . " item(s) expiring in this period.<br>";

            // Send email
            $this->sendReport($virtsStr, $subject, $debug);

            $this->info('Virtual Number Expiry Report completed successfully!');
            $this->info('Total expiries found: ' . $expiries->count());
            
        } catch (\Exception $e) {
            $this->error('Error generating virtual number expiry report: ' . $e->getMessage());
            \Log::error('VirtualNumberExpiryReport Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    /**
     * Send the report email
     */
    protected function sendReport($content, $subject, $debug = false)
    {
        if ($debug) {
            $recipients = [config('reports.debug_recipient')];
        } else {
            $recipients = config('reports.virtual_expiry_recipients');
        }

        $reportData = [
            'subject' => $subject,
            'report_date' => now()->format('jS M Y'),
            'content' => $content,
            'is_debug' => $debug,
            'generated_at' => now()->format('Y-m-d H:i:s T'),
        ];

        try {
            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\VirtualNumberExpiryReportMail',
                    trim($recipient),
                    ['report_data' => $reportData],
                    [],
                    5
                );
            }

            $this->info('Email queued to: ' . implode(', ', $recipients));
        } catch (\Exception $e) {
            $this->error('Failed to queue email: ' . $e->getMessage());
        }
    }
}
