<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment, public string $result) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->result === 'verified' ? 'Payment approved' : 'Payment rejected',
            'message' => $this->result === 'verified'
                ? 'Your payment for order '.$this->payment->order?->order_no.' has been approved.'
                : 'Your payment for order '.$this->payment->order?->order_no.' was rejected. Please review and submit again.',
            'url' => route('student.orders'),
            'type' => 'payment',
            'payment_id' => $this->payment->id,
        ];
    }
}
