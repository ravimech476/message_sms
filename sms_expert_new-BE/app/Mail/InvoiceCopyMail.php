<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\InvoiceController;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceCopyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $toAddress;
    public $ccAddress;
    public $invoiceno;
    public $user;
    public $invoice;

    /**
     * Create a new message instance.
     */
    public function __construct($toAddress, $ccAddress = null, $invoiceno, $user, $invoice)
    {
        $this->toAddress = $toAddress;
        $this->ccAddress = $ccAddress;
        $this->invoiceno = $invoiceno;
        $this->user      = $user;
        $this->invoice   = $invoice;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->toAddress],
            cc: $this->ccAddress ? [$this->ccAddress] : [],
            subject: 'SMS Expert Copy Invoice: ' . $this->invoiceno,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // OLD SYSTEM parity: per-invoice summary + PayPal "Buy Now" with exact gating.
        $pay = InvoiceController::paymentDetails($this->invoiceno);

        return new Content(
            view: 'emails.invoice_copy_mail',
            with: [
                'invoiceno' =>  $this->invoiceno,
                'user' =>  $this->user,
                'invoice' =>  $this->invoice,
                'summary' => $pay['summary'],
                'paypalRef' => $pay['paypalRef'],
                'cardPaypalAllowed' => $pay['cardPaypalAllowed'],
                'buyNowUrl' => $pay['buyNowUrl'],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (empty($this->invoice) || empty($this->user)) {
            return [];
        }

        try {
            // DomPDF needs writable temp + font-cache dirs. The defaults (sys_get_temp_dir()
            // and storage/fonts) often fail on production, which made PDF generation throw,
            // get swallowed, and the email go out WITHOUT the attachment. Force guaranteed-
            // writable paths inside storage and create them if missing. (Mirrors InvoiceMail.)
            $tempDir   = storage_path('app/dompdf');
            $fontCache = storage_path('fonts');
            foreach ([$tempDir, $fontCache] as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
            }

            // Reuse the same formatted invoice document the download route produces.
            $html = InvoiceController::generateInvoiceHtml($this->invoice, $this->user);
            $pdf  = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isRemoteEnabled'      => true,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont'          => 'sans-serif',
                    'tempDir'              => $tempDir,
                    'fontDir'              => $fontCache,
                    'fontCache'            => $fontCache,
                    'chroot'               => base_path(),
                ])
                ->output();

            return [
                Attachment::fromData(fn () => $pdf, 'Invoice_' . $this->invoiceno . '.pdf')
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to attach invoice PDF to copy email', [
                'invoiceno'  => $this->invoiceno,
                'error'      => $e->getMessage(),
                'exception'  => get_class($e),
                'at'         => $e->getFile() . ':' . $e->getLine(),
                'temp_dir'   => storage_path('app/dompdf'),
                'font_cache' => storage_path('fonts'),
            ]);
            return [];
        }
    }
}
