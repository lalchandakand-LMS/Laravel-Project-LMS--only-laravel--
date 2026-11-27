<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchNewsletterCampaign implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 120;
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return 'newsletter-campaign:'.$this->campaignId;
    }

    public function handle(): void
    {
        $campaign = NewsletterCampaign::findOrFail($this->campaignId);
        if (! in_array($campaign->status, ['queued', 'sending'], true)) {
            return;
        }

        DB::transaction(function () {
            $campaign = NewsletterCampaign::query()->whereKey($this->campaignId)->lockForUpdate()->firstOrFail();
            if ($campaign->status === 'queued') {
                $campaign->update(['status' => 'sending']);
            }

            NewsletterSubscriber::query()
                ->where('status', 'subscribed')
                ->select('id')
                ->chunkById(250, function ($subscribers) {
                    $now = now()->toDateTimeString();
                    $rows = $subscribers->map(fn ($subscriber) => [
                        'newsletter_campaign_id' => $this->campaignId,
                        'newsletter_subscriber_id' => $subscriber->id,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    if ($rows !== []) {
                        DB::table('newsletter_campaign_deliveries')->insertOrIgnore($rows);
                    }
                });

            $campaign->update([
                'recipients_count' => $campaign->deliveries()->count(),
            ]);
        });

        $campaign = NewsletterCampaign::findOrFail($this->campaignId);
        if ($campaign->recipients_count === 0) {
            $campaign->update(['status' => 'sent', 'completed_at' => now()]);
            return;
        }

        $campaign->deliveries()->where('status', 'pending')->orderBy('id')->chunkById(100, function ($deliveries) {
            foreach ($deliveries as $delivery) {
                SendNewsletterCampaignEmail::dispatch($delivery->id);
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        NewsletterCampaign::query()->whereKey($this->campaignId)
            ->whereIn('status', ['queued', 'sending'])
            ->update(['status' => 'failed']);
    }
}
