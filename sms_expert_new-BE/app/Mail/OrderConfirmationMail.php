<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $toAddress;
    public $ccAddress;
    public $userContactName;
    public $invoiceno;

    /**
     * Create a new message instance.
     */
    public function __construct($toAddress, $ccAddress = null,$userContactName,$invoiceno)
    {
        $this->toAddress = $toAddress;
        $this->ccAddress = $ccAddress;
        // Contact names are stored URL-encoded in the DB (e.g. "Jemma+Test+"), so decode
        // and trim for a clean greeting ("Hi Jemma Test,") instead of "Hi Jemma+Test+,".
        $this->userContactName = $userContactName !== null && $userContactName !== ''
            ? trim(urldecode($userContactName))
            : $userContactName;
        $this->invoiceno =  $invoiceno;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->toAddress],
            cc: $this->ccAddress ? [$this->ccAddress] : [],
            subject: 'SMS Expert Order Confirmation: ' . $this->invoiceno ,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Resolve what was actually purchased so the confirmation wording matches:
        // "SMS Expert Services" vs "Pre-purchase of SMS Expert Credits" (product.descriptionlong
        // via orderitem.productref) — instead of always saying "credits".
        $productref = \Illuminate\Support\Facades\DB::table('orderitem')
            ->where('invoiceref', $this->invoiceno)
            ->value('productref');
        $orderSummary = \App\Models\Product::summaryFor($productref);

        // Is this an SMS-credits purchase (productref 20551)? Only credits top up the wallet.
        $isCredits = ((int) $productref === 20551);

        return new Content(
            view: 'emails.invoice_orderconfirmation',
            with: [
                'invoiceno' =>  $this->invoiceno,
                'userContactName' => $this->userContactName,
                'orderSummary' => $orderSummary,
                'isCredits' => $isCredits,
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
        return [];
    }
}
