<?php

namespace App\Notifications;

use App\Models\PaymentAdjustment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRefundNotification extends Notification
{
    use Queueable;

    public function __construct(public PaymentAdjustment $adjustment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Payment refund recorded',
            'message' => 'A refund of ৳'.number_format($this->adjustment->amount, 2).' was recorded for order '.$this->adjustment->order?->order_no.'. '.$this->adjustment->reason,
            'url' => route('student.orders'),
            'type' => 'payment_refund',
            'adjustment_id' => $this->adjustment->id,
        ];
    }
}
