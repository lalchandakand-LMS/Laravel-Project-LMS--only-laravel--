<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendNewsletterCampaignEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 30;
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue('mail');
    }

    public function uniqueId(): string
    {
        return 'newsletter-delivery:'.$this->deliveryId;
    }

    public function handle(): void
    {
        $delivery = NewsletterCampaignDelivery::query()
            ->with(['campaign', 'subscriber'])
            ->findOrFail($this->deliveryId);

        if ($delivery->status !== 'pending') {
            return;
        }

        $subscriber = $delivery->subscriber;
        $campaign = $delivery->campaign;

        if (! $subscriber || $subscriber->status !== 'subscribed') {
            $delivery->update(['status' => 'skipped']);
            $this->refreshCampaignProgress($campaign);
            return;
        }

        $unsubscribeUrl = URL::temporarySignedRoute(
            'newsletter.unsubscribe',
            now()->addYears(5),
            ['subscriber' => $subscriber->id]
        );

        $body = $campaign->title."\n\n"
            .$campaign->body."\n\n"
            ."You are receiving this email because you confirmed a newsletter subscription.\n"
            .'Unsubscribe: '.$unsubscribeUrl;

        Mail::raw($body, function ($message) use ($subscriber, $campaign) {
            $message->to($subscriber->email)->subject($campaign->subject);
        });

        $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        $this->refreshCampaignProgress($campaign);
    }

    public function failed(Throwable $exception): void
    {
        $delivery = NewsletterCampaignDelivery::with('campaign')->find($this->deliveryId);
        if (! $delivery || $delivery->status !== 'pending') {
            return;
        }

        $delivery->update(['status' => 'failed', 'failed_at' => now()]);
        $this->refreshCampaignProgress($delivery->campaign);
    }

    private function refreshCampaignProgress(NewsletterCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $locked = NewsletterCampaign::query()->whereKey($campaign->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $counts = $locked->deliveries()
                ->selectRaw("SUM(status = 'sent') as sent_count")
                ->selectRaw("SUM(status = 'failed') as failed_count")
                ->selectRaw("SUM(status = 'skipped') as skipped_count")
                ->first();

            $sent = (int) ($counts->sent_count ?? 0);
            $failed = (int) ($counts->failed_count ?? 0);
            $skipped = (int) ($counts->skipped_count ?? 0);

            $completed = $sent + $failed + $skipped;
            $updates = [
                'sent_count' => $sent,
                'failed_count' => $failed,
                'skipped_count' => $skipped,
            ];

            if ($locked->recipients_count > 0 && $completed >= $locked->recipients_count) {
                $updates['status'] = $failed > 0 ? 'completed_with_errors' : 'sent';
                $updates['completed_at'] = now();
            }

            $locked->update($updates);
        });
    }
}
