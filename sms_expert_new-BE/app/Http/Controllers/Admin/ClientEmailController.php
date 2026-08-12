<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use App\Mail\BulkEmail;
use App\Services\Queue\EmailQueueService;
use Illuminate\Support\Facades\Log;
use App\Models\EmailLog;
use Carbon\Carbon;



class ClientEmailController extends Controller
{
    use LegacyCustomerList;

    public function index()
    {
        // Rows are now loaded via AJAX (server-side DataTables) from getEmailsData().
        return view('admin.email.index');
    }

    /**
     * Server-side DataTables endpoint for the Customer Emails table.
     * Handles paging, searching and sorting against the users table without
     * loading every customer into memory on each page render.
     */
    public function getEmailsData(Request $request)
    {
        // Column index (from DataTables) -> field on legacy row. First column is the
        // checkbox and is not sortable.
        $columns = [null, 'uname', 'busname', 'contactemail'];

        $base = $this->getLegacyCustomers();
        $recordsTotal = $base->count();

        // DataTables global search (PHP-side, since the legacy SQL is a fixed shape)
        $search = trim((string) $request->input('search.value', ''));
        $filtered = $base;
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $filtered = $base->filter(function ($r) use ($needle) {
                foreach (['uname', 'busname', 'contactemail'] as $f) {
                    if (str_contains(mb_strtolower((string) ($r->$f ?? '')), $needle)) {
                        return true;
                    }
                }
                return false;
            })->values();
        }

        $recordsFiltered = $filtered->count();

        // Ordering
        $orderColIdx = $request->input('order.0.column');
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderField = ($orderColIdx !== null && isset($columns[$orderColIdx]) && $columns[$orderColIdx])
            ? $columns[$orderColIdx]
            : 'id';
        $sorted = $orderDir === 'desc'
            ? $filtered->sortByDesc(fn ($r) => mb_strtolower((string) ($r->$orderField ?? '')))->values()
            : $filtered->sortBy(fn ($r) => mb_strtolower((string) ($r->$orderField ?? '')))->values();

        // Pagination
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }

        $rows = $sorted->slice($start, $length)->values();

        $data = $rows->map(function ($r) {
            $email = e($r->contactemail ?? '');
            return [
                'select'       => '<input type="checkbox" class="email-checkbox" value="' . $email . '">',
                'uname'        => e($r->uname ?? ''),
                'busname'      => e(urldecode($r->busname ?? '')),
                'contactemail' => $email,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }


    public function emailform(Request $request)
    {
        $userInfo = Session::get('user_info');
        $now = \Carbon\Carbon::now('Europe/London');

        $selected = $request->get('emails');

        if ($selected) {
            // 💡 FIX: Convert CSV string to array
            $emails = explode(',', $selected);
        } else {
            $userData = $this->getLegacyCustomers()
                ->filter(fn ($c) => !empty($c->contactemail));

            $emails = $userData->pluck('contactemail')->toArray();
        }

        return view('admin.email.show', [
            'emails' => $emails,
            'now' => $now,
            'current_hour' => $now->format('H'),
            'rounded_minute' => floor($now->minute / 5) * 5,
        ]);
    }

    // public function emailform()
    // {
    //     $userInfo = Session::get('user_info');
    //     // Set timezone to Europe/London
    //     $now = Carbon::now('Europe/London');

    //     if (isset($userInfo['bigid'])) {
    //         $userref = $userInfo['bigid'];

    //         // $userData = User::whereNull('login_type')
    //         //     ->orWhere('login_type', 'customer')
    //         //     ->get();

    //         $userData = User::where(function ($query) {
    //             $query->whereNull('login_type')
    //                 ->orWhere('login_type', 'customer');
    //         })
    //             ->whereNotNull('contactemail')
    //             ->where('contactemail', '!=', '')
    //             ->get();
    //     } else {
    //         $userData = collect(); // empty collection if no user info
    //     }

    //     return view('admin.email.show', compact('userData', 'now'));
    // }

    public function sendBulkEmail(Request $request)
    {

        $validated = $request->validate([
            'emails' => 'required|string',
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        $emails = explode(',', $request->emails);
        $subject = $request->subject;
        $messageContent = $request->message;

        $emailQueueService = new EmailQueueService();

        foreach ($emails as $email) {
            $email = trim($email);
            $status = 'queued';
            $errorMessage = null;

            try {
                // Send email via RabbitMQ queue
                $emailQueueService->queueEmail(
                    'App\\Mail\\BulkEmail',
                    $email,
                    [
                        'subject' => $subject,
                        'message_content' => $messageContent,
                    ]
                );

                // Laravel log
                Log::info("Email queued to: $email | Subject: $subject");
            } catch (\Exception $e) {
                $status = 'failed';
                $errorMessage = $e->getMessage();

                Log::error("Failed to queue email to: $email | Error: {$errorMessage}");
            }

            EmailLog::create([
                'to' => $email,
                'subject' => $subject,
                'message' => $messageContent,
                'sent_at' => now(),
                'status' => $status,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Emails have been queued for sending.');
    }

    /**
     * Store an image uploaded from the email rich-text editor and return its public
     * URL in the shape TinyMCE expects: { "location": "https://…/storage/…" }.
     * Hosted URLs are used (not base64) because Gmail/Outlook strip inline base64 images.
     * Requires `php artisan storage:link` so /storage is web-accessible.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5 MB
        ]);

        $path = $request->file('file')->store('email-images', 'public');

        return response()->json([
            'location' => asset('storage/' . $path),
        ]);
    }

    public function scheduleBulkEmail(Request $request)
    {
        $validated = $request->validate([
            'emails' => 'required|string',
            'subject' => 'required|string',
            'message' => 'required|string',
            'send_date' => 'required|date',
            'send_hh' => 'required',
            'send_mm' => 'required',
        ]);

        $emails = explode(',', $validated['emails']);
        $subject = $validated['subject'];
        $messageContent = $validated['message'];

        // Combine date and time
        $sendAt = Carbon::createFromFormat('Y-m-d H:i', $request->send_date . ' ' . $request->send_hh . ':' . $request->send_mm);

        foreach ($emails as $email) {
            try {

                EmailLog::create([
                    'to' => trim($email),
                    'subject' => $subject,
                    'message' => $messageContent,
                    'sent_at' => $sendAt,
                    'status' => 'scheduled',
                    'created_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Optional: Log error
                Log::error("Error scheduling email to $email: " . $e->getMessage());
            }
        }

        return back()->with('success', 'Emails scheduled successfully!');
    }
}
