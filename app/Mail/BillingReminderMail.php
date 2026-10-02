<?php

namespace App\Mail;

use App\Models\SiteOwner;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SiteOwner $owner,
        public Subscription $subscription,
        public string $stage,      // 'expiring' | 'overdue'
        public int $daysLeft,      // negative when overdue
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->stage === 'overdue'
            ? 'Abunə ödənişi gecikib — '.$this->owner->name
            : 'Abunə yenilənmə vaxtı yaxınlaşır — '.$this->owner->name;

        return new Envelope(
            from: config('manager.billing_from'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing-reminder');
    }
}
